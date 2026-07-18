<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider\Telegram;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Luzrain\TelegramBotApi\BotApi;

/**
 * Собирает BotApi с Guzzle (PSR-18) и опциональным прокси.
 */
final class TelegramBotFactory {

	/**
	 * @param   array<string, mixed>|null  $proxy  ip, port, type, auth, user, pass
	 */
	public static function create(string $token, ?array $proxy = null): BotApi {
		$httpFactory = new HttpFactory();
		$client      = new Client(self::guzzleOptions($proxy));

		return new BotApi(
			requestFactory: $httpFactory,
			streamFactory: $httpFactory,
			client: $client,
			token: $token,
		);
	}

	/**
	 * @param   array<string, mixed>|null  $proxy
	 *
	 * @return array<string, mixed>
	 */
	private static function guzzleOptions(?array $proxy): array {
		$options = [
			'http_errors'     => false,
			'timeout'         => 25,
			'connect_timeout' => 8,
		];

		$proxyUri = self::proxyUri($proxy);

		if($proxyUri !== null) {
			$options['proxy'] = $proxyUri;
		}

		return $options;
	}

	/**
	 * @param   array<string, mixed>|null  $proxy
	 */
	private static function proxyUri(?array $proxy): ?string {
		if($proxy === null || empty($proxy['ip'])) {
			return null;
		}

		$host = (string) $proxy['ip'];
		$port = (int) ($proxy['port'] ?? 0);
		$type = strtolower((string) ($proxy['type'] ?? 'http'));
		$auth = !empty($proxy['auth']) && !empty($proxy['user']);
		$user = rawurlencode((string) ($proxy['user'] ?? ''));
		$pass = rawurlencode((string) ($proxy['pass'] ?? ''));
		$cred = $auth ? $user . ':' . $pass . '@' : '';

		$scheme = str_contains($type, 'socks') ? 'socks5' : 'http';

		return $scheme . '://' . $cred . $host . ($port > 0 ? ':' . $port : '');
	}

}
