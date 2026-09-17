<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use App\Core\Security\SecretBox;
use DomainException;

final class PlayfiverConfigService
{
    private const API_URL = 'https://api.playfivers.com';

    public function __construct(private readonly SecretBox $box) {}

    public function publicConfig(): array
    {
        $row = $this->row();
        if (!$row) {
            return [
                'base_url' => self::API_URL,
                'configured' => false,
                'enabled' => false,
                'updated_at' => null,
            ];
        }

        $secrets = $this->decrypt((string)$row['credentials_encrypted']);
        $configured = $this->hasMainCredentials($secrets);

        return [
            'base_url' => (string)$row['base_url'],
            'configured' => $configured,
            'enabled' => $configured && (bool)$row['enabled'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /** @return array{agent_code:string,agent_token:string,agent_secret:string} */
    public function credentials(): array
    {
        $row = $this->row();
        if (!$row) {
            throw new DomainException('Credenciais PlayFiver não configuradas.');
        }

        $secrets = $this->decrypt((string)$row['credentials_encrypted']);
        if (!$this->hasMainCredentials($secrets)) {
            throw new DomainException('Credenciais PlayFiver incompletas.');
        }

        return [
            'agent_code' => (string)$secrets['agent_code'],
            'agent_token' => (string)$secrets['agent_token'],
            'agent_secret' => (string)$secrets['agent_secret'],
        ];
    }

    public function isEnabled(): bool
    {
        return (bool)($this->publicConfig()['enabled'] ?? false);
    }

    public function save(array $data): array
    {
        $pdo = Database::connection();
        $previous = $this->row();
        $secrets = $previous ? $this->decrypt((string)$previous['credentials_encrypted']) : [];

        // V12.14: remove campos legados de catálogo inseridos nas versões experimentais anteriores.
        unset($secrets['catalog_agent_code'], $secrets['catalog_agent_token'], $secrets['catalog_url']);

        foreach (['agent_code','agent_token','agent_secret'] as $field) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value !== '') {
                $secrets[$field] = $this->validateCredential($value);
            }
            if (empty($secrets[$field])) {
                throw new DomainException('Preencha Agent Code, Agent Token e Agent Secret da PlayFiver.');
            }
        }

        $url = rtrim(trim((string)($data['base_url'] ?? ($previous['base_url'] ?? self::API_URL))), '/');
        $this->validateUrl($url);

        $enabled = !empty($data['enabled']) ? 1 : 0;
        $encrypted = $this->box->encrypt($secrets);
        $stmt = $pdo->prepare(
            'INSERT INTO casino_api_credentials (integration_code,base_url,credentials_encrypted,enabled) '
            . 'VALUES (:code,:url,:secret,:enabled) '
            . 'ON DUPLICATE KEY UPDATE base_url=VALUES(base_url),credentials_encrypted=VALUES(credentials_encrypted),enabled=VALUES(enabled)'
        );
        $stmt->execute(['code'=>'playfiver','url'=>$url,'secret'=>$encrypted,'enabled'=>$enabled]);

        return $this->publicConfig();
    }

    public function authenticateCallback(string $agentCode, string $agentSecret): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $secrets = $this->credentials();
        } catch (\Throwable) {
            return false;
        }

        return hash_equals($secrets['agent_code'], trim($agentCode))
            && hash_equals($secrets['agent_secret'], trim($agentSecret));
    }

    private function row(): array|false
    {
        $stmt = Database::connection()->prepare(
            'SELECT base_url, credentials_encrypted, enabled, updated_at '
            . 'FROM casino_api_credentials WHERE integration_code = :code'
        );
        $stmt->execute(['code' => 'playfiver']);
        return $stmt->fetch();
    }

    private function decrypt(string $encrypted): array
    {
        $data = $this->box->decrypt($encrypted);
        return is_array($data) ? $data : [];
    }

    private function hasMainCredentials(array $secrets): bool
    {
        return !empty($secrets['agent_code'])
            && !empty($secrets['agent_token'])
            && !empty($secrets['agent_secret']);
    }

    private function validateCredential(string $value): string
    {
        if (strlen($value) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new DomainException('Credencial inválida.');
        }
        return $value;
    }

    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'api.playfivers.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
            || isset($parts['fragment']) || isset($parts['port'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new DomainException('Use apenas https://api.playfivers.com como URL da API PlayFiver.');
        }
    }
}
