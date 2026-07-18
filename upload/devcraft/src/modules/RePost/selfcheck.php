<?php

declare(strict_types=1);

/**
 * Проверка парсинга xfields audio/video без отправки в Telegram.
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

use DevCraft\Modules\RePost\Services\ContentRenderer;
use DevCraft\Modules\RePost\Services\CopyHelper;

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
assert(str_contains($audios[0], 'a.mp3'), 'first audio url');

$videos = $collect->invoke($renderer, $xfVideo, 'video');
assert(count($videos) === 2, 'parse 2 video urls');

[$i, $v, $a] = $filter->invoke($renderer, 'audio', ['img.jpg'], $videos, $audios);
assert($i === [] && $v === [] && count($a) === 2, 'filter audio keeps only audios');

[$i, $v, $a] = $filter->invoke($renderer, 'video', ['img.jpg'], $videos, $audios);
assert($i === [] && count($v) === 2 && $a === [], 'filter video keeps only videos');

[$i, $v, $a] = $filter->invoke($renderer, 'media', ['img.jpg'], $videos, $audios);
assert(count($i) === 1 && count($v) === 2 && count($a) === 2, 'filter media keeps all');

fwrite(STDOUT, "RePost selfcheck OK\n");
