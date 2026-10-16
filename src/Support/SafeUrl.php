<?php

namespace Arzcode\Finisterre\Support;

use Closure;

/**
 * Whether a URL the package sends a secret to is a public https endpoint.
 *
 * The SMS gateway URL is editable from the settings page and the request carries the
 * gateway key, so a URL pointing into the host's own network (loopback, a private or
 * link-local address such as the cloud metadata service) would turn the page into a
 * way to reach, and leak the key to, services only the server can see.
 */
class SafeUrl
{
    /** @var (Closure(string): list<string>)|null */
    protected static ?Closure $resolver = null;

    /**
     * Replace how a host name is resolved to its addresses, or restore the DNS lookup with null.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    public static function isPublicHttps(?string $url): bool
    {
        return static::publicAddresses($url) !== null;
    }

    /**
     * The addresses a public https URL reaches, or null when it is not one. A name
     * that does not resolve gives an empty list: it is not refused while settings
     * are saved, since the request would fail on its own, but nothing can be sent
     * to it either.
     *
     * @return list<string>|null
     */
    public static function publicAddresses(?string $url): ?array
    {
        if ($url === null || preg_match('/[\x00-\x20\x7F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return static::isPublicAddress($host) ? [$host] : null;
        }

        if (! str_contains($host, '.') || preg_match('/\.(localhost|local|internal|intranet|lan|home|corp|home\.arpa)$/', $host)) {
            return null;
        }

        $addresses = static::addressesOf($host);

        foreach ($addresses as $address) {
            if (! static::isPublicAddress($address)) {
                return null;
            }
        }

        return $addresses;
    }

    /**
     * The CURLOPT_RESOLVE entries that send a request for the URL to the addresses
     * checked, and not to whatever its name resolves to a moment later: a name can
     * answer with a public address to the check and a private one to the request.
     *
     * @param  list<string>  $addresses
     * @return list<string>
     */
    public static function curlResolve(string $url, array $addresses): array
    {
        $parts = parse_url($url);
        $host = strtolower(trim(Typed::string($parts['host'] ?? ''), '[]'));

        // An address in the URL is what the request connects to already.
        if ($addresses === [] || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $port = Typed::int($parts['port'] ?? 443);
        $pinned = array_map(fn(string $address): string => str_contains($address, ':') ? '[' . $address . ']' : $address, $addresses);

        return [$host . ':' . $port . ':' . implode(',', $pinned)];
    }

    protected static function isPublicAddress(string $address): bool
    {
        // ::ffff:127.0.0.1 reaches the IPv4 address it wraps.
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $address, $mapped)) {
            $address = $mapped[1];
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return list<string>
     */
    protected static function addressesOf(string $host): array
    {
        if (static::$resolver instanceof Closure) {
            return (static::$resolver)($host);
        }

        $addresses = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = Typed::string($record['ipv6']);
            }
        }

        return $addresses;
    }
}
