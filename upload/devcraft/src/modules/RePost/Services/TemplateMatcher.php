<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Services;

use DevCraft\Modules\RePost\Models\Template;

/**
 * Отбор шаблонов по условиям новости.
 */
final class TemplateMatcher {

	/**
	 * @param   list<Template>        $templates
	 * @param   array<string, mixed>  $newsRow
	 *
	 * @return list<Template>
	 */
	public function match(array $templates, array $newsRow, string $eventType): array {
		$matched = [];

		foreach($templates as $tpl) {
			$types = $tpl->typeList();

			if(!in_array($eventType, $types, true) && !in_array('cron_' . $eventType, $types, true)) {
				continue;
			}

			if(!$tpl->active) {
				continue;
			}

			if($this->matchesConditions($tpl, $newsRow)) {
				$matched[] = $tpl;
			}
		}

		return $matched;
	}

	/**
	 * @param   array<string, mixed>  $newsRow
	 */
	public function matchesConditions(Template $tpl, array $newsRow): bool {
		$conditions = $tpl->getConditionArray();

		if($conditions === []) {
			return true;
		}

		$relation = strtolower($tpl->condition_relation) === 'or' ? 'or' : 'and';
		$results  = [];

		foreach($conditions as $cond) {
			if(!is_array($cond)) {
				continue;
			}

			$results[] = $this->evalOne($cond, $newsRow);
		}

		if($results === []) {
			return true;
		}

		if($relation === 'or') {
			return in_array(true, $results, true);
		}

		return !in_array(false, $results, true);
	}

	/**
	 * @param   array<string, mixed>  $cond
	 * @param   array<string, mixed>  $newsRow
	 */
	private function evalOne(array $cond, array $newsRow): bool {
		$source = (string) ($cond['source'] ?? 'post');
		$name   = (string) ($cond['name'] ?? '');
		$value  = (string) ($cond['value'] ?? '');
		$op     = (string) ($cond['op'] ?? '=');

		if($name === '') {
			return true;
		}

		if($source === 'category') {
			$cats = array_map('intval', explode(',', (string) ($newsRow['category'] ?? '')));

			return in_array((int) $name, $cats, true) || in_array((int) $value, $cats, true);
		}

		if($source === 'xfields') {
			$xf = (string) ($newsRow['xfields'] ?? '');

			return str_contains($xf, $name . '|' . $value) || ($value === '' && str_contains($xf, $name . '|'));
		}

		$have = (string) ($newsRow[$name] ?? '');

		return match ($op) {
			'!='    => $have !== $value,
			'like'  => str_contains($have, $value),
			default => $have === $value,
		};
	}

}
