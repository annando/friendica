<div id="notification-{{$id}}" data-notification-id="{{$id}}" class="notification-stream-item notification-stream-{{$type}} tread-wrapper panel-default panel">
	{{if $text}}
	<div class="notification-stream-header">
		{{if $emoji}}<span class="notification-stream-emoji" aria-hidden="true">{{$emoji}}</span>{{else}}<i class="ri {{$icon}}" aria-hidden="true"></i>{{/if}}
		<img src="{{$avatar}}" alt="" width="24" height="24" loading="lazy">
		<span>{{$text nofilter}}</span>
	</div>
	{{/if}}
	{{$post nofilter}}
</div>
