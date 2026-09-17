<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Audit\AuditLogger;
use App\Core\Database\Database;
use App\Core\Exceptions\AuthenticationException;
use App\Core\Exceptions\ConflictException;
use App\Core\Security\Token;
use App\Core\Support\Env;
use App\Core\Support\BrazilIdentity;
use App\Modules\Users\UserRepository;
use App\Modules\Wallet\WalletService;
use DomainException;
use PDO;
use PDOException;

final class AuthService
{
    public function __construct(
        private UserRepository $users,
        private WalletService $wallet,
        private AuditLogger $audit,
    ) {}

    public function register(string $cpf, string $phone, string $password, ?string $ip = null): array
    {
        $cpf = BrazilIdentity::cpf($cpf);
        $phone = BrazilIdentity::phone($phone);
        $this->validateRegistration($password);

        try {
            $userId = Database::transaction(function (PDO $pdo) use ($cpf, $phone, $password, $ip): string {
                $collision = $pdo->prepare('SELECT id FROM users WHERE cpf = :cpf OR phone = :phone LIMIT 1');
                $collision->execute(['cpf' => $cpf, 'phone' => $phone]);
                if ($collision->fetch()) throw new ConflictException('CPF ou telefone já cadastrado.');

                $id = $this->uuid();
                $stmt = $pdo->prepare("INSERT INTO users (id,email,cpf,phone,username,status) VALUES (:id,NULL,:cpf,:phone,NULL,'ACTIVE')");
                $stmt->execute(['id' => $id, 'cpf' => $cpf, 'phone' => $phone]);

                $stmt = $pdo->prepare('INSERT INTO user_credentials (user_id,password_hash) VALUES (:user_id,:hash)');
                $stmt->execute(['user_id' => $id, 'hash' => password_hash($password, $this->passwordAlgorithm())]);

                $this->wallet->createForUser($id, $pdo);
                $this->audit->record('USER', $id, 'auth.register', 'user', $id, $ip, [], $pdo);
                return $id;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new ConflictException('CPF ou telefone já cadastrado.');
            throw $e;
        }

        return $this->issueSession($userId, $ip);
    }

    public function login(string $identifier, string $password, ?string $ip = null): array
    {
        $identifier = trim($identifier);
        $user = $this->users->findByLogin($identifier);
        if (!$user || $user['status'] !== 'ACTIVE') {
            throw new AuthenticationException('Invalid credentials.');
        }

        $stmt = Database::connection()->prepare('SELECT password_hash FROM user_credentials WHERE user_id=:user_id');
        $stmt->execute(['user_id' => $user['id']]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($password, (string) $hash)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if (password_needs_rehash((string) $hash, $this->passwordAlgorithm())) {
            $rehash = password_hash($password, $this->passwordAlgorithm());
            $stmt = Database::connection()->prepare(
                'UPDATE user_credentials SET password_hash=:hash, updated_at=NOW() WHERE user_id=:user_id'
            );
            $stmt->execute(['hash' => $rehash, 'user_id' => $user['id']]);
        }

        $this->audit->record('USER', $user['id'], 'auth.login', 'user', $user['id'], $ip);
        return $this->issueSession($user['id'], $ip);
    }

    public function authenticate(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT user_id FROM user_sessions '
            . 'WHERE token_hash=:hash AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['hash' => Token::hash($token)]);
        $userId = $stmt->fetchColumn();
        if (!$userId) {
            return null;
        }

        $touch = $pdo->prepare('UPDATE user_sessions SET last_used_at=NOW() WHERE token_hash=:hash');
        $touch->execute(['hash' => Token::hash($token)]);
        return $this->users->findById((string) $userId);
    }

    public function refresh(?string $token, ?string $ip = null): array
    {
        if (!$token) {
            throw new AuthenticationException('Authentication required.');
        }

        return Database::transaction(function (PDO $pdo) use ($token, $ip): array {
            $stmt = $pdo->prepare(
                'SELECT id,user_id FROM user_sessions '
                . 'WHERE token_hash=:hash AND revoked_at IS NULL AND expires_at > NOW() FOR UPDATE'
            );
            $stmt->execute(['hash' => Token::hash($token)]);
            $session = $stmt->fetch();
            if (!$session) {
                throw new AuthenticationException('Invalid or expired session.');
            }

            $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW(), last_used_at=NOW() WHERE id=:id');
            $stmt->execute(['id' => $session['id']]);

            $this->audit->record('USER', $session['user_id'], 'auth.refresh', 'user_session', $session['id'], $ip, [], $pdo);
            return $this->issueSessionWithin($pdo, (string) $session['user_id']);
        });
    }

    public function logout(?string $token, ?string $ip = null): void
    {
        if (!$token) {
            return;
        }

        Database::transaction(function (PDO $pdo) use ($token, $ip): void {
            $stmt = $pdo->prepare(
                'SELECT id,user_id FROM user_sessions WHERE token_hash=:hash AND revoked_at IS NULL FOR UPDATE'
            );
            $stmt->execute(['hash' => Token::hash($token)]);
            $session = $stmt->fetch();
            if (!$session) {
                return;
            }

            $revoke = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW(), last_used_at=NOW() WHERE id=:id');
            $revoke->execute(['id' => $session['id']]);
            $this->audit->record('USER', $session['user_id'], 'auth.logout', 'user_session', $session['id'], $ip, [], $pdo);
        });
    }

    private function issueSession(string $userId, ?string $ip = null): array
    {
        return Database::transaction(function (PDO $pdo) use ($userId, $ip): array {
            $result = $this->issueSessionWithin($pdo, $userId);
            $this->audit->record('USER', $userId, 'auth.session_created', 'user', $userId, $ip, [], $pdo);
            return $result;
        });
    }

    private function issueSessionWithin(PDO $pdo, string $userId): array
    {
        $token = Token::generate();
        $ttl = max(300, (int) Env::get('SESSION_TTL', '604800'));
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+' . $ttl . ' seconds')
            ->format('Y-m-d H:i:s.u');
        $stmt = $pdo->prepare(
            'INSERT INTO user_sessions (id,user_id,token_hash,expires_at,last_used_at) '
            . 'VALUES (:id,:user_id,:hash,:expires_at,NOW())'
        );
        $stmt->execute([
            'id' => $this->uuid(),
            'user_id' => $userId,
            'hash' => Token::hash($token),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => $this->users->findById($userId),
        ];
    }

    private function validateRegistration(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 200) {
            throw new DomainException('A senha deve ter entre 12 e 200 caracteres.');
        }
    }

    private function passwordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
