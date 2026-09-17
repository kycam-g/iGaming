<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Support\Env;
use RuntimeException;

final class SecretBox
{
    private const CIPHER = 'aes-256-gcm';

    public function encrypt(array $value): string
    {
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $plain = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Unable to encrypt gateway credentials.');
        }
        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_THROW_ON_ERROR));
    }

    public function decrypt(?string $payload): array
    {
        if ($payload === null || trim($payload) === '') return [];
        try {
            $wrapper = json_decode(base64_decode($payload, true) ?: '', true, 512, JSON_THROW_ON_ERROR);
            $iv = base64_decode((string) ($wrapper['iv'] ?? ''), true);
            $tag = base64_decode((string) ($wrapper['tag'] ?? ''), true);
            $data = base64_decode((string) ($wrapper['data'] ?? ''), true);
            if ($iv === false || $tag === false || $data === false) throw new RuntimeException('Invalid encrypted payload.');
            $plain = openssl_decrypt($data, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) throw new RuntimeException('Unable to decrypt gateway credentials.');
            $decoded = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            throw new RuntimeException('Gateway credentials could not be decrypted. Check APP_KEY.', 0, $e);
        }
    }

    private function key(): string
    {
        $secret = (string) Env::get('APP_KEY', '');
        if (strlen($secret) < 24 || str_contains($secret, 'change-me')) {
            throw new RuntimeException('APP_KEY must be a strong secret before storing gateway credentials.');
        }
        return hash('sha256', $secret, true);
    }
}
