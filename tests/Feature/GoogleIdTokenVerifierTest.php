<?php

use App\Services\GoogleIdTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('Google ID tokens are cryptographically verified for this application', function () {
    config()->set('services.google.client_id', 'google-client-id');
    Cache::forget('auth.google.jwks');

    $privateKey = openssl_pkey_new([
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $keyDetails = openssl_pkey_get_details($privateKey);

    Http::fake([
        'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
            'keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'google-key-id',
                'n' => JWT::urlsafeB64Encode($keyDetails['rsa']['n']),
                'e' => JWT::urlsafeB64Encode($keyDetails['rsa']['e']),
            ]],
        ]),
    ]);

    $claims = [
        'iss' => 'https://accounts.google.com',
        'aud' => 'google-client-id',
        'sub' => 'google-subject',
        'email' => 'verified@example.com',
        'email_verified' => true,
        'iat' => now()->timestamp,
        'exp' => now()->addHour()->timestamp,
    ];
    $credential = JWT::encode($claims, $privateKey, 'RS256', 'google-key-id');

    expect(app(GoogleIdTokenVerifier::class)->verify($credential))
        ->toMatchArray([
            'sub' => 'google-subject',
            'email' => 'verified@example.com',
            'email_verified' => true,
        ]);

    $wrongAudienceCredential = JWT::encode(
        [...$claims, 'aud' => 'another-application'],
        $privateKey,
        'RS256',
        'google-key-id',
    );

    expect(fn () => app(GoogleIdTokenVerifier::class)->verify($wrongAudienceCredential))
        ->toThrow(DomainException::class);

    Http::assertSentCount(1);
});
