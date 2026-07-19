<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Ajax;

use DevCraft\Core\Application;
use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Modules\RePost\Models\Template;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Modules\RePost\Services\CopyHelper;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Modules\RePost\Repositories\TemplateRepository;

/**
 * CRUD шаблонов.
 */
final class TemplateHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		/** @var TemplateRepository $repo */
		$repo = Application::instance()->database()->repository(Template::class);

		if($request->method === 'template_copy') {
			$id  = (int) ($request->data['id'] ?? 0);
			$src = $repo->findOneById($id);

			if($src === NULL) {
				return JsonResponse::fail(__('Ошибка'), __('Шаблон не найден'), 'not_found', 404);
			}

			$clone                     = new Template();
			$clone->name               = CopyHelper::uniqueName(
				$src->name,
				static fn(string $n): bool => $repo->select()->where('name', $n)->fetchOne() !== NULL,
			);
			$clone->connection_id      = $src->connection_id;
			$clone->condition          = $src->condition;
			$clone->condition_relation = $src->condition_relation;
			$clone->template_type      = $src->template_type;
			$clone->template           = $src->template;
			$clone->active             = $src->active;
			$clone->cron               = $src->cron;
			$clone->use_proxy          = $src->use_proxy;
			$clone->proxy_id           = $src->proxy_id;
			$repo->saveEntity($clone);

			return JsonResponse::toast(__('Скопировано'), ['id' => $clone->id()]);
		}

		if($request->method === 'template_delete') {
			$id   = (int) ($request->data['id'] ?? 0);
			$item = $repo->findOneById($id);

			if($item === NULL) {
				return JsonResponse::fail(__('Ошибка'), __('Шаблон не найден'), 'not_found', 404);
			}

			$repo->deleteEntity($item);

			return JsonResponse::toast(__('Удалено'), ['deleted' => true]);
		}

		if($request->method === 'template_toggle') {
			$id   = (int) ($request->data['id'] ?? 0);
			$item = $repo->findOneById($id);

			if($item === NULL) {
				return JsonResponse::fail(__('Ошибка'), __('Шаблон не найден'), 'not_found', 404);
			}

			$item->active = !$item->active;
			$repo->saveEntity($item);

			return JsonResponse::toast(
				$item->active? __('Включено') : __('Отключено'),
				['active' => $item->active],
			);
		}

		$id   = (int) ($request->data['id'] ?? 0);
		$name = trim((string) ($request->data['name'] ?? ''));
		$conn = (int) ($request->data['connection_id'] ?? 0);

		if($name === '' || $conn <= 0) {
			return JsonResponse::fail(__('Ошибка'), __('Укажите название и подключение'), 'validation', 422);
		}

		$item = $id > 0? $repo->findOneById($id) : new Template();

		if($id > 0 && $item === NULL) {
			return JsonResponse::fail(__('Ошибка'), __('Шаблон не найден'), 'not_found', 404);
		}

		$templateType = $request->data['template_type'] ?? 'addnews,editnews';

		if(is_array($templateType)) {
			$parts        = array_values(array_filter(array_map(
				static fn(mixed $v): string => trim((string) $v),
				$templateType,
			),
				static fn(string $v): bool => $v !== ''));
			$templateType = $parts !== []? implode(',', $parts) : 'addnews,editnews';
		} else {
			$templateType = trim((string) $templateType);
			if($templateType === '') {
				$templateType = 'addnews,editnews';
			}
		}

		$item->name               = $name;
		$item->connection_id      = $conn;
		$item->template_type      = $templateType;
		$item->template           = (string) ($request->data['template'] ?? '');
		$item->condition          = (string) ($request->data['condition'] ?? '[]');
		$item->condition_relation = (string) ($request->data['condition_relation'] ?? 'and');
		$item->active             = !empty($request->data['active']);
		$item->cron               = !empty($request->data['cron']);
		$item->use_proxy          = !empty($request->data['use_proxy']);
		$proxyId                  = (int) ($request->data['proxy_id'] ?? 0);
		$item->proxy_id           = $proxyId > 0? $proxyId : NULL;

		$repo->saveEntity($item);

		return JsonResponse::toast(__('Сохранено'), [
			'id'       => $item->id(),
			'redirect' => '?mod=repost&action=templates',
		]);
	}

}
