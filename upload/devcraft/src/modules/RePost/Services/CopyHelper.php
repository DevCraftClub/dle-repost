<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

/**
 * Уникальные имена для клонов сущностей.
 */
final class CopyHelper {

	/**
	 * @param   callable(string): bool  $isTaken
	 */
	public static function uniqueName(string $base, callable $isTaken): string {
		$i = 1;

		do {
			$name = $i === 1
				? $base . ' (' . __('копия') . ')'
				: $base . ' (' . __('копия') . ' ' . $i . ')';
			$i++;
		} while($isTaken($name));

		return $name;
	}

}
