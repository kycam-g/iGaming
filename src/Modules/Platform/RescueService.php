<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;

/** Daily recovery based strictly on confirmed cash PlayFiver bets minus wins. */
final class RescueService
{
    private const TZ='America/Sao_Paulo';

    public static function refundMinor(int $lossMinor,int $basisPoints):int
    {
        if($lossMinor<0||$lossMinor>1000000000000||$basisPoints<0||$basisPoints>10000)throw new DomainException('Perda ou taxa de recuperação inválida.');
        return intdiv($lossMinor*$basisPoints,10000);
    }
    private function today():string{return (new DateTimeImmutable('now',new DateTimeZone(self::TZ)))->format('Y-m-d');}
    private function yesterday():string{return (new DateTimeImmutable($this->today()))->modify('-1 day')->format('Y-m-d');}
    private function settings(PDO $db,bool $lock=false):array
    {
        $row=$db->query('SELECT enabled,effective_day,updated_at FROM rescue_settings WHERE id=1'.($lock?' FOR UPDATE':''))->fetch()?:[];
        return ['enabled'=>(bool)($row['enabled']??false),'effective_day'=>$row['effective_day']??null,'updated_at'=>$row['updated_at']??null];
    }
    public function adminSettings():array{return $this->settings(Database::connection());}
    public function saveSettings(bool $enabled):array
    {
        return Database::transaction(function(PDO $db)use($enabled):array{
            $db->exec('INSERT IGNORE INTO rescue_settings(id,enabled) VALUES(1,0)');
            $old=$this->settings($db,true);
            if($enabled&&!$old['enabled']){
                $tomorrow=(new DateTimeImmutable('tomorrow',new DateTimeZone(self::TZ)))->format('Y-m-d');
                $db->prepare('UPDATE rescue_settings SET enabled=1,effective_day=? WHERE id=1')->execute([$tomorrow]);
            }else{
                $db->prepare('UPDATE rescue_settings SET enabled=? WHERE id=1')->execute([(int)$enabled]);
            }
            return $this->settings($db);
        });
    }
    /** Convert Brasília calendar-day boundaries to DATETIME in the current SQL session TZ. */
    private function boundaries(PDO $db,string $day):array
    {
        $tz=new DateTimeZone(self::TZ);
        $start=new DateTimeImmutable($day.' 00:00:00',$tz);
        $end=$start->modify('+1 day');
        $offset=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW())')->fetchColumn();
        return [gmdate('Y-m-d H:i:s',$start->getTimestamp()+$offset),gmdate('Y-m-d H:i:s',$end->getTimestamp()+$offset)];
    }
    private function tiers(PDO $db):array
    {
        $rows=$db->query("SELECT id,title,config FROM promotion_configurations WHERE type='rescue' AND enabled=1 ORDER BY id FOR UPDATE")->fetchAll();
        $tiers=[];
        foreach($rows as $row){$cfg=json_decode((string)$row['config'],true)?:[];
            $min=(int)($cfg['loss_min_cents']??0);$basis=(int)round(((float)($cfg['refund_percent']??0))*100);
            $roll=(float)($cfg['rollover_x']??0);
            if($min<1||$basis<1||$basis>10000||$roll<0||$roll>100)continue;
            $tiers[]=['id'=>(int)$row['id'],'title'=>$row['title'],'minimum_minor'=>$min,'basis_points'=>$basis,'rollover_x'=>$roll];
        }
        usort($tiers,static fn($a,$b)=>$a['minimum_minor']<=>$b['minimum_minor'] ?: $a['id']<=>$b['id']);
        return $tiers;
    }
    /** Run with user lock inside a transaction; unique(user,day) is the final idempotency guard. */
    private function provision(PDO $db,string $user,string $period):void
    {
        $settings=$this->settings($db);
        if(!$settings['enabled']||!$settings['effective_day']||$period<$settings['effective_day']||$period!==$this->yesterday())return;
        $check=$db->prepare('SELECT id FROM rescue_daily_awards WHERE user_id=? AND period_key=?');$check->execute([$user,$period]);if($check->fetchColumn())return;
        $tiers=$this->tiers($db);if(!$tiers)return;
        [$from,$until]=$this->boundaries($db,$period);
        // One ledger entry per direction and event; ignore transfers, deposits and bonuses.
        $query=$db->prepare("SELECT COALESCE(SUM(CASE WHEN le.direction='DEBIT' THEN le.amount_minor ELSE 0 END),0) bet_minor,COALESCE(SUM(CASE WHEN le.direction='CREDIT' THEN le.amount_minor ELSE 0 END),0) win_minor FROM financial_transactions ft JOIN ledger_entries le ON le.transaction_id=ft.id JOIN wallet_accounts wa ON wa.id=le.account_id WHERE ft.user_id=? AND ft.type IN ('CASINO_BET','CASINO_WIN','CASINO_WINBET') AND ft.status='COMPLETED' AND le.reference_type='PLAYFIVER' AND wa.type='CASH' AND ft.created_at>=? AND ft.created_at<?");
        $query->execute([$user,$from,$until]);$volume=$query->fetch();
        $bet=(int)$volume['bet_minor'];$win=(int)$volume['win_minor'];$loss=max(0,$bet-$win);
        $chosen=null;
        foreach($tiers as $tier)if($loss>=$tier['minimum_minor'])$chosen=$tier;
        $amount=$chosen?self::refundMinor($loss,$chosen['basis_points']):0;
        $db->prepare('INSERT IGNORE INTO rescue_daily_awards(user_id,period_key,campaign_id,bet_minor,win_minor,loss_minor,amount_minor,refund_basis_points,rollover_x,state) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$user,$period,$chosen['id']??null,$bet,$win,$loss,$amount,$chosen['basis_points']??0,$chosen['rollover_x']??0,$amount>0?'AVAILABLE':'INELIGIBLE']);
    }
    private function lockUser(PDO $db,string $user):void
    {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$user]);
        if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta inativa.');
    }
    public function status(string $user):array
    {
        return Database::transaction(function(PDO $db)use($user):array{
            $this->lockUser($db,$user);$yesterday=$this->yesterday();$this->provision($db,$user,$yesterday);
            $stmt=$db->prepare('SELECT period_key,bet_minor,win_minor,loss_minor,amount_minor,refund_basis_points,rollover_x,state,claimed_at FROM rescue_daily_awards WHERE user_id=? ORDER BY period_key DESC LIMIT 35');$stmt->execute([$user]);$history=$stmt->fetchAll();
            foreach($history as &$r)if($r['state']==='AVAILABLE'&&$r['period_key']<$yesterday)$r['state']='EXPIRED';unset($r);
            $current=null;foreach($history as $row)if($row['period_key']===$yesterday){$current=$row;break;}
            $settings=$this->settings($db);
            $tiers=$this->tiers($db);
            return ['settings'=>$settings,'today'=>$this->today(),'period_key'=>$yesterday,'current'=>$current,'history'=>$history,'tiers'=>$tiers];
        });
    }
    public function claim(string $user):array
    {
        return Database::transaction(function(PDO $db)use($user):array{
            $this->lockUser($db,$user);$yesterday=$this->yesterday();$this->provision($db,$user,$yesterday);
            $stmt=$db->prepare('SELECT * FROM rescue_daily_awards WHERE user_id=? AND period_key=? FOR UPDATE');$stmt->execute([$user,$yesterday]);$award=$stmt->fetch();
            if(!$award||$award['state']!=='AVAILABLE'||(int)$award['amount_minor']<1)throw new DomainException('Nenhum fundo de resgate elegível disponível hoje.');
            if(!$this->settings($db)['enabled'])throw new DomainException('Fundo de resgate temporariamente desativado.');
            $stmt=$db->prepare("SELECT * FROM promotion_configurations WHERE id=? AND type='rescue'");$stmt->execute([$award['campaign_id']]);$campaign=$stmt->fetch();
            if(!$campaign)throw new DomainException('Campanha de resgate não encontrada.');
            $value=(int)$award['amount_minor'];
            $result=(new PromotionRedemptionService())->creditRescue($db,$user,$campaign,['reward_min_cents'=>$value,'reward_max_cents'=>$value,'rollover_x'=>(float)$award['rollover_x']]);
            $update=$db->prepare("UPDATE rescue_daily_awards SET state='CLAIMED',redemption_id=?,claimed_at=NOW(6) WHERE id=? AND state='AVAILABLE'");$update->execute([$result['id'],$award['id']]);
            if($update->rowCount()!==1)throw new DomainException('Resgate já processado.');
            return $result;
        });
    }
    public function runBatch(int $limit=200):array
    {
        $db=Database::connection();$settings=$this->settings($db);
        if(!$settings['enabled'])return ['disabled'=>true,'processed'=>0,'errors'=>[]];
        // Fechar recompensas não resgatadas em dias anteriores; o resgate já valida o período corrente.
        $db->prepare("UPDATE rescue_daily_awards SET state='EXPIRED' WHERE period_key<? AND state='AVAILABLE'")->execute([$this->yesterday()]);
        $last='';$processed=0;$errors=[];$limit=max(1,min($limit,500));
        do{
            $stmt=$db->prepare('SELECT id FROM users WHERE status=\'ACTIVE\' AND id>? ORDER BY id LIMIT '.$limit);$stmt->execute([$last]);$users=$stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach($users as $user){$last=(string)$user;try{Database::transaction(function(PDO $tx)use($user):void{$this->lockUser($tx,(string)$user);$this->provision($tx,(string)$user,$this->yesterday());});$processed++;}catch(\Throwable $e){$errors[]=['user_id'=>$user,'error'=>$e->getMessage()];}}
        }while(count($users)===$limit);
        return ['disabled'=>false,'processed'=>$processed,'errors'=>$errors];
    }
    public function report():array
    {
        $db=Database::connection();$stmt=$db->query('SELECT a.period_key,a.bet_minor,a.win_minor,a.loss_minor,a.amount_minor,a.state,a.claimed_at,u.username FROM rescue_daily_awards a JOIN users u ON u.id=a.user_id ORDER BY a.period_key DESC,a.id DESC LIMIT 100');
        $awards=$stmt->fetchAll();$yesterday=$this->yesterday();foreach($awards as &$award)if($award['state']==='AVAILABLE'&&$award['period_key']<$yesterday)$award['state']='EXPIRED';unset($award);
        return ['settings'=>$this->settings($db),'awards'=>$awards];
    }
}
