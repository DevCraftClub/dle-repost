<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Support\DataManager;
use DevCraft\Core\Logging\LogGenerator;
use DevCraft\Modules\RePost\Models\Proxy;
use DevCraft\Modules\RePost\Models\CronItem;
use DevCraft\Modules\RePost\Models\Template;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Services\Dto\SendResult;
use DevCraft\Modules\RePost\Services\Dto\PostContext;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;
use DevCraft\Modules\RePost\Repositories\ProxyRepository;
use DevCraft\Modules\RePost\Repositories\CronItemRepository;
use DevCraft\Modules\RePost\Repositories\TemplateRepository;
use DevCraft\Modules\RePost\Repositories\ConnectionRepository;

/**
 * Диспетчер публикации: match → render → provider / очередь.
 */
final class DispatchService {

	public function __construct(
		private readonly ContentRenderer $renderer = new ContentRenderer(),
		private readonly TemplateMatcher $matcher = new TemplateMatcher(),
	) {}

	/**
	 * Ставить ли шаблон в очередь по режиму defer.
	 */
	public static function shouldEnqueue(string $defer, bool $templateCron, bool $cronEnabled): bool {
		return match ($defer) {
			'yes'   => true,
			'no'    => false,
			default => $templateCron && $cronEnabled,
		};
	}

	/**
	 * @param   array{
	 *     defer?: string,
	 *     planned?: ?\DateTimeImmutable,
	 *     template_mode?: string,
	 *     template_ids?: list<int>
	 * }  $options
	 *
	 * @return list<SendResult>
	 */
	public function dispatch(int $newsId, string $eventType = 'addnews', array $options = []): array {
		$log = LogGenerator::for('RePost');
		$log->log([
			'event'      => 'dispatch_start',
			'news_id'    => $newsId,
			'event_type' => $eventType,
			'options'    => [
				'defer'         => $options['defer'] ?? 'template',
				'template_mode' => $options['template_mode'] ?? 'auto',
				'template_ids'  => $options['template_ids'] ?? [],
				'has_planned'   => isset($options['planned']) && $options['planned'] instanceof \DateTimeImmutable,
			],
		], 'info');

		$config = DataManager::getConfig('repost');
		$row    = $this->loadNews($newsId);

		if($row === NULL) {
			$result = SendResult::fail(__('Новость не найдена'));
			$this->logResult($result, ['news_id' => $newsId, 'event_type' => $eventType]);

			return [$result];
		}

		$db = Application::instance()->database();
		/** @var TemplateRepository $tplRepo */
		$tplRepo = $db->repository(Template::class);

		$defer = (string) ($options['defer'] ?? 'template');
		if(!in_array($defer, ['yes', 'no', 'template'], true)) {
			$defer = 'template';
		}

		$mode = (string) ($options['template_mode'] ?? 'auto');
		if(!in_array($mode, ['auto', 'manual'], true)) {
			$mode = 'auto';
		}

		/** @var list<int> $templateIds */
		$templateIds = [];
		if(isset($options['template_ids']) && is_array($options['template_ids'])) {
			$templateIds = array_values(array_map(static fn(mixed $v): int => (int) $v, $options['template_ids']));
		}

		$planned = NULL;
		if(isset($options['planned']) && $options['planned'] instanceof \DateTimeImmutable) {
			$planned = $options['planned'];
		}

		if($mode === 'manual') {
			if($templateIds === []) {
				$result = SendResult::fail(__('Не выбран шаблон'));
				$this->logResult($result, ['news_id' => $newsId, 'event_type' => $eventType, 'manual' => true]);

				return [$result];
			}

			$matched = $tplRepo->findActiveByIds($templateIds);
			if($matched === []) {
				$result = SendResult::fail(__('Не выбран шаблон'));
				$this->logResult($result, [
					'news_id'      => $newsId,
					'event_type'   => $eventType,
					'template_ids' => $templateIds,
				]);

				return [$result];
			}
		} else {
			$templates = $tplRepo->findActiveByEvent($eventType);
			$matched   = $this->matcher->match($templates, $row, $eventType);

			if($matched === []) {
				$result = SendResult::fail(__('Нет подходящих шаблонов'));
				$this->logResult($result, ['news_id' => $newsId, 'event_type' => $eventType]);

				return [$result];
			}
		}

		$log->debug('dispatch_matched', [
			'news_id'      => $newsId,
			'event_type'   => $eventType,
			'template_ids' => array_map(static fn(Template $t): int => $t->id(), $matched),
			'defer'        => $defer,
			'mode'         => $mode,
		]);

		$context     = new PostContext($newsId, $eventType, $row);
		$results     = [];
		$cronEnabled = !empty($config['cron_enabled']);

		foreach($matched as $tpl) {
			if(self::shouldEnqueue($defer, $tpl->cron, $cronEnabled)) {
				$results[] = $this->enqueue($tpl, $newsId, $eventType, $planned);
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
		/** @var CronItemRepository $cronRepo */
		$cronRepo = $db->repository(CronItem::class);
		$tpl      = $tplRepo->findOneById($item->template_id);

		if($tpl === NULL || !$tpl->active) {
			$result = SendResult::fail(__('Шаблон очереди не найден'));
			$this->logResult($result, ['cron_id' => $item->id(), 'template_id' => $item->template_id]);
			$this->scheduleRetryOrFail($item, $result, $config, $cronRepo);

			return $result;
		}

		$row = $this->loadNews($item->news_id);

		if($row === NULL) {
			$result = SendResult::fail(__('Новость не найдена'));
			$this->logResult($result, ['cron_id' => $item->id(), 'news_id' => $item->news_id]);
			$this->scheduleRetryOrFail($item, $result, $config, $cronRepo);

			return $result;
		}

		$event   = $item->event_type !== ''? $item->event_type : 'addnews';
		$context = new PostContext($item->news_id, $event, $row);
		$result  = $this->sendTemplate($tpl, $context, $config);

		if($result->ok) {
			if(!empty($config['cron_autodelete'])) {
				$cronRepo->deleteEntity($item);
			}

			return $result;
		}

		$this->scheduleRetryOrFail($item, $result, $config, $cronRepo);

		return $result;
	}

	/**
	 * @param   array<string, mixed>  $config
	 */
	private function scheduleRetryOrFail(
		CronItem           $item,
		SendResult         $result,
		array              $config,
		CronItemRepository $cronRepo,
	): void {
		$item->attempts   = $item->attempts + 1;
		$item->last_error = mb_substr($result->message, 0, 500);
		$max              = max(1, (int) ($config['cron_max_attempts'] ?? 5));
		$interval         = max(1, (int) ($config['cron_retry_interval'] ?? 300));

		if($item->attempts >= $max) {
			$item->status = 'failed';
		} else {
			$item->status  = 'pending';
			$item->planned = (new \DateTimeImmutable())->modify("+{$interval} seconds");
		}

		$cronRepo->saveEntity($item);

		LogGenerator::for('RePost')->log([
			'event'      => 'cron_retry',
			'cron_id'    => $item->id(),
			'attempts'   => $item->attempts,
			'status'     => $item->status,
			'next_plan'  => $item->planned->format('Y-m-d H:i:s'),
			'last_error' => $item->last_error,
		], $item->status === 'failed'? 'error' : 'info');
	}

	/**
	 * @param   array<string, mixed>  $moduleConfig
	 */
	private function sendTemplate(Template $tpl, PostContext $context, array $moduleConfig): SendResult {
		$db = Application::instance()->database();
		/** @var ConnectionRepository $connRepo */
		$connRepo = $db->repository(Connection::class);
		$conn     = $connRepo->findOneById($tpl->connection_id);

		if($conn === NULL || !$conn->active) {
			$result = SendResult::fail(__('Подключение шаблона неактивно или не найдено'));
			$this->logResult($result, [
				'news_id'       => $context->newsId,
				'template_id'   => $tpl->id(),
				'connection_id' => $tpl->connection_id,
			]);

			return $result;
		}

		$provider = ProviderRegistry::get($conn->provider);

		if($provider === NULL) {
			$result = SendResult::fail(__('Провайдер не найден: {code}', ['{code}' => $conn->provider]));
			$this->logResult($result, [
				'news_id'     => $context->newsId,
				'template_id' => $tpl->id(),
				'provider'    => $conn->provider,
			]);

			return $result;
		}

		$connCfg  = $conn->getConfigArray();
		$sendType = $provider->sendTypeFromConfig($connCfg);
		$message  = $this->renderer->render(
			$tpl->template,
			$context->newsRow,
			$moduleConfig,
			$sendType,
			$provider->templateTags(),
		);
		$proxy    = $this->resolveProxy($tpl);

		LogGenerator::for('RePost')->debug('send_template', [
			'news_id'       => $context->newsId,
			'template_id'   => $tpl->id(),
			'connection_id' => $conn->id(),
			'send_type'     => $sendType,
			'images'        => count($message->images),
			'videos'        => count($message->videos),
			'audios'        => count($message->audios),
			'use_proxy'     => $proxy !== NULL,
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

	private function enqueue(
		Template            $tpl,
		int                 $newsId,
		string              $eventType,
		?\DateTimeImmutable $planned = NULL,
	): SendResult {
		$db = Application::instance()->database();
		/** @var CronItemRepository $cronRepo */
		$cronRepo = $db->repository(CronItem::class);

		$existing = $cronRepo
			->select()
			->where('template_id', $tpl->id())
			->where('news_id', $newsId)
			->fetchOne();

		$item              = $existing instanceof CronItem? $existing : new CronItem();
		$item->template_id = $tpl->id();
		$item->news_id     = $newsId;
		$item->event_type  = $eventType;
		$item->planned     = $planned ?? new \DateTimeImmutable();
		$item->attempts    = 0;
		$item->status      = 'pending';
		$item->last_error  = '';

		$cronRepo->saveEntity($item);

		$result = SendResult::success(__('Добавлено в очередь'));
		$this->logResult($result, [
			'news_id'     => $newsId,
			'template_id' => $tpl->id(),
			'event_type'  => $eventType,
			'queued'      => true,
			'planned'     => $item->planned->format('Y-m-d H:i:s'),
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

		$log->log($payload, $result->ok? 'info' : 'error');
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function resolveProxy(Template $tpl): ?array {
		if(!$tpl->use_proxy) {
			return NULL;
		}

		$db = Application::instance()->database();
		/** @var ProxyRepository $proxyRepo */
		$proxyRepo = $db->repository(Proxy::class);
		$proxy     = $proxyRepo->pickRandomActive($tpl->proxy_id);

		if($proxy === NULL) {
			return NULL;
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
			return NULL;
		}

		$row = $db->super_query('SELECT * FROM ' . PREFIX . "_post WHERE id='{$newsId}' LIMIT 1");

		return is_array($row) && !empty($row['id'])? $row : NULL;
	}

}
