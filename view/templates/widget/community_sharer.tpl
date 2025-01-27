{{*
  * Copyright (C) 2010-2024, the Friendica project
  * SPDX-FileCopyrightText: 2010-2024 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<span id="sidebar-community-no-sharer-inflated" class="widget inflated fakelink" onclick="openCloseWidget('sidebar-community-no-sharer', 'sidebar-community-no-sharer-inflated');" aria-expanded="false">
	<h3>{{$title}}</h3>
</span>
<div id="sidebar-community-no-sharer" class="widget">
	<span class="fakelink" onclick="openCloseWidget('sidebar-community-no-sharer', 'sidebar-community-no-sharer-inflated');" aria-expanded="true">
		<h3>{{$title}}</h3>
	</span>
	<nav>
	<ul class="sidebar-community-no-sharer-ul">
		<li role="menuitem" class="sidebar-community-no-sharer-li{{if !$no_sharer}} selected{{/if}}"><a href="{{$base}}/{{$path_all}}">{{$all}}</a></li>
		<li role="menuitem" class="sidebar-community-no-sharer-li{{if $no_sharer}} selected{{/if}}"><a href="{{$base}}/{{$path_no_sharer}}">{{$no_sharer_label}}</a></li>
	</ul>
	</nav>
</div>
<script>
initWidget('sidebar-community-no-sharer', 'sidebar-community-no-sharer-inflated');
</script>
