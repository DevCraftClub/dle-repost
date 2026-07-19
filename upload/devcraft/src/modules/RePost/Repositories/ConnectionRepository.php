<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\RePost\Models\Connection;

/**
 * Репозиторий подключений RePost.
 */
final class ConnectionRepository extends AbstractRepository {

	public function findOneById(int $id): ?Connection {
		/** @var Connection|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<Connection>
	 */
	public function findActive(): array {
		/** @var list<Connection> */
		return $this->select()->where('active', true)->orderBy('name')->fetchAll();
	}

}
