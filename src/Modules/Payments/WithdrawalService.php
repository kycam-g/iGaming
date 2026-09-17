<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Core\Database\Database;
use App\Core\Support\Env;
use App\Modules\Wallet\WalletService;
use DomainException;
use PDO;
use Throwable;

final class WithdrawalService
{
    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly GatewayConfigRepository $configs,
        private readonly WalletService $wallet,
        private readonly WithdrawalPolicyService $policy,
    ) {}

    public function availableGateways(): array
    {
        return $this->configs->publicFor('WITHDRAWAL');
    }

    public function payoutAccounts(string $userId): array
    {
        $this->ensureDefaultCpfAccount($userId);
        $stmt = Database::connection()->prepare(
            'SELECT id,name,type,key_type,key_value,is_active,is_verified,created_at,updated_at '
            . 'FROM user_payout_accounts WHERE user_id=:user_id AND is_active=1 ORDER BY is_verified DESC,id ASC'
        );
        $stmt->execute(['user_id'=>$userId]);
        return array_map(static fn(array $r): array => [
            'id'=>(int)$r['id'],
            'name'=>(string)$r['name'],
            'type'=>(string)$r['type'],
            'key_type'=>(string)$r['key_type'],
            'key_value'=>(string)$r['key_value'],
            'is_verified'=>(bool)$r['is_verified'],
            'created_at'=>(string)$r['created_at'],
        ], $stmt->fetchAll());
    }

    public function createRequest(string $userId, int $amountMinor, ?string $gatewayCode, int $payoutAccountId, string $idempotencyKey): array
    {
        if ($amountMinor <= 0) throw new DomainException('Informe um valor de saque válido.');
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 190) throw new DomainException('Chave de idempotência inválida.');

        $config = $this->configs->resolve($gatewayCode, 'WITHDRAWAL');
        $min = (int)($config['min_withdrawal_minor'] ?? 0);
        $max = $config['max_withdrawal_minor'] === null ? null : (int)$config['max_withdrawal_minor'];
        if ($amountMinor < $min) throw new DomainException('O valor está abaixo do mínimo permitido para saque.');
        if ($max !== null && $amountMinor > $max) throw new DomainException('O valor excede o máximo permitido para saque.');

        $this->ensureDefaultCpfAccount($userId);

        return Database::transaction(function(PDO $pdo) use ($userId,$amountMinor,$config,$payoutAccountId,$idempotencyKey): array {
            $this->policy->assertAllowed($pdo,$userId,$amountMinor);
            $userStmt=$pdo->prepare('SELECT id,status,cpf,phone,public_id FROM users WHERE id=:id FOR UPDATE');
            $userStmt->execute(['id'=>$userId]);
            $user=$userStmt->fetch();
            if(!$user || (string)$user['status']!=='ACTIVE') throw new DomainException('Usuário não está apto a solicitar saque.');

            $existing=$pdo->prepare("SELECT * FROM payment_transactions WHERE user_id=:user_id AND kind='WITHDRAWAL' AND idempotency_key=:idem LIMIT 1");
            $existing->execute(['user_id'=>$userId,'idem'=>$idempotencyKey]);
            if($row=$existing->fetch()) return $this->mapPayment($row);

            $account=$pdo->prepare('SELECT * FROM user_payout_accounts WHERE id=:id AND user_id=:user_id AND is_active=1 LIMIT 1 FOR UPDATE');
            $account->execute(['id'=>$payoutAccountId,'user_id'=>$userId]);
            $payout=$account->fetch();
            if(!$payout) throw new DomainException('Conta PIX de recebimento inválida.');
            if(!(bool)$payout['is_verified']) throw new DomainException('A chave PIX precisa estar vinculada aos dados cadastrados do usuário.');

            $paymentId=$this->uuid();
            $metadata=[
                'payout_account_id'=>(int)$payout['id'],
                'pix'=>[
                    'key_type'=>(string)$payout['key_type'],
                    'key_value'=>(string)$payout['key_value'],
                    'holder_document'=>(string)($user['cpf'] ?? ''),
                ],
                'review'=>['required'=>true],
            ];
            $stmt=$pdo->prepare(
                "INSERT INTO payment_transactions (id,user_id,gateway_code,kind,status,amount_minor,currency,idempotency_key,metadata) "
                . "VALUES (:id,:user_id,:gateway,'WITHDRAWAL','REVIEW',:amount,'BRL',:idem,:metadata)"
            );
            $stmt->execute([
                'id'=>$paymentId,'user_id'=>$userId,'gateway'=>(string)$config['code'],'amount'=>$amountMinor,
                'idem'=>$idempotencyKey,'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
            ]);

            $this->wallet->debit(
                $userId,'CASH',$amountMinor,'PAYMENT_WITHDRAWAL',$paymentId,
                'withdrawal-hold:'.$paymentId,'WITHDRAWAL_HOLD',$pdo
            );

            $row=$pdo->prepare('SELECT * FROM payment_transactions WHERE id=:id');
            $row->execute(['id'=>$paymentId]);
            return $this->mapPayment((array)$row->fetch());
        });
    }

    public function listForUser(string $userId, int $limit=20): array
    {
        $limit=max(1,min(50,$limit));
        $stmt=Database::connection()->prepare(
            "SELECT id,gateway_code,kind,status,amount_minor,currency,external_id,metadata,created_at,updated_at "
            . "FROM payment_transactions WHERE user_id=:user_id AND kind='WITHDRAWAL' ORDER BY created_at DESC LIMIT $limit"
        );
        $stmt->execute(['user_id'=>$userId]);
        return array_map([$this,'mapPayment'],$stmt->fetchAll());
    }

    public function reject(string $paymentId, string $adminId, string $reason): array
    {
        $reason=trim($reason);
        if(strlen($reason)<3) throw new DomainException('Informe o motivo da reprovação.');

        return Database::transaction(function(PDO $pdo) use($paymentId,$adminId,$reason): array {
            $stmt=$pdo->prepare("SELECT * FROM payment_transactions WHERE id=:id AND kind='WITHDRAWAL' FOR UPDATE");
            $stmt->execute(['id'=>$paymentId]);
            $payment=$stmt->fetch();
            if(!$payment) throw new DomainException('Saque não encontrado.');
            if((string)$payment['status']==='CANCELLED') return $this->mapPayment($payment);
            if((string)$payment['status']!=='REVIEW') throw new DomainException('Somente saques em revisão podem ser reprovados.');

            $this->wallet->credit(
                (string)$payment['user_id'],'CASH',(int)$payment['amount_minor'],'PAYMENT_WITHDRAWAL',$paymentId,
                'withdrawal-refund:'.$paymentId,'WITHDRAWAL_REFUND',$pdo
            );

            $metadata=json_decode((string)$payment['metadata'],true) ?: [];
            $metadata['review']=['required'=>true,'decision'=>'REJECTED','admin_id'=>$adminId,'reason'=>$reason,'at'=>gmdate('c')];
            $up=$pdo->prepare("UPDATE payment_transactions SET status='CANCELLED',metadata=:metadata,updated_at=NOW() WHERE id=:id");
            $up->execute(['metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId]);
            $stmt->execute(['id'=>$paymentId]);
            return $this->mapPayment((array)$stmt->fetch());
        });
    }

    public function approve(string $paymentId, string $adminId, string $note=''): array
    {
        $pdo=Database::connection();
        $payment=Database::transaction(function(PDO $tx) use($paymentId,$adminId,$note): array {
            $stmt=$tx->prepare("SELECT * FROM payment_transactions WHERE id=:id AND kind='WITHDRAWAL' FOR UPDATE");
            $stmt->execute(['id'=>$paymentId]);
            $row=$stmt->fetch();
            if(!$row) throw new DomainException('Saque não encontrado.');
            if(in_array((string)$row['status'],['PROCESSING','PAID'],true)) { $row['_claimed']=false; return $row; }
            if((string)$row['status']!=='REVIEW') throw new DomainException('Somente saques em revisão podem ser aprovados.');
            $metadata=json_decode((string)$row['metadata'],true) ?: [];
            $metadata['review']=['required'=>true,'decision'=>'APPROVED','admin_id'=>$adminId,'note'=>trim($note),'at'=>gmdate('c')];
            $up=$tx->prepare("UPDATE payment_transactions SET status='PROCESSING',metadata=:metadata,updated_at=NOW() WHERE id=:id");
            $up->execute(['metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId]);
            $row['status']='PROCESSING';$row['metadata']=json_encode($metadata);$row['_claimed']=true;
            return $row;
        });

        $claimed=(bool)($payment['_claimed'] ?? false);
        unset($payment['_claimed']);
        if(!$claimed) return $this->mapPayment($payment);

        try {
            $config=$this->configs->configForRuntime((string)$payment['gateway_code']);
            if(empty($config['enabled']) || empty($config['withdrawal_enabled'])) throw new DomainException('Gateway de saque está desabilitado.');
            $gateway=$this->gateways->resolve($config);
            $metadata=json_decode((string)$payment['metadata'],true) ?: [];
            $pix=(array)($metadata['pix'] ?? []);
            $postback=(string)(($config['settings']['webhook_url'] ?? '') ?: rtrim((string)Env::get('APP_URL',''),'/').'/api/webhooks/payments/'.(string)$config['code']);
            $result=$gateway->createWithdrawal([
                'payment_id'=>(string)$payment['id'],
                'amount_minor'=>(int)$payment['amount_minor'],
                'currency'=>(string)$payment['currency'],
                'pix'=>$pix,
                'postback_url'=>$postback,
            ]);
            $remoteStatus=strtoupper((string)($result['status'] ?? 'PROCESSING'));
            $status=in_array($remoteStatus,['PAID','PROCESSING','PENDING'],true) ? ($remoteStatus==='PENDING'?'PROCESSING':$remoteStatus) : 'PROCESSING';
            $metadata['gateway']=array_merge((array)($metadata['gateway'] ?? []),(array)($result['metadata'] ?? []));
            $up=$pdo->prepare('UPDATE payment_transactions SET status=:status,external_id=:external_id,metadata=:metadata,updated_at=NOW() WHERE id=:id');
            $up->execute([
                'status'=>$status,'external_id'=>($result['external_id']??null) ?: null,
                'metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId,
            ]);
        } catch(Throwable $e) {
            $metadata=json_decode((string)$payment['metadata'],true) ?: [];
            $metadata['gateway_error']=['message'=>$e->getMessage(),'at'=>gmdate('c')];
            $pdo->prepare("UPDATE payment_transactions SET status='REVIEW',metadata=:metadata,updated_at=NOW() WHERE id=:id")
                ->execute(['metadata'=>json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId]);
            throw $e;
        }

        $stmt=$pdo->prepare('SELECT * FROM payment_transactions WHERE id=:id');
        $stmt->execute(['id'=>$paymentId]);
        return $this->mapPayment((array)$stmt->fetch());
    }


    /**
     * Reconciles a withdrawal that is already PROCESSING.
     *
     * Pixup does not currently document a dedicated GET transaction endpoint in
     * its public v2 docs. Its cash-out endpoint is idempotent by external_id,
     * therefore we safely replay the same local payment id + payout payload and
     * consume the returned state without creating a second withdrawal.
     */
    public function reconcile(string $paymentId, ?string $adminId = null, string $source = 'ADMIN'): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') throw new DomainException('Saque não informado.');

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT pt.*,u.username,u.email,u.cpf,u.phone FROM payment_transactions pt JOIN users u ON u.id=pt.user_id WHERE pt.id=:id AND pt.kind='WITHDRAWAL' LIMIT 1");
        $stmt->execute(['id'=>$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) throw new DomainException('Saque não encontrado.');

        $status = (string)$payment['status'];
        if (in_array($status, ['PAID','FAILED','CANCELLED'], true)) return $this->mapPayment($payment);
        if ($status !== 'PROCESSING') throw new DomainException('Somente saques em processamento podem ser reconciliados.');

        $config = $this->configs->configForRuntime((string)$payment['gateway_code']);
        if (empty($config['enabled']) || empty($config['withdrawal_enabled'])) {
            throw new DomainException('Gateway de saque está desabilitado.');
        }
        $gateway = $this->gateways->resolve($config);
        $metadata = json_decode((string)$payment['metadata'], true) ?: [];
        $pix = (array)($metadata['pix'] ?? []);

        try {
            if ((string)$payment['gateway_code'] === 'pixup') {
                $postback = (string)(($config['settings']['webhook_url'] ?? '') ?: rtrim((string)Env::get('APP_URL',''),'/').'/api/webhooks/payments/pixup');
                // Safe idempotent replay: Pixup documents external_id as unique and
                // states that resending the same external_id returns the same transaction.
                $remote = $gateway->createWithdrawal([
                    'payment_id'=>(string)$payment['id'],
                    'amount_minor'=>(int)$payment['amount_minor'],
                    'currency'=>(string)$payment['currency'],
                    'pix'=>$pix,
                    'recipient_name'=>(string)($payment['username'] ?? ''),
                    'postback_url'=>$postback,
                ]);
            } else {
                $remote = $gateway->getTransaction((string)($payment['external_id'] ?? ''));
            }
        } catch (Throwable $e) {
            $this->recordReconciliationAttempt($paymentId, $source, $adminId, null, $e->getMessage());
            throw $e;
        }

        $remoteStatus = strtoupper(trim((string)($remote['status'] ?? 'PROCESSING')));
        $normalized = match ($remoteStatus) {
            'PAID', 'CONFIRMED', 'COMPLETED', 'SUCCESS', 'SUCCEEDED' => 'PAID',
            'FAILED', 'CANCELLED', 'CANCELED', 'REJECTED' => 'FAILED',
            default => 'PROCESSING',
        };
        $remoteExternalId = trim((string)($remote['external_id'] ?? ''));
        $remoteMeta = (array)($remote['metadata'] ?? []);

        if ($normalized === 'FAILED') {
            return Database::transaction(function(PDO $tx) use ($paymentId,$source,$adminId,$remoteStatus,$remoteExternalId,$remoteMeta): array {
                $lock=$tx->prepare("SELECT * FROM payment_transactions WHERE id=:id AND kind='WITHDRAWAL' FOR UPDATE");
                $lock->execute(['id'=>$paymentId]);
                $row=$lock->fetch();
                if(!$row) throw new DomainException('Saque não encontrado.');
                if((string)$row['status']==='PAID') return $this->mapPayment($row);
                if(in_array((string)$row['status'],['FAILED','CANCELLED'],true)) return $this->mapPayment($row);
                if((string)$row['status']!=='PROCESSING') throw new DomainException('Saque mudou de status durante a reconciliação.');

                $this->wallet->credit(
                    (string)$row['user_id'],'CASH',(int)$row['amount_minor'],'PAYMENT_WITHDRAWAL',(string)$row['id'],
                    'withdrawal-gateway-refund:'.(string)$row['id'],'WITHDRAWAL_GATEWAY_REFUND',$tx
                );
                $meta=json_decode((string)($row['metadata']??'{}'),true) ?: [];
                $meta['reconciliation']=$this->reconciliationMeta($meta['reconciliation']??null,$source,$adminId,$remoteStatus,null,$remoteMeta);
                $meta['gateway_failure']=array_merge((array)($meta['gateway_failure']??[]),[
                    'event'=>'reconciliation.failed','remote_status'=>$remoteStatus,'refunded_to_user'=>true,'at'=>gmdate('c'),
                ]);
                $up=$tx->prepare("UPDATE payment_transactions SET status='FAILED',external_id=COALESCE(NULLIF(:external_id,''),external_id),metadata=:metadata,updated_at=NOW() WHERE id=:id");
                $up->execute(['external_id'=>$remoteExternalId,'metadata'=>json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId]);
                $lock->execute(['id'=>$paymentId]);
                return $this->mapPayment((array)$lock->fetch());
            });
        }

        return Database::transaction(function(PDO $tx) use ($paymentId,$source,$adminId,$remoteStatus,$normalized,$remoteExternalId,$remoteMeta): array {
            $lock=$tx->prepare("SELECT * FROM payment_transactions WHERE id=:id AND kind='WITHDRAWAL' FOR UPDATE");
            $lock->execute(['id'=>$paymentId]);
            $row=$lock->fetch();
            if(!$row) throw new DomainException('Saque não encontrado.');
            if(in_array((string)$row['status'],['PAID','FAILED','CANCELLED'],true)) return $this->mapPayment($row);
            if((string)$row['status']!=='PROCESSING') throw new DomainException('Saque mudou de status durante a reconciliação.');
            $meta=json_decode((string)($row['metadata']??'{}'),true) ?: [];
            $meta['reconciliation']=$this->reconciliationMeta($meta['reconciliation']??null,$source,$adminId,$remoteStatus,null,$remoteMeta);
            if($normalized==='PAID') {
                $meta['gateway_confirmation']=array_merge((array)($meta['gateway_confirmation']??[]),[
                    'event'=>'reconciliation.confirmed','remote_transaction_id'=>$remoteExternalId!==''?$remoteExternalId:null,'at'=>gmdate('c'),
                ]);
            }
            $up=$tx->prepare("UPDATE payment_transactions SET status=:status,external_id=COALESCE(NULLIF(:external_id,''),external_id),metadata=:metadata,updated_at=NOW() WHERE id=:id");
            $up->execute(['status'=>$normalized,'external_id'=>$remoteExternalId,'metadata'=>json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId]);
            $lock->execute(['id'=>$paymentId]);
            return $this->mapPayment((array)$lock->fetch());
        });
    }

    public function reconcileStale(int $olderThanMinutes = 5, int $limit = 50): array
    {
        $olderThanMinutes=max(1,min(1440,$olderThanMinutes));
        $limit=max(1,min(200,$limit));
        $pdo=Database::connection();
        $stmt=$pdo->query("SELECT id FROM payment_transactions WHERE kind='WITHDRAWAL' AND status='PROCESSING' AND gateway_code='pixup' AND updated_at <= DATE_SUB(NOW(), INTERVAL {$olderThanMinutes} MINUTE) ORDER BY updated_at ASC LIMIT {$limit}");
        $ids=array_map(static fn(array $r): string => (string)$r['id'],$stmt->fetchAll());
        $results=[];
        foreach($ids as $id){
            try{
                $r=$this->reconcile($id,null,'SYSTEM');
                $results[]=['id'=>$id,'ok'=>true,'status'=>$r['status']];
            }catch(Throwable $e){
                $results[]=['id'=>$id,'ok'=>false,'error'=>$e->getMessage()];
            }
        }
        return ['checked'=>count($ids),'results'=>$results];
    }

    private function recordReconciliationAttempt(string $paymentId,string $source,?string $adminId,?string $remoteStatus,?string $error): void
    {
        Database::transaction(function(PDO $pdo) use($paymentId,$source,$adminId,$remoteStatus,$error): void {
            $stmt=$pdo->prepare("SELECT metadata FROM payment_transactions WHERE id=:id AND kind='WITHDRAWAL' FOR UPDATE");
            $stmt->execute(['id'=>$paymentId]);
            $raw=$stmt->fetchColumn();
            if($raw===false) return;
            $meta=json_decode((string)$raw,true) ?: [];
            $meta['reconciliation']=$this->reconciliationMeta($meta['reconciliation']??null,$source,$adminId,$remoteStatus,$error,[]);
            $pdo->prepare('UPDATE payment_transactions SET metadata=:metadata,updated_at=updated_at WHERE id=:id')
                ->execute(['metadata'=>json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'id'=>$paymentId]);
        });
    }

    private function reconciliationMeta(mixed $current,string $source,?string $adminId,?string $remoteStatus,?string $error,array $remoteMeta): array
    {
        $state=is_array($current)?$current:[];
        $attempt=(int)($state['attempts']??0)+1;
        $entry=['at'=>gmdate('c'),'source'=>$source,'admin_id'=>$adminId,'remote_status'=>$remoteStatus,'error'=>$error];
        if($remoteMeta!==[]) $entry['remote_metadata']=$remoteMeta;
        $history=is_array($state['history']??null)?$state['history']:[];
        array_unshift($history,$entry);
        $history=array_slice($history,0,10);
        return ['attempts'=>$attempt,'last_checked_at'=>$entry['at'],'last_source'=>$source,'last_remote_status'=>$remoteStatus,'last_error'=>$error,'history'=>$history];
    }

    private function ensureDefaultCpfAccount(string $userId): void
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT cpf FROM users WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$userId]);
        $cpf=preg_replace('/\D+/','',(string)$stmt->fetchColumn()) ?: '';
        if(strlen($cpf)!==11) throw new DomainException('CPF do usuário precisa estar preenchido antes do saque.');
        $exists=$pdo->prepare("SELECT id FROM user_payout_accounts WHERE user_id=:user_id AND type='PIX' AND key_type='CPF' AND key_value=:cpf LIMIT 1");
        $exists->execute(['user_id'=>$userId,'cpf'=>$cpf]);
        if($exists->fetchColumn()) return;
        $ins=$pdo->prepare("INSERT INTO user_payout_accounts (user_id,name,type,key_type,key_value,is_active,is_verified) VALUES (:user_id,'PIX CPF','PIX','CPF',:cpf,1,1)");
        $ins->execute(['user_id'=>$userId,'cpf'=>$cpf]);
    }

    private function mapPayment(array $r): array
    {
        $metadata=json_decode((string)($r['metadata'] ?? '{}'),true);
        return [
            'id'=>(string)$r['id'],'gateway_code'=>(string)$r['gateway_code'],'kind'=>(string)$r['kind'],'status'=>(string)$r['status'],
            'amount_minor'=>(int)$r['amount_minor'],'currency'=>(string)$r['currency'],'external_id'=>empty($r['external_id'])?null:(string)$r['external_id'],
            'metadata'=>is_array($metadata)?$metadata:[],'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],
        ];
    }

    private function uuid(): string
    {
        $data=random_bytes(16);$data[6]=chr((ord($data[6])&0x0f)|0x40);$data[8]=chr((ord($data[8])&0x3f)|0x80);$hex=bin2hex($data);
        return sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20));
    }
}
