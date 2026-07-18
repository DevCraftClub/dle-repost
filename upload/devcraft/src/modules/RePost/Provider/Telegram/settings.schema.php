<?php

declare(strict_types=1);

use DevCraft\Core\Enums\FormLayout;
use DevCraft\Form\FormSchemaBuilder;

/**
 * Схема настроек подключения Telegram.
 */
return FormSchemaBuilder::create('repost_telegram_connection')
	->layout(FormLayout::STACK)
	->section(__('Telegram'))
		->text('tg_bot', __('Токен бота'))
			->description(__('Токен от @BotFather'))
			->default('')
		->text('tg_chat_id', __('Chat / Channel ID'))
			->description(__('@channel или числовой id'))
			->default('')
		->select('tg_send_type', __('Тип отправки'))
			->options([
				'text'     => __('Текст'),
				'media'    => __('Медиагруппа'),
				'photo'    => __('Фото'),
				'audio'    => __('Аудио'),
				'video'    => __('Видео'),
				'document' => __('Документ'),
			])
			->default('text')
	->build();
