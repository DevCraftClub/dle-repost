<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\RePost\Repositories\TemplateRepository;

/**
 * Шаблон публикации (`{prefix}_repost_templates`).
 */
#[Entity(role: 'repost_template', repository: TemplateRepository::class, table: 'repost_templates')]
#[Index(columns: ['name'], unique: true, name: 'idx_repost_tpl_name')]
#[Index(columns: ['connection_id'], name: 'idx_repost_tpl_conn')]
class Template extends AbstractEntity {

	#[Column(type: 'string(150)')]
	public string $name = '';

	#[Column(type: 'integer', unsigned: true, default: 0)]
	public int $connection_id = 0;

	/** JSON-условия отбора новости. */
	#[Column(type: 'text')]
	public string $condition = '[]';

	#[Column(type: 'string(10)', default: 'and')]
	public string $condition_relation = 'and';

	/** addnews, editnews или оба через запятую. */
	#[Column(type: 'string(50)', default: 'addnews,editnews')]
	public string $template_type = 'addnews,editnews';

	#[Column(type: 'text')]
	public string $template = '';

	#[Column(type: 'boolean', default: true)]
	public bool $active = true;

	#[Column(type: 'boolean', default: false)]
	public bool $cron = false;

	#[Column(type: 'boolean', default: false)]
	public bool $use_proxy = false;

	#[Column(type: 'integer', nullable: true, unsigned: true)]
	public ?int $proxy_id = null;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getConditionArray(): array {
		$decoded = json_decode($this->condition, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @param   list<array<string, mixed>>  $conditions
	 */
	public function setConditionArray(array $conditions): void {
		$this->condition = (string) json_encode($conditions, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * @return list<string>
	 */
	public function typeList(): array {
		$parts = array_map('trim', explode(',', $this->template_type));

		return array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
	}

	public function getColumnVal(string $name): mixed {
		return match ($name) {
			'id'                  => $this->id(),
			'name'                => $this->name,
			'connection_id'       => $this->connection_id,
			'condition'           => $this->condition,
			'condition_relation'  => $this->condition_relation,
			'template_type'       => $this->template_type,
			'template'            => $this->template,
			'active'              => $this->active,
			'cron'                => $this->cron,
			'use_proxy'           => $this->use_proxy,
			'proxy_id'            => $this->proxy_id,
			default               => null,
		};
	}

}
