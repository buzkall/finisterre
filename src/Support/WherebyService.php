<?php

namespace Arzcode\Finisterre\Support;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provisions the video call link for a scheduled event.
 *
 * With a Whereby Embedded API key configured, a meeting room is created
 * through their HTTP API and can be embedded in the public event page.
 * Without one (or when the API call fails), the configured fallback room URL
 * is used and attendees get a plain join link.
 */
class WherebyService
{
    protected const API_URL = 'https://api.whereby.dev/v1/meetings';

    public function assignVideoCall(FinisterreEvent $event): void
    {
        $apiKey = config('finisterre.events.whereby.api_key');

        if (filled($apiKey) && $event->scheduled_start_at && $event->scheduled_end_at) {
            $meeting = $this->createMeeting($event, $apiKey);

            if ($meeting !== null) {
                $event->video_call_url = $meeting['roomUrl'];
                $event->whereby_meeting_id = $meeting['meetingId'];

                return;
            }
        }

        $fallback = config('finisterre.events.whereby.fallback_room_url');

        if (filled($fallback)) {
            $event->video_call_url = $fallback;
        }
    }

    /** @return array{roomUrl: string, meetingId: string}|null */
    protected function createMeeting(FinisterreEvent $event, string $apiKey): ?array
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post(self::API_URL, [
                    'roomNamePrefix' => 'finisterre',
                    'roomMode'       => 'normal',
                    'startDate'      => $event->scheduled_start_at->toIso8601String(),
                    'endDate'        => $event->scheduled_end_at->toIso8601String(),
                ]);

            if ($response->successful() && filled($response->json('roomUrl'))) {
                return [
                    'roomUrl'   => (string)$response->json('roomUrl'),
                    'meetingId' => (string)$response->json('meetingId'),
                ];
            }

            Log::warning('Whereby meeting creation failed', [
                'event_id' => $event->getKey(),
                'status'   => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Whereby meeting creation failed', [
                'event_id' => $event->getKey(),
                'error'    => $e->getMessage(),
            ]);
        }

        return null;
    }
}
