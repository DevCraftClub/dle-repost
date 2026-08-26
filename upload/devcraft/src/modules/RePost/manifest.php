<?php

declare(strict_types=1);

use DevCraft\Modules\RePost\RePostIdentity;

use DevCraft\Types\AdminLink;
use DevCraft\Types\ModuleManifest;
use DevCraft\Builders\ModuleManifestBuilder;
use DevCraft\Builders\ModuleAjaxConfigBuilder;
use DevCraft\Builders\ModuleAssetsBuilder;
use DevCraft\Builders\ComposerTypeBuilder;
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
 * Манифест модуля RePost (fluent ModuleManifestBuilder).
 *
 * @package    DevCraft
 * @since      200.1.0
 * @subpackage Modules.RePost
 *
 * @return ModuleManifest
 */
return ModuleManifestBuilder::create()
	->mod(RePostIdentity::mod())
	->code(RePostIdentity::code())
	->name('RePost')
	->version('200.1.0')
	->description(__('Публикация новостей в социальные сети (Telegram и провайдеры)'))
	->icon('mif-telegram')
	->docsLink('https://readme.devcraft.club/dev/repost/')
	->siteLink('https://devcraft.club/downloads/repost.30/')
	->siteId(30)
	->menu([
		AdminLink::page(__('Главная'), 'dashboard', DashboardPage::class, 'mif-home', RePostIdentity::mod()),
		AdminLink::page(__('Подключения'), 'connections', ConnectionsPage::class, 'mif-link', RePostIdentity::mod()),
		AdminLink::hidden('edit_connection', EditConnectionPage::class),
		AdminLink::page(__('Шаблоны'), 'templates', TemplatesPage::class, 'mif-files-empty', RePostIdentity::mod()),
		AdminLink::hidden('edit_template', EditTemplatePage::class),
		AdminLink::page(__('Теги шаблонов'), 'template_tags', TemplateTagsPage::class, 'mif-tags', RePostIdentity::mod()),
		AdminLink::page(__('Прокси'), 'proxies', ProxiesPage::class, 'mif-earth', RePostIdentity::mod()),
		AdminLink::hidden('edit_proxy', EditProxyPage::class),
		AdminLink::page(__('Очередь'), 'cron', CronPage::class, 'mif-history', RePostIdentity::mod()),
		AdminLink::page(__('Настройки'), 'settings', SettingsPage::class, 'mif-cog', RePostIdentity::mod()),
		AdminLink::page(__('Журнал изменений'), 'changelog', ChangelogPage::class, 'mif-library', RePostIdentity::mod()),
	])
	->ajax(
		ModuleAjaxConfigBuilder::create('admin')
			->methods([
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
			])
	)
	->composerRequired([
		ComposerTypeBuilder::create('luzrain/telegram-bot-api')->minVersion('^3.17')->hardRequired()->build(),
	])
	->changelog(require DLEPlugins::Check(DEVCRAFT_MODULES . '/RePost/changelog.data.php'))
	->assets(ModuleAssetsBuilder::create()->js('repost.js'))
	->build(__DIR__);
