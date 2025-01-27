{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<span id="{{$type}}-sidebar-inflated" class="widget inflated fakelink" aria-expanded="false" onclick="openCloseWidget('{{$type}}-sidebar', '{{$type}}-sidebar-inflated');">
	<h3>{{$title}}</h3>
</span>
<nav id="{{$type}}-sidebar" class="widget">
	<span class="fakelink" aria-expanded="true" onclick="openCloseWidget('{{$type}}-sidebar', '{{$type}}-sidebar-inflated');">
		<h3>{{$title}}</h3>
	</span>
	<div id="{{$type}}-desc">{{$desc nofilter}}</div>
	<ul class="{{$type}}-ul">
		{{if $all_label}}
		<li {{if !is_null($selected) && !$selected}}class="selected"{{/if}}><a href="{{$base}}" class="{{$type}}-link{{if !$selected}} {{$type}}-selected{{/if}} {{$type}}-all">{{$all_label}}</a></li>
		{{/if}}
		{{foreach $options as $option}}
			<li {{if $selected == $option.ref}}class="selected"{{/if}}><a href="{{$base}}{{$type}}={{$option.ref}}" class="{{$type}}-link{{if $selected == $option.ref}} {{$type}}-selected{{/if}}">{{$option.name}}</a></li>
		{{/foreach}}
	</ul>
</nav>
<script>
initWidget('{{$type}}-sidebar', '{{$type}}-sidebar-inflated');
</script>
