<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class AdminPaymentService
{
    public function overview(array $query): array
    {
        $kind = strtoupper(trim((string)($query['kind'] ?? 'DEPOSIT')));
        if (!in_array($kind, ['DEPOSIT','WITHDRAWAL'], true)) $kind = 'DEPOSIT';

        $status = strtoupper(trim((string)($query['status'] ?? '')));
        $gateway = strtolower(trim((string)($query['gateway'] ?? '')));
        $search = trim((string)($query['search'] ?? ''));
        $dateFrom = trim((string)($query['date_from'] ?? ''));
        $dateTo = trim((string)($query['date_to'] ?? ''));
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = max(10, min(100, (int)($query['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        [$where, $params] = $this->filters($kind, $status, $gateway, $search, $dateFrom, $dateTo);
        $pdo = Database::connection();

        $count = $pdo->prepare("SELECT COUNT(*) FROM payment_transactions pt JOIN users u ON u.id=pt.user_id WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $sql = "SELECT pt.id,pt.kind,pt.status,pt.gateway_code,pt.amount_minor,pt.currency,pt.external_id,pt.expires_at,pt.created_at,pt.updated_at,
                       u.public_id,u.cpf,u.phone,u.email,u.username
                FROM payment_transactions pt
                JOIN users u ON u.id=pt.user_id
                WHERE $where
                ORDER BY pt.created_at DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([$this, 'mapRow'], $stmt->fetchAll());

        $summary = $this->summary($kind);
        $gateways = $pdo->query("SELECT code,name FROM payment_gateways ORDER BY name")->fetchAll();

        return [
            'kind'=>$kind,
            'summary'=>$summary,
            'payments'=>$rows,
            'gateways'=>array_map(static fn(array $g): array => ['code'=>(string)$g['code'],'name'=>(string)$g['name']], $gateways),
            'pagination'=>['page'=>$page,'pages'=>max(1,(int)ceil($total/$limit)),'total'=>$total,'limit'=>$limit],
        ];
    }

    public function detail(string $id): array
    {
        $id = trim($id);
        if ($id === '') throw new DomainException('Transação não informada.');
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT pt.*,u.public_id,u.cpf,u.phone,u.email,u.username
                               FROM payment_transactions pt JOIN users u ON u.id=pt.user_id
                               WHERE pt.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch();
        if (!$row) throw new DomainException('Transação não encontrada.');

        $events = [];
        if (!empty($row['external_id'])) {
            $ev = $pdo->prepare("SELECT id,gateway_code,event_key,external_id,event_type,processed_at,created_at,payload
                                 FROM payment_webhook_events
                                 WHERE gateway_code=:g AND (external_id=:x OR payload LIKE :needle)
                                 ORDER BY created_at DESC LIMIT 30");
            $ev->execute(['g'=>$row['gateway_code'],'x'=>$row['external_id'],'needle'=>'%'.addcslashes((string)$row['id'],'%_').'%' ]);
            $events = array_map(static function(array $e): array {
                $payload=json_decode((string)$e['payload'],true);
                return ['id'=>(int)$e['id'],'gateway_code'=>(string)$e['gateway_code'],'event_key'=>(string)$e['event_key'],'external_id'=>$e['external_id']?(string)$e['external_id']:null,'event_type'=>$e['event_type']?(string)$e['event_type']:null,'processed_at'=>$e['processed_at']?(string)$e['processed_at']:null,'created_at'=>(string)$e['created_at'],'payload'=>is_array($payload)?$payload:[]];
            }, $ev->fetchAll());
        }

        $metadata=json_decode((string)$row['metadata'],true);
        return [
            'payment'=>$this->mapRow($row) + [
                'user_id'=>(string)$row['user_id'],
                'idempotency_key'=>(string)$row['idempotency_key'],
                'payment_code'=>$row['payment_code']?(string)$row['payment_code']:null,
                'payment_qr_code'=>$row['payment_qr_code']?(string)$row['payment_qr_code']:null,
                'metadata'=>is_array($metadata)?$metadata:[],
            ],
            'webhooks'=>$events,
        ];
    }

    private function filters(string $kind,string $status,string $gateway,string $search,string $dateFrom,string $dateTo): array
    {
        $parts=['pt.kind=:kind']; $params=['kind'=>$kind];
        if ($status !== '' && in_array($status,['PENDING','PROCESSING','PAID','FAILED','CANCELLED','EXPIRED','REVIEW'],true)) { $parts[]='pt.status=:status'; $params['status']=$status; }
        if ($gateway !== '') { $parts[]='pt.gateway_code=:gateway'; $params['gateway']=$gateway; }
        if ($dateFrom !== '') { $parts[]='pt.created_at>=:date_from'; $params['date_from']=$dateFrom.' 00:00:00'; }
        if ($dateTo !== '') { $parts[]='pt.created_at<DATE_ADD(:date_to, INTERVAL 1 DAY)'; $params['date_to']=$dateTo.' 00:00:00'; }
        if ($search !== '') {
            $digits=preg_replace('/\D+/','',$search) ?: '';
            $parts[]='(pt.id LIKE :s1 OR pt.external_id LIKE :s2 OR u.email LIKE :s3 OR u.username LIKE :s4 OR u.cpf LIKE :s5 OR u.phone LIKE :s6'.($digits!==''?' OR u.public_id=:public_id':'').')';
            foreach(range(1,6) as $i) $params['s'.$i]='%'.$search.'%';
            if($digits!=='') { $params['s5']='%'.$digits.'%'; $params['s6']='%'.$digits.'%'; $params['public_id']=(int)$digits; }
        }
        return [implode(' AND ',$parts),$params];
    }

    private function summary(string $kind): array
    {
        $stmt=Database::connection()->prepare("SELECT status,COUNT(*) total,COALESCE(SUM(amount_minor),0) amount_minor FROM payment_transactions WHERE kind=:kind GROUP BY status");
        $stmt->execute(['kind'=>$kind]);
        $by=[]; $count=0; $amount=0;
        foreach($stmt->fetchAll() as $r){$by[(string)$r['status']]=['count'=>(int)$r['total'],'amount_minor'=>(int)$r['amount_minor']];$count+=(int)$r['total'];$amount+=(int)$r['amount_minor'];}
        return ['count'=>$count,'amount_minor'=>$amount,'by_status'=>$by];
    }

    private function mapRow(array $r): array
    {
        $display=(string)($r['username']??''); if($display==='') $display='Usuário #'.(int)$r['public_id'];
        return [
            'id'=>(string)$r['id'],'kind'=>(string)$r['kind'],'status'=>(string)$r['status'],'gateway_code'=>(string)$r['gateway_code'],
            'amount_minor'=>(int)$r['amount_minor'],'currency'=>(string)$r['currency'],'external_id'=>$r['external_id']?(string)$r['external_id']:null,
            'expires_at'=>$r['expires_at']?(string)$r['expires_at']:null,'created_at'=>(string)$r['created_at'],'updated_at'=>(string)$r['updated_at'],
            'user'=>['public_id'=>(int)$r['public_id'],'name'=>$display,'cpf'=>$r['cpf']?(string)$r['cpf']:null,'phone'=>$r['phone']?(string)$r['phone']:null,'email'=>$r['email']?(string)$r['email']:null],
        ];
    }
}
