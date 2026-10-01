<?php

use Arzcode\Finisterre\Support\InboundEmail\AuthenticationResults;

it('joins the verdict a receiving server spreads over several headers', function() {
    // The shape Fastmail delivers: SPF, DKIM and DMARC are not in the topmost header.
    $raw = implode("\r\n", [
        'Return-Path: <rita@example.com>',
        'ARC-Authentication-Results: i=1; mx-03.messagingengine.com; dmarc=fail',
        'X-ME-Authentication-Results: mx-03.messagingengine.com; x-vs=clean',
        'Authentication-Results: mx-03.messagingengine.com;',
        '    x-csa=none;',
        '    x-ptr=pass smtp.helo=mail.example.com',
        '      policy.ptr=mail.example.com',
        'Authentication-Results: MX-03.messagingengine.com 1;',
        '    arc=none (no signatures found)',
        'Authentication-Results: mx-03.messagingengine.com;',
        '    dkim=pass (2048-bit rsa key sha256) header.d=example.com;',
        '    dmarc=pass policy.published-domain-policy=quarantine',
        '      header.from=example.com;',
        '    spf=pass smtp.mailfrom=rita@example.com',
        'Authentication-Results: mail.evil.test; dmarc=bestguesspass; dkim=fail',
        'From: Rita <rita@example.com>',
    ]);

    expect(AuthenticationResults::fromRawHeaders($raw))
        ->toContain('x-ptr=pass smtp.helo=mail.example.com policy.ptr=mail.example.com')
        ->toContain('arc=none')
        ->toContain('dmarc=pass policy.published-domain-policy=quarantine header.from=example.com')
        ->toContain('spf=pass smtp.mailfrom=rita@example.com')
        ->not->toContain('evil.test')
        ->not->toContain('dmarc=fail')
        ->not->toContain('x-vs=clean');
});

it('has nothing to say about an email without the header', function() {
    expect(AuthenticationResults::fromRawHeaders("From: rita@example.com\r\nSubject: Hi"))->toBe('')
        ->and(AuthenticationResults::ofReceivingServer(['', '  ']))->toBe('');
});
