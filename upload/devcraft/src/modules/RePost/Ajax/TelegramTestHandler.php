<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Ajax;

use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\RePost\Provider\Telegram\TelegramProvider;

/**
 * Тест Telegram и получение chat id.
 */
final class TelegramTestHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		$token = trim((string) ($request->data['tg_bot'] ?? $request->data['token'] ?? ''));

		if($token === '') {
			return JsonResponse::fail(__('Ошибка'), __('Укажите токен бота'), 'validation', 422);
		}

		$provider = new TelegramProvider();

		if($request->method === 'telegram_chat_id') {
			try {
				$updates = $provider->getUpdates($token, null, 10);
			} catch(\Throwable $e) {
				return JsonResponse::fail(__('Ошибка'), $e->getMessage(), 'api', 500);
			}

			$chats = [];

			foreach($updates as $update) {
				$msg = $update->message ?? $update->channelPost;

				if($msg === null) {
					continue;
				}

				$chat = $msg->chat;
				$chats[(string) $chat->id] = [
					'id'    => $chat->id,
					'title' => $chat->title ?? $chat->username ?? $chat->id,
					'type'  => $chat->type,
				];
			}

			return JsonResponse::toast(__('Обновления получены'), ['chats' => array_values($chats)]);
		}

		$chat = trim((string) ($request->data['tg_chat_id'] ?? $request->data['chat'] ?? ''));

		if($chat === '') {
			return JsonResponse::fail(__('Ошибка'), __('Укажите chat id'), 'validation', 422);
		}

		$text   = trim((string) ($request->data['text'] ?? '')) ?: __('Тестовое сообщение RePost');
		$result = $provider->sendTestMessage($token, $chat, $text);

		return $result->ok
			? JsonResponse::toast(__('Тест отправлен'), ['raw' => $result->raw])
			: JsonResponse::fail(__('Ошибка'), $result->message !== '' ? $result->message : __('sendMessage не удался'), 'api', 500, ['raw' => $result->raw]);
	}

}
