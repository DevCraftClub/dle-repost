<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Config\Paths;
use DevCraft\Core\Support\DataManager;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Core\Interfaces\SettingsPageInterface;

/**
 * Настройки RePost.
 */
final class SettingsPage extends AbstractPage implements SettingsPageInterface {

	public function handle(): array {
		$this->addBreadcrumb(__('Настройки'));

		$configFile = Paths::config() . '/repost.json';

		if(!is_file($configFile)) {
			DataManager::saveConfig('repost', DataManager::getConfig('repost'));
		}

		return [
			'view' => 'repost/settings.twig',
			'data' => [
				'page_title' => __('Настройки'),
			],
		];
	}

	public function supplementFormData(): array {
		return [];
	}

}
