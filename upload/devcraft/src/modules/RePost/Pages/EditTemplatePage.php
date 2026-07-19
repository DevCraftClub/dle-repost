<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DLEPlugins;
use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Proxy;
use DevCraft\Modules\RePost\Models\Template;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;
use DevCraft\Modules\RePost\Provider\DefaultTemplateTags;
use DevCraft\Modules\RePost\Repositories\TemplateRepository;

/**
 * Создание / редактирование шаблона.
 */
final class EditTemplatePage extends AbstractPage {

	public function handle(): array {
		global $config, $dle_login_hash;

		$id = (int) ($_GET['id'] ?? 0);
		/** @var TemplateRepository $repo */
		$repo = Application::instance()->database()->repository(Template::class);
		$item = $id > 0? $repo->findOneById($id) : NULL;

		$this->addBreadcrumb(__('Шаблоны'), '?mod=repost&action=templates');
		$this->addBreadcrumb($item? __('Редактирование') : __('Новый шаблон'));

		$db = Application::instance()->database();
		/** @var list<Connection> $conns */
		$conns = $db->repository(Connection::class)->select()->where('active', true)->orderBy('name')->fetchAll();
		/** @var list<Proxy> $proxies */
		$proxies = $db->repository(Proxy::class)->select()->where('active', true)->orderBy('ip')->fetchAll();

		$connOpts = [];

		foreach($conns as $c) {
			$connOpts[$c->id()] = $c->name . ' (' . $c->provider . ')';
		}

		$proxyOpts = [0 => __('— случайный активный —')];

		foreach($proxies as $p) {
			$proxyOpts[$p->id()] = $p->ip . ':' . $p->port . ' (' . $p->type . ')';
		}

		$typeList = $item?->typeList() ?? ['addnews', 'editnews'];
		$dleHome  = rtrim((string) ($config['http_home_url'] ?? '/'), '/') . '/';

		$tagsHints   = $this->resolveTemplateHints($item, $conns);
		$allowedHtml = $tagsHints['allowed_html_tags'];
		$tagHints    = $tagsHints['hints'];

		return [
			'view' => 'repost/edit_template.twig',
			'data' => [
				'page_title'         => $item? __('Редактирование шаблона') : __('Новый шаблон'),
				'item'               => [
					'id'                 => $item?->id() ?? 0,
					'name'               => $item?->name ?? '',
					'connection_id'      => $item?->connection_id ?? 0,
					'template_type'      => $item?->template_type ?? 'addnews,editnews',
					'type_list'          => $typeList,
					'template'           => $item?->template ?? '',
					'condition'          => $item?->condition ?? '[]',
					'condition_relation' => $item?->condition_relation ?? 'and',
					'active'             => $item?->active ?? true,
					'cron'               => $item?->cron ?? false,
					'use_proxy'          => $item?->use_proxy ?? false,
					'proxy_id'           => $item?->proxy_id ?? 0,
				],
				'connections'        => $connOpts,
				'proxies'            => $proxyOpts,
				'condition_fields'   => $this->buildConditionFields(),
				'template_tag_hints' => $tagHints,
				'allowed_html_tags'  => $allowedHtml,
				'list_url'           => '?mod=repost&action=templates',
				'dle_home'           => $dleHome,
				'dle_skin'           => (string) ($config['skin'] ?? 'Default'),
				'pm_wysiwyg'         => !empty($config['allow_pm_wysiwyg']),
				'pm_editor_script'   => $this->buildPmEditorScript(),
				'dle_login_hash'     => (string) ($dle_login_hash ?? ''),
			],
		];
	}

	/**
	 * @param   list<Connection>  $conns
	 *
	 * @return array{hints: list<array{code: string, tag: string, descr: string, group: string}>, allowed_html_tags: list<string>}
	 */
	private function resolveTemplateHints(?Template $item, array $conns): array {
		$providerCode = '';

		if($item !== NULL && $item->connection_id > 0) {
			foreach($conns as $c) {
				if($c->id() === $item->connection_id) {
					$providerCode = $c->provider;
					break;
				}
			}
		}

		$tags = new DefaultTemplateTags();

		if($providerCode !== '') {
			$provider = ProviderRegistry::get($providerCode);

			if($provider !== NULL) {
				$tags = $provider->templateTags();
			}
		}

		$hints = $tags->hints();

		foreach(Application::instance()->dleData()->postXfields() as $name => $meta) {
			$key = is_string($name)? $name : (string) (is_array($meta)? ($meta['name'] ?? '') : '');

			if($key === '') {
				continue;
			}

			$label   = is_array($meta)? (string) ($meta['description'] ?? $key) : $key;
			$descr   = $label !== ''? $label : $key;
			$hints[] = [
				'code'  => '[xfvalue_' . $key . ']',
				'tag'   => 'xfvalue_' . $key,
				'descr' => $descr,
				'group' => 'xfields',
			];
			$hints[] = [
				'code'  => '[xfvalue_' . $key . '_text]',
				'tag'   => 'xfvalue_' . $key . '_text',
				'descr' => $descr . ' (текст без ссылок)',
				'group' => 'xfields',
			];
			$hints[] = [
				'code'  => '[xfvalue_' . $key . '_hashtag]',
				'tag'   => 'xfvalue_' . $key . '_hashtag',
				'descr' => $descr . ' (как хештеги)',
				'group' => 'xfields',
			];
		}

		return [
			'hints'             => $hints,
			'allowed_html_tags' => $tags->allowedHtmlTags(),
		];
	}

	/**
	 * Каталог полей для конфигуратора условий.
	 *
	 * @return array{post: array<string, string>, post_extras: array<string, string>, xfields: array<string, string>, category: array<string, string>}
	 */
	private function buildConditionFields(): array {
		$dleData = Application::instance()->dleData();

		$post = [
			'autor'       => __('Автор'),
			'date'        => __('Дата'),
			'short_story' => __('Короткое содержание'),
			'full_story'  => __('Полное содержание'),
			'title'       => __('Заголовок'),
			'descr'       => __('Описание'),
			'keywords'    => __('Ключевые слова'),
			'alt_name'    => __('ЧПУ Имя'),
			'comm_num'    => __('Кол-во комментариев'),
			'allow_comm'  => __('Разрешить комментарии'),
			'allow_main'  => __('Вывод на главной'),
			'approve'     => __('Проверено'),
			'fixed'       => __('Фиксированная новость'),
			'allow_br'    => __('Разрешить перенос строк'),
			'symbol'      => __('Символ'),
			'tags'        => __('Теги'),
			'metatitle'   => __('Метазаголовок'),
		];

		$postExtras = [
			'news_read'       => __('Кол-во прочтений'),
			'allow_rate'      => __('Разрешить рейтинг'),
			'rating'          => __('Рейтинг'),
			'vote_num'        => __('ID Опроса'),
			'votes'           => __('Кол-во голосов'),
			'view_edit'       => 'view_edit',
			'disable_index'   => __('Запретить индексирование'),
			'related_ids'     => __('Похожие новости'),
			'access'          => __('Доступ'),
			'editdate'        => __('Время редактирования'),
			'editor'          => __('Редактор'),
			'reason'          => __('Причина'),
			'user_id'         => __('ID автора'),
			'disable_search'  => __('Исключить из поиска'),
			'need_pass'       => __('Нужен пароль'),
			'allow_rss'       => __('Разрешить вывод в RSS-ленту'),
			'allow_rss_turbo' => __('Разрешить вывод в Турбо-ленту'),
			'allow_rss_dzen'  => __('Разрешить Дзен'),
			'edited_now'      => 'edited_now',
		];

		$xfields = [];

		foreach($dleData->postXfields() as $name => $meta) {
			$key = is_string($name)? $name : (string) (is_array($meta)? ($meta['name'] ?? '') : '');

			if($key === '') {
				continue;
			}

			$label         = is_array($meta)? (string) ($meta['description'] ?? $key) : $key;
			$xfields[$key] = $label !== ''? $label : $key;
		}

		$category = [];

		foreach($dleData->categories() as $cid => $cname) {
			$category[(string) $cid] = $cname;
		}

		return [
			'post'        => $post,
			'post_extras' => $postExtras,
			'xfields'     => $xfields,
			'category'    => $category,
		];
	}

	private function buildPmEditorScript(): string {
		global $config, $member_id, $user_group, $lang, $tpl;

		if(empty($config['allow_pm_wysiwyg'])) {
			return '';
		}

		if(!is_array($member_id ?? NULL)) {
			$member_id = ['user_group' => 1, 'user_id' => 1, 'name' => ''];
		}

		if(!is_array($user_group ?? NULL) || $user_group === []) {
			$user_group = [
				1 => ['allow_url' => 1, 'allow_image' => 1, 'group_name' => 'Admin'],
			];
		}

		if(!is_array($lang ?? NULL)) {
			$lang = ['language_code' => 'ru', 'direction' => 'ltr'];
		} else {
			$lang['language_code'] = $lang['language_code'] ?? 'ru';
			$lang['direction']     = $lang['direction'] ?? 'ltr';
		}

		if(!isset($tpl) || !is_object($tpl)) {
			if(!class_exists('dle_template', false)) {
				require_once DLEPlugins::Check(ENGINE_DIR . '/classes/templates.class.php');
			}
			$tpl             = new \dle_template();
			$tpl->smartphone = false;
			$tpl->tablet     = false;
		}

		$is_pm_ajax_mode        = true;
		$comments_mobile_editor = false;

		/** @noinspection PhpIncludeInspection */
		include DLEPlugins::Check(ENGINE_DIR . '/editor/pm.php');

		return isset($editor_scrips)? (string) $editor_scrips : '';
	}

}
