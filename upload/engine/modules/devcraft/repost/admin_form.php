<?php

declare(strict_types=1);

if(!defined('DATALIFEENGINE')) {
	die('Hacking attempt!');
}

/**
 * Партиал HTML блока «Отложенная отправка» на форме новости в админке.
 *
 * Подключается через include из repostNewsFormHtmlAdmin() — переменные
 * $templates/$col1/$col2 берутся из области видимости вызывающей функции,
 * т.к. в админке нет $tpl (dle_template) для темизации.
 *
 * @var list<array{id:int,name:string,provider:string}> $templates
 * @var list<array{id:int,name:string,provider:string}> $col1
 * @var list<array{id:int,name:string,provider:string}> $col2
 */
?>
<div class="panel-body" id="repost-news-form">
	<label class="control-label col-md-2">RePost:</label>
	<div class="col-md-10">
		<div class="row">
			<div class="col-sm-6" style="max-width:21.67em;">
				<div class="text-muted text-size-small"><?php echo __('Отложенная отправка'); ?></div>
				<div class="radio"><label><input class="icheck" type="radio" name="repost_defer" value="yes"> <?php echo __('да'); ?></label></div>
				<div class="radio"><label><input class="icheck" type="radio" name="repost_defer" value="no"> <?php echo __('нет'); ?></label></div>
				<div class="radio"><label><input class="icheck" type="radio" name="repost_defer" value="template" checked> <?php echo __('настройка шаблона'); ?></label></div>
			</div>
			<div class="col-sm-6">
				<div class="text-muted text-size-small"><?php echo __('Выбор шаблона'); ?></div>
				<div class="radio"><label><input class="icheck" type="radio" name="repost_tpl_mode" value="auto" checked> <?php echo __('автоматически'); ?></label></div>
				<div class="radio"><label><input class="icheck" type="radio" name="repost_tpl_mode" value="manual"> <?php echo __('выбор из активных'); ?></label></div>
			</div>
		</div>
		<div class="row mt-15">
			<div class="col-sm-12">
				<span class="text-muted text-size-small position-left"><?php echo __('Время отправки'); ?>:</span>
				<input type="date" name="repost_plan_date" class="form-control" style="display:inline-block;width:auto;max-width:11em;">
				<input type="time" name="repost_plan_time" class="form-control" style="display:inline-block;width:auto;max-width:8em;">
				<span class="text-muted text-size-small"><?php echo __('пусто = сразу в очередь / due'); ?></span>
			</div>
		</div>
		<div class="row mt-15" id="repost_tpl_ids_wrap" style="display:none;">
			<div class="col-sm-6" style="max-width:21.67em;">
				<?php if($templates === []) { ?>
					<span class="text-muted text-size-small"><?php echo __('Нет активных шаблонов'); ?></span>
				<?php } else {
					foreach($col1 as $t) {
						$id   = (int) $t['id'];
						$name = htmlspecialchars((string) $t['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						$prov = htmlspecialchars((string) $t['provider'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
						echo '<div class="checkbox"><label><input class="icheck" type="checkbox" name="repost_tpl_ids[]" value="'
							. $id . '" disabled> #' . $id . ' — ' . $name
							. ' <span class="text-muted">(' . $prov . ')</span></label></div>';
					}
				} ?>
			</div>
			<div class="col-sm-6">
				<?php
				foreach($col2 as $t) {
					$id   = (int) $t['id'];
					$name = htmlspecialchars((string) $t['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
					$prov = htmlspecialchars((string) $t['provider'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
					echo '<div class="checkbox"><label><input class="icheck" type="checkbox" name="repost_tpl_ids[]" value="'
						. $id . '" disabled> #' . $id . ' — ' . $name
						. ' <span class="text-muted">(' . $prov . ')</span></label></div>';
				}
				?>
			</div>
		</div>
	</div>
</div>
