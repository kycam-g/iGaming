<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PDO;

final class RedEnvelopeService
{
    private const TZ='America/Sao_Paulo';

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now',new DateTimeZone(self::TZ));
    }
    private function periodContext(): array
    {
        $now=$this->now();
        $today=$now->setTime(0,0,0);
        // Semana promocional: segunda-feira 00:00 até a próxima segunda-feira 00:00 (Brasília).
        $weekStart=$today->modify('monday this week');
        $weekEnd=$weekStart->modify('+7 days');
        return [
            'day_key'=>$today->format('Y-m-d'),
            'weekday'=>strtolower($today->format('l')),
            'week_key'=>$weekStart->format('Y-m-d'),
            'week_end_key'=>$weekEnd->format('Y-m-d'),
            'week_start_utc'=>$weekStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'week_end_utc'=>$weekEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }
    private function lockUser(PDO $db,string $userId): void
    {
        $stmt=$db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$stmt->execute([$userId]);
        if($stmt->fetchColumn()!=='ACTIVE')throw new DomainException('Conta indisponível.');
    }
    private function campaign(PDO $db,bool $lock=false): ?array
    {
        $sql="SELECT * FROM promotion_configurations WHERE type='envelope' AND enabled=1 ORDER BY id DESC LIMIT 1".($lock?' FOR UPDATE':'');
        $row=$db->query($sql)->fetch();
        return $row?:null;
    }
    private function dayEnabled(array $cfg,string $weekday): bool
    {
        return !empty($cfg[$weekday]);
    }
    private function deposits(PDO $db,string $userId,array $ctx,bool $lock=false): int
    {
        $sql="SELECT id,amount_minor FROM payment_transactions WHERE user_id=? AND kind='DEPOSIT' AND status='PAID' AND updated_at>=? AND updated_at<? ORDER BY updated_at ASC".($lock?' FOR UPDATE':'');
        $stmt=$db->prepare($sql);$stmt->execute([$userId,$ctx['week_start_utc'],$ctx['week_end_utc']]);
        $sum=0;foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$sum+=(int)$row['amount_minor'];
        return $sum;
    }
    private function history(PDO $db,string $userId): array
    {
        $hist=$db->prepare('SELECT e.day_key,e.base_amount_minor AS deposit_total_minor,(e.multiplier*100) AS deposit_percent,e.final_amount_minor,e.created_at,r.status,r.wager_required_minor FROM envelope_daily_claims e LEFT JOIN promotion_redemptions r ON r.id=e.redemption_id WHERE e.user_id=? ORDER BY e.id DESC LIMIT 20');
        $hist->execute([$userId]);
        return $hist->fetchAll();
    }
    public function status(string $userId): array
    {
        $db=Database::connection();
        $campaign=$this->campaign($db);
        if(!$campaign)return ['active'=>false,'available'=>false,'campaign'=>null,'history'=>[]];
        $cfg=json_decode((string)$campaign['config'],true)?:[];
        $ctx=$this->periodContext();
        $dayEnabled=$this->dayEnabled($cfg,$ctx['weekday']);
        $depositTotal=$dayEnabled?$this->deposits($db,$userId,$ctx):0;
        $minimum=(int)($cfg['deposit_min_cents']??0);
        $stmt=$db->prepare('SELECT id,day_key,base_amount_minor AS deposit_total_minor,(multiplier*100) AS deposit_percent,final_amount_minor,redemption_id,created_at FROM envelope_daily_claims WHERE user_id=? AND campaign_id=? AND day_key>=? AND day_key<? ORDER BY day_key DESC LIMIT 1');
        $stmt->execute([$userId,$campaign['id'],$ctx['week_key'],$ctx['week_end_key']]);$weekClaim=$stmt->fetch()?:null;
        $eligible=!empty($cfg['auto_enabled']) && $dayEnabled && $minimum>0 && $depositTotal>=$minimum;
        return [
            'active'=>true,
            'available'=>$eligible && !$weekClaim,
            'eligible'=>$eligible,
            'day_enabled'=>$dayEnabled,
            'claimed_this_week'=>(bool)$weekClaim,
            'claimed_today'=>(bool)$weekClaim,
            'campaign'=>['id'=>(int)$campaign['id'],'title'=>$campaign['title'],'message'=>(string)($cfg['message']??'')],
            'today'=>$weekClaim,
            'history'=>$this->history($db,$userId),
            'day_key'=>$ctx['day_key'],
            'week_key'=>$ctx['week_key'],
            'weekday'=>$ctx['weekday'],
            'deposit_total_minor'=>$depositTotal,
            'deposit_min_cents'=>$minimum,
        ];
    }
    public function claim(string $userId): array
    {
        return Database::transaction(function(PDO $db)use($userId):array{
            $this->lockUser($db,$userId);
            $campaign=$this->campaign($db,true);
            if(!$campaign)throw new DomainException('Envelope Vermelho indisponível.');
            $cfg=json_decode((string)$campaign['config'],true)?:[];
            if(empty($cfg['auto_enabled']))throw new DomainException('O Envelope Vermelho não está liberado no momento.');
            $ctx=$this->periodContext();
            if(!$this->dayEnabled($cfg,$ctx['weekday']))throw new DomainException('O Envelope Vermelho não está disponível hoje.');
            $percent=(float)($cfg['deposit_percent']??0);
            $minimum=(int)($cfg['deposit_min_cents']??0);
            if($percent<=0||$percent>100||$minimum<1)throw new DomainException('Envelope Vermelho configurado incorretamente.');
            $old=$db->prepare('SELECT id FROM envelope_daily_claims WHERE user_id=? AND campaign_id=? AND day_key>=? AND day_key<? LIMIT 1 FOR UPDATE');
            $old->execute([$userId,$campaign['id'],$ctx['week_key'],$ctx['week_end_key']]);
            if($old->fetchColumn())throw new DomainException('Você já abriu o Envelope Vermelho desta semana.');
            $depositTotal=$this->deposits($db,$userId,$ctx,true);
            if($depositTotal<$minimum)throw new DomainException('É necessário atingir o depósito mínimo semanal configurado.');
            $final=(int)floor($depositTotal*($percent/100));
            if($final<1||$final>100000000)throw new DomainException('Valor final do envelope fora do limite permitido.');
            // Compatibilidade histórica: base_amount_minor armazena o total de depósitos e multiplier armazena a fração percentual.
            $fraction=$percent/100;
            $insert=$db->prepare('INSERT INTO envelope_daily_claims(user_id,campaign_id,day_key,base_amount_minor,multiplier,final_amount_minor) VALUES(?,?,?,?,?,?)');
            $insert->execute([$userId,$campaign['id'],$ctx['week_key'],$depositTotal,$fraction,$final]);
            $claimId=(int)$db->lastInsertId();
            $award=(new PromotionRedemptionService())->creditEnvelope($db,$userId,$campaign,$cfg,$final);
            $db->prepare('UPDATE envelope_daily_claims SET redemption_id=? WHERE id=?')->execute([$award['id'],$claimId]);
            return ['award'=>$award,'deposit_total_minor'=>$depositTotal,'deposit_percent'=>$percent,'final_amount_minor'=>$final,'day_key'=>$ctx['day_key'],'week_key'=>$ctx['week_key'],'message'=>(string)($cfg['message']??'')];
        });
    }
    public function report(): array
    {
        $db=Database::connection();
        $stmt=$db->query("SELECT e.id,e.day_key,e.base_amount_minor AS deposit_total_minor,(e.multiplier*100) AS deposit_percent,e.final_amount_minor,e.created_at,u.username,c.title,r.status,r.wager_required_minor FROM envelope_daily_claims e JOIN users u ON u.id=e.user_id JOIN promotion_configurations c ON c.id=e.campaign_id LEFT JOIN promotion_redemptions r ON r.id=e.redemption_id ORDER BY e.id DESC LIMIT 100");
        return ['items'=>$stmt->fetchAll()];
    }
}
