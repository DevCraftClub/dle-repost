<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

use DevCraft\Core\Support\DataManager;
use DevCraft\Core\Support\ParseTemplateTags;
use DevCraft\Modules\RePost\Provider\DefaultTemplateTags;
use DevCraft\Modules\RePost\Services\Dto\RenderedMessage;
use DevCraft\Modules\RePost\Provider\TemplateTagsInterface;

/**
 * Рендер шаблона: ParseTemplateTags + теги канала.
 */
final class ContentRenderer {

	/**
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 */
	public function render(
		string                 $template,
		array                  $newsRow,
		array                  $moduleConfig = [],
		string                 $sendType = 'text',
		?TemplateTagsInterface $tags = NULL,
	): RenderedMessage {
		if($moduleConfig === []) {
			$moduleConfig = DataManager::getConfig('repost');
		}

		$tags  ??= new DefaultTemplateTags();
		$extra = $tags->extraPlaceholders($newsRow, $moduleConfig);
		$html  = ParseTemplateTags::apply($template, $newsRow, $extra, ['mode' => 'full', 'globals' => true]);
		$html  = $tags->applyAfterParse($html, $newsRow, $moduleConfig);
		[$html, $buttons] = $this->extractButtons($html);
		[$html, $thumb] = $this->extractThumb($html);
		$media = $this->extractMediaTags($html, $newsRow, $moduleConfig);
		$html  = $media['text'];
		$html  = $tags->sanitizeHtml($html);

		$images = $media['images'];
		$videos = $media['videos'];
		$audios = $media['audios'];

		if(!$media['has_tags'] && $images === [] && $videos === [] && $audios === []) {
			$collected = $this->collectFromNews($newsRow, $moduleConfig);
			$images    = $collected['images'];
			$videos    = $collected['videos'];
			$audios    = $collected['audios'];
		}

		[$images, $videos, $audios] = $this->filterBySendType($sendType, $images, $videos, $audios);

		return new RenderedMessage(
			text    : trim($html),
			images  : $images,
			videos  : $videos,
			audios  : $audios,
			buttons : $buttons,
			sendType: $sendType,
			thumb   : $thumb,
		);
	}

	/**
	 * Оставляет только медиа, уместные для send_type подключения.
	 *
	 * @param   list<string>  $images
	 * @param   list<string>  $videos
	 * @param   list<string>  $audios
	 *
	 * @return array{0: list<string>, 1: list<string>, 2: list<string>}
	 */
	private function filterBySendType(string $sendType, array $images, array $videos, array $audios): array {
		return match ($sendType) {
			'audio', 'media_audio'    => [[], [], $audios],
			'video', 'media_video'    => [[], $videos, []],
			'photo'                   => [$images, [], []],
			'document'                => [$images, $videos, []],
			'media', 'media_document' => [$images, $videos, $audios],
			'text'                    => [[], [], []],
			default                   => [$images, $videos, $audios],
		};
	}

	/**
	 * @return array{0: string, 1: list<array{text: string, url: string}>}
	 */
	private function extractButtons(string $html): array {
		$buttons = [];
		$html    = (string) preg_replace_callback(
			'/\[button=([^\]]+)\](.*?)\[\/button\]/is',
			static function(array $m) use (&$buttons): string {
				$buttons[] = ['url' => trim($m[1]), 'text' => trim(strip_tags($m[2]))];

				return '';
			},
			$html,
		);

		return [$html, $buttons];
	}

	/**
	 * @return array{0: string, 1: ?string}
	 */
	private function extractThumb(string $html): array {
		$thumb = NULL;
		$html  = (string) preg_replace_callback(
			'/\[repost_thumb\](.*?)\[\/repost_thumb\]/is',
			static function(array $m) use (&$thumb): string {
				$candidate = trim(strip_tags($m[1]));

				if($candidate !== '' && $thumb === NULL) {
					$thumb = $candidate;
				}

				return '';
			},
			$html,
		);

		return [$html, $thumb];
	}

	/**
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 *
	 * @return array{text: string, images: list<string>, videos: list<string>, audios: list<string>, has_tags: bool}
	 */
	private function extractMediaTags(string $html, array $newsRow, array $moduleConfig): array {
		$images  = [];
		$videos  = [];
		$audios  = [];
		$hasTags = false;
		$maxAll  = 10;
		$pool    = NULL;

		$getPool = function() use (&$pool, $newsRow, $moduleConfig): array {
			return $pool ??= $this->collectFromNews($newsRow, $moduleConfig);
		};

		$slice = static function(array $list, ?int $index, ?int $max) use ($maxAll): array {
			if($index !== NULL) {
				$item = $list[$index] ?? NULL;

				return $item !== NULL? [$item] : [];
			}

			$limit = $max ?? $maxAll;

			return array_slice($list, 0, max(0, $limit));
		};

		$parseAttrs = static function(string $raw): array {
			$out = [];

			if(preg_match_all('/(url|image|video|audio|file|max)=([^\s\]]+)/i', $raw, $m, PREG_SET_ORDER)) {
				foreach($m as $row) {
					$out[strtolower($row[1])] = trim($row[2], " \t\"'");
				}
			}

			return $out;
		};

		$add = static function(string $kind, string $url) use (&$images, &$videos, &$audios): void {
			$url = trim($url);

			if($url === '') {
				return;
			}

			if($kind === 'video') {
				$videos[] = $url;
			} elseif($kind === 'audio') {
				$audios[] = $url;
			} else {
				$images[] = $url;
			}
		};

		$html = (string) preg_replace_callback(
			'/\[repost_media_(image|photo|video|audio|document|allimages|xfield_([a-z0-9_\-]+))(?:\s+([^\]]*))?\]/i',
			function(array $m) use (
				&$hasTags,
				&$images,
				&$videos,
				&$audios,
				$getPool,
				$slice,
				$parseAttrs,
				$add,
				$newsRow,
				$moduleConfig,
				$maxAll,
			): string {
				$hasTags = true;
				$type    = strtolower($m[1]);
				$xfName  = $m[2] !== ''? $m[2] : NULL;
				$attrs   = $parseAttrs($m[3] ?? '');
				$url     = $attrs['url'] ?? NULL;

				if($url !== NULL) {
					$kind = match ($type) {
						'video' => 'video',
						'audio' => 'audio',
						default => 'image',
					};
					$add($kind, $url);

					return '';
				}

				$indexKey = match ($type) {
					'video' => 'video',
					'audio' => 'audio',
					default => 'image',
				};
				$index    = isset($attrs[$indexKey])? max(0, (int) $attrs[$indexKey] - 1)
					: (isset($attrs['file'])? max(0, (int) $attrs['file'] - 1) : NULL);
				$max      = isset($attrs['max'])? max(0, (int) $attrs['max']) : NULL;

				if($xfName !== NULL) {
					$files = $this->collectXfieldAny($newsRow, $xfName, $moduleConfig);
					foreach($slice($files, $index, $max) as $file) {
						$add('image', $file);
					}

					return '';
				}

				$pool = $getPool();

				if($type === 'allimages' || $type === 'image' || $type === 'photo') {
					foreach($slice($pool['images'], $index, $max) as $file) {
						$add('image', $file);
					}
				} elseif($type === 'video') {
					foreach($slice($pool['videos'], $index, $max) as $file) {
						$add('video', $file);
					}
				} elseif($type === 'audio') {
					foreach($slice($pool['audios'], $index, $max) as $file) {
						$add('audio', $file);
					}
				} elseif($type === 'document') {
					$docs = array_merge($pool['images'], $pool['videos'], $pool['audios']);
					foreach($slice($docs, $index, $max ?? $maxAll) as $file) {
						$add('image', $file);
					}
				}

				return '';
			},
			$html,
		);

		return [
			'text'     => $html,
			'images'   => array_values(array_unique($images)),
			'videos'   => array_values(array_unique($videos)),
			'audios'   => array_values(array_unique($audios)),
			'has_tags' => $hasTags,
		];
	}

	/**
	 * Файлы одного xfield (image/gallery/audio/video).
	 *
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 *
	 * @return list<string>
	 */
	private function collectXfieldAny(array $newsRow, string $name, array $moduleConfig): array {
		$raw   = (string) ($newsRow['xfields'] ?? '');
		$types = $this->xfieldTypes();
		$type  = $types[$name] ?? '';

		if(in_array($type, ['image', 'imagegalery', 'gallery'], true)) {
			$out = [];

			foreach(explode('||', $raw) as $chunk) {
				$chunk = trim($chunk);
				$sep   = strpos($chunk, '|');

				if($sep === false || substr($chunk, 0, $sep) !== $name) {
					continue;
				}

				$value = substr($chunk, $sep + 1);

				if($type === 'image') {
					$path = $this->normalizeXfImagePath($value);

					if($path !== '') {
						$out[] = preg_match('#^(https?:)?//#i', $path) === 1
							? $path
							: 'uploads/posts/' . ltrim(str_replace('\\', '/', $path), '/');
					}
				} else {
					foreach(explode(',', $value) as $p) {
						$path = $this->normalizeXfImagePath($p);

						if($path === '') {
							continue;
						}

						$out[] = preg_match('#^(https?:)?//#i', $path) === 1
							? $path
							: 'uploads/posts/' . ltrim(str_replace('\\', '/', $path), '/');
					}
				}
			}

			return $out;
		}

		if($type === 'video' || $type === 'audio') {
			return $this->collectXfieldFileList($raw, $type);
		}

		foreach(explode('||', $raw) as $chunk) {
			$chunk = trim($chunk);
			$sep   = strpos($chunk, '|');

			if($sep === false || substr($chunk, 0, $sep) !== $name) {
				continue;
			}

			$value = trim(substr($chunk, $sep + 1));

			return $value !== ''? [$value] : [];
		}

		return [];
	}

	/**
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 *
	 * @return array{images: list<string>, videos: list<string>, audios: list<string>}
	 */
	private function collectFromNews(array $newsRow, array $moduleConfig): array {
		$blob    = (string) ($newsRow['full_story'] ?? '') . (string) ($newsRow['short_story'] ?? '') . (string) ($newsRow['xfields'] ?? '');
		$allowed = array_map('trim', explode(',', (string) ($moduleConfig['img_types'] ?? 'jpg,jpeg,png,gif,webp')));
		$images  = [];
		$videos  = [];
		$audios  = [];

		if(preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $blob, $m)) {
			foreach($m[1] as $url) {
				$ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH)? : $url, PATHINFO_EXTENSION));

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
			$part     = str_replace('&#124;', '|', $vm[1]);
			$part     = explode(',', trim($part));
			$part     = explode('|', $part[0]);
			$videos[] = trim($part[0]);
		}

		if(preg_match('#<!--dle_audio_begin:(.+?)-->#is', $blob, $am)) {
			$part     = str_replace('&#124;', '|', $am[1]);
			$part     = explode(',', trim($part));
			$part     = explode('|', $part[0]);
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
					explode(',', $value),
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
		static $cache = NULL;

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
		$fields  = is_array($decoded)? ($decoded['fields'] ?? []) : [];

		if(!is_array($fields)) {
			return $cache;
		}

		foreach($fields as $name => $meta) {
			$key = is_string($name)? $name : (string) (is_array($meta)? ($meta['name'] ?? '') : '');

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

			return $candidate !== ''? $candidate : trim($parts[0]);
		}

		return trim($parts[0]);
	}

}
