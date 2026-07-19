<?php

declare(strict_types=1);

use DevCraft\Types\AdminLink;
use DevCraft\Modules\RePost\Pages\CronPage;
use DevCraft\Modules\RePost\Ajax\CronHandler;
use DevCraft\Modules\RePost\Pages\ProxiesPage;
use DevCraft\Modules\RePost\Ajax\ProxyHandler;
use DevCraft\Modules\RePost\Pages\SettingsPage;
use DevCraft\Modules\RePost\Pages\DashboardPage;
use DevCraft\Modules\RePost\Pages\ChangelogPage;
use DevCraft\Modules\RePost\Pages\TemplatesPage;
use DevCraft\Modules\RePost\Pages\EditProxyPage;
use DevCraft\Modules\RePost\Ajax\SettingsHandler;
use DevCraft\Modules\RePost\Ajax\TemplateHandler;
use DevCraft\Modules\RePost\Pages\ConnectionsPage;
use DevCraft\Modules\RePost\Ajax\ConnectionHandler;
use DevCraft\Modules\RePost\Pages\EditTemplatePage;
use DevCraft\Modules\RePost\Pages\TemplateTagsPage;
use DevCraft\Modules\RePost\Ajax\TelegramTestHandler;
use DevCraft\Modules\RePost\Pages\EditConnectionPage;

/**
 * Манифест модуля RePost.
 *
 * Гидрируется в `ModuleManifest` через `ModuleManifest::fromManifest()` — сам
 * файл возвращает массив в форме, ожидаемой этим методом.
 *
 * @return array{
 *     mod: string,
 *     code?: string,
 *     meta?: array<string, mixed>,
 *     menu?: list<AdminLink>,
 *     ajax?: array{controller?: string, methods?: array<string, class-string>},
 *     changelog?: array<int, array<string, mixed>>,
 *     assets?: array<string, list<string>>,
 * }
 */
return [
	'mod'               => 'repost',
	'code'              => 'repost',
	'meta'              => [
		'name'        => 'RePost',
		'version'     => '200.1.0',
		'description' => __('Публикация новостей в социальные сети (Telegram и провайдеры)'),
		'icon'        => 'mif-telegram',
		'docsLink'    => 'https://readme.devcraft.club/dev/repost/',
		'siteLink'    => 'https://devcraft.club/downloads/repost.30/',
		'siteId'      => 30,
		'author'      => [
			'name'     => 'Maxim Harder',
			'contacts' => [
				['name' => __('E-Mail'), 'link' => 'mailto:dev@devcraft.club'],
				['name' => __('Telegram'), 'link' => 'https://t.me/MaHarder'],
			],
		],
	],
	'menu'              => [
		AdminLink::page(__('Главная'), 'dashboard', DashboardPage::class, 'mif-home', 'repost'),
		AdminLink::page(__('Подключения'), 'connections', ConnectionsPage::class, 'mif-link', 'repost'),
		AdminLink::hidden('edit_connection', EditConnectionPage::class),
		AdminLink::page(__('Шаблоны'), 'templates', TemplatesPage::class, 'mif-files-empty', 'repost'),
		AdminLink::hidden('edit_template', EditTemplatePage::class),
		AdminLink::page(__('Теги шаблонов'), 'template_tags', TemplateTagsPage::class, 'mif-tags', 'repost'),
		AdminLink::page(__('Прокси'), 'proxies', ProxiesPage::class, 'mif-earth', 'repost'),
		AdminLink::hidden('edit_proxy', EditProxyPage::class),
		AdminLink::page(__('Очередь'), 'cron', CronPage::class, 'mif-history', 'repost'),
		AdminLink::page(__('Настройки'), 'settings', SettingsPage::class, 'mif-cog', 'repost'),
		AdminLink::page(__('Журнал изменений'), 'changelog', ChangelogPage::class, 'mif-library', 'repost'),
	],
	'ajax'              => [
		'controller' => 'admin',
		'methods'    => [
			'settings'          => SettingsHandler::class,
			'connection_save'   => ConnectionHandler::class,
			'connection_delete' => ConnectionHandler::class,
			'connection_toggle' => ConnectionHandler::class,
			'connection_copy'   => ConnectionHandler::class,
			'template_save'     => TemplateHandler::class,
			'template_delete'   => TemplateHandler::class,
			'template_toggle'   => TemplateHandler::class,
			'template_copy'     => TemplateHandler::class,
			'proxy_save'        => ProxyHandler::class,
			'proxy_delete'      => ProxyHandler::class,
			'proxy_toggle'      => ProxyHandler::class,
			'proxy_copy'        => ProxyHandler::class,
			'cron_send'         => CronHandler::class,
			'cron_delete'       => CronHandler::class,
			'telegram_test'     => TelegramTestHandler::class,
			'telegram_chat_id'  => TelegramTestHandler::class,
		],
	],
	'composer_required' => [
		['name' => 'luzrain/telegram-bot-api', 'minVersion' => '^3.17', 'hardRequired' => true],
	],
	'changelog'         => require DLEPlugins::Check(DEVCRAFT_MODULES . '/RePost/changelog.data.php'),
	'assets'            => [
		'js' => ['repost.js'],
	],
];
