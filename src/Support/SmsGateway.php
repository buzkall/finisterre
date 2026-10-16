<?php

namespace Arzcode\Finisterre\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The SMS gateway in the settings, which texts the one configured number.
 *
 * The gateway key travels with the request and the URL is editable from the settings
 * page, so the request only goes to a public https address: the one the host name
 * resolved to when it was checked, without following a redirect.
 */
class SmsGateway
{
    protected const int LOOKUP_ATTEMPTS = 3;
    protected const int LOOKUP_RETRY_SECONDS = 1;

    /**
     * @param  string  $id  The gateway's id for the message
     *
     * @throws RuntimeException when the gateway's host name does not resolve, so a queued
     *                          notification is tried again
     */
    public static function send(string $id, string $text): void
    {
        $url = Typed::string(config('finisterre.sms_notification.url'));
        $addresses = static::addresses($url);

        if ($addresses === null) {
            report(new RuntimeException('Finisterre did not send the text message: the SMS API URL is not a public https address.'));

            return;
        }

        // e.g. https://api.smsarena.es/http/sms.php?auth_key=XXXX&id=11964&from=XXXX&to=XXXX&text=XXXX
        $response = Http::timeout(10)
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => SafeUrl::curlResolve($url, $addresses)]])
            ->get($url, [
                'auth_key' => config('finisterre.sms_notification.auth_key'),
                'id'       => $id,
                'from'     => config('finisterre.sms_notification.sender'),
                'to'       => config('finisterre.sms_notification.notify_to'),
                'text'     => $text,
            ]);

        // Reported rather than followed, or the texts would stop arriving unnoticed
        // the day the gateway moves: the URL in the settings needs updating.
        if ($response->redirect()) {
            report(new RuntimeException('Finisterre did not send the text message: the SMS API answered with a redirect to ' . $response->header('Location') . '. Update the SMS API URL in the settings.'));
        }
    }

    /**
     * The public addresses the gateway's host resolves to, or null when the URL is not a
     * public https one. Only the lookup is tried again: a URL that is not public is not
     * going to become one.
     *
     * @return non-empty-list<string>|null
     */
    protected static function addresses(string $url): ?array
    {
        $addresses = SafeUrl::publicAddresses($url);

        for ($attempt = 1; $addresses === [] && $attempt < static::LOOKUP_ATTEMPTS; $attempt++) {
            sleep(static::LOOKUP_RETRY_SECONDS);

            $addresses = SafeUrl::publicAddresses($url);
        }

        throw_if($addresses === [], RuntimeException::class, 'Finisterre did not send the text message: could not resolve the SMS API host.');

        return $addresses;
    }
}
