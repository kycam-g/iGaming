<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class AdminAuthService
{
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $stmt = Database::connection()->prepare("SELECT * FROM admin_users WHERE email=:email AND status='ACTIVE' LIMIT 1");
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            throw new DomainException('Credenciais administrativas inválidas.');
        }
        $token = bin2hex(random_bytes(32));
        $id = $this->uuid();
        $stmt = Database::connection()->prepare('INSERT INTO admin_sessions (id,admin_user_id,token_hash,expires_at) VALUES (:id,:uid,:hash,DATE_ADD(NOW(6), INTERVAL 12 HOUR))');
        $stmt->execute(['id'=>$id,'uid'=>$admin['id'],'hash'=>hash('sha256',$token)]);
        return ['token'=>$token,'admin'=>['id'=>$admin['id'],'email'=>$admin['email'],'name'=>$admin['name']]];
    }

    public function authenticate(?string $token): ?array
    {
        if (!$token) return null;
        $stmt = Database::connection()->prepare("SELECT a.id,a.email,a.name,a.status FROM admin_sessions s JOIN admin_users a ON a.id=s.admin_user_id WHERE s.token_hash=:hash AND s.revoked_at IS NULL AND s.expires_at>NOW(6) AND a.status='ACTIVE' LIMIT 1");
        $stmt->execute(['hash'=>hash('sha256',$token)]);
        return $stmt->fetch() ?: null;
    }

    public function logout(?string $token): void
    {
        if (!$token) return;
        $stmt = Database::connection()->prepare('UPDATE admin_sessions SET revoked_at=NOW(6) WHERE token_hash=:hash AND revoked_at IS NULL');
        $stmt->execute(['hash'=>hash('sha256',$token)]);
    }

    private function uuid(): string
    {
        $data = random_bytes(16); $data[6]=chr((ord($data[6])&0x0f)|0x40); $data[8]=chr((ord($data[8])&0x3f)|0x80); $hex=bin2hex($data);
        return sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20));
    }
}
