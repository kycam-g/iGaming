<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database\Database;
use App\Core\Support\BrazilIdentity;
use App\Modules\Wallet\WalletService;
use DomainException;
use PDO;

final class AdminUserService
{
    public function __construct(private readonly WalletService $wallet) {}

    public function list(array $filters = []): array
    {
        $pdo = Database::connection();
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(10, (int)($filters['limit'] ?? 25)));
        $search = trim((string)($filters['search'] ?? ''));
        $status = strtoupper(trim((string)($filters['status'] ?? '')));

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.email LIKE :search OR u.username LIKE :search OR u.cpf LIKE :search OR u.phone LIKE :search OR u.id LIKE :search OR CAST(u.public_id AS CHAR) LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if (in_array($status, ['ACTIVE', 'BLOCKED', 'SUSPENDED'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count = $pdo->prepare("SELECT COUNT(*) FROM users u {$whereSql}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));
        $page = min($page, $pages);
        $offset = ($page - 1) * $limit;

        $sql = "SELECT u.id,u.public_id,u.email,u.cpf,u.phone,u.username,u.status,u.created_at,u.updated_at,
                       COALESCE(b.cash_minor,0) AS cash_minor,
                       COALESCE(b.bonus_minor,0) AS bonus_minor,
                       COALESCE(d.deposited_minor,0) AS deposited_minor,
                       COALESCE(d.deposit_count,0) AS deposit_count,
                       s.last_seen_at,
                       COALESCE(s.active_sessions,0) AS active_sessions
                FROM users u
                LEFT JOIN (
                    SELECT w.user_id,
                           SUM(CASE WHEN wa.type='CASH' THEN wa.balance_minor ELSE 0 END) cash_minor,
                           SUM(CASE WHEN wa.type='BONUS' THEN wa.balance_minor ELSE 0 END) bonus_minor
                    FROM wallets w JOIN wallet_accounts wa ON wa.wallet_id=w.id GROUP BY w.user_id
                ) b ON b.user_id=u.id
                LEFT JOIN (
                    SELECT user_id,SUM(amount_minor) deposited_minor,COUNT(*) deposit_count
                    FROM payment_transactions WHERE kind='DEPOSIT' AND status='PAID' GROUP BY user_id
                ) d ON d.user_id=u.id
                LEFT JOIN (
                    SELECT user_id,MAX(last_used_at) last_seen_at,
                           SUM(CASE WHEN revoked_at IS NULL AND expires_at>NOW(6) THEN 1 ELSE 0 END) active_sessions
                    FROM user_sessions GROUP BY user_id
                ) s ON s.user_id=u.id
                {$whereSql}
                ORDER BY u.public_id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'users' => array_map(fn(array $r): array => $this->mapListUser($r), $stmt->fetchAll()),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => $pages],
        ];
    }

    public function detail(string $identifier): array
    {
        $pdo = Database::connection();
        $user = $this->findUser($pdo, $identifier);
        $id = (string)$user['id'];

        $stmt = $pdo->prepare("SELECT wa.type,wa.currency,wa.balance_minor FROM wallets w JOIN wallet_accounts wa ON wa.wallet_id=w.id WHERE w.user_id=:id ORDER BY FIELD(wa.type,'CASH','BONUS','AFFILIATE')");
        $stmt->execute(['id' => $id]);
        $balances = array_map(static fn(array $r): array => ['type'=>(string)$r['type'],'currency'=>(string)$r['currency'],'balance_minor'=>(int)$r['balance_minor']], $stmt->fetchAll());

        $stmt = $pdo->prepare("SELECT kind,status,COUNT(*) total,COALESCE(SUM(amount_minor),0) amount_minor FROM payment_transactions WHERE user_id=:id GROUP BY kind,status ORDER BY kind,status");
        $stmt->execute(['id' => $id]);
        $paymentSummary = array_map(static fn(array $r): array => ['kind'=>(string)$r['kind'],'status'=>(string)$r['status'],'total'=>(int)$r['total'],'amount_minor'=>(int)$r['amount_minor']], $stmt->fetchAll());

        $stmt = $pdo->prepare("SELECT id,kind,status,gateway_code,amount_minor,external_id,created_at,updated_at FROM payment_transactions WHERE user_id=:id ORDER BY created_at DESC LIMIT 100");
        $stmt->execute(['id' => $id]);
        $payments = array_map(static fn(array $r): array => ['id'=>(string)$r['id'],'kind'=>(string)$r['kind'],'status'=>(string)$r['status'],'gateway_code'=>(string)$r['gateway_code'],'amount_minor'=>(int)$r['amount_minor'],'external_id'=>$r['external_id'] ? (string)$r['external_id'] : null,'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at']], $stmt->fetchAll());

        $stmt = $pdo->prepare("SELECT le.id,wa.type account_type,le.direction,le.amount_minor,le.balance_before_minor,le.balance_after_minor,le.reference_type,le.reference_id,le.created_at FROM ledger_entries le JOIN wallet_accounts wa ON wa.id=le.account_id JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=:id ORDER BY le.created_at DESC LIMIT 50");
        $stmt->execute(['id' => $id]);
        $ledger = array_map(static fn(array $r): array => ['id'=>(string)$r['id'],'account_type'=>(string)$r['account_type'],'direction'=>(string)$r['direction'],'amount_minor'=>(int)$r['amount_minor'],'balance_before_minor'=>(int)$r['balance_before_minor'],'balance_after_minor'=>(int)$r['balance_after_minor'],'reference_type'=>(string)$r['reference_type'],'reference_id'=>(string)$r['reference_id'],'created_at'=>(string)$r['created_at']], $stmt->fetchAll());

        $stmt = $pdo->prepare("SELECT id,created_at,last_used_at,expires_at,revoked_at,CASE WHEN revoked_at IS NULL AND expires_at>NOW(6) THEN 1 ELSE 0 END active FROM user_sessions WHERE user_id=:id ORDER BY created_at DESC LIMIT 20");
        $stmt->execute(['id' => $id]);
        $sessions = array_map(static fn(array $r): array => ['id'=>(string)$r['id'],'created_at'=>(string)$r['created_at'],'last_used_at'=>$r['last_used_at'] ? (string)$r['last_used_at'] : null,'expires_at'=>(string)$r['expires_at'],'revoked_at'=>$r['revoked_at'] ? (string)$r['revoked_at'] : null,'active'=>(bool)$r['active']], $stmt->fetchAll());

        $stmt = $pdo->prepare("SELECT id,name,type,key_type,key_value,created_at FROM user_payout_accounts WHERE user_id=:id ORDER BY created_at DESC");
        $stmt->execute(['id'=>$id]);
        $payoutAccounts = array_map(static fn(array $r): array => ['id'=>(int)$r['id'],'name'=>(string)$r['name'],'type'=>(string)$r['type'],'key_type'=>(string)$r['key_type'],'key_value'=>(string)$r['key_value'],'created_at'=>(string)$r['created_at']], $stmt->fetchAll());

        return [
            'user' => ['id'=>$id,'public_id'=>(int)$user['public_id'],'email'=>(string)$user['email'],'cpf'=>$user['cpf']?(string)$user['cpf']:null,'phone'=>$user['phone']?(string)$user['phone']:null,'username'=>(string)$user['username'],'status'=>(string)$user['status'],'created_at'=>(string)$user['created_at'],'updated_at'=>(string)$user['updated_at']],
            'balances'=>$balances,'payment_summary'=>$paymentSummary,'payments'=>$payments,'ledger'=>$ledger,'sessions'=>$sessions,'payout_accounts'=>$payoutAccounts,
        ];
    }

    public function updateStatus(string $identifier, string $status): array
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, ['ACTIVE','BLOCKED','SUSPENDED'], true)) throw new DomainException('Status de usuário inválido.');
        return Database::transaction(function (PDO $pdo) use ($identifier,$status): array {
            $user = $this->findUser($pdo,$identifier,true);
            $id=(string)$user['id']; $old=(string)$user['status'];
            if($old!==$status){
                $pdo->prepare('UPDATE users SET status=:status,updated_at=NOW(6) WHERE id=:id')->execute(['status'=>$status,'id'=>$id]);
                if($status!=='ACTIVE') $pdo->prepare('UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,NOW(6)),last_used_at=NOW(6) WHERE user_id=:id AND revoked_at IS NULL')->execute(['id'=>$id]);
            }
            return ['id'=>$id,'public_id'=>(int)$user['public_id'],'old_status'=>$old,'status'=>$status];
        });
    }

    public function updateProfile(string $identifier, string $username, string $email, string $cpf, string $phone, string $status): array
    {
        $username=trim($username); $email=strtolower(trim($email)); $status=strtoupper(trim($status));
        $cpf=BrazilIdentity::cpf($cpf); $phone=BrazilIdentity::phone($phone);
        if(strlen($username)<2 || strlen($username)>64) throw new DomainException('Nome de usuário inválido.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('E-mail inválido.');
        if(!in_array($status,['ACTIVE','BLOCKED','SUSPENDED'],true)) throw new DomainException('Status inválido.');
        return Database::transaction(function(PDO $pdo) use($identifier,$username,$email,$cpf,$phone,$status): array {
            $user=$this->findUser($pdo,$identifier,true); $id=(string)$user['id'];
            $dup=$pdo->prepare('SELECT id FROM users WHERE id<>:id AND (email=:email OR username=:username OR cpf IN (:cpf1,:phone1) OR phone IN (:cpf2,:phone2)) LIMIT 1');
            $dup->execute(['email'=>$email,'username'=>$username,'cpf1'=>$cpf,'phone1'=>$phone,'cpf2'=>$cpf,'phone2'=>$phone,'id'=>$id]);
            if($dup->fetch()) throw new DomainException('E-mail, CPF, telefone ou nome de usuário já está em uso.');
            $pdo->prepare('UPDATE users SET username=:username,email=:email,cpf=:cpf,phone=:phone,status=:status,updated_at=NOW(6) WHERE id=:id')->execute(['username'=>$username,'email'=>$email,'cpf'=>$cpf,'phone'=>$phone,'status'=>$status,'id'=>$id]);
            if($status!=='ACTIVE') $pdo->prepare('UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,NOW(6)) WHERE user_id=:id AND revoked_at IS NULL')->execute(['id'=>$id]);
            return ['id'=>$id,'public_id'=>(int)$user['public_id'],'username'=>$username,'email'=>$email,'cpf'=>$cpf,'phone'=>$phone,'status'=>$status];
        });
    }

    public function resetPassword(string $identifier, string $newPassword): array
    {
        if(strlen($newPassword)<8) throw new DomainException('A nova senha precisa ter pelo menos 8 caracteres.');
        return Database::transaction(function(PDO $pdo) use($identifier,$newPassword): array {
            $user=$this->findUser($pdo,$identifier,true); $id=(string)$user['id'];
            $algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
            $pdo->prepare('UPDATE user_credentials SET password_hash=:hash,updated_at=NOW(6) WHERE user_id=:id')->execute(['hash'=>password_hash($newPassword,$algo),'id'=>$id]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,NOW(6)) WHERE user_id=:id AND revoked_at IS NULL')->execute(['id'=>$id]);
            return ['id'=>$id,'public_id'=>(int)$user['public_id']];
        });
    }

    public function adjustBalance(string $identifier, string $adminId, string $accountType, string $direction, int $amountMinor, string $reason): array
    {
        $direction=strtoupper(trim($direction)); $accountType=strtoupper(trim($accountType)); $reason=trim($reason);
        if(!in_array($accountType,['CASH','BONUS'],true)) throw new DomainException('Conta inválida.');
        if(!in_array($direction,['CREDIT','DEBIT'],true)) throw new DomainException('Movimento inválido.');
        if($amountMinor<=0) throw new DomainException('Valor deve ser maior que zero.');
        if(strlen($reason)<3) throw new DomainException('Informe o motivo do ajuste.');
        $user=$this->findUser(Database::connection(),$identifier); $id=(string)$user['id'];
        $referenceId='admin-'.$adminId.'-'.bin2hex(random_bytes(8));
        $idem='admin-adjust:'.$referenceId;
        $result=$direction==='CREDIT'
            ? $this->wallet->credit($id,$accountType,$amountMinor,'admin_adjustment',$referenceId,$idem,'ADMIN_CREDIT')
            : $this->wallet->debit($id,$accountType,$amountMinor,'admin_adjustment',$referenceId,$idem,'ADMIN_DEBIT');
        Database::connection()->prepare('UPDATE financial_transactions SET metadata=:metadata WHERE id=:id')->execute([
            'metadata'=>json_encode(['admin_id'=>$adminId,'reason'=>$reason],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'id'=>$result['transaction_id'],
        ]);
        return ['id'=>$id,'public_id'=>(int)$user['public_id'],'account_type'=>$accountType,'direction'=>$direction,'amount_minor'=>$amountMinor,'reason'=>$reason,'transaction'=>$result];
    }

    private function findUser(PDO $pdo,string $identifier,bool $forUpdate=false): array
    {
        $identifier=trim($identifier);
        $byPublic=ctype_digit($identifier);
        $sql=$byPublic?'SELECT id,public_id,email,cpf,phone,username,status,created_at,updated_at FROM users WHERE public_id=:v LIMIT 1':'SELECT id,public_id,email,cpf,phone,username,status,created_at,updated_at FROM users WHERE id=:v LIMIT 1';
        if($forUpdate)$sql.=' FOR UPDATE';
        $stmt=$pdo->prepare($sql);$stmt->execute(['v'=>$identifier]);$user=$stmt->fetch();
        if(!$user) throw new DomainException('Usuário não encontrado.');
        return $user;
    }

    private function mapListUser(array $r): array
    {
        return ['id'=>(string)$r['id'],'public_id'=>(int)$r['public_id'],'email'=>(string)$r['email'],'cpf'=>$r['cpf']?(string)$r['cpf']:null,'phone'=>$r['phone']?(string)$r['phone']:null,'username'=>(string)$r['username'],'status'=>(string)$r['status'],'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],'cash_minor'=>(int)$r['cash_minor'],'bonus_minor'=>(int)$r['bonus_minor'],'deposited_minor'=>(int)$r['deposited_minor'],'deposit_count'=>(int)$r['deposit_count'],'last_seen_at'=>$r['last_seen_at']?(string)$r['last_seen_at']:null,'active_sessions'=>(int)$r['active_sessions']];
    }
}
