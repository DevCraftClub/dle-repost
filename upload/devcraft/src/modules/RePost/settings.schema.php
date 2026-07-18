<?php

declare(strict_types=1);

use DevCraft\Core\Enums\FormLayout;
use DevCraft\Form\FormSchemaBuilder;

/**
 * Глобальные настройки RePost (без token/chat — они в подключениях).
 */
return FormSchemaBuilder::create('repost')
	->layout(FormLayout::TABS)
	->section(__('Основные'))
		->text('hashtag_separator', __('Разделитель хештегов'))
			->default(' ')
		->text('tag_separator', __('Разделитель тегов'))
			->default(', ')
		->text('category_separator', __('Разделитель категорий'))
			->default(', ')
		->text('thumb_placeholder', __('Плейсхолдер превью (URL)'))
			->default('')
		->text('img_types', __('Разрешённые расширения изображений'))
			->description(__('Через запятую: jpg,jpeg,png,gif,webp'))
			->default('jpg,jpeg,png,gif,webp')
	->section(__('Cron'))
		->checkbox('cron_enabled', __('Использовать очередь по умолчанию'))
			->description(__('Если выключено — шаблоны с флагом «В очередь cron» всё равно отправляются сразу. Если включено — такие шаблоны только ставятся в очередь (cron.php?cronmode=repost).'))
			->default(false)
		->number('cron_news', __('Новостей за один проход cron'))
			->default(5)
		->number('cron_waittime', __('Задержка между отправками (сек)'))
			->default(2)
		->checkbox('cron_autodelete', __('Удалять запись очереди после успешной отправки'))
			->default(true)
	->build();
