<?php

namespace Arzcode\Finisterre\Controllers;

use Arzcode\Finisterre\Support\InboundEmail\AuthenticationResults;
use Arzcode\Finisterre\Support\InboundEmail\InboundMessage;
use Arzcode\Finisterre\Support\InboundEmail\InboundMessageHandler;
use Arzcode\Finisterre\Support\Typed;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * The Resend driver of reply by email. Resend posts an `email.received` event for
 * every email its receiving domain gets; the event only carries the envelope, so
 * the body and headers are fetched from its API before the reply becomes a comment.
 */
class ResendInboundController extends Controller
{
    public const PATH = 'finisterre/inbound/resend';

    /** How old a signed request may be, in seconds, before it's taken for a replay. */
    private const int TOLERANCE = 300;

    public static function register(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        // No web middleware: Resend has no session or CSRF token, the signature
        // stands in for both.
        Route::post(self::PATH, static::class)->name('finisterre.inbound.resend');
    }

    public function __invoke(Request $request, InboundMessageHandler $handler): Response
    {
        if (! config('finisterre.mail.inbound.enabled') || config('finisterre.mail.inbound.driver') !== 'resend') {
            return response('', 404);
        }

        if (! $this->hasValidSignature($request)) {
            return response('', 401);
        }

        if ($request->input('type') !== 'email.received' || blank($request->input('data.email_id'))) {
            return response('', 204);
        }

        $email = Http::withToken(Typed::string(config('finisterre.mail.inbound.resend.api_key') ?: config('services.resend.key')))
            ->acceptJson()
            ->get('https://api.resend.com/emails/receiving/' . $request->string('data.email_id'));

        // A failed status makes Resend retry the event later.
        if ($email->failed()) {
            Log::error('Finisterre: could not fetch a received email from Resend', [
                'email_id' => $request->input('data.email_id'),
                'status'   => $email->status(),
            ]);

            return response('', 502);
        }

        $handler->handle($this->toInboundMessage(Typed::array($email->json())));

        return response('', 204);
    }

    /**
     * Resend signs its webhooks the Svix way: an HMAC-SHA256 of "id.timestamp.body",
     * keyed by the base64 part of the whsec_ secret.
     */
    protected function hasValidSignature(Request $request): bool
    {
        $secret = Typed::string(config('finisterre.mail.inbound.resend.webhook_secret'));
        $id = (string)$request->header('svix-id');
        $timestamp = (string)$request->header('svix-timestamp');

        if ($secret === '' || $id === '' || ! ctype_digit($timestamp) || abs(Carbon::now()->getTimestamp() - (int)$timestamp) > self::TOLERANCE) {
            return false;
        }

        $key = base64_decode(str($secret)->after('whsec_')->toString(), true);

        if ($key === false) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $request->getContent(), $key, true));

        // The header can hold several space-separated "v1,<signature>" while a secret is rotated.
        return collect(explode(' ', (string)$request->header('svix-signature')))
            ->contains(fn(string $signature) => hash_equals($expected, str($signature)->after(',')->toString()));
    }

    /**
     * @param  array<mixed>  $email
     */
    protected function toInboundMessage(array $email): InboundMessage
    {
        $headers = collect(Arr::wrap($email['headers'] ?? []))
            ->mapWithKeys(fn($value, $name) => [strtolower((string)$name) => match (true) {
                strtolower((string)$name) === 'authentication-results' => AuthenticationResults::ofReceivingServer(Typed::strings(Arr::wrap($value))),
                is_array($value)                                       => implode(' ', Typed::strings($value)),
                default                                                => Typed::string($value),
            }])
            ->all();

        return new InboundMessage(
            messageId: trim(Typed::string($email['message_id'] ?? ''), '<> ') ?: null,
            from: $this->address(Typed::string($email['from'] ?? '')),
            recipients: array_values(array_filter([
                ...Typed::strings(Arr::wrap($email['to'] ?? [])),
                ...Typed::strings(Arr::wrap($email['cc'] ?? [])),
                ...Typed::strings(Arr::wrap($email['received_for'] ?? [])),
            ])),
            html: Typed::string($email['html'] ?? ''),
            text: Typed::string($email['text'] ?? ''),
            headers: $headers,
        );
    }

    /**
     * The bare address of a "Name <address>" sender.
     */
    protected function address(string $from): ?string
    {
        return preg_match('/<([^>]+)>/', $from, $match) ? $match[1] : (trim($from) ?: null);
    }
}
