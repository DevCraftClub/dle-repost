<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Provider;

/**
 * Контракт тегов шаблона и HTML-allowlist канала доставки.
 */
interface TemplateTagsInterface {

	/**
	 * Подсказки для UI (чипы вставки).
	 *
	 * @return list<array{code: string, tag: string, descr: string, group: string}>
	 */
	public function hints(): array;

	/**
	 * Доп. плейсхолдеры после базового ParseTemplateTags.
	 *
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 *
	 * @return array<string, string>
	 */
	public function extraPlaceholders(array $newsRow, array $moduleConfig): array;

	/**
	 * Post-pass после подстановки плейсхолдеров ([if], …).
	 *
	 * @param   array<string, mixed>  $newsRow
	 * @param   array<string, mixed>  $moduleConfig
	 */
	public function applyAfterParse(string $html, array $newsRow, array $moduleConfig): string;

	/**
	 * Имена HTML-тегов без угловых скобок (для strip_tags / toolbar).
	 *
	 * @return list<string>
	 */
	public function allowedHtmlTags(): array;

	/**
	 * BB→HTML + очистка по allowlist канала.
	 */
	public function sanitizeHtml(string $text): string;

}
