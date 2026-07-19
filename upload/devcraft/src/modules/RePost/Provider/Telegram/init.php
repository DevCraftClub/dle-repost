<?php

declare(strict_types=1);

/**
 * Метаданные провайдера Telegram.
 */
use DevCraft\Modules\RePost\Provider\Telegram\TelegramProvider;

return [
	'name'    => 'telegram',
	'title'   => 'Telegram',
	'version' => '200.2.0',
	'class'   => TelegramProvider::class,
];
