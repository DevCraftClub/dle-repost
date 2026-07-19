<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

/**
 * Контракт платформенных лимитов медиа для канала RePost (Telegram/VK/…).
 *
 * В отличие от {@see ProviderInterface::allowedMediaExtensions()} (таксономия
 * `photo/video/audio/document` для маршрутизации отправки конкретным API),
 * здесь упрощённая 3-категорийная таксономия «что вообще разрешено платформой»:
 * файлы/картинки/видео + агрегат — общая для всех платформ форма контракта,
 * при разных числовых значениях лимитов у каждой реализации.
 *
 * @package    DevCraft
 * @since      200.4.0
 * @subpackage Modules.RePost.Provider
 */
interface MediaLimitsInterface {

	/**
	 * Возвращает allowlist расширений по трём категориям + агрегат `all`.
	 *
	 * @return array{files: list<string>, images: list<string>, videos: list<string>, all: list<string>}
	 */
	public static function allowedExtensions(): array;

	/**
	 * @return list<string>
	 */
	public static function allowedFileExtensions(): array;

	/**
	 * @return list<string>
	 */
	public static function allowedImageExtensions(): array;

	/**
	 * @return list<string>
	 */
	public static function allowedVideoExtensions(): array;

	/**
	 * Все числовые/байтовые лимиты платформы как ассоциативный массив.
	 *
	 * @return array<string, int>
	 */
	public static function mediaLimits(): array;

	/**
	 * @param   'photo'|'video'|'audio'|'document'|string  $kind
	 */
	public static function maxBytesFor(string $kind): int;

}
