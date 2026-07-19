<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

use DevCraft\Core\Application;
use DevCraft\Core\Support\DataManager;
use DevCraft\Modules\RePost\Models\CronItem;
use DevCraft\Modules\RePost\Repositories\CronItemRepository;

/**
 * Обработка очереди отложенных публикаций.
 */
final class CronRunner {

	public function run(): int {
		$config = DataManager::getConfig('repost');
		$limit  = max(1, (int) ($config['cron_news'] ?? 5));
		$wait   = max(0, (int) ($config['cron_waittime'] ?? 0));

		$db = Application::instance()->database();
		/** @var CronItemRepository $repo */
		$repo  = $db->repository(CronItem::class);
		$items = $repo->findDue($limit);
		$dispatch = new DispatchService();
		$done     = 0;

		foreach($items as $item) {
			$result = $dispatch->sendCronItem($item);

			if($result->ok) {
				$done++;
			}

			if($wait > 0) {
				sleep($wait);
			}
		}

		return $done;
	}

}
