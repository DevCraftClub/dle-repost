<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Logging\LogGenerator;
use DevCraft\Core\Support\DataManager;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Models\CronItem;
use DevCraft\Modules\RePost\Models\Proxy;
use DevCraft\Modules\RePost\Models\Template;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;
use DevCraft\Modules\RePost\Repositories\ConnectionRepository;
use DevCraft\Modules\RePost\Repositories\CronItemRepository;
use DevCraft\Modules\RePost\Repositories\ProxyRepository;
use DevCraft\Modules\RePost\Repositories\TemplateRepository;
use DevCraft\Modules\RePost\Services\Dto\PostContext;
use DevCraft\Modules\RePost\Services\Dto\SendResult;

/**
 * Диспетчер публикации: match → render → provider / очередь.
 */
final class DispatchService {

	public function __construct(
		private readonly ContentRenderer $renderer = new ContentRenderer(),
		private readonly TemplateMatcher $matcher = new TemplateMatcher(),
	) {}

	/**
	 * @return list<SendResult>
	 */
	public function dispatch(int $newsId, string $eventType = 'addnews'): array {
		$log = LogGenerator::for('RePost');
		$log->log(['event' => 'dispatch_start', 'news_id' => $newsId, 'event_type' => $eventType], 'info');

		$config = DataManager::getConfig('repost');
		$row    = $this->loadNews($newsId);

		if($row === null) {
			$result = SendResult::fail(__('Новость не найдена'));
			$this->logResult($result, ['news_id' => $newsId, 'event_type' => $eventType]);

			return [$result];
		}

		$db = Application::instance()->database();
		/** @var TemplateRepository $tplRepo */
		$tplRepo   = $db->repository(Template::class);
		$templates = $tplRepo->findActiveByEvent($eventType);
		$matched   = $this->matcher->match($templates, $row, $eventType);

		if($matched === []) {
			$result = SendResult::fail(__('Нет подходящих шаблонов'));
			$this->logResult($result, ['news_id' => $newsId, 'event_type' => $eventType]);

			return [$result];
		}

		$log->debug('dispatch_matched', [
			'news_id'      => $newsId,
			'event_type'   => $eventType,
			'template_ids' => array_map(static fn(Template $t): int => $t->id(), $matched),
		]);

		$context = new PostContext($newsId, $eventType, $row);
		$results = [];
		$useCron = !empty($config['cron_enabled']);

		foreach($matched as $tpl) {
			if($tpl->cron && $useCron) {
				$results[] = $this->enqueue($tpl, $newsId, $eventType);
				continue;
			}

			$results[] = $this->sendTemplate($tpl, $context, $config);
		}

		return $results;
	}

	/**
	 * Отправка одного элемента очереди.
	 */
	public function sendCronItem(CronItem $item): SendResult {
		$config = DataManager::getConfig('repost');
		$db     = Application::instance()->database();
		/** @var TemplateRepository $tplRepo */
		$tplRepo = $db->repository(Template::class);
		$tpl     = $tplRepo->findOneById($item->template_id);

		if($tpl === null || !$tpl->active) {
			$result = SendResult::fail(__('Шаблон очереди не найден'));
			$this->logResult($result, ['cron_id' => $item->id(), 'template_id' => $item->template_id]);

			return $result;
		}

		$row = $this->loadNews($item->news_id);

		if($row === null) {
			$result = SendResult::fail(__('Новость не найдена'));
			$this->logResult($result, ['cron_id' => $item->id(), 'news_id' => $item->news_id]);

			return $result;
		}

		$event   = $item->event_type !== '' ? $item->event_type : 'addnews';
		$context = new PostContext($item->news_id, $event, $row);
		$result  = $this->sendTemplate($tpl, $context, $config);

		if($result->ok && !empty($config['cron_autodelete'])) {
			/** @var CronItemRepository $cronRepo */
			$cronRepo = $db->repository(CronItem::class);
			$cronRepo->deleteEntity($item);
		}

		return $result;
	}

	/**
	 * @param   array<string, mixed>  $moduleConfig
	 */
	private function sendTemplate(Template $tpl, PostContext $context, array $moduleConfig): SendResult {
		$db = Application::instance()->database();
		/** @var ConnectionRepository $connRepo */
		$connRepo = $db->repository(Connection::class);
		$conn     = $connRepo->findOneById($tpl->connection_id);

		if($conn === null || !$conn->active) {
			$result = SendResult::fail(__('Подключение шаблона неактивно или не найдено'));
			$this->logResult($result, [
				'news_id'       => $context->newsId,
				'template_id'   => $tpl->id(),
				'connection_id' => $tpl->connection_id,
			]);

			return $result;
		}

		$provider = ProviderRegistry::get($conn->provider);

		if($provider === null) {
			$result = SendResult::fail(__('Провайдер не найден: {code}', ['{code}' => $conn->provider]));
			$this->logResult($result, [
				'news_id'     => $context->newsId,
				'template_id' => $tpl->id(),
				'provider'    => $conn->provider,
			]);

			return $result;
		}

		$connCfg  = $conn->getConfigArray();
		$sendType = (string) ($connCfg['tg_send_type'] ?? 'text');
		$message  = $this->renderer->render($tpl->template, $context->newsRow, $moduleConfig, $sendType);
		$proxy    = $this->resolveProxy($tpl);

		LogGenerator::for('RePost')->debug('send_template', [
			'news_id'       => $context->newsId,
			'template_id'   => $tpl->id(),
			'connection_id' => $conn->id(),
			'send_type'     => $sendType,
			'images'        => count($message->images),
			'videos'        => count($message->videos),
			'audios'        => count($message->audios),
			'use_proxy'     => $proxy !== null,
		]);

		$started = microtime(true);
		$result  = $provider->send($context, $message, $connCfg, $proxy);

		$this->logResult($result, [
			'news_id'       => $context->newsId,
			'template_id'   => $tpl->id(),
			'connection_id' => $conn->id(),
			'send_type'     => $sendType,
			'elapsed_ms'    => (int) round((microtime(true) - $started) * 1000),
		]);

		return $result;
	}

	private function enqueue(Template $tpl, int $newsId, string $eventType): SendResult {
		$db = Application::instance()->database();
		/** @var CronItemRepository $cronRepo */
		$cronRepo = $db->repository(CronItem::class);

		$existing = $cronRepo->select()
			->where('template_id', $tpl->id())
			->where('news_id', $newsId)
			->fetchOne();

		$item = $existing instanceof CronItem ? $existing : new CronItem();
		$item->template_id = $tpl->id();
		$item->news_id     = $newsId;
		$item->event_type  = $eventType;
		$item->planned     = new \DateTimeImmutable();

		$cronRepo->saveEntity($item);

		$result = SendResult::success(__('Добавлено в очередь'));
		$this->logResult($result, [
			'news_id'     => $newsId,
			'template_id' => $tpl->id(),
			'event_type'  => $eventType,
			'queued'      => true,
		]);

		return $result;
	}

	/**
	 * @param   array<string, mixed>  $ctx
	 */
	private function logResult(SendResult $result, array $ctx = []): void {
		$log     = LogGenerator::for('RePost');
		$payload = array_merge($ctx, [
			'ok'      => $result->ok,
			'message' => $result->message,
		]);

		if(!$result->ok && $result->raw !== []) {
			$payload['raw'] = $result->raw;
		}

		$log->log($payload, $result->ok ? 'info' : 'error');
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function resolveProxy(Template $tpl): ?array {
		if(!$tpl->use_proxy) {
			return null;
		}

		$db = Application::instance()->database();
		/** @var ProxyRepository $proxyRepo */
		$proxyRepo = $db->repository(Proxy::class);
		$proxy     = $proxyRepo->pickRandomActive($tpl->proxy_id);

		if($proxy === null) {
			return null;
		}

		return [
			'ip'   => $proxy->ip,
			'port' => $proxy->port,
			'type' => $proxy->type,
			'auth' => $proxy->auth,
			'user' => $proxy->user,
			'pass' => $proxy->pass,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function loadNews(int $newsId): ?array {
		global $db;

		if($newsId <= 0 || !isset($db)) {
			return null;
		}

		$row = $db->super_query('SELECT * FROM ' . PREFIX . "_post WHERE id='{$newsId}' LIMIT 1");

		return is_array($row) && !empty($row['id']) ? $row : null;
	}

}
