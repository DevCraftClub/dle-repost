<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

/**
 * Базовые теги RePost / DLE и минимальная HTML-очистка.
 */
class DefaultTemplateTags implements TemplateTagsInterface {

	public function hints(): array {
		return [
			['code' => '{title}', 'tag' => 'title', 'descr' => __('Заголовок новости'), 'group' => 'base'],
			['code' => '{short-story}', 'tag' => 'short-story', 'descr' => __('Краткая новость'), 'group' => 'base'],
			['code' => '{full-story}', 'tag' => 'full-story', 'descr' => __('Полная новость'), 'group' => 'base'],
			['code' => '{link}', 'tag' => 'link', 'descr' => __('Ссылка на новость'), 'group' => 'base'],
			['code' => '{author}', 'tag' => 'author', 'descr' => __('Автор'), 'group' => 'base'],
			['code' => '{date}', 'tag' => 'date', 'descr' => __('Дата публикации'), 'group' => 'base'],
			['code' => '{hashtags}', 'tag' => 'hashtags', 'descr' => __('Теги как хештеги'), 'group' => 'repost'],
			['code' => '{tags_no_link}', 'tag' => 'tags_no_link', 'descr' => __('Теги без ссылок и без #'), 'group' => 'repost'],
			['code' => '{category-hashtag}', 'tag' => 'category-hashtag', 'descr' => __('Категории как хештеги'), 'group' => 'repost'],
			['code' => '{tags}', 'tag' => 'tags', 'descr' => __('Список тегов'), 'group' => 'repost'],
			[
				'code'  => "[if field]…[/if]",
				'tag'   => 'if',
				'descr' => __('Условный блок по полю новости'),
				'group' => 'repost',
			],
		];
	}

	public function extraPlaceholders(array $newsRow, array $moduleConfig): array {
		$sepHash = (string) ($moduleConfig['hashtag_separator'] ?? ' ');
		$sepTag  = (string) ($moduleConfig['tag_separator'] ?? ', ');

		$tags   = array_filter(array_map('trim', explode(',', (string) ($newsRow['tags'] ?? ''))));
		$hash   = [];
		$noLink = [];

		foreach($tags as $tag) {
			$urlTag   = preg_replace('/\s+/u', '_', $tag) ?? $tag;
			$hash[]   = '#' . $urlTag;
			$noLink[] = $urlTag;
		}

		$catHash = [];
		$catIds  = array_filter(array_map('intval', explode(',', (string) ($newsRow['category'] ?? ''))));

		global $cat_info;

		if(is_array($cat_info ?? NULL)) {
			foreach($catIds as $cid) {
				$name = (string) ($cat_info[$cid]['name'] ?? '');

				if($name !== '') {
					$catHash[] = '#' . str_replace(' ', '_', $name);
				}
			}
		}

		return [
			'{hashtags}'         => implode($sepHash, $hash),
			'{tags_no_link}'     => implode($sepTag, $noLink),
			'{category-hashtag}' => implode($sepTag, $catHash),
			'{tags}'             => implode($sepTag, $tags),
		];
	}

	public function applyAfterParse(string $html, array $newsRow, array $moduleConfig): string {
		$html = (string) preg_replace_callback(
			'/\[if\s+([^\]]+)\](.*?)\[\/if\]/is',
			static function(array $m) use ($newsRow): string {
				$expr = trim($m[1]);
				$body = $m[2];

				if(preg_match('/^([a-z0-9_\-]+)\s*(=|!=)\s*[\'"]?(.*?)[\'"]?$/i', $expr, $parts)) {
					$field = $parts[1];
					$op    = $parts[2];
					$want  = $parts[3];
					$have  = (string) ($newsRow[$field] ?? '');
					$ok    = $op === '='? ($have === $want) : ($have !== $want);

					return $ok? $body : '';
				}

				$have = trim((string) ($newsRow[$expr] ?? ''));

				return $have !== ''? $body : '';
			},
			$html,
		);

		return $this->applyXfTextHashtag($html, $newsRow, $moduleConfig);
	}

	/**
	 * Подстановка [xfvalue_NAME_text] и [xfvalue_NAME_hashtag].
	 *
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 */
	private function applyXfTextHashtag(string $html, array $newsRow, array $moduleConfig): string {
		$sep = (string) ($moduleConfig['tag_separator'] ?? ', ');
		$raw = (string) ($newsRow['xfields'] ?? '');

		if($raw === '' || !str_contains($html, '[xfvalue_')) {
			return $html;
		}

		foreach(explode('||', $raw) as $chunk) {
			$chunk  = trim($chunk);
			$sepPos = strpos($chunk, '|');

			if($sepPos === false) {
				continue;
			}

			$name  = substr($chunk, 0, $sepPos);
			$value = html_entity_decode(
				str_replace(['&#124;', '&#58;'], ['|', ':'], substr($chunk, $sepPos + 1)),
				ENT_QUOTES,
				'UTF-8',
			);
			$parts = array_values(array_filter(array_map('trim', explode(',', $value))));
			$text  = [];
			$hash  = [];

			foreach($parts as $part) {
				// для image|path|… берём читаемый кусок без служебных |
				$plain = trim(explode('|', $part, 2)[0]);

				if($plain === '') {
					continue;
				}

				$text[] = $plain;
				$hash[] = '#' . str_replace(' ', '_', $plain);
			}

			$html = str_replace(
				['[xfvalue_' . $name . '_text]', '[xfvalue_' . $name . '_hashtag]'],
				[implode($sep, $text), implode($sep, $hash)],
				$html,
			);
		}

		return $html;
	}

	public function allowedHtmlTags(): array {
		return [];
	}

	/**
	 * Строка allowlist для strip_tags: `<b><i>…`.
	 */
	public function allowedHtmlAllowlist(): string {
		$tags = $this->allowedHtmlTags();

		if($tags === []) {
			return '';
		}

		$out = '';

		foreach($tags as $tag) {
			$tag = strtolower(trim($tag, "<> \t"));

			if($tag !== '') {
				$out .= '<' . $tag . '>';
			}
		}

		return $out;
	}

	public function sanitizeHtml(string $text): string {
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = (string) preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\/\s*p\s*>/i', "\n", $text);
		$text = (string) preg_replace('/<\s*p[^>]*>/i', "\n", $text);
		$text = strip_tags($text, $this->allowedHtmlAllowlist());
		$text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

		return trim($text);
	}

}
