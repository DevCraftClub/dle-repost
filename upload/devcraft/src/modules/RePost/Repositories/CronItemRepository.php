<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Repositories;

use DevCraft\Core\Abstracts\AbstractRepository;
use DevCraft\Modules\RePost\Models\CronItem;

/**
 * Репозиторий очереди cron RePost.
 */
final class CronItemRepository extends AbstractRepository {

	public function findOneById(int $id): ?CronItem {
		/** @var CronItem|null $entity */
		$entity = $this->select()->where('id', $id)->fetchOne();

		return $entity;
	}

	/**
	 * @return list<CronItem>
	 */
	public function findDue(int $limit): array {
		$now = new \DateTimeImmutable();

		/** @var list<CronItem> */
		return $this->select()
			->where('planned', '<=', $now)
			->orderBy('planned')
			->limit($limit)
			->fetchAll();
	}

	public function deleteByNewsId(int $newsId): void {
		$items = $this->select()->where('news_id', $newsId)->fetchAll();

		foreach($items as $item) {
			$this->deleteEntity($item);
		}
	}

}
