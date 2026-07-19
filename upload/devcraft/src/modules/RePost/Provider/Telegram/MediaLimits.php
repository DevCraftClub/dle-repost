<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider\Telegram;

use DevCraft\Modules\RePost\Provider\AbstractMediaLimit;

/**
 * Лимиты Telegram Bot API для медиа (без Local Bot API).
 */
final class MediaLimits extends AbstractMediaLimit {

	public const CAPTION_MAX     = 1024;

	public const MESSAGE_MAX     = 4096;

	public const MEDIA_GROUP_MAX = 10;

	/** 10 MiB */
	public const PHOTO_MAX_BYTES = 10_485_760;

	/** 50 MiB */
	public const VIDEO_MAX_BYTES = 52_428_800;

	/** 50 MiB */
	public const AUDIO_MAX_BYTES = 52_428_800;

	/** 50 MiB */
	public const DOCUMENT_MAX_BYTES = 52_428_800;

	public static function allowedImageExtensions(): array {
		return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	}

	public static function allowedVideoExtensions(): array {
		// Bot API SendVideo — надёжно для mp4; прочие уходят SendDocument (см. TelegramProvider::sendVideoOne).
		return ['mp4', 'm4v', 'mkv', 'webm', 'avi', 'mov', 'mpeg', 'mpg', '3gp'];
	}

	public static function allowedFileExtensions(): array {
		// audio ['mp3', 'm4a'] + document [] — единый catch-all для «не photo/video».
		return ['mp3', 'm4a'];
	}

	public static function mediaLimits(): array {
		return [
			'caption_max'        => self::CAPTION_MAX,
			'message_max'        => self::MESSAGE_MAX,
			'media_group_max'    => self::MEDIA_GROUP_MAX,
			'photo_max_bytes'    => self::PHOTO_MAX_BYTES,
			'video_max_bytes'    => self::VIDEO_MAX_BYTES,
			'audio_max_bytes'    => self::AUDIO_MAX_BYTES,
			'document_max_bytes' => self::DOCUMENT_MAX_BYTES,
		];
	}

	/**
	 * @param   'photo'|'video'|'audio'|'document'|string  $kind
	 */
	public static function maxBytesFor(string $kind): int {
		return match ($kind) {
			'photo'    => self::PHOTO_MAX_BYTES,
			'video'    => self::VIDEO_MAX_BYTES,
			'audio'    => self::AUDIO_MAX_BYTES,
			'document' => self::DOCUMENT_MAX_BYTES,
			default    => 0,
		};
	}

}
