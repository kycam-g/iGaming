<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

/** Vínculo de indicação é feito exclusivamente dentro da transação de cadastro. */
final class ReferralChestService
{
    public static function bindOnRegistration(PDO $db,string $childId,string $code):void
    {
        $code=strtoupper(trim($code));
        if($code==='')return;
        if(!preg_match('/^[A-F0-9]{20}$/D',$code))throw new DomainException('Código de indicação inválido.');
        $stmt=$db->prepare('SELECT user_id FROM referral_codes WHERE code=?');$stmt->execute([$code]);
        $referrer=$stmt->fetchColumn();
        if(!$referrer || $referrer===$childId)throw new DomainException('Código de indicação inválido.');
        $stmt=$db->prepare('INSERT INTO player_referrals(referred_user_id,referrer_user_id,code_used) VALUES(?,?,?)');
        $stmt->execute([$childId,$referrer,$code]);
    }
    public function status(string $userId):array
    {
        $db=Database::connection();
        // Código estável, gerado no servidor; geração concorrente é resolvida pela chave primária.
        $stmt=$db->prepare('SELECT code FROM referral_codes WHERE user_id=?');$stmt->execute([$userId]);$code=$stmt->fetchColumn();
        if(!$code){
            $code=strtoupper(bin2hex(random_bytes(10)));
            $stmt=$db->prepare('INSERT IGNORE INTO referral_codes(user_id,code) VALUES(?,?)');$stmt->execute([$userId,$code]);
            $stmt=$db->prepare('SELECT code FROM referral_codes WHERE user_id=?');$stmt->execute([$userId]);$code=$stmt->fetchColumn();
        }
        // Somente depósitos efetivamente pagos; depósitos em andamento não qualificam.
        $stmt=$db->prepare("SELECT pr.referred_user_id,COALESCE(SUM(pt.amount_minor),0) AS deposits_minor FROM player_referrals pr JOIN users u ON u.id=pr.referred_user_id AND u.status='ACTIVE' LEFT JOIN payment_transactions pt ON pt.user_id=pr.referred_user_id AND pt.kind='DEPOSIT' AND pt.status='PAID' AND pt.created_at>=pr.created_at WHERE pr.referrer_user_id=? GROUP BY pr.referred_user_id");
        $stmt->execute([$userId]);$deposits=array_map('intval',array_column($stmt->fetchAll(),'deposits_minor'));
        $stmt=$db->query("SELECT id,title,config FROM promotion_configurations WHERE type='chests' AND enabled=1 ORDER BY id");
        $campaigns=[];
        $claimed=$db->prepare("SELECT campaign_id,amount_minor,status,created_at FROM promotion_redemptions WHERE user_id=? AND promotion_type='chests'");$claimed->execute([$userId]);$claimedRows=[];
        foreach($claimed->fetchAll() as $row)$claimedRows[(int)$row['campaign_id']]=$row;
        foreach($stmt->fetchAll() as $row){
            $cfg=json_decode((string)$row['config'],true)?:[];
            $minimum=(int)($cfg['referred_deposit_min_cents']??0);$required=(int)($cfg['referral_count']??0);
            if($minimum<1 || $required<1)continue;
            $qualified=count(array_filter($deposits,static fn(int $amount):bool=>$amount>=$minimum));
            $claim=$claimedRows[(int)$row['id']]??null;
            $campaigns[]=['id'=>(int)$row['id'],'title'=>$row['title'],'required'=>$required,'qualified'=>$qualified,'deposit_min_cents'=>$minimum,'bonus_cents'=>(int)($cfg['bonus_cents']??0),'rollover_x'=>(float)($cfg['rollover_x']??0),'claimed'=>(bool)$claim,'claim_status'=>$claim['status']??null,'available'=>!$claim&&$qualified>=$required];
        }
        return ['code'=>$code,'total_referrals'=>count($deposits),'campaigns'=>$campaigns];
    }
    public function adminReport():array
    {
        $db=Database::connection();
        $rows=$db->query("SELECT pr.referrer_user_id,COUNT(*) AS registered,COALESCE(SUM(d.amount_minor),0) AS paid_deposits_minor FROM player_referrals pr LEFT JOIN (SELECT pt.user_id,SUM(pt.amount_minor) AS amount_minor FROM payment_transactions pt WHERE pt.kind='DEPOSIT' AND pt.status='PAID' GROUP BY pt.user_id) d ON d.user_id=pr.referred_user_id GROUP BY pr.referrer_user_id ORDER BY registered DESC LIMIT 100")->fetchAll();
        return ['referrers'=>$rows];
    }
}
