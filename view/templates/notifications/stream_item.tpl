<div class="notification-stream-item notification-stream-{{$type}} tread-wrapper panel-default panel">
	{{if $text}}
	<div class="notification-stream-header">
		<i class="ri {{$icon}}" aria-hidden="true"></i>
		<img src="{{$avatar}}" alt="" width="24" height="24" loading="lazy">
		<span>{{$text nofilter}}</span>
	</div>
	{{/if}}
	{{$post nofilter}}
</div>
