<?php

declare(strict_types=1);

/**
 * Минимальные проверки RePost.
 * Запуск: php devcraft/src/modules/RePost/selfcheck.php
 */

if(!function_exists('__')) {
	function __(string $text, array $replace = []): string {
		return strtr($text, $replace);
	}
}

define('ENGINE_DIR', dirname(__DIR__, 4) . '/engine');
define('ROOT_DIR', dirname(__DIR__, 4));

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use DevCraft\Modules\RePost\Pages\TemplateTagsPage;
use DevCraft\Modules\RePost\Provider\AbstractProvider;
use DevCraft\Modules\RePost\Provider\DefaultTemplateTags;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;
use DevCraft\Modules\RePost\Provider\Telegram\TelegramTemplateTags;
use DevCraft\Modules\RePost\Provider\Telegram\MediaLimits;
use DevCraft\Modules\RePost\Services\ContentRenderer;
use DevCraft\Modules\RePost\Services\CopyHelper;
use DevCraft\Modules\RePost\Services\DispatchService;
use DevCraft\Modules\RePost\Services\Dto\PostContext;
use DevCraft\Modules\RePost\Services\Dto\RenderedMessage;
use DevCraft\Modules\RePost\Services\Dto\SendResult;
use DevCraft\Types\FormSchema;

$taken = ['Demo (копия)' => true];
$name  = CopyHelper::uniqueName('Demo', static fn(string $n): bool => isset($taken[$n]));
assert($name === 'Demo (копия 2)', 'second copy gets (копия 2)');

$taken2 = [];
$name2  = CopyHelper::uniqueName('Audio', static fn(string $n): bool => isset($taken2[$n]));
assert($name2 === 'Audio (копия)', 'first copy gets (копия)');

$xfAudio = 'yes_or_no|0||audio|http://example.test/a.mp3|2|5 Mb,http://example.test/b.mp3|3|9 Mb';
$xfVideo = 'image|2026-07/pic.png|0|0|1x1|1 Kb||video|http://example.test/v.mkv|6|100 Mb,http://example.test/w.mkv|7|50 Mb';

$renderer = new ContentRenderer();
$ref      = new ReflectionClass($renderer);
$collect  = $ref->getMethod('collectXfieldFileList');
$collect->setAccessible(true);
$filter   = $ref->getMethod('filterBySendType');
$filter->setAccessible(true);

$audios = $collect->invoke($renderer, $xfAudio, 'audio');
assert(count($audios) === 2, 'parse 2 audio urls');

$videos = $collect->invoke($renderer, $xfVideo, 'video');
assert(count($videos) === 2, 'parse 2 video urls');

[$i, $v, $a] = $filter->invoke($renderer, 'audio', ['img.jpg'], $videos, $audios);
assert($i === [] && $v === [] && count($a) === 2, 'filter audio');

[$i, $v, $a] = $filter->invoke($renderer, 'media_audio', ['img.jpg'], $videos, $audios);
assert($i === [] && $v === [] && count($a) === 2, 'filter media_audio');

[$i, $v, $a] = $filter->invoke($renderer, 'media_video', ['img.jpg'], $videos, $audios);
assert($i === [] && count($v) === 2 && $a === [], 'filter media_video');

[$i, $v, $a] = $filter->invoke($renderer, 'media_document', ['img.jpg'], $videos, $audios);
assert(count($i) === 1 && count($v) === 2 && count($a) === 2, 'filter media_document');

$defaultTags = new DefaultTemplateTags();
assert($defaultTags->hints() !== [], 'DefaultTemplateTags hints non-empty');

$tgTags = new TelegramTemplateTags();
$tgHintTags = array_column($tgTags->hints(), 'tag');
assert(in_array('button', $tgHintTags, true), 'Telegram hints contain button');
assert(
	in_array('repost_media_image', $tgHintTags, true)
	|| in_array('repost_media_video', $tgHintTags, true),
	'Telegram hints contain repost_media',
);
assert(in_array('b', $tgTags->allowedHtmlTags(), true), 'Telegram HTML allowlist has b');

$extras = $defaultTags->extraPlaceholders(['tags' => 'foo bar,baz'], []);
assert(str_contains($extras['{hashtags}'], '#foo_bar'), 'hashtags');
assert(str_contains($extras['{tags_no_link}'], 'foo_bar'), 'tags_no_link');

$xfHtml = $defaultTags->applyAfterParse(
	'[xfvalue_mood_text] [xfvalue_mood_hashtag]',
	['xfields' => 'mood|happy,sad'],
	['tag_separator' => ', ']
);
assert(str_contains($xfHtml, 'happy'), 'xf text');
assert(str_contains($xfHtml, '#happy'), 'xf hashtag');

$extract = $ref->getMethod('extractMediaTags');
$extract->setAccessible(true);
$media = $extract->invoke(
	$renderer,
	'[repost_media_image url=https://cdn.example.com/a.jpg][repost_media_video video=2]',
	['xfields' => $xfVideo, 'full_story' => '', 'short_story' => ''],
	[]
);
assert($media['has_tags'] === true, 'has media tags');
assert($media['images'] === ['https://cdn.example.com/a.jpg'], 'url= image');
assert(count($media['videos']) === 1 && str_contains($media['videos'][0], 'w.mkv'), 'video=2 selector');

assert(in_array('repost_media_allimages', $tgHintTags, true), 'hints contain allimages');
assert(in_array('repost_media_xfield', $tgHintTags, true), 'hints contain xfield');

assert(MediaLimits::maxBytesFor('video') === MediaLimits::VIDEO_MAX_BYTES, 'video limit');
assert(MediaLimits::maxBytesFor('photo') === MediaLimits::PHOTO_MAX_BYTES, 'photo limit');

$tgProvider = new \DevCraft\Modules\RePost\Provider\Telegram\TelegramProvider();
assert($tgProvider->sendTypeFromConfig(['tg_send_type' => 'media']) === 'media', 'tg sendTypeFromConfig');
assert($tgProvider->sendTypeFromConfig([]) === 'text', 'tg default text');

$vkProvider = new \DevCraft\Modules\RePost\Provider\VK\VKProvider();
assert($vkProvider->sendTypeFromConfig(['vk_send_type' => 'photo']) === 'photo', 'vk sendTypeFromConfig');
assert($vkProvider->sendTypeFromConfig(['tg_send_type' => 'media']) === 'media', 'vk legacy tg_send_type fallback');
assert($vkProvider->sendTypeFromConfig([]) === 'text', 'vk default text');

$stub = new class extends AbstractProvider {
	protected function mediaByteLimits(): array {
		return ['video' => 100];
	}

	public static function code(): string {
		return 'stub';
	}

	public static function meta(): array {
		return ['name' => 'stub', 'title' => 'Stub', 'version' => '0'];
	}

	public function settingsSchema(): FormSchema {
		throw new RuntimeException('not used');
	}

	public function allowedMediaExtensions(): array {
		return [
			'photo'    => [],
			'video'    => [],
			'audio'    => [],
			'document' => [],
		];
	}

	public function send(
		PostContext $context,
		RenderedMessage $message,
		array $connectionConfig,
		?array $proxy = null,
	): SendResult {
		return $this->ok();
	}

	public function filterPublic(RenderedMessage $message): array {
		return $this->filterMediaByLimits($message);
	}

	public function classifyPublic(string $path, string $pref = 'document'): string {
		return $this->classifyMediaByExtension($path, $pref);
	}
};

$extStub = new class extends AbstractProvider {
	protected function mediaByteLimits(): array {
		return [];
	}

	public static function code(): string {
		return 'ext';
	}

	public static function meta(): array {
		return ['name' => 'ext', 'title' => 'Ext', 'version' => '0'];
	}

	public function settingsSchema(): FormSchema {
		throw new RuntimeException('not used');
	}

	public function allowedMediaExtensions(): array {
		return [
			'photo'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
			'video'    => ['mp4', 'm4v', 'mkv', 'webm', 'avi', 'mov', 'mpeg', 'mpg', '3gp'],
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
		return $this->ok();
	}

	public function classifyPublic(string $path, string $pref = 'document'): string {
		return $this->classifyMediaByExtension($path, $pref);
	}
};

assert($extStub->classifyPublic('clip.mp4', 'photo') === 'video', 'mp4 → video');
assert($extStub->classifyPublic('clip.mkv', 'photo') === 'video', 'mkv → video');
assert($extStub->classifyPublic('track.mp3', 'audio') === 'audio', 'mp3 audio');
assert(in_array('mp4', $extStub->allowedMediaExtensions()['video'], true), 'ext mp4');
assert(in_array('mkv', $extStub->allowedMediaExtensions()['video'], true), 'ext mkv');
assert(in_array('mp3', $extStub->allowedMediaExtensions()['audio'], true), 'ext mp3');

$dir = ROOT_DIR . '/uploads/_repost_selfcheck';
if(!is_dir($dir)) {
	mkdir($dir, 0777, true);
}
$small = $dir . '/small.bin';
$big   = $dir . '/big.bin';
file_put_contents($small, str_repeat('a', 50));
file_put_contents($big, str_repeat('b', 200));

$msg = new RenderedMessage(
	text: 't',
	videos: ['uploads/_repost_selfcheck/small.bin', 'uploads/_repost_selfcheck/big.bin'],
);
$out = $stub->filterPublic($msg);
assert(count($out['message']->videos) === 1, 'keeps small video');
assert(str_contains($out['message']->videos[0], 'small.bin'), 'small kept');
assert(count($out['skipped']) === 1, 'skips oversized');
assert($out['skipped'][0]['kind'] === 'video', 'skipped kind video');

@unlink($small);
@unlink($big);
@rmdir($dir);

assert(class_exists(TemplateTagsPage::class), 'TemplateTagsPage exists');

if(!class_exists('DLEPlugins', false)) {
	class DLEPlugins {
		public static function Check(string $path): string {
			return $path;
		}
	}
}

assert(ProviderRegistry::all() !== [], 'ProviderRegistry non-empty');
$tgProvider = ProviderRegistry::get('telegram');
assert($tgProvider !== null, 'telegram provider');
assert($tgProvider->templateTags()->hints() !== [], 'telegram registry hints non-empty');

assert(DispatchService::shouldEnqueue('yes', false, false) === true, 'defer yes always queue');
assert(DispatchService::shouldEnqueue('no', true, true) === false, 'defer no always sync');
assert(DispatchService::shouldEnqueue('template', true, true) === true, 'template+cron+enabled');
assert(DispatchService::shouldEnqueue('template', true, false) === false, 'template cron disabled');
assert(DispatchService::shouldEnqueue('template', false, true) === false, 'template without cron flag');

fwrite(STDOUT, "RePost selfcheck OK\n");
