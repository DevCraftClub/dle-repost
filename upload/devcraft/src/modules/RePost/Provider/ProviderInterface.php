<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

use DevCraft\Types\FormSchema;
use DevCraft\Modules\RePost\Services\Dto\PostContext;
use DevCraft\Modules\RePost\Services\Dto\RenderedMessage;
use DevCraft\Modules\RePost\Services\Dto\SendResult;

/**
 * Контракт канала доставки RePost (соцсеть, webhook, БД, внешний API и т.п.).
 */
interface ProviderInterface {

	public static function code(): string;

	/**
	 * @return array{name: string, title: string, version: string}
	 */
	public static function meta(): array;

	/**
	 * Схема полей подключения (произвольный JSON в connection.config).
	 */
	public function settingsSchema(): FormSchema;

	/**
	 * Доставка отрендеренного сообщения.
	 *
	 * @param   array<string, mixed>       $connectionConfig  Настройки из формы провайдера
	 * @param   array<string, mixed>|null  $proxy             Опциональный HTTP/SOCKS-прокси
	 */
	public function send(
		PostContext $context,
		RenderedMessage $message,
		array $connectionConfig,
		?array $proxy = null,
	): SendResult;

}
