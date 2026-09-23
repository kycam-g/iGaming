<?php

declare(strict_types=1);
namespace App\Modules\Platform;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class SupportService
{
    private const CATEGORIES=['deposit','withdrawal','bonus','account','games','other'];
    private const PRIORITIES=['normal','high','urgent'];
    private const STATUSES=['OPEN','IN_PROGRESS','RESOLVED','CLOSED'];
    private function db():PDO{return Database::connection();}

    public function userList(string $userId):array
    {
        $stmt=$this->db()->prepare("SELECT id,ticket_code,category,priority,subject,status,last_user_message_at,last_admin_message_at,created_at,updated_at FROM support_tickets WHERE user_id=? ORDER BY updated_at DESC LIMIT 100");
        $stmt->execute([$userId]);return $stmt->fetchAll();
    }
    public function userCreate(string $userId,array $input):array
    {
        $category=strtolower(trim((string)($input['category']??'')));if(!in_array($category,self::CATEGORIES,true))throw new DomainException('Categoria de atendimento inválida.');
        $subject=trim((string)($input['subject']??''));$message=trim((string)($input['message']??''));
        if($subject===''||mb_strlen($subject)>160)throw new DomainException('Informe um assunto de até 160 caracteres.');
        if($message===''||mb_strlen($message)>3000)throw new DomainException('Informe uma mensagem de até 3000 caracteres.');
        $attachment=$this->attachment($input['attachment_path']??null);
        return Database::transaction(function(PDO $db)use($userId,$category,$subject,$message,$attachment):array{
            $code='SUP-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
            $stmt=$db->prepare("INSERT INTO support_tickets(ticket_code,user_id,category,subject,status,last_user_message_at) VALUES(?,?,?,?,'OPEN',NOW(6))");$stmt->execute([$code,$userId,$category,$subject]);$id=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO support_messages(ticket_id,sender_type,sender_id,message,attachment_path) VALUES(?,'USER',?,?,?)")->execute([$id,$userId,$message,$attachment]);
            return $this->userTicket($userId,$id,$db);
        });
    }
    public function userReply(string $userId,int $ticketId,array $input):array
    {
        $message=trim((string)($input['message']??''));if($message===''||mb_strlen($message)>3000)throw new DomainException('Informe uma mensagem de até 3000 caracteres.');$attachment=$this->attachment($input['attachment_path']??null);
        return Database::transaction(function(PDO $db)use($userId,$ticketId,$message,$attachment):array{
            $stmt=$db->prepare('SELECT status FROM support_tickets WHERE id=? AND user_id=? FOR UPDATE');$stmt->execute([$ticketId,$userId]);$status=$stmt->fetchColumn();if(!$status)throw new DomainException('Atendimento não encontrado.');if($status==='CLOSED')throw new DomainException('Este atendimento está fechado.');
            $db->prepare("INSERT INTO support_messages(ticket_id,sender_type,sender_id,message,attachment_path) VALUES(?,'USER',?,?,?)")->execute([$ticketId,$userId,$message,$attachment]);
            $db->prepare("UPDATE support_tickets SET status=IF(status='RESOLVED','OPEN',status),last_user_message_at=NOW(6),resolved_at=NULL WHERE id=?")->execute([$ticketId]);
            return $this->userTicket($userId,$ticketId,$db);
        });
    }
    public function userTicket(string $userId,int $ticketId,?PDO $db=null):array
    {
        $db=$db?:$this->db();$stmt=$db->prepare('SELECT id,ticket_code,category,priority,subject,status,created_at,updated_at FROM support_tickets WHERE id=? AND user_id=?');$stmt->execute([$ticketId,$userId]);$ticket=$stmt->fetch();if(!$ticket)throw new DomainException('Atendimento não encontrado.');
        $msg=$db->prepare("SELECT id,sender_type,message,attachment_path,created_at FROM support_messages WHERE ticket_id=? AND sender_type<>'INTERNAL' ORDER BY id");$msg->execute([$ticketId]);$ticket['messages']=$msg->fetchAll();return $ticket;
    }
    public function adminList(array $query=[]):array
    {
        $where=[];$args=[];$status=strtoupper(trim((string)($query['status']??'')));if($status!==''&&in_array($status,self::STATUSES,true)){$where[]='t.status=?';$args[]=$status;}
        $category=strtolower(trim((string)($query['category']??'')));if($category!==''&&in_array($category,self::CATEGORIES,true)){$where[]='t.category=?';$args[]=$category;}
        $search=trim((string)($query['q']??''));if($search!==''){$where[]='(t.ticket_code LIKE ? OR t.subject LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.public_id LIKE ?)';$like='%'.$search.'%';array_push($args,$like,$like,$like,$like,$like);}
        $sql="SELECT t.*,u.username,u.email,u.public_id,a.name AS assigned_admin_name,(SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id=t.id) AS message_count FROM support_tickets t JOIN users u ON u.id=t.user_id LEFT JOIN admin_users a ON a.id=t.assigned_admin_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY FIELD(t.status,'OPEN','IN_PROGRESS','RESOLVED','CLOSED'),FIELD(t.priority,'urgent','high','normal'),t.updated_at DESC LIMIT 300";
        $stmt=$this->db()->prepare($sql);$stmt->execute($args);$rows=$stmt->fetchAll();foreach($rows as &$r){$r['sla_hours']=$this->slaHours($r);}unset($r);return $rows;
    }
    public function adminTicket(int $ticketId):array
    {
        $stmt=$this->db()->prepare('SELECT t.*,u.username,u.email,u.public_id,a.name AS assigned_admin_name FROM support_tickets t JOIN users u ON u.id=t.user_id LEFT JOIN admin_users a ON a.id=t.assigned_admin_id WHERE t.id=?');$stmt->execute([$ticketId]);$ticket=$stmt->fetch();if(!$ticket)throw new DomainException('Atendimento não encontrado.');
        $msg=$this->db()->prepare('SELECT id,sender_type,sender_id,message,attachment_path,created_at FROM support_messages WHERE ticket_id=? ORDER BY id');$msg->execute([$ticketId]);$ticket['messages']=$msg->fetchAll();$ticket['sla_hours']=$this->slaHours($ticket);return $ticket;
    }
    public function adminReply(string $adminId,int $ticketId,array $input,NotificationService $notifications):array
    {
        $message=trim((string)($input['message']??''));if($message===''||mb_strlen($message)>3000)throw new DomainException('Informe uma resposta de até 3000 caracteres.');$attachment=$this->attachment($input['attachment_path']??null);$internal=filter_var($input['internal']??false,FILTER_VALIDATE_BOOLEAN);
        $ticket=Database::transaction(function(PDO $db)use($adminId,$ticketId,$message,$attachment,$internal):array{
            $stmt=$db->prepare('SELECT * FROM support_tickets WHERE id=? FOR UPDATE');$stmt->execute([$ticketId]);$ticket=$stmt->fetch();if(!$ticket)throw new DomainException('Atendimento não encontrado.');
            $type=$internal?'INTERNAL':'ADMIN';$db->prepare('INSERT INTO support_messages(ticket_id,sender_type,sender_id,message,attachment_path) VALUES(?,?,?,?,?)')->execute([$ticketId,$type,$adminId,$message,$attachment]);
            if(!$internal)$db->prepare("UPDATE support_tickets SET status=IF(status='OPEN','IN_PROGRESS',status),assigned_admin_id=COALESCE(assigned_admin_id,?),last_admin_message_at=NOW(6) WHERE id=?")->execute([$adminId,$ticketId]);
            return $ticket;
        });
        if(!$internal)$notifications->sendUser((string)$ticket['user_id'],'support','Nova resposta do suporte','Há uma nova resposta no atendimento '.$ticket['ticket_code'].'.','/suporte?ticket='.$ticketId,'high','support_reply:'.$ticketId.':'.time());
        return $this->adminTicket($ticketId);
    }
    public function adminUpdate(string $adminId,int $ticketId,array $input):array
    {
        $status=strtoupper(trim((string)($input['status']??'')));if(!in_array($status,self::STATUSES,true))throw new DomainException('Status inválido.');$priority=strtolower(trim((string)($input['priority']??'normal')));if(!in_array($priority,self::PRIORITIES,true))throw new DomainException('Prioridade inválida.');
        $stmt=$this->db()->prepare("UPDATE support_tickets SET status=?,priority=?,assigned_admin_id=COALESCE(assigned_admin_id,?),resolved_at=IF(?='RESOLVED',COALESCE(resolved_at,NOW(6)),NULL),closed_at=IF(?='CLOSED',COALESCE(closed_at,NOW(6)),NULL) WHERE id=?");$stmt->execute([$status,$priority,$adminId,$status,$status,$ticketId]);return $this->adminTicket($ticketId);
    }
    private function attachment(mixed $path):?string{$v=trim((string)$path);if($v==='')return null;if(!preg_match('~^/uploads/support/[a-z0-9_-]+\.(?:png|jpg|webp)$~i',$v))throw new DomainException('Anexo inválido.');return $v;}
    private function slaHours(array $ticket):int{$base=$ticket['last_user_message_at']??$ticket['created_at']??null;if(!$base||in_array((string)($ticket['status']??''),['RESOLVED','CLOSED'],true))return 0;return max(0,(int)floor((time()-strtotime((string)$base))/3600));}
}
