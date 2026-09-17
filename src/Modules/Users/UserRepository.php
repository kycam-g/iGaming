<?php

declare(strict_types=1);

namespace App\Modules\Users;

use App\Core\Database\Database;
use App\Core\Support\BrazilIdentity;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function findByLogin(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->findByEmail($identifier);
        }

        $digits = BrazilIdentity::loginDigits($identifier);
        if ($digits === '') return null;

        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE cpf = :cpf OR phone = :phone LIMIT 1'
        );
        $stmt->execute(['cpf' => $digits, 'phone' => $digits]);
        return $stmt->fetch() ?: null;
    }

    public function findById(string $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id,public_id,email,cpf,phone,username,status,created_at,updated_at FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
