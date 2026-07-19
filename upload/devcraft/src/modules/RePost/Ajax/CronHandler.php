<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Ajax;

use DevCraft\Core\Application;
use DevCraft\Core\Http\AjaxRequest;
use DevCraft\Core\Http\JsonResponse;
use DevCraft\Core\Interfaces\AjaxHandlerInterface;
use DevCraft\Core\Interfaces\ResponseInterface;
use DevCraft\Core\Support\DataManager;
use DevCraft\Modules\RePost\Models\CronItem;
use DevCraft\Modules\RePost\Repositories\CronItemRepository;
use DevCraft\Modules\RePost\Services\DispatchService;

/**
 * Действия очереди cron.
 */
final class CronHandler implements AjaxHandlerInterface {

	public function handle(AjaxRequest $request): ResponseInterface {
		/** @var CronItemRepository $repo */
		$repo = Application::instance()->database()->repository(CronItem::class);
		$id   = (int) ($request->data['id'] ?? 0);
		$item = $repo->findOneById($id);

		if($item === null) {
			return JsonResponse::fail(__('Ошибка'), __('Запись не найдена'), 'not_found', 404);
		}

		if($request->method === 'cron_delete') {
			$repo->deleteEntity($item);

			return JsonResponse::toast(__('Удалено'), ['deleted' => true]);
		}

		$result = (new DispatchService())->sendCronItem($item);

		if(!$result->ok) {
			return JsonResponse::fail(__('Ошибка'), $result->message, 'send_failed', 500);
		}

		$config  = DataManager::getConfig('repost');
		$deleted = !empty($config['cron_autodelete']);

		return JsonResponse::toast(
			$result->message !== '' ? $result->message : __('Отправлено'),
			['ok' => true, 'deleted' => $deleted, 'id' => $id]
		);
	}

}
