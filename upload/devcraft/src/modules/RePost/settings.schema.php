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
		->checkbox('cron_enabled', __('Очередь для шаблонов с флагом «В очередь cron»'))
			->description(__(
				'Если включено — шаблоны с флагом «В очередь cron» ставятся в очередь (режим «настройка шаблона» на форме новости). '
				. 'Обработка: авто-cron DLE ($config[\'cron\']) → engine/modules/cron.php → repostRunCron(). '
				. 'На форме новости можно принудительно отложить или отправить сразу.',
			))
			->default(false)
		->number('cron_news', __('Новостей за один проход cron'))
			->default(5)
		->number('cron_waittime', __('Задержка между отправками (сек)'))
			->default(2)
		->number('cron_retry_interval', __('Интервал повтора при ошибке/таймауте (сек)'))
			->default(300)
		->number('cron_max_attempts', __('Максимум попыток отправки'))
			->default(5)
		->checkbox('cron_autodelete', __('Удалять запись очереди после успешной отправки'))
			->default(true)
	->section(__('VK'))
		->text('vk_api_host', __('Базовый домен API'))
			->description(__(
				'Домен без схемы, например <code>vk.com</code> или <code>vk.ru</code>. '
				. 'Запросы уходят на <code>https://api.{домен}/method</code>. '
				. 'Смена без обновления аддона при миграции VK com→ru.',
			))
			->default('vk.com')
	->build();
