<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\RePost\Repositories\CronItemRepository;

/**
 * Элемент очереди отложенной публикации (`{prefix}_repost_cron`).
 */
#[Entity(role: 'repost_cron', repository: CronItemRepository::class, table: 'repost_cron')]
#[Index(columns: ['template_id', 'news_id'], unique: true, name: 'idx_repost_cron_uniq')]
#[Index(columns: ['planned'], name: 'idx_repost_cron_planned')]
class CronItem extends AbstractEntity {

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $template_id = 0;

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $news_id = 0;

	#[Column(type: 'datetime')]
	public \DateTimeImmutable $planned;

	#[Column(type: 'string(50)', default: 'addnews')]
	public string $event_type = 'addnews';

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
		$this->planned   = new \DateTimeImmutable();
	}

	public function getColumnVal(string $name): mixed {
		return match ($name) {
			'id'          => $this->id(),
			'template_id' => $this->template_id,
			'news_id'     => $this->news_id,
			'planned'     => $this->planned,
			'event_type'  => $this->event_type,
			default       => null,
		};
	}

}
