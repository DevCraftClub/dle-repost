<?php

declare(strict_types=1);

/**
 * Журнал изменений RePost.
 *
 * Гидрируется в `Changelog[]` через `Changelog::listFromManifest()` /
 * `ModuleManifest::fromManifest()` — сам файл возвращает массив массивов.
 *
 * @return array<int, array{version: string, date?: string, changes?: array<string, list<string>>}>
 */
return [
	[
		'version' => '200.1.0',
		'date'    => '2026-07-18',
		'changes' => [
			'added'   => [
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
			],
			'changed' => [
				__('DLE-теги через ParseTemplateTags; префикс медиа-тегов [repost_media_*]'),
				__('Telegram: album photo/video; media_audio/document через группу; одиночные — один файл'),
				__('Список шаблонов: колонка прокси (Нет / Случайный / ip:port)'),
			],
			'fixed'   => [
				__('Тихие сбои отправки без записи в Admin logs'),
			],
			'removed' => [
				__('Зависимость от MH Admin и engine/ajax/maharder/*'),
			],
		],
	],
];
