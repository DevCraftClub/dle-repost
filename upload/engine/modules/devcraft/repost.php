<?php

declare(strict_types=1);

/**
 * Bootstrap + API отправки для хуков / парсеров.
 *
 * include DLEPlugins::Check(ENGINE_DIR . '/modules/devcraft/repost.php');
 * sendRepost($id, 'addnews'|'editnews', $options);
 */

use DevCraft\Modules\RePost\Services\DispatchService;

if(!defined('DATALIFEENGINE')) {
	die('Hacking attempt!');
}

require_once DLEPlugins::Check(ROOT_DIR . '/devcraft/init.php');

if(!defined('DEVCRAFT_BOOTSTRAPPED')) {
	return;
}

require_once DLEPlugins::Check(ENGINE_DIR . '/modules/devcraft/repost_news_form.php');

if(!function_exists('sendRepost')) {
	/**
	 * Публикация новости через RePost.
	 *
	 * @param   int                                $id       ID новости
	 * @param   string                             $type     addnews|editnews
	 * @param   array<string, mixed>               $options  defer / planned / template_mode / template_ids
	 *
	 * @return list<\DevCraft\Modules\RePost\Services\Dto\SendResult>
	 */
	function sendRepost(int $id, string $type = 'addnews', array $options = []): array {
		$dispatch = new DispatchService();

		return $dispatch->dispatch($id, $type, $options);
	}
}

if(!function_exists('repostDeleteCronForNews')) {
	function repostDeleteCronForNews(int $newsId): void {
		if($newsId <= 0 || !defined('DEVCRAFT_BOOTSTRAPPED')) {
			return;
		}

		$db = \DevCraft\Core\Application::instance()->database();
		/** @var \DevCraft\Modules\RePost\Repositories\CronItemRepository $repo */
		$repo = $db->repository(\DevCraft\Modules\RePost\Models\CronItem::class);
		$repo->deleteByNewsId($newsId);
	}
}

if(!function_exists('repostRunCron')) {
	function repostRunCron(): int {
		return (new \DevCraft\Modules\RePost\Services\CronRunner())->run();
	}
}
