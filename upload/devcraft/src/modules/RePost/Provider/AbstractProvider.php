<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

use DevCraft\Modules\RePost\Services\Dto\SendResult;

/**
 * База канала доставки: хелперы SendResult без HTTP/ORM.
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * @param   array<string, mixed>  $raw
	 */
	protected function ok(string $message = '', array $raw = []): SendResult {
		return SendResult::success($message !== '' ? $message : __('Отправлено'), $raw);
	}

	/**
	 * @param   array<string, mixed>  $raw
	 */
	protected function fail(string $message, array $raw = []): SendResult {
		return SendResult::fail($message, $raw);
	}

}
