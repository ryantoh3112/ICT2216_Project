<?php

namespace App\Service;

class JwtService
{
    private string $secret;
    private string $algo = 'sha256'; // HMAC SHA-256

    public function __construct(string $jwtSecret)
    {
        $this->secret = $jwtSecret;
    }

    public function createToken(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $payload['iat'] = time();
        $payload['exp'] = time() + 3600;

        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            $base64UrlHeader . "." . $base64UrlPayload,
            $this->secret,
            true
        );

        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public function verifyToken(string $token): array
    {
        [$headerB64, $payloadB64, $signatureB64] = explode('.', $token);

        $data = $headerB64 . '.' . $payloadB64;
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->secret, true)
        );

        if (!hash_equals($expectedSig, $signatureB64)) {
            throw new \Exception('Invalid signature');
        }

        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);

        if ($payload['exp'] < time()) {
            throw new \Exception('Token expired');
        }
        if (isset($payload['iat']) && $payload['iat'] > time() + 60) {
            throw new \Exception('Token issued in the future (iat)');
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
