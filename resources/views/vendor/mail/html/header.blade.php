@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === config('app.name'))
<img src="https://res.cloudinary.com/drkspw0y/image/upload/w_600,q_auto,f_png/v1783845349/CorpersLink-logo-dark_onfswc.png" class="logo" width="280" height="81" alt="{{ config('app.name') }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
