<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider\Telegram;

use DevCraft\Modules\RePost\Provider\DefaultTemplateTags;

/**
 * Теги и HTML Bot API для канала Telegram.
 */
final class TelegramTemplateTags extends DefaultTemplateTags {

	public function hints(): array {
		$base = parent::hints();

		$extra = [
			[
				'code'  => '[button=https://example.com]Текст[/button]',
				'tag'   => 'button',
				'descr' => __('Кнопка под сообщением (url + подпись)'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_image image=X max=Z]',
				'tag'   => 'repost_media_image',
				'descr' => __('Изображение из новости (индекс / лимит)'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_image url=https://example.com/pic.jpg]',
				'tag'   => 'repost_media_image_url',
				'descr' => __('Прямая ссылка или локальный путь на изображение'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_video video=X max=Z]',
				'tag'   => 'repost_media_video',
				'descr' => __('Видео из новости (индекс / лимит)'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_video url=https://example.com/v.mp4]',
				'tag'   => 'repost_media_video_url',
				'descr' => __('Прямая ссылка или путь на видео'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_audio audio=X max=Z]',
				'tag'   => 'repost_media_audio',
				'descr' => __('Аудио из новости (индекс / лимит)'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_audio url=https://example.com/a.mp3]',
				'tag'   => 'repost_media_audio_url',
				'descr' => __('Прямая ссылка или путь на аудио'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_document url=https://example.com/file.pdf]',
				'tag'   => 'repost_media_document',
				'descr' => __('Документ по прямой ссылке / пути'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_allimages image=X max=Z]',
				'tag'   => 'repost_media_allimages',
				'descr' => __('Все изображения новости и доп. полей'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_media_xfield_XXX file=Y max=Z]',
				'tag'   => 'repost_media_xfield',
				'descr' => __('Медиа из доп. поля XXX'),
				'group' => 'telegram',
			],
			[
				'code'  => '[repost_thumb]uploads/posts/…[/repost_thumb]',
				'tag'   => 'repost_thumb',
				'descr' => __('Миниатюра для audio/video'),
				'group' => 'telegram',
			],
		];

		return array_merge($base, $extra);
	}

	/**
	 * @return list<string>
	 */
	public function allowedHtmlTags(): array {
		return [
			'b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del',
			'a', 'code', 'pre', 'tg-spoiler', 'blockquote',
		];
	}

	public function sanitizeHtml(string $text): string {
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

		$map = [
			'[b]'  => '<b>', '[/b]' => '</b>',
			'[u]'  => '<u>', '[/u]' => '</u>',
			'[i]'  => '<i>', '[/i]' => '</i>',
			'[s]'  => '<s>', '[/s]' => '</s>',
			'[code]' => '<code>', '[/code]' => '</code>',
			'{comments}' => '', '{addcomments}' => '', '{navigation}' => '',
			'{pages}' => '', '{PAGEBREAK}' => '', '{favorites}' => '', '{poll}' => '',
		];

		$text = (string) preg_replace_callback(
			'/\[url=(.*?)\](.*?)\[\/url\]/is',
			static fn(array $m): string => '<a href="' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '">'
				. htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</a>',
			$text
		);

		$text = (string) preg_replace(
			'/\[(edit|add-favorites|del-favorites|complaint|comments-subscribe|comments-unsubscribe|day-news|allow-comments-subscribe)\].*?\[\/\1\]/is',
			'',
			$text
		);

		$text = str_replace(array_keys($map), array_values($map), $text);

		$text = (string) preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\/\s*p\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*p[^>]*>/i', "\n", $text);
		$text = (string) preg_replace('/<\/\s*div\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*div[^>]*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*hr\s*\/?\s*>/i', "\n", $text);

		$text = strip_tags($text, $this->allowedHtmlAllowlist());
		$text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

		return trim($text);
	}

}
