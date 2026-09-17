<?php

declare(strict_types=1);

namespace App\Modules\Wallet;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class WalletService
{
    private const ACCOUNT_TYPES = ['CASH', 'BONUS', 'AFFILIATE'];

    public function createForUser(string $userId, ?PDO $pdo = null): void
    {
        if ($pdo instanceof PDO) {
            $this->createForUserWithin($pdo, $userId);
            return;
        }

        Database::transaction(fn(PDO $connection) => $this->createForUserWithin($connection, $userId));
    }

    private function createForUserWithin(PDO $pdo, string $userId): void
    {
        $walletId = $this->uuid();
        $stmt = $pdo->prepare('INSERT INTO wallets (id,user_id) VALUES (:id,:user_id)');
        $stmt->execute(['id' => $walletId, 'user_id' => $userId]);

        foreach (['CASH', 'BONUS'] as $type) {
            $stmt = $pdo->prepare(
                "INSERT INTO wallet_accounts (id,wallet_id,type,balance_minor,currency) VALUES (:id,:wallet_id,:type,0,'BRL')"
            );
            $stmt->execute(['id' => $this->uuid(), 'wallet_id' => $walletId, 'type' => $type]);
        }
    }

    public function balance(string $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT wa.id, wa.type, wa.balance_minor, wa.currency '
            . 'FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id '
            . 'WHERE w.user_id=:user_id ORDER BY wa.type'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function transactions(string $userId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT ft.id, ft.type, ft.status, ft.reference_type, ft.reference_id, ft.correlation_id, ft.created_at, '
            . 'le.direction, le.amount_minor, le.balance_before_minor, le.balance_after_minor, wa.type AS account_type, wa.currency '
            . 'FROM financial_transactions ft '
            . 'JOIN ledger_entries le ON le.transaction_id=ft.id '
            . 'JOIN wallet_accounts wa ON wa.id=le.account_id '
            . 'WHERE ft.user_id=:user_id ORDER BY ft.created_at DESC LIMIT ' . $limit
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function credit(
        string $userId,
        string $accountType,
        int $amountMinor,
        string $referenceType,
        string $referenceId,
        string $idempotencyKey,
        string $transactionType = 'CREDIT',
    ): array {
        return $this->move($userId, $accountType, $amountMinor, 'CREDIT', $referenceType, $referenceId, $idempotencyKey, $transactionType);
    }

    public function debit(
        string $userId,
        string $accountType,
        int $amountMinor,
        string $referenceType,
        string $referenceId,
        string $idempotencyKey,
        string $transactionType = 'DEBIT',
    ): array {
        return $this->move($userId, $accountType, $amountMinor, 'DEBIT', $referenceType, $referenceId, $idempotencyKey, $transactionType);
    }

    public function accountBalanceMinor(string $userId, string $accountType = 'CASH'): int
    {
        $accountType = strtoupper(trim($accountType));
        if (!in_array($accountType, self::ACCOUNT_TYPES, true)) throw new DomainException('Invalid wallet account type.');
        $stmt = Database::connection()->prepare(
            'SELECT wa.balance_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id '
            . 'WHERE w.user_id=:user_id AND wa.type=:type LIMIT 1'
        );
        $stmt->execute(['user_id'=>$userId,'type'=>$accountType]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) throw new DomainException('Wallet account not found.');
        return (int)$balance;
    }

    /**
     * Liquida uma rodada de cassino de forma atômica e idempotente no ledger.
     * Um WinBet pode gerar uma entrada de débito e outra de crédito na mesma transação financeira.
     */
    public function settleCasino(
        string $userId,
        int $betMinor,
        int $winMinor,
        string $provider,
        string $providerTransactionId,
        string $gameCode,
        string $eventType,
    ): array {
        $provider = strtoupper(trim($provider));
        $providerTransactionId = trim($providerTransactionId);
        $gameCode = trim($gameCode);
        $eventType = strtoupper(trim($eventType));
        if ($betMinor < 0 || $winMinor < 0) throw new DomainException('Invalid casino amounts.');
        if ($provider === '' || strlen($provider) > 40 || $providerTransactionId === '' || strlen($providerTransactionId) > 160 || $gameCode === '' || strlen($gameCode) > 190) {
            throw new DomainException('Invalid casino transaction reference.');
        }
        if (!in_array($eventType, ['BET','WIN','WINBET'], true)) throw new DomainException('Invalid casino event type.');

        $idempotencyKey = strtolower($provider).':'.$providerTransactionId;
        $scope = 'casino:'.strtolower($provider).':'.$userId;
        $transactionType = 'CASINO_'.$eventType;

        return Database::transaction(function (PDO $pdo) use ($userId,$betMinor,$winMinor,$provider,$providerTransactionId,$gameCode,$eventType,$idempotencyKey,$scope,$transactionType): array {
            $idem = $pdo->prepare('INSERT IGNORE INTO idempotency_keys (`key`,scope) VALUES (:key,:scope)');
            $idem->execute(['key'=>$idempotencyKey,'scope'=>$scope]);
            if ($idem->rowCount() === 0) {
                $stmt = $pdo->prepare(
                    'SELECT wa.balance_minor,wa.currency FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id '
                    . "WHERE w.user_id=:user_id AND wa.type='CASH' LIMIT 1"
                );
                $stmt->execute(['user_id'=>$userId]);
                $account = $stmt->fetch();
                if (!$account) throw new DomainException('Wallet account not found.');
                return ['balance_minor'=>(int)$account['balance_minor'],'currency'=>$account['currency'],'idempotent_replay'=>true];
            }

            $stmt = $pdo->prepare(
                'SELECT wa.* FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id '
                . "WHERE w.user_id=:user_id AND wa.type='CASH' FOR UPDATE"
            );
            $stmt->execute(['user_id'=>$userId]);
            $account = $stmt->fetch();
            if (!$account) throw new DomainException('Wallet account not found.');

            $before = (int)$account['balance_minor'];
            if ($betMinor > $before) throw new DomainException('Insufficient balance.');
            $afterBet = $before - $betMinor;
            $after = $afterBet + $winMinor;

            $transactionId = $this->uuid();
            $correlationId = $this->uuid();
            $metadata = json_encode([
                'provider'=>$provider,
                'provider_transaction_id'=>$providerTransactionId,
                'game_code'=>$gameCode,
                'event_type'=>$eventType,
                'bet_minor'=>$betMinor,
                'win_minor'=>$winMinor,
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $stmt = $pdo->prepare(
                "INSERT INTO financial_transactions (id,user_id,type,status,reference_type,reference_id,correlation_id,metadata) "
                . "VALUES (:id,:user_id,:type,'COMPLETED','PLAYFIVER',:reference_id,:correlation_id,:metadata)"
            );
            $stmt->execute([
                'id'=>$transactionId,'user_id'=>$userId,'type'=>$transactionType,'reference_id'=>$providerTransactionId,
                'correlation_id'=>$correlationId,'metadata'=>$metadata,
            ]);

            if ($betMinor > 0) {
                $stmt = $pdo->prepare(
                    'INSERT INTO ledger_entries (id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) '
                    . "VALUES (:id,:transaction_id,:account_id,'DEBIT',:amount,:before,:after,'PLAYFIVER',:reference_id,:correlation_id)"
                );
                $stmt->execute([
                    'id'=>$this->uuid(),'transaction_id'=>$transactionId,'account_id'=>$account['id'],'amount'=>$betMinor,
                    'before'=>$before,'after'=>$afterBet,'reference_id'=>$providerTransactionId,'correlation_id'=>$correlationId,
                ]);
            }
            if ($winMinor > 0) {
                $stmt = $pdo->prepare(
                    'INSERT INTO ledger_entries (id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) '
                    . "VALUES (:id,:transaction_id,:account_id,'CREDIT',:amount,:before,:after,'PLAYFIVER',:reference_id,:correlation_id)"
                );
                $stmt->execute([
                    'id'=>$this->uuid(),'transaction_id'=>$transactionId,'account_id'=>$account['id'],'amount'=>$winMinor,
                    'before'=>$afterBet,'after'=>$after,'reference_id'=>$providerTransactionId,'correlation_id'=>$correlationId,
                ]);
            }

            if ($after !== $before) {
                $stmt = $pdo->prepare('UPDATE wallet_accounts SET balance_minor=:balance,updated_at=NOW() WHERE id=:id');
                $stmt->execute(['balance'=>$after,'id'=>$account['id']]);
            }
            $stmt = $pdo->prepare('UPDATE idempotency_keys SET transaction_id=:transaction_id WHERE `key`=:key AND scope=:scope');
            $stmt->execute(['transaction_id'=>$transactionId,'key'=>$idempotencyKey,'scope'=>$scope]);

            return [
                'transaction_id'=>$transactionId,
                'correlation_id'=>$correlationId,
                'balance_minor'=>$after,
                'currency'=>$account['currency'],
                'idempotent_replay'=>false,
            ];
        });
    }

    private function move(
        string $userId,
        string $accountType,
        int $amountMinor,
        string $direction,
        string $referenceType,
        string $referenceId,
        string $idempotencyKey,
        string $transactionType,
    ): array {
        $accountType = strtoupper(trim($accountType));
        if (!in_array($accountType, self::ACCOUNT_TYPES, true)) {
            throw new DomainException('Invalid wallet account type.');
        }
        if ($amountMinor <= 0) {
            throw new DomainException('Amount must be positive.');
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 190) {
            throw new DomainException('Invalid idempotency key.');
        }
        if ($referenceType === '' || $referenceId === '') {
            throw new DomainException('Financial reference is required.');
        }

        return Database::transaction(function (PDO $pdo) use (
            $userId,
            $accountType,
            $amountMinor,
            $direction,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $transactionType,
        ): array {
            $scope = 'wallet:' . $userId;
            $idem = $pdo->prepare(
                'INSERT IGNORE INTO idempotency_keys (`key`,scope) VALUES (:key,:scope)'
            );
            $idem->execute(['key' => $idempotencyKey, 'scope' => $scope]);

            if ($idem->rowCount() === 0) {
                return $this->previousIdempotentResult($pdo, $scope, $idempotencyKey);
            }

            $stmt = $pdo->prepare(
                'SELECT wa.* FROM wallet_accounts wa '
                . 'JOIN wallets w ON w.id=wa.wallet_id '
                . 'WHERE w.user_id=:user_id AND wa.type=:type FOR UPDATE'
            );
            $stmt->execute(['user_id' => $userId, 'type' => $accountType]);
            $account = $stmt->fetch();
            if (!$account) {
                throw new DomainException('Wallet account not found.');
            }

            $before = (int) $account['balance_minor'];
            $after = $direction === 'CREDIT' ? $before + $amountMinor : $before - $amountMinor;
            if ($after < 0) {
                throw new DomainException('Insufficient balance.');
            }

            $transactionId = $this->uuid();
            $correlationId = $this->uuid();
            $stmt = $pdo->prepare(
                "INSERT INTO financial_transactions (id,user_id,type,status,reference_type,reference_id,correlation_id,metadata) "
                . "VALUES (:id,:user_id,:type,'COMPLETED',:reference_type,:reference_id,:correlation_id,:metadata)"
            );
            $stmt->execute([
                'id' => $transactionId,
                'user_id' => $userId,
                'type' => strtoupper(trim($transactionType)),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'correlation_id' => $correlationId,
                'metadata' => '{}',
            ]);

            $stmt = $pdo->prepare('UPDATE wallet_accounts SET balance_minor=:balance, updated_at=NOW() WHERE id=:id');
            $stmt->execute(['balance' => $after, 'id' => $account['id']]);

            $stmt = $pdo->prepare(
                'INSERT INTO ledger_entries '
                . '(id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) '
                . 'VALUES (:id,:transaction_id,:account_id,:direction,:amount,:before,:after,:reference_type,:reference_id,:correlation_id)'
            );
            $stmt->execute([
                'id' => $this->uuid(),
                'transaction_id' => $transactionId,
                'account_id' => $account['id'],
                'direction' => $direction,
                'amount' => $amountMinor,
                'before' => $before,
                'after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'correlation_id' => $correlationId,
            ]);

            $stmt = $pdo->prepare(
                'UPDATE idempotency_keys SET transaction_id=:transaction_id WHERE `key`=:key AND scope=:scope'
            );
            $stmt->execute(['transaction_id' => $transactionId, 'key' => $idempotencyKey, 'scope' => $scope]);

            return [
                'transaction_id' => $transactionId,
                'correlation_id' => $correlationId,
                'balance_minor' => $after,
                'currency' => $account['currency'],
                'idempotent_replay' => false,
            ];
        });
    }

    private function previousIdempotentResult(PDO $pdo, string $scope, string $key): array
    {
        $stmt = $pdo->prepare(
            'SELECT ft.id AS transaction_id, ft.correlation_id, le.balance_after_minor, wa.currency '
            . 'FROM idempotency_keys ik '
            . 'JOIN financial_transactions ft ON ft.id=ik.transaction_id '
            . 'JOIN ledger_entries le ON le.transaction_id=ft.id '
            . 'JOIN wallet_accounts wa ON wa.id=le.account_id '
            . 'WHERE ik.`key`=:key AND ik.scope=:scope LIMIT 1'
        );
        $stmt->execute(['key' => $key, 'scope' => $scope]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new DomainException('Idempotent operation is not available.');
        }

        return [
            'transaction_id' => $row['transaction_id'],
            'correlation_id' => $row['correlation_id'],
            'balance_minor' => (int) $row['balance_after_minor'],
            'currency' => $row['currency'],
            'idempotent_replay' => true,
        ];
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
