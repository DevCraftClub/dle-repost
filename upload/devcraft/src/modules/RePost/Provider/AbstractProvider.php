<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

use DevCraft\Modules\RePost\Services\Dto\SendResult;
use DevCraft\Modules\RePost\Services\Dto\RenderedMessage;

/**
 * База канала доставки: SendResult + лимиты размера + расширения + download URL.
 *
 * Перед аплоадом файлов в send() вызывайте filterMediaByLimits().
 */
abstract class AbstractProvider implements ProviderInterface {

	/** @var list<string> */
	private array $tempMediaFiles = [];

	/**
	 * Лимиты размера по типу медиа (байты). Нет ключа или 0 = без лимита для типа.
	 *
	 * @return array{photo?: int, video?: int, audio?: int, document?: int}
	 */
	abstract protected function mediaByteLimits(): array;

	/**
	 * Допустимые расширения по типу. Пустой список для типа = не фильтровать.
	 *
	 * @return array{photo: list<string>, video: list<string>, audio: list<string>, document: list<string>}
	 */
	public function allowedMediaExtensions(): array {
		return [
			'photo'    => [],
			'video'    => [],
			'audio'    => [],
			'document' => [],
		];
	}

	/**
	 * Теги шаблона и HTML-allowlist канала.
	 */
	public function templateTags(): TemplateTagsInterface {
		return new DefaultTemplateTags();
	}

	/**
	 * Тип отправки из config подключения: `{code}_send_type`, иначе legacy `tg_send_type`.
	 *
	 * @param   array<string, mixed>  $connectionConfig
	 */
	public function sendTypeFromConfig(array $connectionConfig): string {
		$key  = static::code() . '_send_type';
		$type = (string) ($connectionConfig[$key] ?? $connectionConfig['tg_send_type'] ?? 'text');

		return $type !== ''? $type : 'text';
	}

	/**
	 * @param   array<string, mixed>  $raw
	 */
	protected function ok(string $message = '', array $raw = []): SendResult {
		return SendResult::success($message !== ''? $message : __('Отправлено'), $raw);
	}

	/**
	 * @param   array<string, mixed>  $raw
	 */
	protected function fail(string $message, array $raw = []): SendResult {
		return SendResult::fail($message, $raw);
	}

	/**
	 * Классификация по расширению: photo|video|audio|document.
	 *
	 * @param   'photo'|'video'|'audio'|'document'|string  $preferred  Подсказка из тега
	 */
	protected function classifyMediaByExtension(string $path, string $preferred = 'document'): string {
		$ext = $this->pathExtension($path);
		$map = $this->allowedMediaExtensions();

		foreach(['photo', 'video', 'audio'] as $kind) {
			$allowed = array_map('strtolower', $map[$kind] ?? []);

			if($allowed !== [] && $ext !== '' && in_array($ext, $allowed, true)) {
				return $kind;
			}
		}

		$doc = array_map('strtolower', $map['document'] ?? []);

		if($doc !== [] && $ext !== '' && !in_array($ext, $doc, true)) {
			// не в document allowlist — всё равно document (отправим как файл)
			return 'document';
		}

		if($preferred === 'photo' || $preferred === 'video' || $preferred === 'audio') {
			$allowed = array_map('strtolower', $map[$preferred] ?? []);

			if($allowed === [] || ($ext !== '' && in_array($ext, $allowed, true))) {
				return $preferred;
			}

			return 'document';
		}

		return 'document';
	}

	/**
	 * Оставляет локальные файлы в пределах лимита; URL без локального файла — без проверки размера.
	 * Перекладывает файлы по allowlist расширений.
	 *
	 * @return array{
	 *     message: RenderedMessage,
	 *     skipped: list<array{path: string, kind: string, bytes: int, limit: int}>
	 * }
	 */
	protected function filterMediaByLimits(RenderedMessage $message): array {
		$message = $this->reclassifyMedia($message);
		$skipped = [];
		$images  = $this->filterPathList($message->images, 'photo', $skipped);
		$videos  = $this->filterPathList($message->videos, 'video', $skipped);
		$audios  = $this->filterPathList($message->audios, 'audio', $skipped);

		return [
			'message' => new RenderedMessage(
				text    : $message->text,
				images  : $images,
				videos  : $videos,
				audios  : $audios,
				buttons : $message->buttons,
				sendType: $message->sendType,
				thumb   : $message->thumb,
			),
			'skipped' => $skipped,
		];
	}

	/**
	 * Путь на диске для URL / относительного uploads; иначе null.
	 */
	protected function localMediaPath(string $path): ?string {
		global $config;

		$path = trim(str_replace('\\', '/', $path));

		if($path === '' || !defined('ROOT_DIR')) {
			return NULL;
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

		return NULL;
	}

	/**
	 * Скачивает публичный URL во temp; private/localhost → null.
	 */
	protected function downloadRemoteMedia(string $url): ?string {
		$url = trim($url);

		if($url === '' || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
			return NULL;
		}

		if($this->isPrivateHostUrl($url) || !defined('ROOT_DIR')) {
			return NULL;
		}

		$dir = ROOT_DIR . '/uploads/_repost_tmp';

		if(!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			return NULL;
		}

		$ext  = $this->pathExtension($url);
		$file = $dir . '/rp_' . bin2hex(random_bytes(8)) . ($ext !== ''? '.' . $ext : '');
		$ctx  = stream_context_create([
			'http' => ['timeout' => 60, 'follow_location' => 1, 'max_redirects' => 3],
			'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
		]);
		$data = @file_get_contents($url, false, $ctx);

		if($data === false || $data === '') {
			return NULL;
		}

		if(@file_put_contents($file, $data) === false) {
			return NULL;
		}

		$this->tempMediaFiles[] = $file;

		return $file;
	}

	/**
	 * Локальный путь или скачанный temp; иначе null.
	 */
	protected function resolveLocalOrDownload(string $path): ?string {
		$local = $this->localMediaPath($path);

		if($local !== NULL) {
			return $local;
		}

		if(str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
			return $this->downloadRemoteMedia($path);
		}

		return NULL;
	}

	protected function cleanupTempMedia(): void {
		foreach($this->tempMediaFiles as $file) {
			if(is_file($file)) {
				@unlink($file);
			}
		}

		$this->tempMediaFiles = [];
	}

	protected function isPrivateHostUrl(string $url): bool {
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
			return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE) === false;
		}

		return false;
	}

	/**
	 * @param   list<string>                                                     $paths
	 * @param   list<array{path: string, kind: string, bytes: int, limit: int}>  $skipped
	 *
	 * @return list<string>
	 */
	private function filterPathList(array $paths, string $kind, array &$skipped): array {
		$limits = $this->mediaByteLimits();
		$out    = [];

		foreach($paths as $path) {
			$path = trim((string) $path);

			if($path === '') {
				continue;
			}

			// Лимит по фактическому виду файла (mkv в images не режем как photo 10 МБ).
			$fileKind = $this->classifyMediaByExtension($path, $kind);
			$limit    = (int) ($limits[$fileKind] ?? $limits[$kind] ?? 0);
			$local    = $this->localMediaPath($path);

			if($local === NULL || $limit <= 0) {
				$out[] = $path;
				continue;
			}

			$bytes = @filesize($local);

			if($bytes === false) {
				$out[] = $path;
				continue;
			}

			if($bytes > $limit) {
				$skipped[] = [
					'path'  => $path,
					'kind'  => $fileKind,
					'bytes' => $bytes,
					'limit' => $limit,
				];
				continue;
			}

			$out[] = $path;
		}

		return $out;
	}

	private function reclassifyMedia(RenderedMessage $message): RenderedMessage {
		$images = [];
		$videos = [];
		$audios = [];

		$push = function(string $path, string $preferred) use (&$images, &$videos, &$audios): void {
			$path = trim($path);

			if($path === '') {
				return;
			}

			$kind = $this->classifyMediaByExtension($path, $preferred);

			match ($kind) {
				'video' => $videos[] = $path,
				'audio' => $audios[] = $path,
				'photo' => $images[] = $path,
				// document: оставляем в корзине preferred (video-шаблон не теряет mkv в photo-лимите)
				default => match ($preferred) {
					'video' => $videos[] = $path,
					'audio' => $audios[] = $path,
					default => $images[] = $path,
				},
			};
		};

		foreach($message->images as $p) {
			$push($p, 'photo');
		}

		foreach($message->videos as $p) {
			$push($p, 'video');
		}

		foreach($message->audios as $p) {
			$push($p, 'audio');
		}

		return new RenderedMessage(
			text    : $message->text,
			images  : array_values(array_unique($images)),
			videos  : array_values(array_unique($videos)),
			audios  : array_values(array_unique($audios)),
			buttons : $message->buttons,
			sendType: $message->sendType,
			thumb   : $message->thumb,
		);
	}

	private function pathExtension(string $path): string {
		$path = trim($path);

		if(str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
			$urlPath = parse_url($path, PHP_URL_PATH);
			$path    = is_string($urlPath)? $urlPath : $path;
		}

		return strtolower(pathinfo($path, PATHINFO_EXTENSION));
	}

}
