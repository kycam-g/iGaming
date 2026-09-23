<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class NotificationService
{
    private const CATEGORIES=['announcement','financial','promotion','security','support','system','user'];
    private const PRIORITIES=['normal','high','urgent'];
    private const AUDIENCES=['ALL','USER'];

    private function db():PDO{return Database::connection();}

    public function forUser(string $userId):array
    {
        $this->syncAutomaticForUser($userId);
        $db=$this->db();
        $stmt=$db->prepare("SELECT n.id,n.category,n.priority,n.title,n.message,n.link_path,n.created_at,CASE WHEN r.notification_id IS NULL THEN 0 ELSE 1 END AS is_read FROM platform_notifications n LEFT JOIN user_notification_reads r ON r.notification_id=n.id AND r.user_id=? WHERE n.enabled=1 AND (n.starts_at IS NULL OR n.starts_at<=NOW()) AND (n.ends_at IS NULL OR n.ends_at>=NOW()) AND (n.audience='ALL' OR (n.audience='USER' AND n.user_id=?)) ORDER BY is_read ASC,n.created_at DESC,n.id DESC LIMIT 100");
        $stmt->execute([$userId,$userId]);
        $items=$stmt->fetchAll();
        $unread=0;foreach($items as &$item){$item['id']=(int)$item['id'];$item['is_read']=(bool)$item['is_read'];if(!$item['is_read'])$unread++;}unset($item);
        return ['items'=>$items,'unread_count'=>$unread,'categories'=>self::CATEGORIES];
    }

    public function markRead(string $userId,int $notificationId):void
    {
        if($notificationId<1)throw new DomainException('Notificação inválida.');
        $db=$this->db();
        $check=$db->prepare("SELECT id FROM platform_notifications WHERE id=? AND enabled=1 AND (audience='ALL' OR (audience='USER' AND user_id=?)) LIMIT 1");
        $check->execute([$notificationId,$userId]);
        if(!$check->fetchColumn())throw new DomainException('Notificação não encontrada.');
        $stmt=$db->prepare('INSERT IGNORE INTO user_notification_reads(user_id,notification_id) VALUES(?,?)');$stmt->execute([$userId,$notificationId]);
    }

    public function markAllRead(string $userId):void
    {
        $stmt=$this->db()->prepare("INSERT IGNORE INTO user_notification_reads(user_id,notification_id) SELECT ?,n.id FROM platform_notifications n WHERE n.enabled=1 AND (n.starts_at IS NULL OR n.starts_at<=NOW()) AND (n.ends_at IS NULL OR n.ends_at>=NOW()) AND (n.audience='ALL' OR (n.audience='USER' AND n.user_id=?))");
        $stmt->execute([$userId,$userId]);
    }

    public function adminList():array
    {
        $sql="SELECT n.id,n.category,n.priority,n.audience,n.user_id,n.title,n.message,n.link_path,n.enabled,n.starts_at,n.ends_at,n.created_at,n.updated_at,u.public_id,u.username FROM platform_notifications n LEFT JOIN users u ON u.id=n.user_id ORDER BY n.id DESC LIMIT 300";
        $rows=$this->db()->query($sql)->fetchAll();
        foreach($rows as &$row){$row['id']=(int)$row['id'];$row['enabled']=(bool)$row['enabled'];}unset($row);return $rows;
    }

    public function save(array $input):array
    {
        $db=$this->db();$id=max(0,(int)($input['id']??0));
        $category=strtolower(trim((string)($input['category']??'')));if(!in_array($category,self::CATEGORIES,true))throw new DomainException('Categoria de notificação inválida.');
        $priority=strtolower(trim((string)($input['priority']??'normal')));if(!in_array($priority,self::PRIORITIES,true))throw new DomainException('Prioridade de notificação inválida.');
        $audience=strtoupper(trim((string)($input['audience']??'ALL')));if(!in_array($audience,self::AUDIENCES,true))throw new DomainException('Público da notificação inválido.');
        $title=trim((string)($input['title']??''));$message=trim((string)($input['message']??''));
        if($title===''||mb_strlen($title)>120)throw new DomainException('Informe um título de até 120 caracteres.');
        if($message===''||mb_strlen($message)>1000)throw new DomainException('Informe uma mensagem de até 1000 caracteres.');
        $link=trim((string)($input['link_path']??''));if($link!==''&&!preg_match('~^/(?:$|[a-z0-9/_?&=.-]+$)~i',$link))throw new DomainException('O link deve ser um caminho interno válido.');
        $starts=$this->date($input['starts_at']??null);$ends=$this->date($input['ends_at']??null);if($starts&&$ends&&$starts>$ends)throw new DomainException('Período da notificação inválido.');
        $userId=null;
        if($audience==='USER'){$identifier=trim((string)($input['user_identifier']??$input['user_id']??''));if($identifier==='')throw new DomainException('Informe o jogador destinatário.');$userId=$this->resolveUser($db,$identifier);if(!$userId)throw new DomainException('Jogador destinatário não encontrado.');}
        $enabled=filter_var($input['enabled']??true,FILTER_VALIDATE_BOOLEAN)?1:0;
        $args=['category'=>$category,'priority'=>$priority,'audience'=>$audience,'user_id'=>$userId,'title'=>$title,'message'=>$message,'link_path'=>$link===''?null:$link,'enabled'=>$enabled,'starts_at'=>$starts,'ends_at'=>$ends];
        if($id){$args['id']=$id;$stmt=$db->prepare('UPDATE platform_notifications SET category=:category,priority=:priority,audience=:audience,user_id=:user_id,title=:title,message=:message,link_path=:link_path,enabled=:enabled,starts_at=:starts_at,ends_at=:ends_at WHERE id=:id');$stmt->execute($args);if(!$stmt->rowCount()){$check=$db->prepare('SELECT id FROM platform_notifications WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())throw new DomainException('Notificação não encontrada.');}}
        else{$stmt=$db->prepare('INSERT INTO platform_notifications(category,priority,audience,user_id,title,message,link_path,enabled,starts_at,ends_at) VALUES(:category,:priority,:audience,:user_id,:title,:message,:link_path,:enabled,:starts_at,:ends_at)');$stmt->execute($args);$id=(int)$db->lastInsertId();}
        $stmt=$db->prepare('SELECT * FROM platform_notifications WHERE id=?');$stmt->execute([$id]);return $stmt->fetch()?:[];
    }

    public function delete(int $id):void
    {
        if($id<1)throw new DomainException('Notificação inválida.');$stmt=$this->db()->prepare('DELETE FROM platform_notifications WHERE id=?');$stmt->execute([$id]);
    }


    public function automationSettings():array
    {
        $rows=$this->db()->query("SELECT event_key,category,priority,title_template,message_template,link_path,enabled,updated_at FROM notification_automation_settings ORDER BY category,event_key")->fetchAll();
        foreach($rows as &$row)$row['enabled']=(bool)$row['enabled'];unset($row);return $rows;
    }

    public function saveAutomationSetting(array $input):array
    {
        $event=trim((string)($input['event_key']??''));if(!preg_match('/^[a-z0-9_]{3,80}$/',$event))throw new DomainException('Evento automático inválido.');
        $category=strtolower(trim((string)($input['category']??'')));if(!in_array($category,self::CATEGORIES,true))throw new DomainException('Categoria inválida.');
        $priority=strtolower(trim((string)($input['priority']??'normal')));if(!in_array($priority,self::PRIORITIES,true))throw new DomainException('Prioridade inválida.');
        $title=trim((string)($input['title_template']??''));$message=trim((string)($input['message_template']??''));
        if($title===''||mb_strlen($title)>120)throw new DomainException('Título automático inválido.');
        if($message===''||mb_strlen($message)>1000)throw new DomainException('Mensagem automática inválida.');
        $link=trim((string)($input['link_path']??''));if($link!==''&&!preg_match('~^/(?:$|[a-z0-9/_?&=.-]+$)~i',$link))throw new DomainException('Link interno inválido.');
        $enabled=filter_var($input['enabled']??true,FILTER_VALIDATE_BOOLEAN)?1:0;
        $stmt=$this->db()->prepare('UPDATE notification_automation_settings SET category=?,priority=?,title_template=?,message_template=?,link_path=?,enabled=? WHERE event_key=?');
        $stmt->execute([$category,$priority,$title,$message,$link===''?null:$link,$enabled,$event]);
        if(!$stmt->rowCount()){$check=$this->db()->prepare('SELECT event_key FROM notification_automation_settings WHERE event_key=?');$check->execute([$event]);if(!$check->fetchColumn())throw new DomainException('Evento automático não encontrado.');}
        $stmt=$this->db()->prepare('SELECT * FROM notification_automation_settings WHERE event_key=?');$stmt->execute([$event]);return $stmt->fetch()?:[];
    }

    private function automaticMap():array
    {
        $rows=$this->db()->query('SELECT event_key,category,priority,title_template,message_template,link_path,enabled FROM notification_automation_settings')->fetchAll();$out=[];
        foreach($rows as $row)$out[(string)$row['event_key']]=$row;return $out;
    }

    private function money(int $minor):string{return 'R$ '.number_format($minor/100,2,',','.');}

    private function emitAutomatic(array $settings,string $event,string $userId,string $source,array $vars=[]):void
    {
        $cfg=$settings[$event]??null;if(!$cfg||(int)($cfg['enabled']??0)!==1)return;
        $replace=[];foreach($vars as $k=>$v)$replace['{'.$k.'}']=(string)$v;
        $title=strtr((string)$cfg['title_template'],$replace);$message=strtr((string)$cfg['message_template'],$replace);
        $sourceKey=$event.':'.$source;
        $stmt=$this->db()->prepare("INSERT IGNORE INTO platform_notifications(category,priority,audience,user_id,source_key,title,message,link_path,enabled,starts_at) VALUES(?,?,'USER',?,?,?,?,?,1,NOW(6))");
        $stmt->execute([$cfg['category'],$cfg['priority'],$userId,$sourceKey,$title,$message,$cfg['link_path']?:null]);
    }

    private function syncAutomaticForUser(string $userId):void
    {
        $db=$this->db();$settings=$this->automaticMap();if(!$settings)return;
        $stmt=$db->prepare("SELECT id,kind,status,amount_minor,updated_at FROM payment_transactions WHERE user_id=? ORDER BY updated_at DESC LIMIT 40");$stmt->execute([$userId]);
        foreach($stmt->fetchAll() as $row){$kind=(string)$row['kind'];$status=(string)$row['status'];$amount=$this->money((int)$row['amount_minor']);$id=(string)$row['id'];
            if($kind==='DEPOSIT'){if(in_array($status,['PENDING','PROCESSING'],true))$this->emitAutomatic($settings,'deposit_created',$userId,$id,['amount'=>$amount]);elseif($status==='PAID')$this->emitAutomatic($settings,'deposit_paid',$userId,$id,['amount'=>$amount]);elseif(in_array($status,['FAILED','CANCELLED','EXPIRED'],true))$this->emitAutomatic($settings,'deposit_failed',$userId,$id.':'.$status,['amount'=>$amount]);}
            elseif($kind==='WITHDRAWAL'){if($status==='REVIEW')$this->emitAutomatic($settings,'withdrawal_created',$userId,$id,['amount'=>$amount]);elseif($status==='PROCESSING')$this->emitAutomatic($settings,'withdrawal_processing',$userId,$id,['amount'=>$amount]);elseif($status==='PAID')$this->emitAutomatic($settings,'withdrawal_paid',$userId,$id,['amount'=>$amount]);elseif(in_array($status,['FAILED','CANCELLED'],true))$this->emitAutomatic($settings,'withdrawal_failed',$userId,$id.':'.$status,['amount'=>$amount]);}
        }
        $stmt=$db->prepare("SELECT reference_id,COALESCE(SUM(le.amount_minor),0) amount_minor FROM financial_transactions ft JOIN ledger_entries le ON le.transaction_id=ft.id WHERE ft.user_id=? AND ft.reference_type='first_deposit_bonus' AND ft.status='COMPLETED' AND le.direction='CREDIT' GROUP BY reference_id");$stmt->execute([$userId]);
        foreach($stmt->fetchAll() as $row)$this->emitAutomatic($settings,'first_deposit_bonus',$userId,(string)$row['reference_id'],['amount'=>$this->money((int)$row['amount_minor'])]);

        $stmt=$db->prepare("SELECT COALESCE(SUM(total_spins-used_spins),0) c FROM roulette_spin_credits WHERE user_id=?");$stmt->execute([$userId]);$c=(int)$stmt->fetchColumn();if($c>0)$this->emitAutomatic($settings,'promo_roulette_available',$userId,'spins:'.$c,['count'=>$c]);

        $stmt=$db->prepare("SELECT COALESCE(SUM(GREATEST(0,(SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(pc.config,'$.free_spins_per_day')) AS UNSIGNED))-x.used)),0) FROM (SELECT cs.id,cs.campaign_id,COUNT(cw.id) used FROM cashwheel_sessions cs LEFT JOIN cashwheel_spins cw ON cw.session_id=cs.id AND cw.day_key=CURDATE() WHERE cs.user_id=? AND cs.status='ACTIVE' GROUP BY cs.id,cs.campaign_id) x JOIN promotion_configurations pc ON pc.id=x.campaign_id AND pc.enabled=1");$stmt->execute([$userId]);$c=(int)$stmt->fetchColumn();if($c>0)$this->emitAutomatic($settings,'promo_cashwheel_available',$userId,'day:'.date('Y-m-d').':'.$c,['count'=>$c]);

        $stmt=$db->prepare("SELECT COALESCE(SUM(GREATEST(0,CAST(JSON_UNQUOTE(JSON_EXTRACT(pc.config,'$.spins_per_day')) AS UNSIGNED)-COALESCE(s.used,0))),0) FROM lottery_states ls JOIN promotion_configurations pc ON pc.id=ls.campaign_id AND pc.enabled=1 LEFT JOIN (SELECT campaign_id,user_id,COUNT(*) used FROM lottery_spins WHERE day_key=CURDATE() GROUP BY campaign_id,user_id) s ON s.campaign_id=ls.campaign_id AND s.user_id=ls.user_id WHERE ls.user_id=?");$stmt->execute([$userId]);$c=(int)$stmt->fetchColumn();if($c>0)$this->emitAutomatic($settings,'promo_lottery_available',$userId,'day:'.date('Y-m-d').':'.$c,['count'=>$c]);

        $stmt=$db->prepare("SELECT COUNT(*) FROM (SELECT pc.id,CAST(JSON_UNQUOTE(JSON_EXTRACT(pc.config,'$.referral_count')) AS UNSIGNED) req,COUNT(CASE WHEN d.total_minor>=CAST(JSON_UNQUOTE(JSON_EXTRACT(pc.config,'$.referred_deposit_min_cents')) AS UNSIGNED) THEN 1 END) qual FROM promotion_configurations pc LEFT JOIN (SELECT pr.referrer_user_id,pr.referred_user_id,COALESCE(SUM(pt.amount_minor),0) total_minor FROM player_referrals pr LEFT JOIN payment_transactions pt ON pt.user_id=pr.referred_user_id AND pt.kind='DEPOSIT' AND pt.status='PAID' GROUP BY pr.referrer_user_id,pr.referred_user_id) d ON d.referrer_user_id=? WHERE pc.type='chests' AND pc.enabled=1 GROUP BY pc.id HAVING qual>=req) q");$stmt->execute([$userId]);$c=(int)$stmt->fetchColumn();if($c>0)$this->emitAutomatic($settings,'promo_chest_available',$userId,'count:'.$c,['count'=>$c]);

        $stmt=$db->prepare("SELECT COALESCE(FLOOR(pending_units/10000),0) FROM rebate_accounts WHERE user_id=?");$stmt->execute([$userId]);$rebate=(int)$stmt->fetchColumn();$min=(int)($db->query('SELECT min_claim_minor FROM rebate_settings WHERE id=1')->fetchColumn()?:0);if($rebate>0&&$rebate>=$min)$this->emitAutomatic($settings,'promo_rebate_available',$userId,'amount:'.$rebate,['amount'=>$this->money($rebate)]);

        $stmt=$db->prepare("SELECT amount_minor,period_key FROM rescue_daily_awards WHERE user_id=? AND state='AVAILABLE' ORDER BY period_key DESC LIMIT 1");$stmt->execute([$userId]);if($row=$stmt->fetch())$this->emitAutomatic($settings,'promo_rescue_available',$userId,(string)$row['period_key'],['amount'=>$this->money((int)$row['amount_minor'])]);

        $stmt=$db->prepare("SELECT COUNT(*) FROM vip_period_awards WHERE user_id=? AND state='AVAILABLE'");$stmt->execute([$userId]);$c=(int)$stmt->fetchColumn();if($c>0)$this->emitAutomatic($settings,'vip_available',$userId,'count:'.$c,['count'=>$c]);
    }
    private function resolveUser(PDO $db,string $identifier):?string
    {
        $stmt=$db->prepare('SELECT id FROM users WHERE id=? OR CAST(public_id AS CHAR)=? OR username=? OR email=? LIMIT 1');$stmt->execute([$identifier,$identifier,$identifier,$identifier]);$value=$stmt->fetchColumn();return $value?(string)$value:null;
    }

    private function date(mixed $value):?string
    {
        $value=trim((string)($value??''));if($value==='')return null;$value=str_replace('T',' ',$value);if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/',$value))throw new DomainException('Data/hora inválida.');return strlen($value)===16?$value.':00':$value;
    }
}
