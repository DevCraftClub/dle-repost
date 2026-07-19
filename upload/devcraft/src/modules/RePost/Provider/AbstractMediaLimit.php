<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

/**
 * Базовая реализация {@see MediaLimitsInterface}: собирает `allowedExtensions()`
 * один раз из трёх конкретных методов, общих для всех платформ.
 *
 * @package    DevCraft
 * @since      200.4.0
 * @subpackage Modules.RePost.Provider
 */
abstract class AbstractMediaLimit implements MediaLimitsInterface {

	public static function allowedExtensions(): array {
		$files  = static::allowedFileExtensions();
		$images = static::allowedImageExtensions();
		$videos = static::allowedVideoExtensions();

		return [
			'files'  => $files,
			'images' => $images,
			'videos' => $videos,
			'all'    => [...$files, ...$images, ...$videos],
		];
	}

}
