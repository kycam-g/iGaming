<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

/** Comissões apenas para indicações diretas e apostas confirmadas após ativação. */
final class AgencyService
{
    private function settings(PDO $db): array
    {
        $row=$db->query('SELECT enabled,activated_at FROM agency_settings WHERE id=1')->fetch();
        return ['enabled'=>(bool)($row['enabled']??false),'activated_at'=>$row['activated_at']??null];
    }
    public function adminSettings():array {return $this->settings(Database::connection());}
    public function saveSettings(bool $enabled):array
    {
        return Database::transaction(function(PDO $db)use($enabled):array{
            $db->exec('INSERT IGNORE INTO agency_settings(id,enabled) VALUES(1,0)');
            $stmt=$db->query('SELECT enabled FROM agency_settings WHERE id=1 FOR UPDATE');$prior=(bool)$stmt->fetchColumn();
            if($enabled && !$prior){
                $stmt=$db->query("SELECT COUNT(*) FROM promotion_configurations WHERE type='agency' AND enabled=1");
                if((int)$stmt->fetchColumn()===0)throw new DomainException('Cadastre e habilite uma faixa de comissão antes de ativar.');
                $db->exec('UPDATE agency_settings SET enabled=1,activated_at=NOW(6) WHERE id=1');
            }elseif(!$enabled){$db->exec('UPDATE agency_settings SET enabled=0 WHERE id=1');}
            return $this->settings($db);
        });
    }
    private function levels(PDO $db):array
    {
        $rows=$db->query("SELECT id,title,config FROM promotion_configurations WHERE type='agency' AND enabled=1 ORDER BY id")->fetchAll();$tiers=[];
        foreach($rows as $row){$config=json_decode((string)$row['config'],true)?:[];$tiers[]=['id'=>(int)$row['id'],'title'=>$row['title'],'level'=>(int)($config['level']??0),'threshold'=>(int)($config['team_bet_min_cents']??0),'rate'=>(float)($config['commission_percent']??0)];}
        usort($tiers,static fn($a,$b)=>$a['threshold']<=>$b['threshold']?:$a['level']<=>$b['level']);
        return $tiers;
    }
    private function level(array $tiers,int $volume):?array
    {
        $result=null;foreach($tiers as $tier){if($volume >= $tier['threshold'])$result=$tier;else break;}return $result;
    }
    private function volume(PDO $db,string $user,?string $activation,?string $until=null):int
    {
        if($activation===null)return 0;
        $stmt=$db->prepare("SELECT COALESCE(SUM(le.amount_minor),0) FROM player_referrals pr JOIN financial_transactions ft ON ft.user_id=pr.referred_user_id AND ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND ft.created_at>=pr.created_at AND ft.created_at>=? AND (? IS NULL OR ft.created_at<=?) JOIN ledger_entries le ON le.transaction_id=ft.id AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER' WHERE pr.referrer_user_id=?");
        $stmt->execute([$activation,$until,$until,$user]);return (int)$stmt->fetchColumn();
    }
    public function status(string $user):array
    {
        $db=Database::connection();$settings=$this->settings($db);$tiers=$this->levels($db);
        $stmt=$db->prepare("SELECT pr.referred_user_id,u.username,pr.created_at,COALESCE(d.total,0) AS deposits_minor FROM player_referrals pr JOIN users u ON u.id=pr.referred_user_id LEFT JOIN (SELECT user_id,SUM(amount_minor) AS total FROM payment_transactions WHERE kind='DEPOSIT' AND status='PAID' GROUP BY user_id) d ON d.user_id=pr.referred_user_id WHERE pr.referrer_user_id=? ORDER BY pr.created_at DESC LIMIT 100");
        $stmt->execute([$user]);$referrals=$stmt->fetchAll();
        $stmt=$db->prepare('SELECT code FROM referral_codes WHERE user_id=?');$stmt->execute([$user]);$code=$stmt->fetchColumn();
        if(!$code){(new ReferralChestService())->status($user);$stmt->execute([$user]);$code=$stmt->fetchColumn();}
        $volume=$this->volume($db,$user,$settings['activated_at']);
        $stmt=$db->prepare('SELECT id,bet_minor,commission_percent,amount_minor,created_at FROM agency_commissions WHERE referrer_user_id=? ORDER BY created_at DESC LIMIT 50');$stmt->execute([$user]);$history=$stmt->fetchAll();
        $stmt=$db->prepare('SELECT COALESCE(SUM(amount_minor),0) FROM agency_commissions WHERE referrer_user_id=?');$stmt->execute([$user]);$total=(int)$stmt->fetchColumn();
        $stmt=$db->prepare("SELECT COALESCE(wa.balance_minor,0) FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=? AND wa.type='AFFILIATE'");$stmt->execute([$user]);$balance=(int)$stmt->fetchColumn();
        return ['code'=>$code,'settings'=>$settings,'tiers'=>$tiers,'level'=>$this->level($tiers,$volume),'team_bet_minor'=>$volume,'referrals'=>$referrals,'total_referrals'=>count($referrals),'total_commission_minor'=>$total,'affiliate_balance_minor'=>$balance,'history'=>$history];
    }
    public function report():array
    {
        $db=Database::connection();
        $stmt=$db->query("SELECT c.referrer_user_id,u.username,COUNT(*) AS events,SUM(c.bet_minor) AS bets_minor,SUM(c.amount_minor) AS commissions_minor FROM agency_commissions c JOIN users u ON u.id=c.referrer_user_id GROUP BY c.referrer_user_id,u.username ORDER BY commissions_minor DESC LIMIT 100");
        $history=$db->query('SELECT id,referrer_user_id,referred_user_id,bet_minor,commission_percent,amount_minor,created_at FROM agency_commissions ORDER BY created_at DESC LIMIT 100')->fetchAll();
        return ['settings'=>$this->settings($db),'referrers'=>$stmt->fetchAll(),'history'=>$history];
    }
    /** Reconciliador idempotente: cada lançamento de aposta pode gerar no máximo uma comissão. */
    public function runBatch(int $limit=200):array
    {
        $db=Database::connection();$settings=$this->settings($db);if(!$settings['enabled']||!$settings['activated_at'])return ['processed'=>0,'credited_minor'=>0,'disabled'=>true];
        $tiers=$this->levels($db);if(!$tiers)return ['processed'=>0,'credited_minor'=>0,'disabled'=>true];
        $limit=max(1,min(500,$limit));$processed=0;$credited=0;
        // Backlog é paginado pelo par (created_at,id), sem OFFSET e sem reprocessar apostas marcadas.
        $lastDate='1000-01-01 00:00:00';$lastId='';
        while(true){
            $stmt=$db->prepare("SELECT le.id AS ledger_id,le.amount_minor AS bet_minor,ft.user_id AS referred_user_id,pr.referrer_user_id,ft.created_at FROM ledger_entries le JOIN financial_transactions ft ON ft.id=le.transaction_id JOIN player_referrals pr ON pr.referred_user_id=ft.user_id JOIN users referrer ON referrer.id=pr.referrer_user_id AND referrer.status='ACTIVE' WHERE ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER' AND ft.created_at>=? AND ft.created_at>=pr.created_at AND (ft.created_at>? OR (ft.created_at=? AND le.id>?)) AND NOT EXISTS(SELECT 1 FROM agency_commissions c WHERE c.bet_ledger_id=le.id) ORDER BY ft.created_at,le.id LIMIT {$limit}");
            $stmt->execute([$settings['activated_at'],$lastDate,$lastDate,$lastId]);$rows=$stmt->fetchAll();if(!$rows)break;
            foreach($rows as $row){$lastDate=(string)$row['created_at'];$lastId=(string)$row['ledger_id'];
                $amount=Database::transaction(function(PDO $tx)use($row,$settings,$tiers):?int{
                    // Trava por indicador; execução concorrente do cron também é idempotente.
                    $lock=$tx->prepare("SELECT status FROM users WHERE id=? FOR UPDATE");$lock->execute([$row['referrer_user_id']]);if($lock->fetchColumn()!=='ACTIVE')return null;
                    $state=$tx->query('SELECT enabled,activated_at FROM agency_settings WHERE id=1')->fetch();
                    if(!(int)$state['enabled'] || $state['activated_at']!==$settings['activated_at'])return null;
                    $exists=$tx->prepare('SELECT id FROM agency_commissions WHERE bet_ledger_id=?');$exists->execute([$row['ledger_id']]);if($exists->fetchColumn())return null;
                    $volume=$this->volume($tx,(string)$row['referrer_user_id'],$settings['activated_at'],(string)$row['created_at']);$tier=$this->level($tiers,$volume)??$tiers[0];
                    $rate=$volume<$tier['threshold']?0.0:$tier['rate'];$minor=(int)round((int)$row['bet_minor']*$rate/100,0,PHP_ROUND_HALF_UP);
                    $commissionId=PromotionRedemptionService::uuid();$txid=null;
                    if($minor>0){
                        $account=$tx->prepare("SELECT wa.id,wa.balance_minor FROM wallet_accounts wa JOIN wallets w ON w.id=wa.wallet_id WHERE w.user_id=? AND wa.type='AFFILIATE' FOR UPDATE");$account->execute([$row['referrer_user_id']]);$wallet=$account->fetch();if(!$wallet)throw new DomainException('Conta de afiliado ausente.');
                        $txid=PromotionRedemptionService::uuid();$corr=PromotionRedemptionService::uuid();$before=(int)$wallet['balance_minor'];$after=$before+$minor;
                        $insert=$tx->prepare("INSERT INTO financial_transactions(id,user_id,type,status,reference_type,reference_id,correlation_id,metadata) VALUES (?,?,'AGENCY_COMMISSION','COMPLETED','AGENCY',?,?,'{}')");$insert->execute([$txid,$row['referrer_user_id'],$commissionId,$corr]);
                        $tx->prepare('UPDATE wallet_accounts SET balance_minor=? WHERE id=?')->execute([$after,$wallet['id']]);
                        $insert=$tx->prepare("INSERT INTO ledger_entries(id,transaction_id,account_id,direction,amount_minor,balance_before_minor,balance_after_minor,reference_type,reference_id,correlation_id) VALUES (?,?,?,'CREDIT',?,?,?,'AGENCY',?,?)");$insert->execute([PromotionRedemptionService::uuid(),$txid,$wallet['id'],$minor,$before,$after,$commissionId,$corr]);
                    }
                    $insert=$tx->prepare('INSERT INTO agency_commissions(id,bet_ledger_id,referrer_user_id,referred_user_id,campaign_id,bet_minor,commission_percent,amount_minor,financial_transaction_id) VALUES (?,?,?,?,?,?,?,?,?)');
                    $insert->execute([$commissionId,$row['ledger_id'],$row['referrer_user_id'],$row['referred_user_id'],$tier['id'],$row['bet_minor'],$rate,$minor,$txid]);
                    return $minor;
                });
                if($amount!==null){$processed++;$credited+=$amount;}
            }
            if(count($rows)<$limit)break;
        }
        return ['processed'=>$processed,'credited_minor'=>$credited,'disabled'=>false];
    }
}
