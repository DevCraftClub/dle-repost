<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;
use DevCraft\Modules\RePost\Repositories\ConnectionRepository;

/**
 * Создание / редактирование подключения.
 */
final class EditConnectionPage extends AbstractPage {

	public function handle(): array {
		$id = (int) ($_GET['id'] ?? 0);
		/** @var ConnectionRepository $repo */
		$repo = Application::instance()->database()->repository(Connection::class);
		$item = $id > 0 ? $repo->findOneById($id) : null;

		$this->addBreadcrumb(__('Подключения'), '?mod=repost&action=connections');
		$this->addBreadcrumb($item ? __('Редактирование') : __('Новое подключение'));

		$provider = $item?->provider ?? (string) ($_GET['provider'] ?? 'telegram');
		$prov     = ProviderRegistry::get($provider);
		$cfg      = $item?->getConfigArray() ?? [];

		$fields = [];

		if($prov !== null) {
			$schema = $prov->settingsSchema();

			foreach($schema->sections as $section) {
				foreach($section->fields as $field) {
					$fields[] = [
						'name'    => $field->id,
						'label'   => $field->label,
						'type'    => $field->type,
						'options' => $field->options,
						'value'   => $cfg[$field->id] ?? ($field->default ?? ''),
						'help'    => $field->description ?? '',
					];
				}
			}
		}

		return [
			'view' => 'repost/edit_connection.twig',
			'data' => [
				'page_title' => $item ? __('Редактирование подключения') : __('Новое подключение'),
				'item'       => [
					'id'       => $item?->id() ?? 0,
					'name'     => $item?->name ?? '',
					'provider' => $provider,
					'active'   => $item?->active ?? true,
				],
				'providers'  => ProviderRegistry::options(),
				'fields'     => $fields,
				'list_url'   => '?mod=repost&action=connections',
			],
		];
	}

}
