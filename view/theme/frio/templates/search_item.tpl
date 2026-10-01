{{*
  * Copyright (C) 2010-2026, the Friendica project
  * SPDX-FileCopyrightText: 2010-2026 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<div id="item-{{$item.guid}}" class="item-{{$item.id}}">
	<span class="commented" style="display: none;">{{$item.commented}}</span>
	<span class="received" style="display: none;">{{$item.received}}</span>
	<span class="created" style="display: none;">{{$item.created_date}}</span>
	<span class="uriid" style="display: none;">{{$item.uriid}}</span>
	<div class="wall-item-container panel-body{{$item.indent}} {{$item.shiny}} {{$item.previewing}}">
		<div class="media">
			{{* Put additional actions in a top-right dropdown menu *}}

			{{* The avatar picture and the photo-menu *}}
			<div class="dropdown pull-left"><!-- Dropdown -->
				<div class="hidden-sm hidden-xs contact-photo-wrapper mframe{{if $item.owner_url}} wwfrom{{/if}}">
					<a href="{{$item.profile_url}}" class="userinfo click-card u-url" id="wall-item-photo-menu-{{$item.id}}">
						<div class="contact-photo-image-wrapper">
							<img src="{{$item.thumb}}" class="contact-photo media-object {{$item.sparkle}}" id="wall-item-photo-{{$item.id}}" alt="{{$item.name}}" loading="lazy"/>
						</div>
					</a>
				</div>
				<div class="hidden-lg hidden-md contact-photo-wrapper mframe{{if $item.owner_url}} wwfrom{{/if}}">
					<a href="{{$item.profile_url}}" class="userinfo click-card u-url" id="wall-item-photo-menu-xs-{{$item.id}}">
						<div class="contact-photo-image-wrapper">
							<img src="{{$item.thumb}}" class="contact-photo-xs media-object {{$item.sparkle}}" id="wall-item-photo-xs-{{$item.id}}" alt="{{$item.name}}" loading="lazy"/>
						</div>
					</a>
				</div>
			</div><!-- ./Dropdown -->


			{{* contact info header*}}
			<div class="contact-info hidden-sm hidden-xs media-body"><!-- Desktop -->
				<div class="preferences">
					{{if $item.network_svg && $item.plink}}
						<span class="wall-item-network"><a href="{{$item.plink.href}}" class="plink u-url" target="_blank"><img class="network-svg" src="{{$item.network_svg}}" alt="{{$item.plink.title}}" title="{{$item.network_name}} - {{$item.plink.title}}" loading="lazy"/></a></span>
					{{elseif $item.plink}}
						<a href="{{$item.plink.href}}" class="plink u-url" aria-label="{{$item.plink.title}}" title="{{$item.network_name}} - {{$item.plink.title}}" target="_blank">{{$item.network_name}}</a>
					{{elseif $item.network_svg}}
						<span class="wall-item-network"><img class="network-svg" src="{{$item.network_svg}}" title="{{$item.network_name}}" loading="lazy" aria-hidden="true"/></span>
					{{else}}
						<span class="wall-item-network" title="{{$item.app}}">{{$item.network_name}}</span>
					{{/if}}
				</div>
				<h2 class="media-heading">
					<a href="{{$item.profile_url}}" title="{{$item.linktitle}}" class="wall-item-name-link userinfo hover-card">
						<span class="wall-item-name {{$item.sparkle}}">{{$item.name}}</span>
					</a>
				{{if $item.owner_url}}
					{{$item.via}}
					<a href="{{$item.owner_url}}" target="redir" title="{{$item.olinktitle}}" class="wall-item-name-link userinfo hover-card">
						<span class="wall-item-name {{$item.osparkle}}" id="wall-item-ownername-{{$item.id}}">{{$item.owner_name}}</span>
					</a>
				{{/if}}
					<div class="additional-info text-muted">
						<div id="wall-item-ago-{{$item.id}}" class="wall-item-ago">
							<small>
								{{if $item.inreplyto_url}}
									<a class="time" href="{{$item.inreplyto_url}}"><i class="ri ri-reply-line" aria-hidden="true"></i> {{$item.inreplyto}}</a>
									<span aria-hidden="true">&bull;</span>
								{{/if}}
								<a href="{{$item.plink.orig}}">
									<time class="time" title="{{$item.localtime}}" data-toggle="tooltip" datetime="{{$item.utc}}">{{$item.ago}}</time>
								</a>
								{{if $item.direction}}
									{{include file="sub/direction.tpl" direction=$item.direction}}
								{{/if}}
								{{if $item.pinned}}
									<span aria-hidden="true">&bull;</span> <i class="ri ri-pushpin-line" aria-hidden="true" title="{{$item.pinned}}"></i>
									<span class="sr-only">{{$item.pinned}}</span>
								{{/if}}
								{{if $item.connector}}
									<span aria-hidden="true">&bull;</span>
									<i class="ri ri-plug-line" title="{{$item.connector}}" aria-hidden="true"></i>
								{{elseif $item.privacy}}
									<span aria-hidden="true">&bull;</span>
									<span class="navicon lock fakelink" onClick="lockview(event, 'item', {{$item.id}});" title="{{$item.privacy}}" data-toggle="tooltip">
										<i class="ri {{if $item.private == 1}}ri-lock-line{{elseif $item.private == 0}}ri-global-line{{else}}ri-eye-off-line{{/if}}" aria-hidden="true"></i>
									</span>
								{{/if}}
							</small>
						</div>

						{{if $item.location_html}}
						<div id="wall-item-location-{{$item.id}}" class="wall-item-location">
							<small><span class="location">({{$item.location_html nofilter}})</span></small>
						</div>
						{{/if}}
					</div>
				{{* @todo $item.created have to be inserted *}}
				</h2>
			</div>

			{{* contact info header for smartphones *}}
			<div class="contact-info contact-info-xs hidden-lg hidden-md">
				<div class="preferences">
					{{if $item.network_svg && $item.plink}}
						<span class="wall-item-network"><a href="{{$item.plink.href}}" class="plink u-url" target="_blank"><img class="network-svg" src="{{$item.network_svg}}" alt="{{$item.plink.title}}" title="{{$item.network_name}} - {{$item.plink.title}}" loading="lazy"/></a></span>
					{{elseif $item.plink}}
						<a href="{{$item.plink.href}}" class="plink u-url" aria-label="{{$item.plink.title}}" title="{{$item.network_name}} - {{$item.plink.title}}" target="_blank">{{$item.network_name}}</a>
					{{elseif $item.network_svg}}
						<span class="wall-item-network"><img class="network-svg" src="{{$item.network_svg}}" alt="{{$item.plink.title}}" title="{{$item.network_name}} - {{$item.plink.title}}" loading="lazy"/></span>
					{{else}}
						<span class="wall-item-network" title="{{$item.app}}">{{$item.network_name}}</span>
					{{/if}}
				</div>
				<h5 class="media-heading">
					<a href="{{$item.profile_url}}" title="{{$item.linktitle}}" class="wall-item-name-link userinfo hover-card"><span>{{$item.name}}</span></a>
					<p class="text-muted"><small>
						{{if $item.inreplyto_url}}<a class="time" href="{{$item.inreplyto_url}}"><i class="ri ri-reply-line" aria-hidden="true"></i> {{$item.inreplyto}}</a> <span aria-hidden="true">&bull;</span>{{/if}}
						<span class="wall-item-ago">{{$item.ago}}</span> {{if $item.location_html}}&nbsp;&mdash;&nbsp;({{$item.location_html nofilter}}){{/if}}
						{{if $item.direction}}
							{{include file="sub/direction.tpl" direction=$item.direction}}
						{{/if}}
						{{if $item.connector}}
							<span aria-hidden="true">&bull;</span> <i class="ri ri-plug-line" title="{{$item.connector}}" aria-hidden="true"></i>
						{{elseif $item.privacy}}
							<span aria-hidden="true">&bull;</span>
							<span class="navicon lock fakelink" onClick="lockview(event, 'item', {{$item.id}});" title="{{$item.privacy}}" data-toggle="tooltip">
								<i class="ri {{if $item.private == 1}}ri-lock-line{{elseif $item.private == 0}}ri-global-line{{else}}ri-eye-off-line{{/if}}" aria-hidden="true"></i>
							</span>
						{{/if}}</small>
					</p>
				</h5>
			</div>

			<div class="clearfix"></div>

			<hr />

			{{* item content *}}
			<article class="wall-item-content {{$item.type}}{{if $click_to_display}} click-to-display{{/if}}" id="wall-item-content-{{$item.id}}" lang="{{$item.lang}}" aria-posinset="{{$item.id}}" aria-setsize="-1"{{if $click_to_display}} onclick="clickToDisplay(event, '{{$item.plink.orig}}');"{{/if}}>
				{{if $item.title}}
				<span class="wall-item-title" id="wall-item-title-{{$item.id}}"><h3 class="media-heading" dir="auto"><a href="{{$item.plink.href}}" class="{{$item.sparkle}}">{{$item.title}}</a></h3><br /></span>
				{{/if}}

				<div class="wall-item-body" id="wall-item-body-{{$item.id}}" dir="auto">{{$item.body_html nofilter}}</div>
			</article>

			<!-- TODO -->
			<div class="wall-item-bottom">
				<div class="wall-item-links"></div>
				<div class="tags wall-item-tags">
			{{if !$item.suppress_tags}}
				{{foreach $item.hashtags as $tag}}
					<span class="tag hashtag label border border-default">{{$tag nofilter}}</span>
				{{/foreach}}

				{{foreach $item.mentions as $tag}}
					<span class="tag mention label border border-primary">{{$tag nofilter}}</span>
				{{/foreach}}
			{{/if}}

      {{* No implicit mentions unlike wall_thread? *}}

				{{foreach $item.folders as $cat}}
					<span class="tag folder label border border-success"><a href="{{$cat.url}}">{{$cat.name}}</a> {{if $cat.removeurl}}<a class="filerm" href="{{$cat.removeurl}}" title="{{$remove}}"><i class="ri ri-close-circle-line"></i></a>{{/if}}</span>
				{{/foreach}}

				{{foreach $item.categories as $cat}}
					<span class="tag category label border border-danger"><a href="{{$cat.url}}">{{$cat.name}}</a> {{if $cat.removeurl}}<a class="filerm" href="{{$cat.removeurl}}" title="{{$remove}}"><i class="ri ri-close-circle-line"></i></a>{{/if}}</span>
				{{/foreach}}
				</div>
				{{if $item.edited}}<div class="itemedited text-muted">{{$item.edited['label']}} (<span title="{{$item.edited['date']}}">{{$item.edited['relative']}}</span>)</div>{{/if}}
			</div>
			<!-- ./TODO -->

			<div class="wall-item-actions">
			<div class="wall-item-actions-items btn-toolbar btn-group" role="group">
				<div class="wall-item-actions-row">
				{{* Link to the full post, where the comments are shown *}}
				<a href="{{$item.plink.orig}}" class="btn button-comments" id="comment-{{$item.id}}" title="{{$item.switchcomment}}">
					<i class="ri ri-chat-3-line" aria-hidden="true"></i>
					<span class="total" title="{{$item.responses.comment.title}}">{{$item.responses.comment.total}}</span>
					<span class="action-label">{{$item.switchcomment}}</span>
				</a>

				{{if $item.vote.announce OR $item.vote.share}}
					<div class="share-links btn-group" role="group">
						<button type="button" class="btn dropdown-toggle{{if $item.responses.announce.self}} active{{/if}}" data-toggle="dropdown" id="shareMenuOptions-{{$item.id}}" aria-haspopup="true" aria-expanded="false" title="{{$item.menu}}">
							<i class="ri ri-share-forward-line" aria-hidden="true"></i>
							{{if $item.responses.announce.total}}
							{{assign var=shares value=$item.responses.announce.total}}
							{{else}}
							{{assign var=shares value=0}}
							{{/if}}
							{{if $item.quoteshares.total}}
							{{assign var=quotes value=$item.quoteshares.total}}
							{{else}}
							{{assign var=quotes value=0}}
							{{/if}}
							<span class="total" title="{{$item.responses.announce.title}} {{$item.responses.quoteshares.title}}">{{if $quotes+$shares > 0}}{{$quotes+$shares}}{{/if}}</span>
							<span class="action-label">{{$item.vote.announce.1}}</span>
						</button>
						<ul class="dropdown-menu dropdown-menu-left" role="menu" aria-labelledby="shareMenuOptions-{{$item.id}}">
							{{if $item.vote.announce}} {{* edit the posting *}}
							<li role="menuitem">
								{{if $item.responses.announce.self}}
								<a class="btn-link" id="announce-{{$item.id}}" href="javascript:doActivityItemAction({{$item.id}}, 'announce', true);" title="{{$item.vote.unannounce.0}}">
									<i class="ri ri-forbid-2-line" aria-hidden="true"></i> {{$item.vote.unannounce.1}}
								</a>
								{{else}}
								<a class="btn-link" id="announce-{{$item.id}}" href="javascript:doActivityItemAction({{$item.id}}, 'announce');" title="{{$item.vote.announce.0}}">
									<i class="ri ri-repeat-line" aria-hidden="true"></i> {{$item.vote.announce.1}}
								</a>
								{{/if}}
							</li>
							{{/if}}
							{{if $item.vote.share}}
							<li role="menuitem">
								<a class="btn-link" id="share-{{$item.id}}" href="javascript:jotShare({{$item.id}});" title="{{$item.vote.share.0}}">
									<i class="ri ri-double-quotes-r" aria-hidden="true"></i> {{$item.vote.share.1}}
								</a>
							</li>
							{{/if}}
							{{if $item.browsershare}}
							<li role="menuitem">
								<button type="button" class="btn-link button-browser-share" onclick="navigator.share({url: '{{$item.plink.orig}}'})" title="{{$item.browsershare.1}}">
									<i class="ri ri-share-line" aria-hidden="true"></i> {{$item.browsershare.0}}
								</button>
							</li>
							{{/if}}
						</ul>
					</div>
				{{/if}}

				{{* Buttons for like and dislike *}}
				{{if $item.vote}}
					{{if $item.vote.like}}
					<button type="button" class="btn button-likes{{if $item.responses.like.self}} active" aria-pressed="true{{/if}}" id="like-{{$item.id}}" title="{{$item.vote.like.0}}" onclick="doActivityItemAction({{$item.id}}, 'like'{{if $item.responses.like.self}}, true{{/if}});" >
						<i class="ri ri-thumb-up-line" aria-hidden="true"></i>
						<span class="total" title="{{$item.responses.like.title}}">{{$item.responses.like.total}}</span>
						<span class="action-label">{{$item.vote.like.1}}</span>
					</button>
					{{/if}}
					{{if $item.vote.dislike}}
					<button type="button" class="btn button-likes{{if $item.responses.dislike.self}} active" aria-pressed="true{{/if}}" id="dislike-{{$item.id}}" title="{{$item.vote.dislike.0}}" onclick="doActivityItemAction({{$item.id}}, 'dislike'{{if $item.responses.dislike.self}}, true{{/if}});" >
						<i class="ri ri-thumb-down-line" aria-hidden="true"></i>
						<span class="total" title="{{$item.responses.dislike.title}}">{{$item.responses.dislike.total}}</span>
						<span class="action-label">{{$item.vote.dislike.1}}</span>
					</button>
					{{/if}}
				{{/if}}

				{{* Put additional actions in a dropdown menu *}}
				{{if $item.edpost || $item.tagger || $item.filer || $item.pin || $item.star || $item.follow_thread || $item.ignore || ($item.drop && $item.drop.dropping)}}
					<div class="more-links btn-group">
						<button type="button" class="btn dropdown-toggle" data-toggle="dropdown" id="dropdownMenuOptions-{{$item.id}}" aria-haspopup="true" aria-expanded="false" title="{{$item.menu}}">
							<i class="ri ri-more-line" aria-hidden="true"></i>
							<span class="action-label">{{$item.menu}}</span>
						</button>
						<ul class="dropdown-menu dropdown-menu-right" role="menu" aria-labelledby="dropdownMenuOptions-{{$item.id}}">
							{{if $item.edpost}} {{* edit the posting *}}
							<li role="menuitem">
								<a href="javascript:editpost('{{$item.edpost.0}}?mode=none');" title="{{$item.edpost.1}}" class="btn-link navicon pencil"><i class="ri ri-pencil-line" aria-hidden="true"></i>&ensp;{{$item.edpost.1}}</a>
							</li>
							{{/if}}

							{{* Available: On your own posts *}}
							{{if $item.pin}}
								<li role="menuitem">
									<a id="pin-{{$item.id}}" href="javascript:doPin({{$item.id}});" class="btn-link {{$item.pin.classdo}}" title="{{$item.pin.do}}"><i class="ri ri-pushpin-line" aria-hidden="true"></i>&ensp;{{$item.pin.do}}</a>
									<a id="unpin-{{$item.id}}" href="javascript:doPin({{$item.id}});" class="btn-link {{$item.pin.classundo}}" title="{{$item.pin.undo}}"><i class="ri ri-pushpin-line" aria-hidden="true"></i>&ensp;{{$item.pin.undo}}</a>
								</li>
							{{/if}}

							{{* Available: On your own posts *}}
							{{* TODO: This currently creates a duplicate post according to: https://forum.friendi.ca/display/373ebf56-2469-ed09-ccc0-ecd539065051 *}}
							{{if $item.tagger}} {{* tag the post *}}
								<li role="menuitem">
									<a id="tagger-{{$item.id}}" href="javascript:itemTag({{$item.id}});" class="btn-link {{$item.tagger.class}}" title="{{$item.tagger.add}}"><i class="ri ri-hashtag" aria-hidden="true"></i>&ensp;{{$item.tagger.add}}</a>
								</li>
							{{/if}}

							{{* Available: On posts made by anyone ("star" changed to "bookmark") *}}
							{{if $item.star}}
								<li role="menuitem">
									<a id="star-{{$item.id}}" href="javascript:doStar({{$item.id}});" class="btn-link {{$item.star.classdo}}" title="{{$item.star.do}}"><i class="ri ri-bookmark-line" aria-hidden="true"></i>&ensp;{{$item.star.do}}</a>
									<a id="unstar-{{$item.id}}" href="javascript:doStar({{$item.id}});" class="btn-link {{$item.star.classundo}}" title="{{$item.star.undo}}"><i class="ri ri-bookmark-fill" aria-hidden="true"></i>&ensp;{{$item.star.undo}}</a>
								</li>
							{{/if}}

							{{* Available: On all posts and comments *}}
							{{if $item.filer}}
								<li role="menuitem">
									<a id="filer-{{$item.id}}" href="javascript:itemFiler({{$item.id}});" class="btn-link filer-item filer-icon" title="{{$item.filer}}"><i class="ri ri-folder-line" aria-hidden="true"></i>&ensp;{{$item.filer}}</a>
								</li>
							{{/if}}

							{{* Available: On (some) posts and comments? *}}
							{{* Not supported by Firefox desktop as of 2026-05 *}}
							{{* Requires HTTPS and requires that the permission is enabled *}}
							{{* TODO: Add the relevant checks so this is only available/clickable when on a device where its supported *}}
							{{if $item.browsershare}}
							<li role="menuitem" class="button-browser-share">
									<a id="browser-share-{{$item.id}}" href="javascript:navigator.share({url: '{{$item.plink.orig}}'})" class="btn-link button-browser-share" title="{{$item.browsershare.1}}"><i class="ri ri-share-line" aria-hidden="true"></i>&ensp;{{$item.browsershare.0}}</a>
							</li>
							{{/if}}

							{{* Available: On posts made by others *}}
							{{* Identical to unignore in functionality but only seen on posts you haven't liked/commented on - could they be merged? *}}
							{{if $item.follow_thread}}
								<li role="menuitem">
									<a id="follow_thread-{{$item.id}}" href="javascript:{{$item.follow_thread.action}}" class="btn-link" title="{{$item.follow_thread.title}}"><i class="ri ri-notification-3-line" aria-hidden="true"></i>&ensp;{{$item.follow_thread.title}}</a>
								</li>
							{{/if}}

							{{* Available: On all posts *}}
							{{if $item.ignore}}
								<li role="menuitem">
									<a id="ignore-{{$item.id}}" href="javascript:doIgnoreThread({{$item.id}});" class="btn-link {{$item.ignore.classdo}}" title="{{$item.ignore.do}}"><i class="ri ri-notification-off-line" aria-hidden="true"></i>&ensp;{{$item.ignore.do}}</a>
								</li>
								<li role="menuitem">
									<a id="unignore-{{$item.id}}" href="javascript:doIgnoreThread({{$item.id}});" class="btn-link {{$item.ignore.classundo}}"  title="{{$item.ignore.undo}}"><i class="ri ri-notification-3-line" aria-hidden="true"></i>&ensp;{{$item.ignore.undo}}</a>
								</li>
							{{/if}}

							{{* Available: On posts made by others *}}
							{{if $item.complete_thread}}
								<li role="menuitem">
									<a id="complete_thread-{{$item.id}}" href="javascript:{{$item.complete_thread.action}}" class="btn-link" title="{{$item.complete_thread.title}}"><i class="ri ri-download-line" aria-hidden="true"></i>&ensp;{{$item.complete_thread.title}}</a>
								</li>
							{{/if}}

							<li class="divider"><hr></li>

							{{* Available: On posts made by others *}}
							{{if $item.collapse}}
								<li role="menuitem">
									<a class="btn-link navicon collapse" href="javascript:collapseAuthor('item/collapse/{{$item.id}}', 'item-{{$item.guid}}');" title="{{$item.collapse.label}}"><i class="ri ri-subtract-line" aria-hidden="true"></i>&ensp;{{$item.collapse.label}}</a>
								</li>
							{{/if}}

							{{* Available: On posts made by others *}}
							{{if $item.ignore_author}}
								<li role="menuitem">
									<a class="btn-link navicon ignore" href="javascript:ignoreAuthor('item/ignore/{{$item.id}}', 'item-{{$item.guid}}');" title="{{$item.ignore_author.label}}"><i class="ri ri-eye-off-line" aria-hidden="true"></i>&ensp;{{$item.ignore_author.label}}</a>
								</li>
							{{/if}}

							{{* Available: On posts made by others *}}
							{{if $item.block}}
								<li role="menuitem">
									<a class="btn-link navicon block" href="javascript:blockAuthor('item/block/{{$item.id}}', 'item-{{$item.guid}}');" title="{{$item.block.label}}"><i class="ri ri-forbid-2-line" aria-hidden="true"></i>&ensp;{{$item.block.label}}</a>
								</li>
							{{/if}}

							{{if $item.collapse || $item.ignore_author || $item.block }}
								<li class="divider"><hr></li>
							{{/if}}

							{{* Available: On posts made by others *}}
							{{if $item.ignore_server}}
								<li role="menuitem">
									<a class="btn-link navicon ignoreServer" href="javascript:ignoreServer('settings/server/{{$item.author_gsid}}/ignore', 'item-{{$item.guid}}');" title="{{$item.ignore_server.label}}"><i class="ri ri-eye-off-line" aria-hidden="true"></i>&ensp;{{$item.ignore_server.label}}</a>
								</li>
							{{/if}}

							{{* Available: On all posts and comments *}}
							<li role="menuitem">
								<a id="searchtext-{{$item.id}}" href="javascript:displaySearchText({{$item.uriid}});" class="btn-link filer-item" title="{{$item.searchtext}}"><i class="ri ri-file-text-line" aria-hidden="true"></i>&ensp;{{$item.searchtext}}</a>
							</li>

							{{* Available: On all posts and comments *}}
							{{if $item.language}}
								<li role="menuitem">
									<a id="language-{{$item.id}}" href="javascript:displayLanguage({{$item.uriid}});" class="btn-link filer-item" title="{{$item.language}}"><i class="ri ri-translate-2" aria-hidden="true"></i>&ensp;{{$item.language}}</a>
								</li>
							{{/if}}

							{{* Available: On all posts and comments made by others *}}
							{{if $item.report}}
								<li role="menuitem">
									<a class="btn-link navicon ignore" href="{{$item.report.href}}"><i class="ri ri-flag-line" aria-hidden="true"></i>&ensp;{{$item.report.label}}</a>
								</li>
							{{/if}}

							{{* Available: If you're an admin, on all posts and comments on your instance *}}
							{{if $item.drop && $item.drop.dropping}}
								<li role="menuitem">
                                	<a class="btn-link navicon delete" href="javascript:dropItem('item/drop/{{$item.id}}', 'item-{{$item.guid}}');" title="{{$item.drop.label}}"><i class="ri ri-delete-bin-line" aria-hidden="true"></i>&ensp;{{$item.drop.label}}</a>
								</li>
							{{/if}}
						</ul>
					</div>
				{{/if}}
				<span class="wall-item-actions-right">
					{{* Event attendance buttons *}}
				{{if $item.isevent}}
					<span class="vote-event">
						<button type="button" class="btn btn-default button-event{{if $item.responses.attendyes.self}} active" aria-pressed="true{{/if}}" id="attendyes-{{$item.id}}" title="{{$item.attend.0}}" onclick="doActivityItemAction({{$item.id}}, 'attendyes'{{if $item.responses.attendyes.self}}, true{{/if}});"><i class="ri ri-check-line" aria-hidden="true"><span class="sr-only">{{$item.attend.0}}</span></i></button>
						<button type="button" class="btn btn-default button-event{{if $item.responses.attendno.self}} active" aria-pressed="true{{/if}}" id="attendno-{{$item.id}}" title="{{$item.attend.1}}" onclick="doActivityItemAction({{$item.id}}, 'attendno'{{if $item.responses.attendno.self}}, true{{/if}});"><i class="ri ri-close-line" aria-hidden="true"><span class="sr-only">{{$item.attend.1}}</span></i></button>
						<button type="button" class="btn btn-default button-event{{if $item.responses.attendmaybe.self}} active" aria-pressed="true{{/if}}" id="attendmaybe-{{$item.id}}" title="{{$item.attend.2}}" onclick="doActivityItemAction({{$item.id}}, 'attendmaybe'{{if $item.responses.attendmaybe.self}}, true{{/if}});"><i class="ri ri-question-line" aria-hidden="true"><span class="sr-only">{{$item.attend.2}}</span></i></button>
					</span>
				{{/if}}

					<span class="pull-right checkbox">
				{{if $item.drop && $item.drop.pagedrop}}
						<input type="checkbox" title="{{$item.drop.select}}" name="itemselected[]" id="checkbox-{{$item.id}}" class="item-select" value="{{$item.id}}" />
						<label for="checkbox-{{$item.id}}"></label>
				{{/if}}
					</span>
				</span>
				</div>
			</div>
			</div><!--./wall-item-actions-->

			<div class="wall-emoji-responses">
				{{foreach $item.reactions as $emoji}}
					{{if $emoji.icon.fa}}
						<span class="wall-item-emoji" title="{{$emoji.title}}"><i class="ri {{$emoji.icon.fa}}" aria-hidden="true"></i> {{$emoji.total}}</span>
					{{else}}
						<span class="wall-item-emoji" title="{{$emoji.title}}">{{$emoji.emoji}} {{$emoji.total}}</span>
					{{/if}}
				{{/foreach}}
			</div>

		</div><!--./media>-->
	</div><!--./scrollable-->
</div><!-- ./panel-body -->
