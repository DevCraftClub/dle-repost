<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\CronItem;
use DevCraft\Modules\RePost\Models\Template;

/**
 * Очередь отложенных публикаций.
 */
final class CronPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Очередь'));

		$db = Application::instance()->database();
		/** @var list<CronItem> $items */
		$items = $db->repository(CronItem::class)->select()->orderBy('planned')->fetchAll();
		/** @var list<Template> $tpls */
		$tpls = $db->repository(Template::class)->select()->fetchAll();
		$map  = [];

		foreach($tpls as $t) {
			$map[$t->id()] = $t->name;
		}

		$rows = [];

		foreach($items as $item) {
			$rows[] = [
				'id'          => $item->id(),
				'news_id'     => $item->news_id,
				'template'    => $map[$item->template_id] ?? ('#' . $item->template_id),
				'event_type'  => $item->event_type,
				'planned'     => $item->planned->format('Y-m-d H:i:s'),
			];
		}

		return [
			'view' => 'repost/cron.twig',
			'data' => [
				'page_title' => __('Очередь'),
				'items'      => $rows,
			],
		];
	}

}
