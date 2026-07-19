<div id="repost-news-form">
	<label>RePost</label>
	<div style="margin:0.5em 0;">
		<span class="grey">{repost-defer-label}:</span>
		<label style="display:inline;margin-right:0.75em;"><input type="radio" name="repost_defer" value="yes"> {repost-yes-label}</label>
		<label style="display:inline;margin-right:0.75em;"><input type="radio" name="repost_defer" value="no"> {repost-no-label}</label>
		<label style="display:inline;"><input type="radio" name="repost_defer" value="template" checked> {repost-tpl-mode-label}</label>
	</div>
	<div style="margin:0.5em 0;">
		<span class="grey">{repost-time-label}:</span>
		<input type="date" name="repost_plan_date" class="wide" style="width:auto;max-width:11em;display:inline-block;">
		<input type="time" name="repost_plan_time" class="wide" style="width:auto;max-width:8em;display:inline-block;">
		<span class="grey">{repost-time-hint}</span>
	</div>
	<div style="margin:0.5em 0;">
		<span class="grey">{repost-select-tpl-label}:</span>
		<label style="display:inline;margin-right:0.75em;"><input type="radio" name="repost_tpl_mode" value="auto" checked> {repost-auto-label}</label>
		<label style="display:inline;"><input type="radio" name="repost_tpl_mode" value="manual"> {repost-manual-label}</label>
	</div>
	<div id="repost_tpl_ids_wrap" style="display:none;margin:0.5em 0;">
		{repost-templates-block}
	</div>
</div>
