<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Abstracts\AbstractPage;

/**
 * Журнал изменений.
 */
final class ChangelogPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Журнал изменений'));

		return [
			'view' => 'pages/changelog.twig',
			'data' => [
				'page_title' => __('Журнал изменений'),
			],
		];
	}

}
