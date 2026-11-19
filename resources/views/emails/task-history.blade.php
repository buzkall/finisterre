{{-- @formatter:off --}}
{{-- Each entry folds with <details>: the newest one is open, older ones show a one-line summary. Clients
     without <details> support (Gmail, Outlook) render every entry expanded --}}
<hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 0;">
<div style="margin-top:24px;">
<p style="font-weight:bold;color:#18181b;margin:0 0 12px;">{{ __('finisterre::finisterre.notification.history') }}</p>
@foreach ($entries as $entry)
<details{{ $loop->first ? ' open' : '' }} style="margin:0 0 12px;padding:12px 14px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
<summary style="cursor:pointer;font-size:13px;color:#6b7280;line-height:1.6;">
@if (filled($entry['author']))
<strong style="color:#374151;">{{ $entry['author'] }}</strong> &middot;
@endif
{{ $entry['date']?->format('d-m-y H:i') }}
@if (! $loop->first && filled($entry['excerpt']))
&mdash; <span style="color:#9ca3af;">{{ $entry['excerpt'] }}</span>
@endif
</summary>
<div style="font-size:14px;color:#111827;line-height:1.6;margin-top:6px;">
{!! $entry['body'] !!}
</div>
</details>
@endforeach
</div>
{{-- @formatter:on --}}
