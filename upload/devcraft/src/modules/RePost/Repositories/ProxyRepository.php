<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\RePost\Models\Proxy;

/**
 * Репозиторий прокси RePost.
 */
final class ProxyRepository extends AbstractRepository {

	public function findOneById(int $id): ?Proxy {
		/** @var Proxy|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<Proxy>
	 */
	public function findActive(): array {
		/** @var list<Proxy> */
		return $this->select()->where('active', true)->fetchAll();
	}

	public function pickRandomActive(?int $preferId = null): ?Proxy {
		if($preferId !== null && $preferId > 0) {
			$one = $this->findOneById($preferId);

			if($one !== null && $one->active) {
				return $one;
			}
		}

		$all = $this->findActive();

		if($all === []) {
			return null;
		}

		return $all[array_rand($all)];
	}

}
