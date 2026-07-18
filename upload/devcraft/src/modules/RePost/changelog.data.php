<?php

declare(strict_types=1);

/**
 * Журнал изменений RePost.
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
				__('AbstractProvider и контракт канала доставки'),
				__('Копирование подключений, прокси и шаблонов'),
				__('Логирование отправки через LogGenerator (error/info/debug)'),
			],
			'changed' => [
				__('DLE-теги через ParseTemplateTags; префикс медиа-тегов [repost_media_*]'),
				__('Telegram: SendAudio/SendVideo на каждый файл; album без смешения audio'),
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
