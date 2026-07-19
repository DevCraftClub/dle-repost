<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services\Dto;

/**
 * Контекст публикации новости.
 */
final readonly class PostContext {

	/**
	 * @param   array<string, mixed>  $newsRow
	 */
	public function __construct(
		public int $newsId,
		public string $eventType,
		public array $newsRow,
	) {}

}
