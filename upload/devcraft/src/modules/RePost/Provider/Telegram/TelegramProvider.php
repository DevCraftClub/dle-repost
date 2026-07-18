<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider\Telegram;

use DLEPlugins;
use DevCraft\Types\FormSchema;
use DevCraft\Modules\RePost\Provider\AbstractProvider;
use DevCraft\Modules\RePost\Services\Dto\PostContext;
use DevCraft\Modules\RePost\Services\Dto\RenderedMessage;
use DevCraft\Modules\RePost\Services\Dto\SendResult;
use Luzrain\TelegramBotApi\BotApi;
use Luzrain\TelegramBotApi\Method;
use Luzrain\TelegramBotApi\Type;

/**
 * Канал доставки Telegram через luzrain/telegram-bot-api.
 */
final class TelegramProvider extends AbstractProvider {

	public static function code(): string {
		return 'telegram';
	}

	public static function meta(): array {
		return [
			'name'    => 'telegram',
			'title'   => 'Telegram',
			'version' => '200.2.0',
		];
	}

	public function settingsSchema(): FormSchema {
		/** @var FormSchema $schema */
		$schema = require DLEPlugins::Check(__DIR__ . '/settings.schema.php');

		return $schema;
	}

	public function send(
		PostContext $context,
		RenderedMessage $message,
		array $connectionConfig,
		?array $proxy = null,
	): SendResult {
		$token = trim((string) ($connectionConfig['tg_bot'] ?? ''));
		$chat  = trim((string) ($connectionConfig['tg_chat_id'] ?? ''));
		$type  = (string) ($connectionConfig['tg_send_type'] ?? $message->sendType ?: 'text');

		if($token === '' || $chat === '') {
			return $this->fail(__('Не заданы токен бота или chat id'));
		}

		$chat    = str_replace('%40', '@', $chat);
		$text    = $this->clip($message->text, $type === 'text' ? MediaLimits::MESSAGE_MAX : MediaLimits::CAPTION_MAX);
		$markup  = $this->buildReplyMarkup($message->buttons);
		$bot     = TelegramBotFactory::create($token, $proxy);

		try {
			$result = match($type) {
				'photo'    => $this->sendPhoto($bot, $chat, $message, $text, $markup),
				'audio'    => $this->sendAudio($bot, $chat, $message, $text, $markup),
				'video'    => $this->sendVideo($bot, $chat, $message, $text, $markup),
				'document' => $this->sendDocument($bot, $chat, $message, $text, $markup),
				'media'    => $this->sendMedia($bot, $chat, $message, $text, $markup),
				default    => $bot->call(new Method\SendMessage(
					chatId: $chat,
					text: $text,
					parseMode: 'HTML',
					replyMarkup: $markup,
				)),
			};
		} catch(\Throwable $e) {
			return $this->fail($e->getMessage() !== '' ? $e->getMessage() : __('Ошибка Telegram API'));
		}

		if($result instanceof SendResult) {
			return $result;
		}

		return $this->ok(__('Отправлено'), $this->toRaw($result));
	}

	/**
	 * @return list<Type\Update>
	 *
	 * @throws \Throwable
	 */
	public function getUpdates(string $token, ?array $proxy = null, int $limit = 10): array {
		$bot = TelegramBotFactory::create($token, $proxy);

		/** @var list<Type\Update> $updates */
		$updates = $bot->call(new Method\GetUpdates(limit: $limit));

		return $updates;
	}

	/**
	 * Тестовое текстовое сообщение.
	 *
	 * @param   array<string, mixed>|null  $proxy
	 */
	public function sendTestMessage(string $token, string $chatId, string $text, ?array $proxy = null): SendResult {
		$chat = str_replace('%40', '@', trim($chatId));

		if($token === '' || $chat === '') {
			return $this->fail(__('Не заданы токен бота или chat id'));
		}

		$bot = TelegramBotFactory::create($token, $proxy);

		try {
			$result = $bot->call(new Method\SendMessage(
				chatId: $chat,
				text: $text,
				parseMode: 'HTML',
			));
		} catch(\Throwable $e) {
			return $this->fail($e->getMessage() !== '' ? $e->getMessage() : __('Ошибка Telegram API'));
		}

		return $this->ok(__('Отправлено'), $this->toRaw($result));
	}

	private function sendPhoto(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|SendResult {
		$photo = $message->images[0] ?? '';

		if($photo === '') {
			return $this->fail(__('Нет изображения для sendPhoto'));
		}

		$media = $this->resolveMedia($photo);

		if($media === null) {
			return $this->fail(__('Файл изображения недоступен локально'));
		}

		return $bot->call(new Method\SendPhoto(
			chatId: $chat,
			photo: $media,
			caption: $text,
			parseMode: 'HTML',
			replyMarkup: $markup,
		));
	}

	/**
	 * Каждый трек — отдельный SendAudio.
	 */
	private function sendAudio(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): SendResult {
		if($message->audios === []) {
			return $this->fail(__('Нет аудио для sendAudio'));
		}

		$raw   = [];
		$sent  = 0;
		$first = true;

		foreach($message->audios as $audio) {
			$media = $this->resolveMedia($audio);

			if($media === null) {
				continue;
			}

			$msg = $bot->call(new Method\SendAudio(
				chatId: $chat,
				audio: $media,
				caption: $first ? $text : null,
				parseMode: $first ? 'HTML' : null,
				replyMarkup: $first ? $markup : null,
			));
			$raw[] = $this->toRaw($msg);
			$sent++;
			$first = false;
		}

		return $sent > 0
			? $this->ok(__('Отправлено'), ['messages' => $raw, 'count' => $sent])
			: $this->fail(__('Аудиофайл недоступен локально'));
	}

	/**
	 * Каждый файл — отдельный SendVideo.
	 */
	private function sendVideo(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): SendResult {
		if($message->videos === []) {
			return $this->fail(__('Нет видео для sendVideo'));
		}

		$raw   = [];
		$sent  = 0;
		$first = true;

		foreach($message->videos as $video) {
			$media = $this->resolveMedia($video);

			if($media === null) {
				continue;
			}

			$msg = $bot->call(new Method\SendVideo(
				chatId: $chat,
				video: $media,
				caption: $first ? $text : null,
				parseMode: $first ? 'HTML' : null,
				replyMarkup: $first ? $markup : null,
			));
			$raw[] = $this->toRaw($msg);
			$sent++;
			$first = false;
		}

		return $sent > 0
			? $this->ok(__('Отправлено'), ['messages' => $raw, 'count' => $sent])
			: $this->fail(__('Видеофайл недоступен локально'));
	}

	private function sendDocument(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|SendResult {
		$doc = $message->images[0] ?? $message->videos[0] ?? '';

		if($doc === '') {
			return $this->fail(__('Нет файла для sendDocument'));
		}

		$media = $this->resolveMedia($doc);

		if($media === null) {
			return $this->fail(__('Документ недоступен локально'));
		}

		return $bot->call(new Method\SendDocument(
			chatId: $chat,
			document: $media,
			caption: $text,
			parseMode: 'HTML',
			replyMarkup: $markup,
		));
	}

	/**
	 * Album photo/video; audio — отдельными SendAudio (Telegram не смешивает типы).
	 *
	 * @return Type\Message|list<Type\Message>|SendResult
	 */
	private function sendMedia(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|array|SendResult {
		$items = $this->buildMediaGroup($message, $text);
		$raw   = [];

		if($items === []) {
			if($message->audios === []) {
				return $bot->call(new Method\SendMessage(
					chatId: $chat,
					text: $text,
					parseMode: 'HTML',
					replyMarkup: $markup,
				));
			}
		} elseif(count($items) === 1) {
			$one = $items[0];

			if($one instanceof Type\InputMediaPhoto) {
				$raw[] = $this->toRaw($bot->call(new Method\SendPhoto(
					chatId: $chat,
					photo: $one->media,
					caption: $one->caption ?? $text,
					parseMode: 'HTML',
					replyMarkup: $markup,
				)));
			} else {
				$raw[] = $this->toRaw($bot->call(new Method\SendVideo(
					chatId: $chat,
					video: $one->media,
					caption: $one->caption ?? $text,
					parseMode: 'HTML',
					replyMarkup: $markup,
				)));
			}
		} else {
			$raw[] = $this->toRaw($bot->call(new Method\SendMediaGroup(
				chatId: $chat,
				media: $items,
			)));
		}

		$audioFirst = $items === [];
		$audioRes   = $this->sendAudioTracks(
			$bot,
			$chat,
			$message->audios,
			$audioFirst ? $text : '',
			$audioFirst ? $markup : null,
		);

		if($audioRes !== null) {
			if(!$audioRes->ok && $raw === []) {
				return $audioRes;
			}

			$raw['audios'] = $audioRes->raw;
		}

		return $raw === [] && $items === []
			? $this->fail(__('Нет медиа для отправки'))
			: $this->ok(__('Отправлено'), $raw);
	}

	/**
	 * @param   list<string>  $audios
	 */
	private function sendAudioTracks(
		BotApi $bot,
		string $chat,
		array $audios,
		string $caption,
		?Type\InlineKeyboardMarkup $markup,
	): ?SendResult {
		if($audios === []) {
			return null;
		}

		$tmp = new RenderedMessage(text: $caption, audios: $audios);

		return $this->sendAudio($bot, $chat, $tmp, $caption, $markup);
	}

	/**
	 * Только photo/video для SendMediaGroup.
	 *
	 * @return list<Type\InputMediaPhoto|Type\InputMediaVideo>
	 */
	private function buildMediaGroup(RenderedMessage $message, string $caption): array {
		$items = [];
		$n     = 0;

		$push = function (string $kind, string $src) use (&$items, &$n, $caption): void {
			if(count($items) >= MediaLimits::MEDIA_GROUP_MAX) {
				return;
			}

			$media = $this->resolveMedia($src);

			if($media === null) {
				return;
			}

			$cap  = ($n === 0 && $caption !== '') ? $caption : null;
			$mode = $cap !== null ? 'HTML' : null;

			$items[] = $kind === 'video'
				? new Type\InputMediaVideo(media: $media, caption: $cap, parseMode: $mode)
				: new Type\InputMediaPhoto(media: $media, caption: $cap, parseMode: $mode);
			$n++;
		};

		foreach($message->images as $img) {
			$push('photo', $img);
		}

		foreach($message->videos as $vid) {
			$push('video', $vid);
		}

		return $items;
	}

	/**
	 * Локальный файл → InputFile; публичный URL → string; приватный хост без файла → null.
	 */
	private function resolveMedia(string $path): Type\InputFile|string|null {
		$local = $this->localPath($path);

		if($local !== null) {
			return new Type\InputFile($local);
		}

		$url = $this->toAbsoluteUrl($path);

		return $this->isPrivateHostUrl($url) ? null : $url;
	}

	/**
	 * @param   list<array{text: string, url: string}>  $buttons
	 */
	private function buildReplyMarkup(array $buttons): ?Type\InlineKeyboardMarkup {
		if($buttons === []) {
			return null;
		}

		$rows = [];

		foreach($buttons as $btn) {
			$text = trim($btn['text'] ?? '');
			$url  = trim($btn['url'] ?? '');

			if($text === '' || $url === '') {
				continue;
			}

			$rows[] = [new Type\InlineKeyboardButton(text: $text, url: $url)];
		}

		if($rows === []) {
			return null;
		}

		return new Type\InlineKeyboardMarkup(inlineKeyboard: $rows);
	}

	private function isPrivateHostUrl(string $url): bool {
		$host = parse_url($url, PHP_URL_HOST);

		if(!is_string($host) || $host === '') {
			return true;
		}

		$host = strtolower($host);

		if($host === 'localhost' || str_ends_with($host, '.test') || str_ends_with($host, '.local')
			|| str_ends_with($host, '.localhost') || str_ends_with($host, '.invalid')
		) {
			return true;
		}

		if(filter_var($host, FILTER_VALIDATE_IP)) {
			return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
		}

		return false;
	}

	/**
	 * Путь на диске, если файл лежит в uploads сайта.
	 */
	private function localPath(string $path): ?string {
		global $config;

		$path = trim(str_replace('\\', '/', $path));

		if($path === '' || !defined('ROOT_DIR')) {
			return null;
		}

		$candidates = [];

		if(str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
			$home = rtrim((string) ($config['http_home_url'] ?? ''), '/');

			if($home !== '' && str_starts_with($path, $home)) {
				$rel          = ltrim(substr($path, strlen($home)), '/');
				$candidates[] = ROOT_DIR . '/' . $rel;
			}

			$urlPath = parse_url($path, PHP_URL_PATH);

			if(is_string($urlPath) && $urlPath !== '') {
				$candidates[] = ROOT_DIR . $urlPath;
			}
		} elseif(str_starts_with($path, '/')) {
			$candidates[] = ROOT_DIR . $path;
			$candidates[] = $path;
		} else {
			$candidates[] = ROOT_DIR . '/' . ltrim($path, '/');
		}

		foreach($candidates as $candidate) {
			if(is_file($candidate) && is_readable($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	private function toAbsoluteUrl(string $path): string {
		global $config;

		$path = trim($path);

		if($path === '') {
			return $path;
		}

		if(str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
			return $path;
		}

		$home = rtrim((string) ($config['http_home_url'] ?? '/'), '/');

		if(str_starts_with($path, '/')) {
			return $home . $path;
		}

		return $home . '/' . ltrim($path, '/');
	}

	private function clip(string $text, int $max): string {
		if(mb_strlen($text) <= $max) {
			return $text;
		}

		return mb_substr($text, 0, $max - 1) . '…';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function toRaw(mixed $result): array {
		try {
			$decoded = json_decode((string) json_encode($result), true);
		} catch(\Throwable) {
			return [];
		}

		return is_array($decoded) ? $decoded : [];
	}

}
