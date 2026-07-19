<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\RePost\Repositories\ConnectionRepository;

/**
 * Подключение к соцсети (`{prefix}_repost_connections`).
 */
#[Entity(role: 'repost_connection', repository: ConnectionRepository::class, table: 'repost_connections')]
#[Index(columns: ['name'], unique: true, name: 'idx_repost_conn_name')]
#[Index(columns: ['provider'], name: 'idx_repost_conn_provider')]
class Connection extends AbstractEntity {

	#[Column(type: 'string(300)')]
	public string $name = '';

	#[Column(type: 'string(100)')]
	public string $provider = 'telegram';

	/** JSON-конфиг провайдера (token, chat, …). */
	#[Column(type: 'text')]
	public string $config = '{}';

	#[Column(type: 'boolean', default: true)]
	public bool $active = true;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getConfigArray(): array {
		$decoded = json_decode($this->config, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @param   array<string, mixed>  $config
	 */
	public function setConfigArray(array $config): void {
		$this->config = (string) json_encode($config, JSON_UNESCAPED_UNICODE);
	}

	public function getColumnVal(string $name): mixed {
		return match ($name) {
			'id'       => $this->id(),
			'name'     => $this->name,
			'provider' => $this->provider,
			'config'   => $this->config,
			'active'   => $this->active,
			default    => null,
		};
	}

}
