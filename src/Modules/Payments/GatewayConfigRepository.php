<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Core\Database\Database;
use App\Core\Security\SecretBox;
use DomainException;
use PDO;

final class GatewayConfigRepository
{
    public function __construct(private readonly SecretBox $secretBox) {}

    public function publicFor(string $operation): array
    {
        $operation = strtoupper($operation);
        $flag = $operation === 'WITHDRAWAL' ? 'withdrawal_enabled' : 'deposit_enabled';
        $priority = $operation === 'WITHDRAWAL' ? 'priority_withdrawal' : 'priority_deposit';
        $stmt = Database::connection()->query(
            "SELECT code,name,mode,{$priority} AS priority,min_deposit_minor,max_deposit_minor,min_withdrawal_minor,max_withdrawal_minor,public_config " .
            "FROM payment_gateways WHERE enabled=1 AND {$flag}=1 ORDER BY {$priority} ASC,name ASC"
        );
        return array_map(function (array $row) use ($operation): array {
            $public = json_decode((string) ($row['public_config'] ?? '{}'), true) ?: [];
            return [
                'code' => $row['code'],
                'name' => $row['name'],
                'mode' => $row['mode'],
                'priority' => (int) $row['priority'],
                'sandbox' => $row['mode'] === 'SANDBOX',
                'min_amount_minor' => (int) ($operation === 'WITHDRAWAL' ? $row['min_withdrawal_minor'] : $row['min_deposit_minor']),
                'max_amount_minor' => $operation === 'WITHDRAWAL'
                    ? ($row['max_withdrawal_minor'] === null ? null : (int) $row['max_withdrawal_minor'])
                    : ($row['max_deposit_minor'] === null ? null : (int) $row['max_deposit_minor']),
                'public_config' => $public,
            ];
        }, $stmt->fetchAll());
    }

    public function resolve(?string $code, string $operation): array
    {
        $operation = strtoupper($operation);
        $flag = $operation === 'WITHDRAWAL' ? 'withdrawal_enabled' : 'deposit_enabled';
        $priority = $operation === 'WITHDRAWAL' ? 'priority_withdrawal' : 'priority_deposit';
        $pdo = Database::connection();
        if ($code !== null && $code !== '' && $code !== 'auto') {
            $stmt = $pdo->prepare("SELECT * FROM payment_gateways WHERE code=:code AND enabled=1 AND {$flag}=1 LIMIT 1");
            $stmt->execute(['code' => $code]);
        } else {
            $stmt = $pdo->query("SELECT * FROM payment_gateways WHERE enabled=1 AND {$flag}=1 ORDER BY {$priority} ASC,name ASC LIMIT 1");
        }
        $row = $stmt->fetch();
        if (!$row) throw new DomainException('Nenhum gateway disponível para esta operação.');
        return $this->hydrate($row);
    }

    public function adminList(): array
    {
        $rows = Database::connection()->query('SELECT * FROM payment_gateways ORDER BY priority_deposit,priority_withdrawal,name')->fetchAll();
        return array_map(function (array $row): array {
            $credentials = $this->secretBox->decrypt($row['credentials_encrypted'] ?? null);
            $row['credentials_configured'] = $credentials !== [];
            $row['credential_fields'] = array_values(array_keys($credentials));
            unset($row['credentials_encrypted']);
            $row['settings'] = json_decode((string) ($row['settings'] ?? '{}'), true) ?: [];
            $row['public_config'] = json_decode((string) ($row['public_config'] ?? '{}'), true) ?: [];
            return $row;
        }, $rows);
    }

    public function save(array $input): array
    {
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        if (!preg_match('/^[a-z0-9_\-]{2,64}$/', $code)) throw new DomainException('Código de gateway inválido.');
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') throw new DomainException('Nome do gateway é obrigatório.');

        $existing = $this->findRaw($code);
        $credentials = $existing ? $this->secretBox->decrypt($existing['credentials_encrypted'] ?? null) : [];
        foreach ((array) ($input['credentials'] ?? []) as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') continue;
            if ($value === null || $value === '' || $value === '********') continue;
            $credentials[$key] = trim((string) $value);
        }
        foreach ((array) ($input['clear_credentials'] ?? []) as $key) unset($credentials[(string) $key]);

        $settings = (array) ($input['settings'] ?? []);
        $public = (array) ($input['public_config'] ?? []);
        $encrypted = $credentials === [] ? null : $this->secretBox->encrypt($credentials);
        $params = [
            'code' => $code,
            'name' => $name,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'deposit_enabled' => !empty($input['deposit_enabled']) ? 1 : 0,
            'withdrawal_enabled' => !empty($input['withdrawal_enabled']) ? 1 : 0,
            'mode' => strtoupper((string) ($input['mode'] ?? 'PRODUCTION')) === 'SANDBOX' ? 'SANDBOX' : 'PRODUCTION',
            'priority_deposit' => max(1, (int) ($input['priority_deposit'] ?? 100)),
            'priority_withdrawal' => max(1, (int) ($input['priority_withdrawal'] ?? 100)),
            'min_deposit_minor' => max(0, (int) ($input['min_deposit_minor'] ?? 100)),
            'max_deposit_minor' => $this->nullablePositiveInt($input['max_deposit_minor'] ?? null),
            'min_withdrawal_minor' => max(0, (int) ($input['min_withdrawal_minor'] ?? 100)),
            'max_withdrawal_minor' => $this->nullablePositiveInt($input['max_withdrawal_minor'] ?? null),
            'credentials_encrypted' => $encrypted,
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'public_config' => json_encode($public, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $sql = "INSERT INTO payment_gateways (code,name,enabled,deposit_enabled,withdrawal_enabled,mode,priority_deposit,priority_withdrawal,min_deposit_minor,max_deposit_minor,min_withdrawal_minor,max_withdrawal_minor,credentials_encrypted,settings,public_config) VALUES (:code,:name,:enabled,:deposit_enabled,:withdrawal_enabled,:mode,:priority_deposit,:priority_withdrawal,:min_deposit_minor,:max_deposit_minor,:min_withdrawal_minor,:max_withdrawal_minor,:credentials_encrypted,:settings,:public_config) ON DUPLICATE KEY UPDATE name=VALUES(name),enabled=VALUES(enabled),deposit_enabled=VALUES(deposit_enabled),withdrawal_enabled=VALUES(withdrawal_enabled),mode=VALUES(mode),priority_deposit=VALUES(priority_deposit),priority_withdrawal=VALUES(priority_withdrawal),min_deposit_minor=VALUES(min_deposit_minor),max_deposit_minor=VALUES(max_deposit_minor),min_withdrawal_minor=VALUES(min_withdrawal_minor),max_withdrawal_minor=VALUES(max_withdrawal_minor),credentials_encrypted=VALUES(credentials_encrypted),settings=VALUES(settings),public_config=VALUES(public_config)";
        Database::connection()->prepare($sql)->execute($params);
        return $this->findAdmin($code);
    }

    public function configForRuntime(string $code): array
    {
        $row = $this->findRaw($code);
        if (!$row) throw new DomainException('Gateway não encontrado.');
        return $this->hydrate($row);
    }

    private function hydrate(array $row): array
    {
        $row['credentials'] = $this->secretBox->decrypt($row['credentials_encrypted'] ?? null);
        $row['settings'] = json_decode((string) ($row['settings'] ?? '{}'), true) ?: [];
        $row['public_config'] = json_decode((string) ($row['public_config'] ?? '{}'), true) ?: [];
        return $row;
    }

    private function findRaw(string $code): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM payment_gateways WHERE code=:code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return $stmt->fetch() ?: null;
    }

    private function findAdmin(string $code): array
    {
        foreach ($this->adminList() as $row) if ($row['code'] === $code) return $row;
        throw new DomainException('Gateway não encontrado após salvar.');
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $n = (int) $value;
        return $n > 0 ? $n : null;
    }
}
