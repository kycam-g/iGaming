<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class AdminFinanceService
{
    private const STATUSES=['PENDING','PROCESSING','PAID','FAILED','CANCELLED','EXPIRED','REVIEW'];

    public function deposits(array $filters): array
    {
        $pdo=Database::connection();
        $page=max(1,(int)($filters['page']??1)); $limit=min(100,max(10,(int)($filters['limit']??25))); $offset=($page-1)*$limit;
        $where=["p.kind='DEPOSIT'"]; $params=[];
        $search=trim((string)($filters['search']??''));
        if($search!==''){
            $digits=preg_replace('/\D+/','',$search)??'';
            $where[]='(p.id LIKE :q1 OR p.external_id LIKE :q2 OR u.cpf LIKE :q3 OR u.phone LIKE :q4'.($digits!==''?' OR u.public_id=:pid':'').')';
            $like='%'.$search.'%'; $params += ['q1'=>$like,'q2'=>$like,'q3'=>'%'.$digits.'%','q4'=>'%'.$digits.'%']; if($digits!=='')$params['pid']=(int)$digits;
        }
        $status=strtoupper(trim((string)($filters['status']??''))); if(in_array($status,self::STATUSES,true)){ $where[]='p.status=:status'; $params['status']=$status; }
        $gateway=trim((string)($filters['gateway']??'')); if($gateway!==''){ $where[]='p.gateway_code=:gateway'; $params['gateway']=$gateway; }
        $from=trim((string)($filters['from']??'')); if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where[]='p.created_at>=:date_from'; $params['date_from']=$from.' 00:00:00'; }
        $to=trim((string)($filters['to']??'')); if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where[]='p.created_at<DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to']=$to.' 00:00:00'; }
        $w=implode(' AND ',$where);
        $count=$pdo->prepare("SELECT COUNT(*) FROM payment_transactions p JOIN users u ON u.id=p.user_id WHERE $w"); $count->execute($params); $total=(int)$count->fetchColumn();
        $sql="SELECT p.id,p.gateway_code,p.status,p.amount_minor,p.currency,p.external_id,p.expires_at,p.created_at,p.updated_at,u.public_id,u.cpf,u.phone,u.email,u.username FROM payment_transactions p JOIN users u ON u.id=p.user_id WHERE $w ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt=$pdo->prepare($sql); $stmt->execute($params); $items=array_map([$this,'mapDeposit'],$stmt->fetchAll());
        $summary=$pdo->query("SELECT status,COUNT(*) total,COALESCE(SUM(amount_minor),0) amount_minor FROM payment_transactions WHERE kind='DEPOSIT' GROUP BY status")->fetchAll();
        $metrics=['paid_minor'=>0,'paid_count'=>0,'pending_minor'=>0,'pending_count'=>0,'failed_count'=>0,'expired_count'=>0];
        foreach($summary as $r){$s=(string)$r['status'];$n=(int)$r['total'];$a=(int)$r['amount_minor'];if($s==='PAID'){ $metrics['paid_minor']=$a;$metrics['paid_count']=$n;} if(in_array($s,['PENDING','PROCESSING'],true)){ $metrics['pending_minor']+=$a;$metrics['pending_count']+=$n;} if(in_array($s,['FAILED','CANCELLED','REVIEW'],true))$metrics['failed_count']+=$n;if($s==='EXPIRED')$metrics['expired_count']=$n;}
        $gateways=$pdo->query("SELECT code,name FROM payment_gateways ORDER BY name")->fetchAll();
        return ['items'=>$items,'pagination'=>['page'=>$page,'pages'=>max(1,(int)ceil($total/$limit)),'total'=>$total,'limit'=>$limit],'metrics'=>$metrics,'gateways'=>$gateways];
    }

    public function deposit(string $id): array
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare("SELECT p.*,u.public_id,u.cpf,u.phone,u.email,u.username FROM payment_transactions p JOIN users u ON u.id=p.user_id WHERE p.id=:id AND p.kind='DEPOSIT' LIMIT 1"); $stmt->execute(['id'=>trim($id)]); $row=$stmt->fetch(); if(!$row)throw new DomainException('Depósito não encontrado.');
        $payment=$this->mapDeposit($row); $payment['payment_code']=$row['payment_code']?(string)$row['payment_code']:null; $payment['payment_qr_code']=$row['payment_qr_code']?(string)$row['payment_qr_code']:null; $payment['idempotency_key']=(string)$row['idempotency_key']; $payment['metadata']=$this->json((string)$row['metadata']);
        $ev=$pdo->prepare("SELECT id,event_key,event_type,external_id,processed_at,created_at FROM payment_webhook_events WHERE gateway_code=:g AND (external_id=:e OR (:e2 IS NULL AND external_id IS NULL)) ORDER BY created_at DESC LIMIT 50"); $ev->execute(['g'=>$row['gateway_code'],'e'=>$row['external_id'],'e2'=>$row['external_id']]);
        return ['payment'=>$payment,'webhooks'=>array_map(static fn(array $e)=>['id'=>(int)$e['id'],'event_key'=>(string)$e['event_key'],'event_type'=>$e['event_type']?(string)$e['event_type']:null,'external_id'=>$e['external_id']?(string)$e['external_id']:null,'processed_at'=>$e['processed_at']?(string)$e['processed_at']:null,'created_at'=>(string)$e['created_at']],$ev->fetchAll())];
    }

    /** Auditoria local, somente leitura. Não equivale a consulta ao saldo/status da Pixup. */
    public function reconciliation(): array
    {
        $pdo=Database::connection();
        $sql="SELECT p.id,p.external_id,p.gateway_code,p.status,p.amount_minor,p.created_at,u.public_id,
            (SELECT COUNT(*) FROM financial_transactions ft WHERE ft.reference_type='payment_deposit' AND ft.reference_id=p.id) AS financial_count
            FROM payment_transactions p JOIN users u ON u.id=p.user_id
            WHERE p.kind='DEPOSIT' AND (p.status='PROCESSING' OR (p.status='PAID' AND NOT EXISTS
              (SELECT 1 FROM financial_transactions ft WHERE ft.reference_type='payment_deposit' AND ft.reference_id=p.id))
              OR (p.status='PENDING' AND p.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)))
            ORDER BY p.created_at DESC LIMIT 100";
        // Ledger é a fonte contábil; evita inferir pagamento apenas por webhook recebido.
        $rows=$pdo->query($sql)->fetchAll();
        $items=[];
        foreach($rows as $r){
            $reason=$r['status']==='PROCESSING'?'Processamento interrompido ou em andamento':($r['status']==='PAID'?'Pago sem lançamento financeiro encontrado':'Pendente há mais de 30 minutos; verificar no gateway');
            $items[]=['id'=>$r['id'],'public_id'=>(int)$r['public_id'],'gateway_code'=>$r['gateway_code'],'external_id'=>$r['external_id'],'status'=>$r['status'],'amount_minor'=>(int)$r['amount_minor'],'created_at'=>$r['created_at'],'reason'=>$reason];
        }
        $unprocessed=$pdo->query("SELECT COUNT(*) FROM payment_webhook_events WHERE processed_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
        return ['mode'=>'LOCAL_ONLY','gateway_verified'=>false,'items'=>$items,'unprocessed_webhooks'=>(int)$unprocessed,'note'=>'Análise local: não consulta a Pixup e não confirma pagamento. Nenhum saldo é alterado.'];
    }

    private function mapDeposit(array $r): array { return ['id'=>(string)$r['id'],'gateway_code'=>(string)$r['gateway_code'],'status'=>(string)$r['status'],'amount_minor'=>(int)$r['amount_minor'],'currency'=>(string)$r['currency'],'external_id'=>$r['external_id']?(string)$r['external_id']:null,'expires_at'=>$r['expires_at']?(string)$r['expires_at']:null,'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],'user'=>['public_id'=>(int)$r['public_id'],'cpf'=>$r['cpf']?(string)$r['cpf']:null,'phone'=>$r['phone']?(string)$r['phone']:null,'email'=>$r['email']?(string)$r['email']:null,'username'=>$r['username']?(string)$r['username']:null]]; }
    private function json(string $v): array { $d=json_decode($v,true); return is_array($d)?$d:[]; }
}
