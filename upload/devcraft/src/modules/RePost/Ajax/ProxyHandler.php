<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Ajax;

use DevCraft\Core\Application;
use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\RePost\Models\Proxy;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Modules\RePost\Repositories\ProxyRepository;

/**
 * CRUD прокси.
 */
final class ProxyHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		/** @var ProxyRepository $repo */
		$repo = Application::instance()->database()->repository(Proxy::class);

		if($request->method === 'proxy_copy') {
			$id  = (int) ($request->data['id'] ?? 0);
			$src = $repo->findOneById($id);

			if($src === NULL) {
				return JsonResponse::fail(__('Ошибка'), __('Прокси не найден'), 'not_found', 404);
			}

			$port = $src->port;

			while(
				$repo
					->select()
					->where('ip', $src->ip)
					->where('port', $port)
					->where('type', $src->type)
					->fetchOne() !== NULL
			) {
				$port++;

				if($port > 65535) {
					return JsonResponse::fail(
						__('Ошибка'),
						__('Не удалось подобрать свободный порт для копии'),
						'conflict',
						409,
					);
				}
			}

			$clone         = new Proxy();
			$clone->ip     = $src->ip;
			$clone->port   = $port;
			$clone->type   = $src->type;
			$clone->user   = $src->user;
			$clone->pass   = $src->pass;
			$clone->auth   = $src->auth;
			$clone->active = $src->active;
			$repo->saveEntity($clone);

			return JsonResponse::toast(__('Скопировано'), ['id' => $clone->id()]);
		}

		if($request->method === 'proxy_delete') {
			$id   = (int) ($request->data['id'] ?? 0);
			$item = $repo->findOneById($id);

			if($item === NULL) {
				return JsonResponse::fail(__('Ошибка'), __('Прокси не найден'), 'not_found', 404);
			}

			$repo->deleteEntity($item);

			return JsonResponse::toast(__('Удалено'), ['deleted' => true]);
		}

		if($request->method === 'proxy_toggle') {
			$id   = (int) ($request->data['id'] ?? 0);
			$item = $repo->findOneById($id);

			if($item === NULL) {
				return JsonResponse::fail(__('Ошибка'), __('Прокси не найден'), 'not_found', 404);
			}

			$item->active = !$item->active;
			$repo->saveEntity($item);

			return JsonResponse::toast(
				$item->active? __('Включено') : __('Отключено'),
				['active' => $item->active],
			);
		}

		$id   = (int) ($request->data['id'] ?? 0);
		$ip   = trim((string) ($request->data['ip'] ?? ''));
		$port = (int) ($request->data['port'] ?? 0);

		if($ip === '' || $port <= 0) {
			return JsonResponse::fail(__('Ошибка'), __('Укажите IP и порт'), 'validation', 422);
		}

		$item = $id > 0? $repo->findOneById($id) : new Proxy();

		if($id > 0 && $item === NULL) {
			return JsonResponse::fail(__('Ошибка'), __('Прокси не найден'), 'not_found', 404);
		}

		$item->ip     = $ip;
		$item->port   = $port;
		$item->type   = (string) ($request->data['type'] ?? 'http');
		$item->user   = trim((string) ($request->data['user'] ?? ''))? : NULL;
		$item->pass   = (string) ($request->data['pass'] ?? '')? : NULL;
		$item->auth   = !empty($request->data['auth']);
		$item->active = !empty($request->data['active']);
		$repo->saveEntity($item);

		return JsonResponse::toast(__('Сохранено'), [
			'id'       => $item->id(),
			'redirect' => '?mod=repost&action=proxies',
		]);
	}

}
