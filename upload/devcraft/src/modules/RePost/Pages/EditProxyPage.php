<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Proxy;
use DevCraft\Modules\RePost\Repositories\ProxyRepository;

/**
 * Создание / редактирование прокси.
 */
final class EditProxyPage extends AbstractPage {

	public function handle(): array {
		$id = (int) ($_GET['id'] ?? 0);
		/** @var ProxyRepository $repo */
		$repo = Application::instance()->database()->repository(Proxy::class);
		$item = $id > 0 ? $repo->findOneById($id) : null;

		$this->addBreadcrumb(__('Прокси'), '?mod=repost&action=proxies');
		$this->addBreadcrumb($item ? __('Редактирование') : __('Новый прокси'));

		return [
			'view' => 'repost/edit_proxy.twig',
			'data' => [
				'page_title' => $item ? __('Редактирование прокси') : __('Новый прокси'),
				'item'       => [
					'id'     => $item?->id() ?? 0,
					'ip'     => $item?->ip ?? '',
					'port'   => $item?->port ?? 0,
					'type'   => $item?->type ?? 'http',
					'user'   => $item?->user ?? '',
					'pass'   => $item?->pass ?? '',
					'auth'   => $item?->auth ?? false,
					'active' => $item?->active ?? true,
				],
				'list_url'   => '?mod=repost&action=proxies',
			],
		];
	}

}
