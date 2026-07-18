<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Ajax;

use DevCraft\Core\Application;
use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\RePost\Models\Connection;
use DevCraft\Modules\RePost\Repositories\ConnectionRepository;
use DevCraft\Modules\RePost\Services\CopyHelper;

/**
 * CRUD подключений.
 */
final class ConnectionHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		/** @var ConnectionRepository $repo */
		$repo = Application::instance()->database()->repository(Connection::class);

		if($request->method === 'connection_copy') {
			$id  = (int) ($request->data['id'] ?? 0);
			$src = $repo->findOneById($id);

			if($src === null) {
				return JsonResponse::fail(__('Ошибка'), __('Подключение не найдено'), 'not_found', 404);
			}

			$clone           = new Connection();
			$clone->name     = CopyHelper::uniqueName(
				$src->name,
				static fn(string $n): bool => $repo->select()->where('name', $n)->fetchOne() !== null
			);
			$clone->provider = $src->provider;
			$clone->config   = $src->config;
			$clone->active   = $src->active;
			$repo->saveEntity($clone);

			return JsonResponse::toast(__('Скопировано'), ['id' => $clone->id()]);
		}

		if($request->method === 'connection_delete') {
			$id   = (int) ($request->data['id'] ?? 0);
			$item = $repo->findOneById($id);

			if($item === null) {
				return JsonResponse::fail(__('Ошибка'), __('Подключение не найдено'), 'not_found', 404);
			}

			$repo->deleteEntity($item);

			return JsonResponse::toast(__('Удалено'), ['deleted' => true]);
		}

		if($request->method === 'connection_toggle') {
			$id   = (int) ($request->data['id'] ?? 0);
			$item = $repo->findOneById($id);

			if($item === null) {
				return JsonResponse::fail(__('Ошибка'), __('Подключение не найдено'), 'not_found', 404);
			}

			$item->active = !$item->active;
			$repo->saveEntity($item);

			return JsonResponse::toast(
				$item->active ? __('Включено') : __('Отключено'),
				['active' => $item->active]
			);
		}

		$id       = (int) ($request->data['id'] ?? 0);
		$name     = trim((string) ($request->data['name'] ?? ''));
		$provider = trim((string) ($request->data['provider'] ?? 'telegram'));
		$active   = !empty($request->data['active']);
		$config   = $request->data['config'] ?? [];

		if($name === '') {
			return JsonResponse::fail(__('Ошибка'), __('Укажите название'), 'validation', 422);
		}

		if(!is_array($config)) {
			$config = [];
		}

		$item = $id > 0 ? $repo->findOneById($id) : new Connection();

		if($id > 0 && $item === null) {
			return JsonResponse::fail(__('Ошибка'), __('Подключение не найдено'), 'not_found', 404);
		}

		$item->name     = $name;
		$item->provider = $provider !== '' ? $provider : 'telegram';
		$item->active   = $active;
		$item->setConfigArray($config);
		$repo->saveEntity($item);

		return JsonResponse::toast(__('Сохранено'), [
			'id'       => $item->id(),
			'redirect' => '?mod=repost&action=connections',
		]);
	}

}
