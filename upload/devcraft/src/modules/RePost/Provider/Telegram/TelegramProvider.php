<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider\Telegram;

use DLEPlugins;
use DevCraft\Types\FormSchema;
use DevCraft\Modules\RePost\Provider\AbstractProvider;
use DevCraft\Modules\RePost\Provider\TemplateTagsInterface;
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

	public function templateTags(): TemplateTagsInterface {
		return new TelegramTemplateTags();
	}

	/**
	 * @return array{photo: int, video: int, audio: int, document: int}
	 */
	protected function mediaByteLimits(): array {
		return [
			'photo'    => MediaLimits::PHOTO_MAX_BYTES,
			'video'    => MediaLimits::VIDEO_MAX_BYTES,
			'audio'    => MediaLimits::AUDIO_MAX_BYTES,
			'document' => MediaLimits::DOCUMENT_MAX_BYTES,
		];
	}

	/**
	 * @return array{photo: list<string>, video: list<string>, audio: list<string>, document: list<string>}
	 */
	public function allowedMediaExtensions(): array {
		return [
			'photo'    => MediaLimits::allowedImageExtensions(),
			'video'    => MediaLimits::allowedVideoExtensions(),
			'audio'    => ['mp3', 'm4a'],
			'document' => [],
		];
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

		try {
			$filtered = $this->filterMediaByLimits($message);
			$message  = $filtered['message'];
			$skipped  = $filtered['skipped'];

			if(in_array($type, ['photo', 'video', 'audio', 'document', 'media', 'media_video', 'media_audio', 'media_document'], true)
				&& !$this->hasMediaForType($message, $type)
			) {
				return $this->fail(
					__('Все файлы превышают лимит канала или недоступны'),
					['skipped_oversized' => $skipped],
				);
			}

			$chat   = str_replace('%40', '@', $chat);
			$text   = $this->clip($message->text, $type === 'text' ? MediaLimits::MESSAGE_MAX : MediaLimits::CAPTION_MAX);
			$markup = $this->buildReplyMarkup($message->buttons);
			$bot    = TelegramBotFactory::create($token, $proxy);

			$result = match($type) {
				'photo'           => $this->sendPhoto($bot, $chat, $message, $text, $markup),
				'audio'           => $this->sendAudioOne($bot, $chat, $message, $text, $markup),
				'video'           => $this->sendVideoOne($bot, $chat, $message, $text, $markup),
				'document'        => $this->sendDocument($bot, $chat, $message, $text, $markup),
				'media'           => $this->sendMedia($bot, $chat, $message, $text, $markup),
				'media_video'     => $this->sendMedia($bot, $chat, $message, $text, $markup),
				'media_audio'     => $this->sendAudioGroup($bot, $chat, $message, $text, $markup),
				'media_document'  => $this->sendDocumentGroup($bot, $chat, $message, $text, $markup),
				default           => $bot->call(new Method\SendMessage(
					chatId: $chat,
					text: $text,
					parseMode: 'HTML',
					replyMarkup: $markup,
				)),
			};
		} catch(\Throwable $e) {
			return $this->fail($e->getMessage() !== '' ? $e->getMessage() : __('Ошибка Telegram API'), [
				'skipped_oversized' => $skipped ?? [],
			]);
		} finally {
			$this->cleanupTempMedia();
		}

		if($result instanceof SendResult) {
			if(($skipped ?? []) !== []) {
				$raw                      = $result->raw;
				$raw['skipped_oversized'] = $skipped;

				return $result->ok
					? $this->ok($result->message, $raw)
					: $this->fail($result->message, $raw);
			}

			return $result;
		}

		$raw = $this->toRaw($result);

		if(($skipped ?? []) !== []) {
			$raw['skipped_oversized'] = $skipped;
		}

		return $this->ok(__('Отправлено'), $raw);
	}

	private function hasMediaForType(RenderedMessage $message, string $type): bool {
		return match($type) {
			'photo'          => $message->images !== [],
			'video', 'media_video' => $message->videos !== [] || $message->images !== [],
			'audio', 'media_audio' => $message->audios !== [],
			'document'       => $message->images !== [] || $message->videos !== [] || $message->audios !== [],
			'media', 'media_document' => $message->images !== [] || $message->videos !== [] || $message->audios !== [],
			default          => true,
		};
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

		$media = $this->resolveMedia($photo, 'photo');

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
	 * Одиночный audio — только первый файл.
	 */
	private function sendAudioOne(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|SendResult {
		$audio = $message->audios[0] ?? '';

		if($audio === '') {
			return $this->fail(__('Нет аудио для sendAudio'));
		}

		$media = $this->resolveMedia($audio, 'audio');

		if($media === null) {
			return $this->fail(__('Аудиофайл недоступен локально'));
		}

		return $bot->call(new Method\SendAudio(
			chatId: $chat,
			audio: $media,
			caption: $text,
			parseMode: 'HTML',
			thumbnail: $this->resolveThumb($message),
			replyMarkup: $markup,
		));
	}

	/**
	 * Одиночный video — только первый файл.
	 */
	private function sendVideoOne(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|SendResult {
		$video = $message->videos[0] ?? '';

		if($video === '') {
			return $this->fail(__('Нет видео для sendVideo'));
		}

		// SendVideo стабильно принимает MPEG-4; mkv/avi/… — как документ.
		if(!$this->isTelegramNativeVideo($video)) {
			$media = $this->resolveMedia($video, 'document');

			if($media === null) {
				return $this->fail(__('Видеофайл недоступен локально'));
			}

			return $bot->call(new Method\SendDocument(
				chatId: $chat,
				document: $media,
				caption: $text,
				parseMode: 'HTML',
				thumbnail: $this->resolveThumb($message),
				replyMarkup: $markup,
			));
		}

		$media = $this->resolveMedia($video, 'video');

		if($media === null) {
			return $this->fail(__('Видеофайл недоступен локально'));
		}

		return $bot->call(new Method\SendVideo(
			chatId: $chat,
			video: $media,
			caption: $text,
			parseMode: 'HTML',
			thumbnail: $this->resolveThumb($message),
			replyMarkup: $markup,
		));
	}

	private function sendDocument(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|SendResult {
		$doc = $message->images[0] ?? $message->videos[0] ?? $message->audios[0] ?? '';

		if($doc === '') {
			return $this->fail(__('Нет файла для sendDocument'));
		}

		$media = $this->resolveMedia($doc, 'document');

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
	 * Album photo/video; несколько audio — одним SendMediaGroup (type=media).
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

			return $this->sendAudioGroup($bot, $chat, $message, $text, $markup);
		}

		if(count($items) === 1) {
			$one = $items[0];

			if($one instanceof Type\InputMediaPhoto) {
				$raw[] = $this->toRaw($bot->call(new Method\SendPhoto(
					chatId: $chat,
					photo: $one->media,
					caption: $one->caption ?? $text,
					parseMode: 'HTML',
					replyMarkup: $markup,
				)));
			} elseif($one instanceof Type\InputMediaDocument) {
				$raw[] = $this->toRaw($bot->call(new Method\SendDocument(
					chatId: $chat,
					document: $one->media,
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

		if($message->audios !== []) {
			$audioRes = $this->sendAudioGroup($bot, $chat, $message, '', null);

			if($audioRes instanceof SendResult) {
				if(!$audioRes->ok) {
					$raw['audios_error'] = $audioRes->raw;
				} else {
					$raw['audios'] = $audioRes->raw;
				}
			} else {
				$raw['audios'] = $this->toRaw($audioRes);
			}
		}

		return $this->ok(__('Отправлено'), $raw);
	}

	/**
	 * Группа аудио (media_audio).
	 *
	 * @return Type\Message|list|SendResult
	 */
	private function sendAudioGroup(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|array|SendResult {
		if($message->audios === []) {
			return $this->fail(__('Нет аудио для media_audio'));
		}

		if(count($message->audios) === 1) {
			return $this->sendAudioOne($bot, $chat, $message, $text, $markup);
		}

		$items = [];
		$n     = 0;

		foreach($message->audios as $src) {
			if(count($items) >= MediaLimits::MEDIA_GROUP_MAX) {
				break;
			}

			$media = $this->resolveMedia($src, 'audio');

			if($media === null) {
				continue;
			}

			$cap     = ($n === 0 && $text !== '') ? $text : null;
			$items[] = new Type\InputMediaAudio(
				media: $media,
				caption: $cap,
				parseMode: $cap !== null ? 'HTML' : null,
			);
			$n++;
		}

		if($items === []) {
			return $this->fail(__('Аудиофайлы недоступны локально'));
		}

		if(count($items) === 1) {
			return $bot->call(new Method\SendAudio(
				chatId: $chat,
				audio: $items[0]->media,
				caption: $text,
				parseMode: 'HTML',
				replyMarkup: $markup,
			));
		}

		return $bot->call(new Method\SendMediaGroup(
			chatId: $chat,
			media: $items,
		));
	}

	/**
	 * Группа документов (media_document).
	 *
	 * @return Type\Message|list|SendResult
	 */
	private function sendDocumentGroup(
		BotApi $bot,
		string $chat,
		RenderedMessage $message,
		string $text,
		?Type\InlineKeyboardMarkup $markup,
	): Type\Message|array|SendResult {
		$sources = array_merge($message->images, $message->videos, $message->audios);

		if($sources === []) {
			return $this->fail(__('Нет файлов для media_document'));
		}

		if(count($sources) === 1) {
			return $this->sendDocument($bot, $chat, $message, $text, $markup);
		}

		$items = [];
		$n     = 0;

		foreach($sources as $src) {
			if(count($items) >= MediaLimits::MEDIA_GROUP_MAX) {
				break;
			}

			$media = $this->resolveMedia($src, 'document');

			if($media === null) {
				continue;
			}

			$cap     = ($n === 0 && $text !== '') ? $text : null;
			$items[] = new Type\InputMediaDocument(
				media: $media,
				caption: $cap,
				parseMode: $cap !== null ? 'HTML' : null,
			);
			$n++;
		}

		if($items === []) {
			return $this->fail(__('Файлы недоступны локально'));
		}

		if(count($items) === 1) {
			return $bot->call(new Method\SendDocument(
				chatId: $chat,
				document: $items[0]->media,
				caption: $text,
				parseMode: 'HTML',
				replyMarkup: $markup,
			));
		}

		return $bot->call(new Method\SendMediaGroup(
			chatId: $chat,
			media: $items,
		));
	}

	/**
	 * photo / native video / прочее видео как document для SendMediaGroup.
	 *
	 * @return list<Type\InputMediaPhoto|Type\InputMediaVideo|Type\InputMediaDocument>
	 */
	private function buildMediaGroup(RenderedMessage $message, string $caption): array {
		$items = [];
		$n     = 0;

		$push = function (string $kind, string $src) use (&$items, &$n, $caption): void {
			if(count($items) >= MediaLimits::MEDIA_GROUP_MAX) {
				return;
			}

			$asDocument = $kind === 'video' && !$this->isTelegramNativeVideo($src);
			$mediaKind  = $asDocument ? 'document' : $kind;
			$media      = $this->resolveMedia($src, $mediaKind);

			if($media === null) {
				return;
			}

			$cap  = ($n === 0 && $caption !== '') ? $caption : null;
			$mode = $cap !== null ? 'HTML' : null;

			$items[] = match(true) {
				$asDocument     => new Type\InputMediaDocument(media: $media, caption: $cap, parseMode: $mode),
				$kind === 'video' => new Type\InputMediaVideo(media: $media, caption: $cap, parseMode: $mode),
				default         => new Type\InputMediaPhoto(media: $media, caption: $cap, parseMode: $mode),
			};
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

	/** MPEG-4 для SendVideo / InputMediaVideo; иначе — документ. */
	private function isTelegramNativeVideo(string $path): bool {
		$path = trim($path);

		if(str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
			$urlPath = parse_url($path, PHP_URL_PATH);
			$path    = is_string($urlPath) ? $urlPath : $path;
		}

		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		return $ext === 'mp4' || $ext === 'm4v';
	}

	/**
	 * Локальный файл / скачанный URL → InputFile; иначе null.
	 */
	private function resolveMedia(string $path, string $kind = 'document'): Type\InputFile|string|null {
		$local = $this->resolveLocalOrDownload($path);

		if($local === null) {
			return null;
		}

		$limit = MediaLimits::maxBytesFor($kind);
		$bytes = @filesize($local);

		if($limit > 0 && $bytes !== false && $bytes > $limit) {
			return null;
		}

		return new Type\InputFile($local);
	}

	private function resolveThumb(RenderedMessage $message): Type\InputFile|string|null {
		$thumb = trim((string) ($message->thumb ?? ''));

		if($thumb === '') {
			return null;
		}

		return $this->resolveMedia($thumb, 'photo');
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
