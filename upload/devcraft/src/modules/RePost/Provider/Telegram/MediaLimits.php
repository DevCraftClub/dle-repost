<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider\Telegram;

/**
 * Лимиты Telegram Bot API для медиа.
 */
final class MediaLimits {

	public const CAPTION_MAX = 1024;

	public const MESSAGE_MAX = 4096;

	public const MEDIA_GROUP_MAX = 10;

	public const PHOTO_MAX_BYTES = 10485760;

}
