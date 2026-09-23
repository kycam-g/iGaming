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

    private function resolveUser(PDO $db,string $identifier):?string
    {
        $stmt=$db->prepare('SELECT id FROM users WHERE id=? OR CAST(public_id AS CHAR)=? OR username=? OR email=? LIMIT 1');$stmt->execute([$identifier,$identifier,$identifier,$identifier]);$value=$stmt->fetchColumn();return $value?(string)$value:null;
    }

    private function date(mixed $value):?string
    {
        $value=trim((string)($value??''));if($value==='')return null;$value=str_replace('T',' ',$value);if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/',$value))throw new DomainException('Data/hora inválida.');return strlen($value)===16?$value.':00':$value;
    }
}
