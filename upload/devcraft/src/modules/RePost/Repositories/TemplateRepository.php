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
		$all = $this->select()->where('active', true)->orderBy('id')->fetchAll();
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

}
