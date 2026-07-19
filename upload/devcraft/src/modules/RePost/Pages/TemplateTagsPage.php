<?php

declare(strict_types=1);

namespace DevCraft\Modules\RePost\Pages;

use DevCraft\Core\Application;
use DevCraft\Core\Abstracts\AbstractPage;
use DevCraft\Modules\RePost\Provider\ProviderRegistry;
use DevCraft\Modules\RePost\Provider\DefaultTemplateTags;

/**
 * Справочник тегов шаблонов (базовые + по провайдерам + xfields).
 */
final class TemplateTagsPage extends AbstractPage {

	public function handle(): array {
		$this->addBreadcrumb(__('Теги шаблонов'));

		$meta = Application::instance()->registry()->forMod('repost')?->meta() ?? [];
		$docs = rtrim((string) ($meta['docsLink'] ?? 'https://readme.devcraft.club/dev/repost/'), '/') . '/';

		$baseTags  = new DefaultTemplateTags();
		$providers = [];

		foreach(ProviderRegistry::all() as $code => $info) {
			$provider = ProviderRegistry::get($code);

			if($provider === NULL) {
				continue;
			}

			$tags        = $provider->templateTags();
			$providers[] = [
				'code'             => $code,
				'title'            => (string) ($info['title'] ?? $code),
				'version'          => (string) ($info['version'] ?? ''),
				'hint_groups'      => $this->groupHints($tags->hints()),
				'html_tags'        => $tags->allowedHtmlTags(),
				'media_extensions' => $provider->allowedMediaExtensions(),
			];
		}

		return [
			'view' => 'repost/template_tags.twig',
			'data' => [
				'page_title'   => __('Теги шаблонов'),
				'docs_link'    => $docs,
				'docs_tags'    => $docs . 'template_tags/',
				'base_groups'  => $this->groupHints($baseTags->hints()),
				'providers'    => $providers,
				'xfield_hints' => $this->buildXfieldHints(),
			],
		];
	}

	/**
	 * @param   list<array{code?: string, tag?: string, descr?: string, group?: string}>  $hints
	 *
	 * @return array<string, list<array{code: string, tag: string, descr: string, group: string}>>
	 */
	private function groupHints(array $hints): array {
		$groups = [];

		foreach($hints as $hint) {
			$group            = (string) ($hint['group'] ?? 'other');
			$groups[$group][] = [
				'code'  => (string) ($hint['code'] ?? ''),
				'tag'   => (string) ($hint['tag'] ?? ''),
				'descr' => (string) ($hint['descr'] ?? ''),
				'group' => $group,
			];
		}

		return $groups;
	}

	/**
	 * @return list<array{code: string, tag: string, descr: string, group: string}>
	 */
	private function buildXfieldHints(): array {
		$hints = [];

		foreach(Application::instance()->dleData()->postXfields() as $name => $meta) {
			$key = is_string($name)? $name : (string) (is_array($meta)? ($meta['name'] ?? '') : '');

			if($key === '') {
				continue;
			}

			$label   = is_array($meta)? (string) ($meta['description'] ?? $key) : $key;
			$descr   = $label !== ''? $label : $key;
			$hints[] = [
				'code'  => '[xfvalue_' . $key . ']',
				'tag'   => 'xfvalue_' . $key,
				'descr' => $descr,
				'group' => 'xfields',
			];
			$hints[] = [
				'code'  => '[xfvalue_' . $key . '_text]',
				'tag'   => 'xfvalue_' . $key . '_text',
				'descr' => $descr . ' (текст без ссылок)',
				'group' => 'xfields',
			];
			$hints[] = [
				'code'  => '[xfvalue_' . $key . '_hashtag]',
				'tag'   => 'xfvalue_' . $key . '_hashtag',
				'descr' => $descr . ' (как хештеги)',
				'group' => 'xfields',
			];
		}

		return $hints;
	}

}
