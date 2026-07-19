# RePost

Публикация новостей DLE в социальные сети и другие каналы доставки: несколько шаблонов, несколько API-подключений, pluggable-провайдеры (Telegram встроен, [VK.com](https://devcraft.club/downloads/repost.30/) — платное дополнение).

| | |
|---|---|
| Версия | **200.1.0** |
| Совместимость | DevCraft Admin ≥ **200.4.0**, DLE **20.0** |
| Зависимости (composer) | [`luzrain/telegram-bot-api`](https://github.com/luzrain/telegram-bot-api), [`guzzlehttp/guzzle`](https://github.com/guzzle/guzzle) |

## Возможности

- Несколько шаблонов с условиями (категории, поля, xfields)
- Несколько подключений API (разные боты / каналы)
- Провайдеры в `Provider/{Name}/` (Telegram в комплекте)
- Очередь через авто-cron DLE, прокси (в т.ч. случайный), копирование сущностей
- Автопостинг при добавлении/редактировании новости; логи в Admin → Logs (модуль RePost)
- Отложенная отправка и выбор шаблонов прямо на форме новости

## Установка

1. DevCraft Admin ≥ 200.4.0.
2. ZIP через DLE Plugin Manager.
3. В каталоге `devcraft/`: `composer require luzrain/telegram-bot-api guzzlehttp/guzzle && composer dump-autoload` (если пакетов ещё нет в `composer.json`). Vendor в архив плагина не входит.
4. Таблицы Cycle ORM создаются по моделям при первом обращении к модулю в админке.
5. Очередь обрабатывается через авто-cron DLE (`$config['cron']`) → `engine/modules/cron.php` → `repostRunCron()`.

## Документация

https://readme.devcraft.club/dev/repost/
