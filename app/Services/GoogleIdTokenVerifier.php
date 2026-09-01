<?php

namespace App\Services;

use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleIdTokenVerifier
{
    /**
     * @return array{sub: string, email: string, email_verified: bool, name?: string, picture?: string}
     */
    public function verify(string $credential): array
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new DomainException(__('Google sign-in is not configured.'));
        }

        try {
            $claims = (array) JWT::decode($credential, JWK::parseKeySet($this->googleKeys(), 'RS256'));
        } catch (Throwable $exception) {
            throw new DomainException(__('Google returned an invalid identity token.'), previous: $exception);
        }

        $audience = $claims['aud'] ?? null;
        $issuer = $claims['iss'] ?? null;
        $subject = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        $isEmailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! $this->hasAudience($audience, $clientId)
            || ! isset($claims['exp'])
            || ! is_string($subject)
            || $subject === ''
            || ! is_string($email)
            || $email === ''
            || ! $isEmailVerified) {
            throw new DomainException(__('Google did not provide a verified identity for this application.'));
        }

        $identity = [
            'sub' => $subject,
            'email' => $email,
            'email_verified' => true,
        ];

        if (isset($claims['name']) && is_string($claims['name'])) {
            $identity['name'] = $claims['name'];
        }

        if (isset($claims['picture']) && is_string($claims['picture'])) {
            $identity['picture'] = $claims['picture'];
        }

        return $identity;
    }

    /**
     * @return array<string, mixed>
     */
    private function googleKeys(): array
    {
        return Cache::remember('auth.google.jwks', now()->addMinutes(30), fn (): array => Http::timeout(5)
            ->retry(2, 100)
            ->get('https://www.googleapis.com/oauth2/v3/certs')
            ->throw()
            ->json());
    }

    private function hasAudience(mixed $audience, string $clientId): bool
    {
        return $audience === $clientId
            || (is_array($audience) && in_array($clientId, $audience, true));
    }
}
