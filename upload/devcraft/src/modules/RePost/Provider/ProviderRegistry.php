<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

use DLEPlugins;

/**
 * Реестр провайдеров: сканирует каталоги Provider/{Name}/init.php.
 */
final class ProviderRegistry {

	/** @var array<string, array{name: string, title: string, version: string, class: class-string<ProviderInterface>}>|null */
	private static ?array $cache = null;

	/**
	 * @return array<string, array{name: string, title: string, version: string, class: class-string<ProviderInterface>}>
	 */
	public static function all(): array {
		if(self::$cache !== null) {
			return self::$cache;
		}

		$base = dirname(__DIR__) . '/Provider';
		$out  = [];

		if(!is_dir($base)) {
			return self::$cache = $out;
		}

		foreach(scandir($base) ?: [] as $entry) {
			if($entry === '.' || $entry === '..' || $entry === 'ProviderInterface.php' || $entry === 'ProviderRegistry.php') {
				continue;
			}

			$dir = $base . '/' . $entry;

			if(!is_dir($dir)) {
				continue;
			}

			$init = $dir . '/init.php';

			if(!is_file($init)) {
				continue;
			}

			/** @var mixed $meta */
			$meta = include DLEPlugins::Check($init);

			if(!is_array($meta) || empty($meta['name']) || empty($meta['class'])) {
				continue;
			}

			$name = (string) $meta['name'];
			/** @var class-string<ProviderInterface> $class */
			$class = (string) $meta['class'];

			$out[$name] = [
				'name'    => $name,
				'title'   => (string) ($meta['title'] ?? $name),
				'version' => (string) ($meta['version'] ?? '0.0.0'),
				'class'   => $class,
			];
		}

		return self::$cache = $out;
	}

	public static function get(string $code): ?ProviderInterface {
		$all = self::all();

		if(!isset($all[$code])) {
			return null;
		}

		$class = $all[$code]['class'];

		if(!is_a($class, ProviderInterface::class, true)) {
			return null;
		}

		return new $class();
	}

	/**
	 * @return array<string, string> code => title
	 */
	public static function options(): array {
		$opts = [];

		foreach(self::all() as $code => $meta) {
			$opts[$code] = $meta['title'];
		}

		return $opts;
	}

}
