<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

use DevCraft\Core\Support\DataManager;
use DevCraft\Core\Support\ParseTemplateTags;
use DevCraft\Modules\RePost\Services\Dto\RenderedMessage;

/**
 * Рендер шаблона: ParseTemplateTags + RePost-специфика.
 */
final class ContentRenderer {

	/**
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 */
	public function render(string $template, array $newsRow, array $moduleConfig = [], string $sendType = 'text'): RenderedMessage {
		if($moduleConfig === []) {
			$moduleConfig = DataManager::getConfig('repost');
		}

		$extra = $this->buildExtra($newsRow, $moduleConfig);
		$html  = ParseTemplateTags::apply($template, $newsRow, $extra, ['mode' => 'full', 'globals' => true]);
		$html  = $this->processIfBlocks($html, $newsRow);
		[$html, $buttons] = $this->extractButtons($html);
		$media            = $this->extractMediaTags($html);
		$html             = $media['text'];
		$html             = $this->decodeForTelegram($html);

		$images = $media['images'];
		$videos = $media['videos'];
		$audios = $media['audios'];

		if($images === [] && $videos === [] && $audios === []) {
			$collected = $this->collectFromNews($newsRow, $moduleConfig);
			$images    = $collected['images'];
			$videos    = $collected['videos'];
			$audios    = $collected['audios'];
		}

		[$images, $videos, $audios] = $this->filterBySendType($sendType, $images, $videos, $audios);

		return new RenderedMessage(
			text: trim($html),
			images: $images,
			videos: $videos,
			audios: $audios,
			buttons: $buttons,
			sendType: $sendType,
		);
	}

	/**
	 * Оставляет только медиа, уместные для tg_send_type.
	 *
	 * @param   list<string>  $images
	 * @param   list<string>  $videos
	 * @param   list<string>  $audios
	 *
	 * @return array{0: list<string>, 1: list<string>, 2: list<string>}
	 */
	private function filterBySendType(string $sendType, array $images, array $videos, array $audios): array {
		return match($sendType) {
			'audio'             => [[], [], $audios],
			'video'             => [[], $videos, []],
			'photo', 'document' => [$images, [], []],
			'media'             => [$images, $videos, $audios],
			'text'              => [[], [], []],
			default             => [$images, $videos, $audios],
		};
	}

	/**
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 *
	 * @return array<string, string>
	 */
	private function buildExtra(array $newsRow, array $moduleConfig): array {
		$sepHash = (string) ($moduleConfig['hashtag_separator'] ?? ' ');
		$sepTag  = (string) ($moduleConfig['tag_separator'] ?? ', ');
		$sepCat  = (string) ($moduleConfig['category_separator'] ?? ', ');

		$tags = array_filter(array_map('trim', explode(',', (string) ($newsRow['tags'] ?? ''))));
		$hash = [];

		foreach($tags as $tag) {
			$urlTag = preg_replace('/\s+/u', '_', $tag) ?? $tag;
			$hash[] = '#' . $urlTag;
		}

		$catHash = [];
		$catIds  = array_filter(array_map('intval', explode(',', (string) ($newsRow['category'] ?? ''))));

		global $cat_info;

		if(is_array($cat_info ?? null)) {
			foreach($catIds as $cid) {
				$name = (string) ($cat_info[$cid]['name'] ?? '');

				if($name !== '') {
					$catHash[] = '#' . str_replace(' ', '_', $name);
				}
			}
		}

		return [
			'{hashtags}'         => implode($sepHash, $hash),
			'{category-hashtag}' => implode($sepTag, $catHash),
			'{tags}'             => implode($sepTag, $tags),
		];
	}

	/**
	 * @param   array<string, mixed>  $newsRow
	 */
	private function processIfBlocks(string $html, array $newsRow): string {
		return (string) preg_replace_callback(
			'/\[if\s+([^\]]+)\](.*?)\[\/if\]/is',
			static function (array $m) use ($newsRow): string {
				$expr = trim($m[1]);
				$body = $m[2];

				if(preg_match('/^([a-z0-9_\-]+)\s*(=|!=)\s*[\'"]?(.*?)[\'"]?$/i', $expr, $parts)) {
					$field = $parts[1];
					$op    = $parts[2];
					$want  = $parts[3];
					$have  = (string) ($newsRow[$field] ?? '');

					$ok = $op === '=' ? ($have === $want) : ($have !== $want);

					return $ok ? $body : '';
				}

				$field = $expr;
				$have  = trim((string) ($newsRow[$field] ?? ''));

				return $have !== '' ? $body : '';
			},
			$html
		);
	}

	/**
	 * @return array{0: string, 1: list<array{text: string, url: string}>}
	 */
	private function extractButtons(string $html): array {
		$buttons = [];
		$html    = (string) preg_replace_callback(
			'/\[button=([^\]]+)\](.*?)\[\/button\]/is',
			static function (array $m) use (&$buttons): string {
				$buttons[] = ['url' => trim($m[1]), 'text' => trim(strip_tags($m[2]))];

				return '';
			},
			$html
		);

		return [$html, $buttons];
	}

	/**
	 * @return array{text: string, images: list<string>, videos: list<string>, audios: list<string>}
	 */
	private function extractMediaTags(string $html): array {
		$images = [];
		$videos = [];
		$audios = [];

		$html = (string) preg_replace_callback(
			'/\[repost_media_(image|photo|video|audio)=([^\]]+)\]/i',
			static function (array $m) use (&$images, &$videos, &$audios): string {
				$type = strtolower($m[1]);
				$url  = trim($m[2]);

				if($type === 'video') {
					$videos[] = $url;
				} elseif($type === 'audio') {
					$audios[] = $url;
				} else {
					$images[] = $url;
				}

				return '';
			},
			$html
		);

		return [
			'text'   => $html,
			'images' => $images,
			'videos' => $videos,
			'audios' => $audios,
		];
	}

	/**
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 *
	 * @return array{images: list<string>, videos: list<string>, audios: list<string>}
	 */
	private function collectFromNews(array $newsRow, array $moduleConfig): array {
		$blob     = (string) ($newsRow['full_story'] ?? '') . (string) ($newsRow['short_story'] ?? '') . (string) ($newsRow['xfields'] ?? '');
		$allowed  = array_map('trim', explode(',', (string) ($moduleConfig['img_types'] ?? 'jpg,jpeg,png,gif,webp')));
		$images   = [];
		$videos   = [];
		$audios   = [];

		if(preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $blob, $m)) {
			foreach($m[1] as $url) {
				$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION));

				if(in_array($ext, $allowed, true)) {
					$images[] = $url;
				}
			}
		}

		$xf = (string) ($newsRow['xfields'] ?? '');

		foreach($this->collectXfieldImages($xf, $allowed) as $url) {
			$images[] = $url;
		}

		foreach($this->collectXfieldFileList($xf, 'video') as $url) {
			$videos[] = $url;
		}

		foreach($this->collectXfieldFileList($xf, 'audio') as $url) {
			$audios[] = $url;
		}

		if(preg_match('#<!--dle_video_begin:(.+?)-->#is', $blob, $vm)) {
			$part = str_replace('&#124;', '|', $vm[1]);
			$part = explode(',', trim($part));
			$part = explode('|', $part[0]);
			$videos[] = trim($part[0]);
		}

		if(preg_match('#<!--dle_audio_begin:(.+?)-->#is', $blob, $am)) {
			$part = str_replace('&#124;', '|', $am[1]);
			$part = explode(',', trim($part));
			$part = explode('|', $part[0]);
			$audios[] = trim($part[0]);
		}

		return [
			'images' => array_values(array_unique($images)),
			'videos' => array_values(array_unique(array_filter($videos))),
			'audios' => array_values(array_unique(array_filter($audios))),
		];
	}

	/**
	 * Список файлов из xfield type audio/video: `url|id|size,url2|…`.
	 *
	 * @return list<string>
	 */
	private function collectXfieldFileList(string $raw, string $wantType): array {
		if($raw === '') {
			return [];
		}

		$types = $this->xfieldTypes();
		$out   = [];

		foreach(explode('||', $raw) as $chunk) {
			$chunk = trim($chunk);

			if($chunk === '') {
				continue;
			}

			$sep = strpos($chunk, '|');

			if($sep === false) {
				continue;
			}

			$name  = substr($chunk, 0, $sep);
			$value = substr($chunk, $sep + 1);
			$type  = $types[$name] ?? '';

			if($type !== $wantType) {
				continue;
			}

			$value = html_entity_decode(str_replace('&#124;', '|', $value), ENT_QUOTES, 'UTF-8');

			foreach(explode(',', $value) as $entry) {
				$entry = trim($entry);

				if($entry === '') {
					continue;
				}

				$url = trim(explode('|', $entry, 2)[0]);

				if($url !== '') {
					$out[] = $url;
				}
			}
		}

		return $out;
	}

	/**
	 * Картинки из доп. полей image / imagegalery (формат DLE `name|paths||…`).
	 *
	 * @param   list<string>  $allowedExt
	 *
	 * @return list<string>
	 */
	private function collectXfieldImages(string $raw, array $allowedExt): array {
		if($raw === '') {
			return [];
		}

		$types = $this->xfieldTypes();
		$out   = [];

		foreach(explode('||', $raw) as $chunk) {
			$chunk = trim($chunk);

			if($chunk === '') {
				continue;
			}

			$sep = strpos($chunk, '|');

			if($sep === false) {
				continue;
			}

			$name  = substr($chunk, 0, $sep);
			$value = substr($chunk, $sep + 1);
			$type  = $types[$name] ?? '';

			if(!in_array($type, ['image', 'imagegalery', 'gallery'], true)) {
				continue;
			}

			$paths = $type === 'image'
				? [$this->normalizeXfImagePath($value)]
				: array_map(
					fn(string $p): string => $this->normalizeXfImagePath($p),
					explode(',', $value)
				);

			foreach($paths as $path) {
				if($path === '') {
					continue;
				}

				if(preg_match('#^(https?:)?//#i', $path) === 1) {
					$out[] = $path;
					continue;
				}

				$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

				if($ext !== '' && !in_array($ext, $allowedExt, true)) {
					continue;
				}

				$out[] = 'uploads/posts/' . ltrim(str_replace('\\', '/', $path), '/');
			}
		}

		return $out;
	}

	/**
	 * @return array<string, string> name => type
	 */
	private function xfieldTypes(): array {
		static $cache = null;

		if(is_array($cache)) {
			return $cache;
		}

		$cache = [];

		if(!defined('ENGINE_DIR')) {
			return $cache;
		}

		$path = ENGINE_DIR . '/data/xfields.json';

		if(!is_readable($path)) {
			return $cache;
		}

		$decoded = json_decode((string) file_get_contents($path), true);
		$fields  = is_array($decoded) ? ($decoded['fields'] ?? []) : [];

		if(!is_array($fields)) {
			return $cache;
		}

		foreach($fields as $name => $meta) {
			$key = is_string($name) ? $name : (string) (is_array($meta) ? ($meta['name'] ?? '') : '');

			if($key === '' || !is_array($meta)) {
				continue;
			}

			$cache[$key] = (string) ($meta['type'] ?? '');
		}

		return $cache;
	}

	private function normalizeXfImagePath(string $value): string {
		$value = trim(str_replace(['&#124;', '&#58;'], ['|', ':'], $value));

		if($value === '') {
			return '';
		}

		$parts = explode('|', $value);

		if(count($parts) > 1) {
			$candidate = trim($parts[1]);

			return $candidate !== '' ? $candidate : trim($parts[0]);
		}

		return trim($parts[0]);
	}

	private function decodeForTelegram(string $text): string {
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

		// Telegram HTML не поддерживает <br>/<p>/<div> — только перевод строки
		$text = (string) preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\/\s*p\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*p[^>]*>/i', "\n", $text);
		$text = (string) preg_replace('/<\/\s*div\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*div[^>]*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*hr\s*\/?\s*>/i', "\n", $text);

		// Только теги, которые принимает parse_mode=HTML
		$text = strip_tags($text, '<b><strong><i><em><u><ins><s><strike><del><a><code><pre><tg-spoiler><blockquote>');
		$text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

		return trim($text);
	}

}
