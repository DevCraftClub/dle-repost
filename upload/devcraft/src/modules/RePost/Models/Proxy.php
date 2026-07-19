<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use DevCraft\Core\Abstracts\AbstractEntity;
use DevCraft\Modules\RePost\Repositories\ProxyRepository;

/**
 * Прокси для отправки (`{prefix}_repost_proxies`).
 */
#[Entity(role: 'repost_proxy', repository: ProxyRepository::class, table: 'repost_proxies')]
#[Index(columns: ['ip', 'port', 'type'], unique: true, name: 'idx_repost_proxy_uniq')]
class Proxy extends AbstractEntity {

	#[Column(type: 'string(255)')]
	public string $ip = '';

	#[Column(type: 'integer', default: 0)]
	public int $port = 0;

	#[Column(type: 'string(20)', default: 'http')]
	public string $type = 'http';

	#[Column(type: 'string(255)', nullable: true)]
	public ?string $user = NULL;

	#[Column(type: 'string(255)', nullable: true)]
	public ?string $pass = NULL;

	#[Column(type: 'boolean', default: false)]
	public bool $auth = false;

	#[Column(type: 'boolean', default: true)]
	public bool $active = true;

	public function __construct() {
		$this->createdAt = new \DateTimeImmutable();
	}

	public function getColumnVal(string $name): mixed {
		return match ($name) {
			'id'     => $this->id(),
			'ip'     => $this->ip,
			'port'   => $this->port,
			'type'   => $this->type,
			'user'   => $this->user,
			'pass'   => $this->pass,
			'auth'   => $this->auth,
			'active' => $this->active,
			default  => NULL,
		};
	}

}
