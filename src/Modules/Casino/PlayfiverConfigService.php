<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use App\Core\Security\SecretBox;
use DomainException;

final class PlayfiverConfigService
{
    public function __construct(private readonly SecretBox $box) {}

    public function publicConfig(): array
    {
        $stmt = Database::connection()->prepare('SELECT base_url, credentials_encrypted, enabled, updated_at FROM casino_api_credentials WHERE integration_code = :code');
        $stmt->execute(['code' => 'playfiver']);
        $row = $stmt->fetch();
        if (!$row) return ['base_url' => 'https://api.playfivers.com', 'configured' => false, 'enabled' => false, 'updated_at' => null];
        $secrets = $this->box->decrypt($row['credentials_encrypted']);
        return ['base_url' => $row['base_url'], 'configured' => !empty($secrets['agent_code']) && !empty($secrets['agent_token']) && !empty($secrets['agent_secret']), 'enabled' => (bool)$row['enabled'], 'updated_at' => $row['updated_at']];
    }

    public function save(array $data): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT base_url, credentials_encrypted FROM casino_api_credentials WHERE integration_code = :code');
        $stmt->execute(['code' => 'playfiver']);
        $previous = $stmt->fetch();
        $secrets = $previous ? $this->box->decrypt($previous['credentials_encrypted']) : [];
        foreach (['agent_code','agent_token','agent_secret'] as $field) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value !== '') {
                if (strlen($value) > 1024 || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new DomainException('Credencial inválida.');
                $secrets[$field] = $value;
            }
            if (empty($secrets[$field])) throw new DomainException('Preencha Agent Code, Agent Token e Agent Secret.');
        }
        $url = rtrim(trim((string)($data['base_url'] ?? ($previous['base_url'] ?? 'https://api.playfivers.com'))), '/');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'api.playfivers.com' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port']) || !in_array($parts['path'] ?? '', ['', '/'], true)) throw new DomainException('Use apenas https://api.playfivers.com como URL da API.');
        // Production cannot be enabled until launch and financial callbacks are verified.
        $encrypted = $this->box->encrypt($secrets);
        $stmt = $pdo->prepare('INSERT INTO casino_api_credentials (integration_code,base_url,credentials_encrypted,enabled) VALUES (:code,:url,:secret,0) ON DUPLICATE KEY UPDATE base_url=VALUES(base_url),credentials_encrypted=VALUES(credentials_encrypted),enabled=0');
        $stmt->execute(['code'=>'playfiver','url'=>$url,'secret'=>$encrypted]);
        return $this->publicConfig();
    }
}
