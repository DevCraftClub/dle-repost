<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services\Dto;

/**
 * Результат рендера шаблона перед отправкой.
 */
final readonly class RenderedMessage {

	/**
	 * @param   list<string>               $images
	 * @param   list<string>               $videos
	 * @param   list<string>               $audios
	 * @param   list<array{text: string, url: string}>  $buttons
	 */
	public function __construct(
		public string $text,
		public array $images = [],
		public array $videos = [],
		public array $audios = [],
		public array $buttons = [],
		public string $sendType = 'text',
	) {}

}
