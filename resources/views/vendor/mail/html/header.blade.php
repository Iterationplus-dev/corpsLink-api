@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === config('app.name'))
<img src="https://res.cloudinary.com/drkspw0y/image/upload/w_360,q_auto,f_png/v1783845349/CorpersLink-logo-dark_onfswc.png" class="logo" width="180" height="52" alt="{{ config('app.name') }}">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
