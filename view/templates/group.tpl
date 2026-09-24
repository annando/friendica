{{*
  * Copyright (C) 2010-2026, the Friendica project
  * SPDX-FileCopyrightText: 2010-2026 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<div class="generic-page-wrapper">
	<form method="post" action="group/{{$id}}" up-submit>
		<input type="hidden" name="form_security_token" value="{{$form_token}}">
		<p>
			<a href="groups" class="btn btn-default"><i class="ri ri-arrow-left-line" aria-hidden="true"></i> {{$back}}</a>
			<button type="submit" class="btn btn-default"><i class="ri ri-check-double-line" aria-hidden="true"></i> {{$mark_seen}}</button>
		</p>
	</form>
	{{$editor nofilter}}
	<h1><a href="contact/{{$cid}}/conversations">{{$title}}</a></h1>

	<div id="group-content" data-reload-after-post>
		{{if !$threads}}
			<p>{{$no_threads}}</p>
		{{else}}
			<table id="group-threads" class="table">
				<thead>
					<tr>
						<th colspan="2">{{$thread}}</th>
						<th class="group-overview-count">{{$comments}}</th>
						<th class="group-overview-count">{{$unread}}</th>
						<th>{{$latest}}</th>
					</tr>
				</thead>
				<tbody>
					{{foreach $threads as $thread}}
					<tr>
						<td class="group-overview-avatar">
							<a href="{{$thread.link}}"><img src="{{$thread.thumb}}" alt="{{$thread.author}}"></a>
						</td>
						<td class="group-overview-title">
							<a href="display/{{$thread.guid}}">{{if $thread.unseen}}<strong>{{$thread.title}}</strong>{{else}}{{$thread.title}}{{/if}}</a>
							<div><a href="{{$thread.link}}">{{$thread.author}}</a>, {{$thread.created}}</div>
						</td>
						<td class="group-overview-count" data-label="{{$comments}}">{{$thread.comments}}</td>
						<td class="group-overview-count group-overview-unread" data-label="{{$unread}}">{{$thread.unread}}</td>
						<td class="group-overview-latest">
							{{$thread.latest nofilter}}
						</td>
					</tr>
					{{/foreach}}
				</tbody>
			</table>
		{{/if}}

		{{$paginate nofilter}}
	</div>
</div>
