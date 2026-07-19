<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services\Dto;

/**
 * Результат отправки в провайдер.
 */
final readonly class SendResult {

	/**
	 * @param   array<string, mixed>  $raw
	 */
	public function __construct(
		public bool $ok,
		public string $message = '',
		public array $raw = [],
	) {}

	public static function success(string $message = '', array $raw = []): self {
		return new self(true, $message, $raw);
	}

	public static function fail(string $message, array $raw = []): self {
		return new self(false, $message, $raw);
	}

}
