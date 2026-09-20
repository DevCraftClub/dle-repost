<?php

declare(strict_types=1);

use DevCraft\Builders\ChangelogBuilder;

/**
 * Журнал изменений RePost.
 *
 * @return list<\DevCraft\Types\Changelog>
 */
return [
	ChangelogBuilder::create('200.1.0')
		->date('2026-09-20')
		->added([
			__('Миграция на DevCraft Admin: multi-provider ядро, Telegram как эталон'),
			__('Несколько подключений API и шаблонов с привязкой к подключению'),
			__('Очередь cron, прокси, автопостинг через install.xml'),
			__('Парсинг xfields audio/video; фильтр медиа по tg_send_type'),
			__('Подтипы media/media_video/media_audio/media_document; одиночные типы — первый файл'),
			__('TemplateTagsInterface + чипы тегов и HTML-allowlist канала в редакторе'),
			__('Селекторы repost_media_* (image/max/url), tags_no_link, xfvalue_*_text/hashtag, thumb'),
			__('allowedMediaExtensions + скачивание внешних url= на сервер'),
			__('Справочник «Теги шаблонов» в админке (hints / HTML / расширения)'),
			__('AbstractProvider и контракт канала доставки'),
			__('Копирование подключений, прокси и шаблонов'),
			__('Логирование отправки через LogGenerator (error/info/debug)'),
		])
		->changed([
			__('DLE-теги через ParseTemplateTags; префикс медиа-тегов [repost_media_*]'),
			__('Telegram: album photo/video; media_audio/document через группу; одиночные — один файл'),
			__('Список шаблонов: колонка прокси (Нет / Случайный / ip:port)'),
			__('Хуки DLE: Controller/hooks.php и Controller/news_form.php от корня сайта, без engine/modules/devcraft.'),
			__('install.xml: иконка — путь к Public/icon.png, allow_groups 1,2, notice — страница плагина и документация.'),
			__('Журнал изменений через ChangelogBuilder; docsLink на latest/dev/repost/install.'),
		])
		->fixed([
			__('Тихие сбои отправки без записи в Admin logs'),
		])
		->removed([
			__('Зависимость от MH Admin и engine/ajax/maharder/*'),
			__('Файлы engine/modules/devcraft/repost.php, repost_news_form.php и repost/admin_form.php.'),
			__('Автопосев repost.json при открытии настроек.'),
		])
		->build(),
];
