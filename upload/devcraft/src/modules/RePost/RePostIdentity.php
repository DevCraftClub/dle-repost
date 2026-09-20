<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost;

use DevCraft\Core\Abstracts\AbstractModuleIdentity;

/**
 * Identity модуля RePost.
 *
 * MODULE/CODE = DLE mod (`engine/inc/repost.php` → `?mod=repost`).
 * Каталог модуля: `devcraft/src/modules/RePost/`.
 */
final class RePostIdentity extends AbstractModuleIdentity {

	public const string MODULE = 'repost';

	public const string CODE = 'repost';

}
