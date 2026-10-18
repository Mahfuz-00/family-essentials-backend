<?php

namespace App\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mints (and caches) an OAuth 2.0 access token from a Google service account.
 *
 * Kept dependency-light on purpose: signing is the only thing needed, and
 * `firebase/php-jwt` already does it correctly.
 */
class GoogleServiceAccountToken
{
    public function __construct(private readonly string $credentialsPath) {}

    public function accessToken(): string
    {
        return Cache::remember($this->cacheKey(), 3300, function (): string {
            $credentials = $this->credentials();
            $now = time();

            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    'Google service-account sign-in failed: '.$response->status()
                );
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @return array{client_email: string, private_key: string}
     */
    private function credentials(): array
    {
        if (! is_file($this->credentialsPath)) {
            throw new RuntimeException(
                "Firebase service-account file not found at [{$this->credentialsPath}]."
            );
        }

        $decoded = json_decode((string) file_get_contents($this->credentialsPath), true);

        if (! is_array($decoded) || ! isset($decoded['client_email'], $decoded['private_key'])) {
            throw new RuntimeException(
                'The Firebase service-account file is missing client_email or private_key.'
            );
        }

        return [
            'client_email' => (string) $decoded['client_email'],
            'private_key' => (string) $decoded['private_key'],
        ];
    }

    private function cacheKey(): string
    {
        return 'google:access-token:'.md5($this->credentialsPath);
    }
}
