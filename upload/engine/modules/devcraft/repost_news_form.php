<?php

declare(strict_types=1);

/**
 * Блок настроек RePost на форме новости + разбор POST-опций.
 */

if(!defined('DATALIFEENGINE')) {
	die('Hacking attempt!');
}

if(!function_exists('repostOptionsFromRequest')) {
	/**
	 * Опции отправки из POST формы новости.
	 *
	 * @return array{
	 *     defer: string,
	 *     planned: ?\DateTimeImmutable,
	 *     template_mode: string,
	 *     template_ids: list<int>
	 * }
	 */
	function repostOptionsFromRequest(): array {
		$defer = (string) ($_POST['repost_defer'] ?? 'template');
		if(!in_array($defer, ['yes', 'no', 'template'], true)) {
			$defer = 'template';
		}

		$mode = (string) ($_POST['repost_tpl_mode'] ?? 'auto');
		if(!in_array($mode, ['auto', 'manual'], true)) {
			$mode = 'auto';
		}

		$rawIds = $_POST['repost_tpl_ids'] ?? [];
		if(!is_array($rawIds)) {
			$rawIds = [];
		}

		$ids = array_values(array_unique(array_filter(
			array_map(static fn(mixed $v): int => (int) $v, $rawIds),
			static fn(int $id): bool => $id > 0,
		)));

		$planned = null;
		$date    = trim((string) ($_POST['repost_plan_date'] ?? ''));
		$time    = trim((string) ($_POST['repost_plan_time'] ?? ''));

		if($date !== '') {
			if($time === '') {
				$time = '00:00';
			}

			try {
				$planned = new \DateTimeImmutable($date . ' ' . $time);
			} catch(\Throwable) {
				$planned = null;
			}
		}

		return [
			'defer'         => $defer,
			'planned'       => $planned,
			'template_mode' => $mode,
			'template_ids'  => $ids,
		];
	}
}

if(!function_exists('repostNewsFormTemplates')) {
	/**
	 * @return list<array{id:int,name:string,provider:string}>
	 */
	function repostNewsFormTemplates(): array {
		$templates = [];

		try {
			if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
				require_once DLEPlugins::Check(ROOT_DIR . '/devcraft/init.php');
			}

			if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
				return [];
			}

			$db = \DevCraft\Core\Application::instance()->database();
			/** @var list<\DevCraft\Modules\RePost\Models\Template> $list */
			$list = $db->repository(\DevCraft\Modules\RePost\Models\Template::class)->findActiveAll();
			$connMap = [];
			/** @var list<\DevCraft\Modules\RePost\Models\Connection> $conns */
			$conns = $db->repository(\DevCraft\Modules\RePost\Models\Connection::class)->select()->fetchAll();

			foreach($conns as $c) {
				$connMap[$c->id()] = $c->provider;
			}

			foreach($list as $tpl) {
				$templates[] = [
					'id'       => $tpl->id(),
					'name'     => $tpl->name,
					'provider' => $connMap[$tpl->connection_id] ?? '?',
				];
			}
		} catch(\Throwable) {
			return [];
		}

		return $templates;
	}
}

if(!function_exists('repostNewsFormHtml')) {
	/**
	 * HTML блока «Отложенная отправка».
	 *
	 * @param   string  $context  admin|frontend
	 */
	function repostNewsFormHtml(string $context = 'admin'): string {
		$templates = repostNewsFormTemplates();
		$context   = $context === 'frontend' ? 'frontend' : 'admin';

		ob_start();
		if($context === 'frontend') {
			repostNewsFormHtmlFrontend($templates);
		} else {
			repostNewsFormHtmlAdmin($templates);
		}

		return (string) ob_get_clean();
	}
}

if(!function_exists('repostNewsFormHtmlAdmin')) {
	/**
	 * HTML блока «Отложенная отправка» для admin add/edit news — там нет $tpl
	 * (dle_template) в контексте, поэтому это чистый PHP-partial с i18n,
	 * а не .tpl-файл.
	 *
	 * @param   list<array{id:int,name:string,provider:string}>  $templates
	 */
	function repostNewsFormHtmlAdmin(array $templates): void {
		$col1 = [];
		$col2 = [];
		if($templates !== []) {
			$half = (int) ceil(count($templates) / 2);
			$col1 = array_slice($templates, 0, $half);
			$col2 = array_slice($templates, $half);
		}

		include DLEPlugins::Check(ENGINE_DIR . '/modules/devcraft/repost/admin_form.php');

		repostNewsFormScript(true);
	}
}

if(!function_exists('repostNewsFormHtmlFrontend')) {
	/**
	 * HTML блока «Отложенная отправка» для фронтенда (форма добавления новости
	 * пользователем) — здесь доступен настоящий $tpl (dle_template), поэтому
	 * фрагмент рендерится через отдельный dle_template-инстанс и .tpl-файлы
	 * из templates/{skin}/devcraft/repost/, а не через echo/heredoc.
	 *
	 * @param   list<array{id:int,name:string,provider:string}>  $templates
	 */
	function repostNewsFormHtmlFrontend(array $templates): void {
		global $config;

		$skin = totranslit((string) ($config['skin'] ?? 'Default'), false, false);
		if(!is_dir(ROOT_DIR . '/templates/' . $skin . '/devcraft/repost')) {
			$skin = 'Default';
		}

		$fragment      = new dle_template();
		$fragment->dir = ROOT_DIR . '/templates/' . $skin;
		$fragment->load_template('devcraft/repost/frontend.tpl');

		if($templates === []) {
			$templatesBlock = '<span class="grey">' . __('Нет активных шаблонов') . '</span>';
		} else {
			$itemTpl        = $fragment->sub_load_template('devcraft/repost/frontend_item.tpl');
			$templatesBlock = '';

			foreach($templates as $t) {
				$templatesBlock .= str_replace(
					['{id}', '{name}', '{provider}'],
					[
						(int) $t['id'],
						htmlspecialchars((string) $t['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
						htmlspecialchars((string) $t['provider'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
					],
					$itemTpl,
				);
			}
		}

		$fragment->set('{repost-defer-label}', __('Отложенная отправка'));
		$fragment->set('{repost-yes-label}', __('да'));
		$fragment->set('{repost-no-label}', __('нет'));
		$fragment->set('{repost-tpl-mode-label}', __('настройка шаблона'));
		$fragment->set('{repost-time-label}', __('Время отправки'));
		$fragment->set('{repost-time-hint}', __('пусто = сразу в очередь / due'));
		$fragment->set('{repost-select-tpl-label}', __('Выбор шаблона'));
		$fragment->set('{repost-auto-label}', __('автоматически'));
		$fragment->set('{repost-manual-label}', __('выбор из активных'));
		$fragment->set('{repost-templates-block}', $templatesBlock);

		$fragment->compile('content');

		echo $fragment->result['content'] ?? '';

		repostNewsFormScript(false);
	}
}

if(!function_exists('repostNewsFormScript')) {
	function repostNewsFormScript(bool $withIcheck): void {
		?>
<script>
(function () {
	var wrap = document.getElementById('repost_tpl_ids_wrap');
	if (!wrap) return;
	function sync() {
		var m = document.querySelector('#repost-news-form input[name="repost_tpl_mode"]:checked');
		var manual = m && m.value === 'manual';
		wrap.style.display = manual ? '' : 'none';
		wrap.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
			el.disabled = !manual;
<?php if($withIcheck) { ?>
			if (window.jQuery && jQuery(el).data('iCheck')) {
				jQuery(el).iCheck(manual ? 'enable' : 'disable');
			}
<?php } ?>
		});
	}
	function bind() {
<?php if($withIcheck) { ?>
		if (window.jQuery && jQuery.fn.iCheck) {
			jQuery('#repost-news-form input.icheck').on('ifChanged', sync);
		}
<?php } ?>
		document.querySelectorAll('#repost-news-form input[name="repost_tpl_mode"]').forEach(function (el) {
			el.addEventListener('change', sync);
		});
		sync();
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
</script>
		<?php
	}
}
