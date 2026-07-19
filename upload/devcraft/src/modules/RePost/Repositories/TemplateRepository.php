<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\RePost\Models\Template;

/**
 * Репозиторий шаблонов RePost.
 */
final class TemplateRepository extends AbstractRepository {

	public function findOneById(int $id): ?Template {
		/** @var Template|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<Template>
	 */
	public function findActiveByEvent(string $eventType): array {
		/** @var list<Template> $all */
		$all = $this->findActiveAll();
		$out = [];

		foreach($all as $tpl) {
			if(in_array($eventType, $tpl->typeList(), true)
				|| in_array('cron_' . $eventType, $tpl->typeList(), true)
			) {
				$out[] = $tpl;
			}
		}

		return $out;
	}

	/**
	 * @return list<Template>
	 */
	public function findActiveAll(): array {
		/** @var list<Template> */
		return $this->select()->where('active', true)->orderBy('id')->fetchAll();
	}

	/**
	 * Активные шаблоны по списку id (порядок как в БД).
	 *
	 * @param   list<int>  $ids
	 *
	 * @return list<Template>
	 */
	public function findActiveByIds(array $ids): array {
		$ids = array_values(array_unique(array_filter(
			array_map(static fn(mixed $v): int => (int) $v, $ids),
			static fn(int $id): bool => $id > 0,
		)));

		if($ids === []) {
			return [];
		}

		/** @var list<Template> */
		return $this->select()
			->where('active', true)
			->where('id', 'in', $ids)
			->orderBy('id')
			->fetchAll();
	}

}
