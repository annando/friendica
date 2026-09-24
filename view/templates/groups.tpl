{{*
  * Copyright (C) 2010-2026, the Friendica project
  * SPDX-FileCopyrightText: 2010-2026 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<div class="generic-page-wrapper">
	<h1>{{$title}}</h1>

	{{if !$groups}}
		<p>{{$no_groups}}</p>
	{{else}}
		<table id="group-overview" class="table">
			<thead>
				<tr>
					<th colspan="2">{{$group}}</th>
					<th class="group-overview-count">{{$posts}}</th>
					<th class="group-overview-count">{{$unread}}</th>
					<th>{{$latest}}</th>
				</tr>
			</thead>
			<tbody>
				{{foreach $groups as $group}}
				<tr>
					<td class="group-overview-avatar">
						<a href="contact/{{$group.id}}/conversations"><img src="{{$group.thumb}}" alt="{{$group.name}}"></a>
					</td>
					<td class="group-overview-title">
						<a href="{{$group.link}}"><strong>{{$group.name}}</strong></a>
						<div>{{$group.about}}</div>
					</td>
					<td class="group-overview-count" data-label="{{$posts}}">{{$group.posts}}</td>
					<td class="group-overview-count group-overview-unread" data-label="{{$unread}}">{{$group.unread}}</td>
					<td class="group-overview-latest">
						{{$group.latest nofilter}}
					</td>
				</tr>
				{{/foreach}}
			</tbody>
		</table>
	{{/if}}
</div>
