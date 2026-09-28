{{-- @formatter:off --}}
<hr style="border:none;border-top:1px solid #e5e7eb;margin:32px 0 0;">
<div style="margin-top:24px;">
<p style="font-weight:bold;color:#18181b;margin:0 0 12px;">{{ __('finisterre::finisterre.notification.history') }}</p>
@foreach ($entries as $entry)
<div style="margin:0 0 12px;padding:12px 14px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;">
<div style="font-size:13px;color:#6b7280;line-height:1.6;margin-bottom:6px;">
@if (filled($entry['author']))
<strong style="color:#374151;">{{ $entry['author'] }}</strong> &middot;
@endif
{{ $entry['date']?->format('d-m-y H:i') }}
</div>
<div style="font-size:14px;color:#111827;line-height:1.6;">
{!! $entry['body'] !!}
</div>
</div>
@endforeach
</div>
{{-- @formatter:on --}}
