<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Models\CronItem;
use DevCraft\Modules\RePost\Models\Template;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;

/**
 * Главная страница RePost.
 */
final class DashboardPage extends AbstractPage {

	public function handle(): array {
		$registry  = Application::instance()->registry();
		$plugin    = $registry->forMod('repost');
		$meta      = $plugin?->meta() ?? [];
		$context   = $this->adminContext();
		$changelog = $plugin?->changelog() ?? [];
		$latest    = isset($changelog[0]) ? $changelog[0]->toArray() : null;
		$menu      = [];

		if($latest !== null) {
			$latest['teaser_items'] = $changelog[0]->teaserItems(3);
		}

		foreach($context->menu() as $link) {
			if($link->type !== 'link' || $link->action === null || $link->action === 'dashboard') {
				continue;
			}

			$menu[] = [
				'name'   => $link->name,
				'link'   => $link->link,
				'icon'   => $link->extra,
				'action' => $link->action,
			];
		}

		$db = Application::instance()->database();
		$stats = [
			'connections' => $db->repository(Connection::class)->select()->count(),
			'templates'   => $db->repository(Template::class)->select()->count(),
			'queue'       => $db->repository(CronItem::class)->select()->count(),
			'providers'   => count(ProviderRegistry::all()),
		];

		return [
			'view' => 'pages/dashboard.twig',
			'data' => [
				'page_title' => (string) ($meta['name'] ?? 'RePost'),
				'dashboard'  => [
					'app'              => [
						'name'        => (string) ($meta['name'] ?? 'RePost'),
						'version'     => (string) ($meta['version'] ?? '0.0.0'),
						'description' => (string) ($meta['description'] ?? ''),
						'icon'        => (string) ($meta['icon'] ?? ''),
						'docs_link'   => (string) ($meta['docsLink'] ?? ''),
						'site_link'   => (string) ($meta['siteLink'] ?? ''),
						'site_id'     => (int) ($meta['siteId'] ?? 0),
						'code'        => 'repost',
					],
					'author'           => $context->author()->toArray(),
					'lic_link'         => $context->licLink(),
					'menu'             => $menu,
					'changelog_latest' => $latest,
					'changelog_url'    => '?mod=repost&action=changelog',
					'show_assets'      => false,
					'show_update'      => false,
					'extra_stats'      => $stats,
				],
			],
		];
	}

}
