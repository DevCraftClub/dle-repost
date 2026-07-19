<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;

/**
 * Список подключений.
 */
final class ConnectionsPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Подключения'));

		/** @var list<\DevCraft\Modules\RePost\Models\Connection> $items */
		$items = Application::instance()->database()->repository(Connection::class)
			->select()->orderBy('id', 'DESC')->fetchAll();

		$providers = ProviderRegistry::all();
		$rows      = [];

		foreach($items as $item) {
			$rows[] = [
				'id'       => $item->id(),
				'name'     => $item->name,
				'provider' => $providers[$item->provider]['title'] ?? $item->provider,
				'active'   => $item->active,
				'edit_url' => '?mod=repost&action=edit_connection&id=' . $item->id(),
			];
		}

		return [
			'view' => 'repost/connections.twig',
			'data' => [
				'page_title' => __('Подключения'),
				'items'      => $rows,
				'providers'  => ProviderRegistry::options(),
				'new_url'    => '?mod=repost&action=edit_connection',
			],
		];
	}

}
