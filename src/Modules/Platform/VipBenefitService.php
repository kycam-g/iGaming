<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;

/** Entitlements periódicos separados dos créditos efetivos, com unicidade por período. */
final class VipBenefitService
{
    private const TZ = 'America/Sao_Paulo';
    private function now(): DateTimeImmutable { return new DateTimeImmutable('now',new DateTimeZone(self::TZ)); }
    private function settings(PDO $db): array {
        $row=$db->query('SELECT enabled,maintenance_mode,downgrade_steps,activated_at FROM vip_program_settings WHERE id=1')->fetch();
        return $row ?: ['enabled'=>0,'maintenance_mode'=>'lifelong','downgrade_steps'=>1,'activated_at'=>null];
    }
    public function adminSettings():array {return $this->settings(Database::connection());}
    public function saveSettings(array $body):array {
        $mode=(string)($body['maintenance_mode']??'');
        if(!in_array($mode,['lifelong','downgrade'],true))throw new DomainException('Regra de manutenção inválida.');
        $steps=filter_var($body['downgrade_steps']??null,FILTER_VALIDATE_INT);
        if($steps===false||$steps<1||$steps>20)throw new DomainException('Redução deve ser de 1 a 20 níveis.');
        $enabled=filter_var($body['enabled']??false,FILTER_VALIDATE_BOOLEAN)?1:0;
        Database::connection()->prepare('UPDATE vip_program_settings SET activated_at=CASE WHEN enabled=0 AND ?=1 THEN NOW(6) ELSE activated_at END,enabled=?,maintenance_mode=?,downgrade_steps=? WHERE id=1')->execute([$enabled,$enabled,$mode,$steps]);
        return $this->adminSettings();
    }
    private function levels(PDO $db):array {
        $rows=$db->query("SELECT id,title,config FROM promotion_configurations WHERE type='vip' AND enabled=1 ORDER BY id")->fetchAll();$levels=[];
        foreach($rows as $row){$cfg=json_decode((string)$row['config'],true)?:[];$level=(int)($cfg['level']??0);
            if($level<1||(int)($cfg['goal_cents']??0)<1)continue;
            $row['cfg']=$cfg;$row['level']=$level;$levels[$level]=$row;
        }ksort($levels);return $levels;
    }
    private function sqlDate(PDO $db,DateTimeImmutable $date):string {
        $utc=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $offset=(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),NOW())')->fetchColumn();
        return gmdate('Y-m-d H:i:s',strtotime($utc)+$offset);
    }
    private function wager(PDO $db,string $user,?DateTimeImmutable $from=null,?DateTimeImmutable $until=null):int {
        $sql="SELECT COALESCE(SUM(le.amount_minor),0) FROM ledger_entries le JOIN financial_transactions ft ON ft.id=le.transaction_id WHERE ft.user_id=? AND ft.type IN ('CASINO_BET','CASINO_WINBET') AND ft.status='COMPLETED' AND le.direction='DEBIT' AND le.reference_type='PLAYFIVER'";
        $params=[$user];if($from){$sql.=' AND ft.created_at>=?';$params[]=$this->sqlDate($db,$from);}if($until){$sql.=' AND ft.created_at<?';$params[]=$this->sqlDate($db,$until);}
        $stmt=$db->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn();
    }
    private function earned(array $levels,int $volume):int {
        $earned=0;foreach($levels as $n=>$row)if($volume>=(int)$row['cfg']['goal_cents'])$earned=$n;return $earned;
    }
    /** Chamar sob lock de users; não criar vínculo VIP retroativo em meses anteriores à ativação. */
    private function member(PDO $db,string $user,array $levels,array $settings,DateTimeImmutable $now):array {
        $total=$this->wager($db,$user);$earned=$this->earned($levels,$total);
        $stmt=$db->prepare('SELECT * FROM vip_memberships WHERE user_id=? FOR UPDATE');$stmt->execute([$user]);$member=$stmt->fetch();
        if(!$member){
            $db->prepare('INSERT INTO vip_memberships(user_id,earned_level,effective_level) VALUES (?,?,?)')->execute([$user,$earned,$earned]);
            $stmt->closeCursor();$stmt->execute([$user]);$member=$stmt->fetch();
        }
        $before=(int)$member['effective_level'];$oldEarned=(int)$member['earned_level'];
        if($earned>$oldEarned){$before=$earned;$oldEarned=$earned;}
        // Avaliar exclusivamente o último mês encerrado (sem retroatividade ao primeiro acesso).
        $start=$now->modify('first day of this month')->setTime(0,0);
        $previous=$start->modify('-1 month');$period=$previous->format('Y-m');
        $check=$db->prepare('SELECT 1 FROM vip_monthly_reviews WHERE user_id=? AND period_key=?');$check->execute([$user,$period]);
        $reviewed=(bool)$check->fetchColumn();$suspended=(int)$member['suspended'];
        if($settings['maintenance_mode']==='downgrade')$suspended=0;
        // Comparação DATETIME no fuso da própria sessão MySQL.
        $activeSince=$settings['activated_at']??null;
        $joinedBefore=((string)$member['joined_at'] <= $this->sqlDate($db,$previous));
        $activeBefore=$activeSince!==null && (string)$activeSince <= $this->sqlDate($db,$previous);
        if($settings['enabled'] && !$reviewed && $joinedBefore && $activeBefore && $before>0){
            $volume=$this->wager($db,$user,$previous,$start);
            $required=(int)($levels[$before]['cfg']['maintenance_cents']??0);
            $after=$before;$rule='maintained';
            if($required>0 && $volume<$required){
                if($settings['maintenance_mode']==='downgrade'){$positions=array_keys($levels);$idx=array_search($before,$positions,true);$after=((int)$idx-(int)$settings['downgrade_steps'])<0?0:($positions[(int)$idx-(int)$settings['downgrade_steps']]??0);$suspended=0;$rule='downgrade';}
                else{$suspended=1;$rule='suspended';}
            }else{$suspended=0;}
            $db->prepare('INSERT INTO vip_monthly_reviews(user_id,period_key,volume_minor,required_minor,before_level,after_level,rule_applied) VALUES (?,?,?,?,?,?,?)')->execute([$user,$period,$volume,$required,$before,$after,$rule]);
            $before=$after;
        }
        if($suspended && $settings['maintenance_mode']==='lifelong' && $before>0){
            $required=(int)($levels[$before]['cfg']['maintenance_cents']??0);
            if($required===0 || $this->wager($db,$user,$start,null)>=$required)$suspended=0;
        }
        // Recupera o patamar conquistado após cumprir a meta mensal atual.
        if($settings['enabled'] && $settings['maintenance_mode']==='downgrade' && $before<$oldEarned && isset($levels[$oldEarned])){
            $restoreRequired=(int)($levels[$oldEarned]['cfg']['maintenance_cents']??0);
            if($restoreRequired>0 && $this->wager($db,$user,$start,null)>=$restoreRequired)$before=$oldEarned;
        }
        if($oldEarned!==(int)$member['earned_level'] || $before!==(int)$member['effective_level'] || $suspended!==(int)$member['suspended']){
            $db->prepare('UPDATE vip_memberships SET earned_level=?,effective_level=?,suspended=? WHERE user_id=?')->execute([$oldEarned,$before,$suspended,$user]);
        }
        return ['earned_level'=>$oldEarned,'effective_level'=>$before,'suspended'=>(bool)$suspended,'month_minor'=>$this->wager($db,$user,$start,null),'joined_at'=>$member['joined_at']];
    }
    private function periods(DateTimeImmutable $now):array {
        $today=$now->setTime(2,0);if($now<$today)$today=$today->modify('-1 day');
        $week=$now->modify('monday this week')->setTime(2,0);if($now<$week)$week=$week->modify('-7 days');
        $month=$now->modify('first day of this month')->setTime(2,0);if($now<$month)$month=$month->modify('-1 month');
        return ['daily'=>$today->format('Y-m-d'),'weekly'=>$week->format('Y-m-d'),'monthly'=>$month->format('Y-m')];
    }
    /** Provisiona somente período corrente; valores são snapshots e não mudam após edição do Admin. */
    private function provision(PDO $db,string $user,array $member,array $levels,array $settings,DateTimeImmutable $now):void {
        $level=(int)$member['effective_level'];if(!$settings['enabled']||$member['suspended']||$level<1||!isset($levels[$level]))return;
        $row=$levels[$level];$config=$row['cfg'];
        foreach($this->periods($now) as $kind=>$period){$value=(int)($config[$kind.'_bonus_cents']??0);if($value<=0)continue;
            // Não conceder benefício semanal/mensal referente a período iniciado antes da adesão.
            $released=$kind==='monthly'?new DateTimeImmutable($period.'-01 02:00:00',new DateTimeZone(self::TZ)):new DateTimeImmutable($period.' 02:00:00',new DateTimeZone(self::TZ));
            $releaseSql=$this->sqlDate($db,$released);
            if($kind!=='daily' && (string)$member['joined_at']>$releaseSql)continue;
            if($kind!=='daily' && $settings['activated_at']!==null && (string)$settings['activated_at']>$releaseSql)continue;
            $db->prepare('INSERT IGNORE INTO vip_period_awards(user_id,campaign_id,kind,period_key,level_snapshot,amount_minor,rollover_x) VALUES (?,?,?,?,?,?,?)')->execute([$user,$row['id'],$kind,$period,$level,$value,(float)($config['rollover_x']??0)]);
        }
    }
    private function sync(PDO $db,string $user,DateTimeImmutable $now):array {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$user]);if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta não está ativa.');
        $levels=$this->levels($db);$settings=$this->settings($db);$member=$this->member($db,$user,$levels,$settings,$now);
        $this->provision($db,$user,$member,$levels,$settings,$now);
        return [$member,$levels,$settings];
    }
    public function status(string $user):array {
        return Database::transaction(function(PDO $db)use($user):array{
            [$member,$levels,$settings]=$this->sync($db,$user,$this->now());
            $stmt=$db->prepare('SELECT id,kind,period_key,level_snapshot,amount_minor,rollover_x,state,redemption_id,created_at,claimed_at FROM vip_period_awards WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 60');$stmt->execute([$user]);$awards=$stmt->fetchAll();
            $stmt=$db->prepare('SELECT period_key,volume_minor,required_minor,before_level,after_level,rule_applied FROM vip_monthly_reviews WHERE user_id=? ORDER BY period_key DESC LIMIT 12');$stmt->execute([$user]);
            return ['member'=>$member,'settings'=>$settings,'benefits'=>$awards,'reviews'=>$stmt->fetchAll(),'periods'=>$this->periods($this->now())];
        });
    }
    public function claim(string $user,string $kind):array {
        if(!in_array($kind,['daily','weekly','monthly'],true))throw new DomainException('Benefício VIP inválido.');
        return Database::transaction(function(PDO $db)use($user,$kind):array{
            [$member,$levels,$settings]=$this->sync($db,$user,$this->now());
            if(!$settings['enabled']||$member['suspended'])throw new DomainException('Benefícios VIP indisponíveis ou manutenção pendente.');
            $period=$this->periods($this->now())[$kind];
            $stmt=$db->prepare('SELECT * FROM vip_period_awards WHERE user_id=? AND kind=? AND period_key=? FOR UPDATE');$stmt->execute([$user,$kind,$period]);$entitlement=$stmt->fetch();
            if(!$entitlement)throw new DomainException('Benefício ainda não disponível para seu nível VIP.');
            if($entitlement['state']!=='AVAILABLE')throw new DomainException('Benefício deste período já resgatado.');
            $stmt=$db->prepare('SELECT * FROM promotion_configurations WHERE id=? AND type=\'vip\'');$stmt->execute([$entitlement['campaign_id']]);$row=$stmt->fetch();
            if(!$row)throw new DomainException('Configuração VIP não encontrada.');
            $cfg=['bonus_cents'=>(int)$entitlement['amount_minor'],'rollover_x'=>(float)$entitlement['rollover_x']];
            $award=(new PromotionRedemptionService())->creditVipRecurring($db,$user,$row,$cfg,'vip_'.$kind);
            $db->prepare("UPDATE vip_period_awards SET state='CLAIMED',redemption_id=?,claimed_at=NOW(6) WHERE id=? AND state='AVAILABLE'")->execute([$award['id'],$entitlement['id']]);
            return $award;
        });
    }
    /** Job executado após agendamento em cron; paginação por id para evitar uso excessivo de memória. */
    public function runBatch(int $limit=200):array {
        $db=Database::connection();$limit=max(1,min(500,$limit));$processed=0;$failures=[];$last='';
        do{$stmt=$db->prepare('SELECT id FROM users WHERE status=\'ACTIVE\' AND id>? ORDER BY id LIMIT '.$limit);$stmt->execute([$last]);$ids=$stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach($ids as $user){$last=(string)$user;try{Database::transaction(function(PDO $tx)use($user):void{$this->sync($tx,(string)$user,$this->now());});$processed++;}
                catch(\Throwable $error){$failures[]=['user_id'=>$user,'error'=>$error->getMessage()];}}
        }while(count($ids)===$limit);
        return ['processed'=>$processed,'errors'=>$failures];
    }
    public function history(int $limit=100):array {
        $stmt=Database::connection()->query('SELECT r.period_key,r.volume_minor,r.required_minor,r.before_level,r.after_level,r.rule_applied,r.created_at,u.username FROM vip_monthly_reviews r JOIN users u ON u.id=r.user_id ORDER BY r.id DESC LIMIT '.max(1,min($limit,200)));
        return $stmt->fetchAll();
    }
}
