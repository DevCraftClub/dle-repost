<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Proxy;

/**
 * Список прокси.
 */
final class ProxiesPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Прокси'));

		/** @var list<Proxy> $items */
		$items = Application::instance()->database()->repository(Proxy::class)
			->select()->orderBy('id', 'DESC')->fetchAll();

		$rows = [];

		foreach($items as $item) {
			$rows[] = [
				'id'       => $item->id(),
				'ip'       => $item->ip,
				'port'     => $item->port,
				'type'     => $item->type,
				'active'   => $item->active,
				'edit_url' => '?mod=repost&action=edit_proxy&id=' . $item->id(),
			];
		}

		return [
			'view' => 'repost/proxies.twig',
			'data' => [
				'page_title' => __('Прокси'),
				'items'      => $rows,
				'new_url'    => '?mod=repost&action=edit_proxy',
			],
		];
	}

}
