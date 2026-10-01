{{-- @formatter:off --}}
{{-- Laravel's notifications::email with the panel's brand logo in the header instead of the app name --}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
{{-- Where a reply by email is cut: everything from here down is the quoted email. It opens the
     email, above the logo, so in a reply it sits right under what the person is writing --}}
@if ($acceptsReplies ?? false)
<tr>
<td id="finisterre-reply-above" align="center" style="padding: 12px 16px; border-bottom: 1px dashed #d1d5db; color: #9ca3af; font-size: 12px; line-height: 1.5; text-align: center;">{{ __('finisterre::finisterre.mail.reply_above') }}</td>
</tr>
@endif
<x-mail::header :url="config('app.url')">
@if (filled($logo ?? null))
<img src="{{ $logo }}" alt="{{ config('app.name') }}" style="max-height: 60px; max-width: 220px; height: auto; width: auto;">
@else
{{ config('app.name') }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
<x-mail::subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
{{-- @formatter:on --}}
