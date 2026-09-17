<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Core\Database\Database;
use App\Core\Support\Env;
use App\Integrations\Payments\Pixup\PixupGateway;
use App\Modules\Wallet\WalletService;
use DomainException;
use PDO;
use PDOException;

final class PaymentService
{
    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly GatewayConfigRepository $configs,
        private readonly WalletService $wallet,
    ) {}

    public function availableGateways(string $operation = 'DEPOSIT'): array
    {
        return $this->configs->publicFor($operation);
    }

    public function createDeposit(string $userId, ?string $gatewayCode, int $amountMinor, string $idempotencyKey): array
    {
        if ($amountMinor < 100) throw new DomainException('O depósito mínimo é R$ 1,00.');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 190) throw new DomainException('Invalid idempotency key.');

        $config = $this->configs->resolve($gatewayCode, 'DEPOSIT');
        $min = (int) ($config['min_deposit_minor'] ?? 100);
        $max = $config['max_deposit_minor'] === null ? null : (int) $config['max_deposit_minor'];
        if ($amountMinor < $min) throw new DomainException('Valor abaixo do mínimo permitido pelo gateway.');
        if ($max !== null && $amountMinor > $max) throw new DomainException('Valor acima do máximo permitido pelo gateway.');

        $existing = $this->findByIdempotency($userId, 'DEPOSIT', $idempotencyKey);
        if ($existing) return $this->formatPayment($existing, true);

        $paymentId = $this->uuid();
        try {
            Database::transaction(function (PDO $pdo) use ($paymentId, $userId, $config, $amountMinor, $idempotencyKey): void {
                $stmt = $pdo->prepare("INSERT INTO payment_transactions (id,user_id,gateway_code,kind,status,amount_minor,currency,idempotency_key,metadata) VALUES (:id,:user_id,:gateway,'DEPOSIT','PENDING',:amount,'BRL',:idem,'{}')");
                $stmt->execute(['id'=>$paymentId,'user_id'=>$userId,'gateway'=>$config['code'],'amount'=>$amountMinor,'idem'=>$idempotencyKey]);
            });
        } catch (PDOException $e) {
            $existing = $this->findByIdempotency($userId, 'DEPOSIT', $idempotencyKey);
            if ($existing) return $this->formatPayment($existing, true);
            throw $e;
        }

        try {
            $gateway = $this->gateways->resolve($config);
            $payer = $this->payer($userId);
            $postback = (string) (($config['settings']['webhook_url'] ?? '') ?: rtrim((string) Env::get('APP_URL', ''), '/') . '/api/webhooks/payments/' . $config['code']);
            $created = $gateway->createDeposit([
                'payment_id'=>$paymentId,
                'user_id'=>$userId,
                'amount_minor'=>$amountMinor,
                'currency'=>'BRL',
                'payer'=>$payer,
                'postback_url'=>$postback,
            ]);
            $status = $this->normalizeStatus((string) ($created['status'] ?? 'PENDING'));
            $stmt=Database::connection()->prepare('UPDATE payment_transactions SET external_id=:external_id,status=:status,payment_code=:payment_code,payment_qr_code=:qr,expires_at=:expires_at,metadata=:metadata WHERE id=:id');
            $stmt->execute([
                'external_id'=>$created['external_id']?:null,
                'status'=>$status,
                'payment_code'=>$created['payment_code']??null,
                'qr'=>$created['qr_code']??null,
                'expires_at'=>$created['expires_at']??null,
                'metadata'=>json_encode($created['metadata']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'id'=>$paymentId,
            ]);
        } catch (\Throwable $e) {
            Database::connection()->prepare("UPDATE payment_transactions SET status='FAILED',metadata=:metadata WHERE id=:id AND status='PENDING'")->execute([
                'metadata'=>json_encode(['gateway_error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'id'=>$paymentId,
            ]);
            throw $e;
        }
        return $this->getForUser($userId, $paymentId);
    }

    public function listForUser(string $userId, int $limit = 30): array
    {
        $limit=max(1,min(100,$limit));
        $stmt=Database::connection()->prepare('SELECT * FROM payment_transactions WHERE user_id=:user_id ORDER BY created_at DESC LIMIT '.$limit);
        $stmt->execute(['user_id'=>$userId]);
        return array_map(fn(array $row)=>$this->formatPayment($row,false),$stmt->fetchAll());
    }

    public function confirmSandboxDeposit(string $userId, string $paymentId): array
    {
        if (strtolower((string) Env::get('APP_ENV','production')) !== 'local') throw new DomainException('Sandbox confirmation is only available in local environment.');
        $row=$this->getRawForUser($userId,$paymentId);
        if($row['gateway_code']!=='sandbox_pix'||$row['kind']!=='DEPOSIT') throw new DomainException('Sandbox deposit not found.');
        $this->creditDepositOnce($row);
        return $this->getForUser($userId,$paymentId);
    }

    public function processWebhook(string $gatewayCode, string $rawBody, array $headers, array $payload): array
    {
        $config=$this->configs->configForRuntime($gatewayCode);
        if(empty($config['enabled'])) throw new DomainException('Gateway desabilitado.');
        $gateway=$this->gateways->resolve($config);
        if($gateway instanceof PixupGateway) $gateway->validateWebhook($rawBody,$headers);

        $data=is_array($payload['data']??null)?$payload['data']:$payload;
        $event=(string)($payload['event']??$this->header($headers,'X-Webhook-Event')??'unknown');
        $remoteTransaction=(string)($payload['transaction_id']??$data['transaction_id']??'');
        $localPaymentId=(string)($data['external_id']??$payload['external_id']??'');
        $eventKey=(string)($this->header($headers,'X-Webhook-Id')??'');
        if($eventKey==='') $eventKey=hash('sha256',$gatewayCode.'|'.$event.'|'.$remoteTransaction.'|'.$rawBody);

        $inserted=false;
        try {
            $stmt=Database::connection()->prepare('INSERT INTO payment_webhook_events (gateway_code,event_key,external_id,event_type,payload) VALUES (:gateway,:event_key,:external_id,:event_type,:payload)');
            $stmt->execute(['gateway'=>$gatewayCode,'event_key'=>$eventKey,'external_id'=>$remoteTransaction?:null,'event_type'=>$event,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $inserted=true;
        } catch (PDOException $e) {
            if((string)$e->getCode()!=='23000') throw $e;
        }
        if(!$inserted) return ['received'=>true,'duplicate'=>true];

        $payment=$this->findWebhookPayment($gatewayCode,$localPaymentId,$remoteTransaction);
        if(!$payment) {
            $this->markWebhookProcessed($gatewayCode,$eventKey);
            return ['received'=>true,'matched'=>false];
        }

        if($event==='cashin.confirmed' || strtolower((string)($data['status']??''))==='paid' || strtolower((string)($data['status']??''))==='confirmed') {
            $this->creditDepositOnce($payment);
        } elseif($event==='cashin.expired') {
            Database::connection()->prepare("UPDATE payment_transactions SET status='EXPIRED' WHERE id=:id AND status IN ('PENDING','PROCESSING')")->execute(['id'=>$payment['id']]);
        } elseif($event==='cashin.refunded') {
            Database::connection()->prepare("UPDATE payment_transactions SET status='REVIEW' WHERE id=:id")->execute(['id'=>$payment['id']]);
        }
        $this->markWebhookProcessed($gatewayCode,$eventKey);
        return ['received'=>true,'matched'=>true,'payment_id'=>$payment['id']];
    }

    public function getForUser(string $userId,string $paymentId): array { return $this->formatPayment($this->getRawForUser($userId,$paymentId),false); }

    private function creditDepositOnce(array $payment): void
    {
        $claim=Database::transaction(function(PDO $pdo) use($payment): ?array {
            $stmt=$pdo->prepare('SELECT * FROM payment_transactions WHERE id=:id FOR UPDATE'); $stmt->execute(['id'=>$payment['id']]); $row=$stmt->fetch();
            if(!$row) throw new DomainException('Payment not found.');
            if($row['status']==='PAID') return null;
            if(!in_array($row['status'],['PENDING','PROCESSING'],true)) throw new DomainException('Deposit cannot be confirmed in its current status.');
            $pdo->prepare("UPDATE payment_transactions SET status='PROCESSING' WHERE id=:id")->execute(['id'=>$row['id']]);
            return $row;
        });
        if($claim===null) return;
        $this->wallet->credit((string)$claim['user_id'],'CASH',(int)$claim['amount_minor'],'payment_deposit',(string)$claim['id'],'payment-credit:'.$claim['id'],'DEPOSIT');
        Database::connection()->prepare("UPDATE payment_transactions SET status='PAID' WHERE id=:id")->execute(['id'=>$claim['id']]);
    }

    private function findWebhookPayment(string $gatewayCode,string $localPaymentId,string $remoteTransaction): ?array
    {
        if($localPaymentId!=='') { $stmt=Database::connection()->prepare('SELECT * FROM payment_transactions WHERE id=:id AND gateway_code=:g LIMIT 1'); $stmt->execute(['id'=>$localPaymentId,'g'=>$gatewayCode]); if($r=$stmt->fetch())return $r; }
        if($remoteTransaction!=='') { $stmt=Database::connection()->prepare('SELECT * FROM payment_transactions WHERE external_id=:x AND gateway_code=:g LIMIT 1'); $stmt->execute(['x'=>$remoteTransaction,'g'=>$gatewayCode]); if($r=$stmt->fetch())return $r; }
        return null;
    }
    private function markWebhookProcessed(string $g,string $k): void { Database::connection()->prepare('UPDATE payment_webhook_events SET processed_at=NOW(6) WHERE gateway_code=:g AND event_key=:k')->execute(['g'=>$g,'k'=>$k]); }
    private function findByIdempotency(string $u,string $kind,string $key): ?array { $s=Database::connection()->prepare('SELECT * FROM payment_transactions WHERE user_id=:u AND kind=:k AND idempotency_key=:i LIMIT 1');$s->execute(['u'=>$u,'k'=>$kind,'i'=>$key]);return $s->fetch()?:null; }
    private function getRawForUser(string $u,string $id): array { $s=Database::connection()->prepare('SELECT * FROM payment_transactions WHERE id=:id AND user_id=:u LIMIT 1');$s->execute(['id'=>$id,'u'=>$u]);$r=$s->fetch();if(!$r)throw new DomainException('Payment not found.');return $r; }
    private function payer(string $userId): array { $s=Database::connection()->prepare('SELECT username,email,cpf,phone FROM users WHERE id=:id LIMIT 1');$s->execute(['id'=>$userId]);$u=$s->fetch()?:[];return array_filter(['name'=>$u['username']??null,'email'=>$u['email']??null,'document'=>$u['cpf']??null,'phone'=>$u['phone']??null]); }
    private function normalizeStatus(string $status): string { return match(strtolower($status)){ 'paid','confirmed','completed'=>'PAID','processing'=>'PROCESSING','failed'=>'FAILED','cancelled','canceled'=>'CANCELLED','expired'=>'EXPIRED',default=>'PENDING'}; }
    private function header(array $headers,string $name): ?string { foreach($headers as $k=>$v)if(strcasecmp((string)$k,$name)===0)return is_array($v)?(string)reset($v):(string)$v;return null; }
    private function formatPayment(array $r,bool $replay): array { return ['id'=>$r['id'],'gateway_code'=>$r['gateway_code'],'kind'=>$r['kind'],'status'=>$r['status'],'amount_minor'=>(int)$r['amount_minor'],'currency'=>$r['currency'],'external_id'=>$r['external_id'],'payment_code'=>$r['payment_code'],'payment_qr_code'=>$r['payment_qr_code'],'expires_at'=>$r['expires_at'],'created_at'=>$r['created_at'],'idempotent_replay'=>$replay]; }
    private function uuid(): string { $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);$h=bin2hex($d);return sprintf('%s-%s-%s-%s-%s',substr($h,0,8),substr($h,8,4),substr($h,12,4),substr($h,16,4),substr($h,20)); }
}
