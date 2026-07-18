<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Models\Proxy;
use DevCraft\Modules\RePost\Models\Template;

/**
 * Список шаблонов.
 */
final class TemplatesPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Шаблоны'));

		$db = Application::instance()->database();
		/** @var list<Template> $items */
		$items = $db->repository(Template::class)->select()->orderBy('id', 'DESC')->fetchAll();
		/** @var list<Connection> $conns */
		$conns = $db->repository(Connection::class)->select()->fetchAll();
		/** @var list<Proxy> $proxies */
		$proxies = $db->repository(Proxy::class)->select()->fetchAll();
		$map     = [];
		$proxyMap = [];

		foreach($conns as $c) {
			$map[$c->id()] = $c->name;
		}

		foreach($proxies as $p) {
			$proxyMap[$p->id()] = $p->ip . ':' . $p->port;
		}

		$rows = [];

		foreach($items as $item) {
			$rows[] = [
				'id'          => $item->id(),
				'name'        => $item->name,
				'connection'  => $map[$item->connection_id] ?? ('#' . $item->connection_id),
				'type'        => $item->template_type,
				'active'      => $item->active,
				'cron'        => $item->cron,
				'proxy_label' => $this->proxyLabel($item, $proxyMap),
				'edit_url'    => '?mod=repost&action=edit_template&id=' . $item->id(),
			];
		}

		return [
			'view' => 'repost/templates.twig',
			'data' => [
				'page_title' => __('Шаблоны'),
				'items'      => $rows,
				'new_url'    => '?mod=repost&action=edit_template',
			],
		];
	}

	/**
	 * @param   array<int, string>  $proxyMap
	 */
	private function proxyLabel(Template $item, array $proxyMap): string {
		if(!$item->use_proxy) {
			return __('Нет');
		}

		$pid = $item->proxy_id ?? 0;

		if($pid <= 0) {
			return __('Случайный');
		}

		return $proxyMap[$pid] ?? ('#' . $pid);
	}

}
