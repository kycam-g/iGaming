<?php

declare(strict_types=1);

namespace App\Modules\Casino;

use App\Core\Database\Database;
use DomainException;
use PDO;

final class CasinoCatalogService
{
    public function providers(): array
    {
        return Database::connection()->query('SELECT id,code,name,enabled,mode,api_source,created_at FROM casino_providers ORDER BY name')->fetchAll();
    }

    public function games(bool $public = false): array
    {
        $sql = 'SELECT g.id,g.provider_id,g.external_id,g.name,g.category,g.image_url,g.enabled,g.featured,g.sort_order,p.code AS provider_code,p.name AS provider_name,p.api_source AS api_source,p.enabled AS provider_enabled,p.mode AS provider_mode FROM casino_games g JOIN casino_providers p ON p.id=g.provider_id';
        if ($public) $sql .= " WHERE g.enabled=1 AND p.enabled=1 AND p.mode='DEMO'";
        return Database::connection()->query($sql . ' ORDER BY g.featured DESC,g.sort_order ASC,g.id DESC')->fetchAll();
    }

    public function saveProvider(array $data): array
    {
        $code = strtolower(trim((string)($data['code'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        $mode = (string)($data['mode'] ?? 'DEMO');
        $apiSource = strtoupper(trim((string)($data['api_source'] ?? 'MANUAL')));
        if (!preg_match('/^[a-z0-9_-]{2,60}$/', $code) || mb_strlen($name)>120 || $name==='' || !in_array($mode,['DEMO','PRODUCTION'],true) || !in_array($apiSource,['MANUAL','PLAYFIVER'],true)) throw new DomainException('Dados do provedor inválidos.');
        $id = (int)($data['id'] ?? 0);
        $pdo = Database::connection();
        if ($id) {
            $stmt=$pdo->prepare('UPDATE casino_providers SET name=:name,enabled=:enabled,mode=:mode,api_source=:api_source WHERE id=:id');
            $stmt->execute(['name'=>$name,'enabled'=>!empty($data['enabled'])?1:0,'mode'=>$mode,'api_source'=>$apiSource,'id'=>$id]);
            if (!$stmt->rowCount() && !$this->providerExists($id)) throw new DomainException('Provedor não encontrado.');
        } else {
            $stmt=$pdo->prepare('INSERT INTO casino_providers(code,name,enabled,mode,api_source) VALUES(:code,:name,:enabled,:mode,:api_source)');
            $stmt->execute(['code'=>$code,'name'=>$name,'enabled'=>!empty($data['enabled'])?1:0,'mode'=>$mode,'api_source'=>$apiSource]);
            $id=(int)$pdo->lastInsertId();
        }
        $stmt=$pdo->prepare('SELECT id,code,name,enabled,mode,api_source FROM casino_providers WHERE id=:id');$stmt->execute(['id'=>$id]);return $stmt->fetch();
    }

    private function providerExists(int $id): bool
    {
        $s=Database::connection()->prepare('SELECT 1 FROM casino_providers WHERE id=:id');$s->execute(['id'=>$id]);return (bool)$s->fetchColumn();
    }

    public function saveGame(array $data): array
    {
        $id=(int)($data['id']??0);$provider=(int)($data['provider_id']??0);
        $external=trim((string)($data['external_id']??''));$name=trim((string)($data['name']??''));
        $category=(string)($data['category']??'SLOTS');$image=trim((string)($data['image_url']??''));$sort=(int)($data['sort_order']??100);
        if (!$this->providerExists($provider) || $external==='' || strlen($external)>190 || $name==='' || mb_strlen($name)>190 || !in_array($category,['SLOTS','LIVE','TABLE','OTHER'],true) || strlen($image)>500 || ($image!=='' && (!filter_var($image,FILTER_VALIDATE_URL) || !str_starts_with($image,'https://'))) || $sort<0 || $sort>100000) throw new DomainException('Dados do jogo inválidos. Imagens exigem URL HTTPS.');
        $args=['provider'=>$provider,'external'=>$external,'name'=>$name,'category'=>$category,'image'=>$image?:null,'enabled'=>!empty($data['enabled'])?1:0,'featured'=>!empty($data['featured'])?1:0,'sort'=>$sort];
        $pdo=Database::connection();
        if ($id) {
            $args['id']=$id;
            $s=$pdo->prepare('UPDATE casino_games SET provider_id=:provider,external_id=:external,name=:name,category=:category,image_url=:image,enabled=:enabled,featured=:featured,sort_order=:sort WHERE id=:id');$s->execute($args);
            $s=$pdo->prepare('SELECT id FROM casino_games WHERE id=:id');$s->execute(['id'=>$id]);if (!$s->fetch())throw new DomainException('Jogo não encontrado.');
        } else {
            $s=$pdo->prepare('INSERT INTO casino_games(provider_id,external_id,name,category,image_url,enabled,featured,sort_order) VALUES(:provider,:external,:name,:category,:image,:enabled,:featured,:sort)');$s->execute($args);$id=(int)$pdo->lastInsertId();
        }
        $s=$pdo->prepare('SELECT id,provider_id,external_id,name,category,image_url,enabled,featured,sort_order FROM casino_games WHERE id=:id');$s->execute(['id'=>$id]);return $s->fetch();
    }

    public function deleteGame(int $id): void
    {
        if ($id <= 0) throw new DomainException('Jogo inválido.');
        $stmt=Database::connection()->prepare('DELETE FROM casino_games WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        if (!$stmt->rowCount()) throw new DomainException('Jogo não encontrado.');
    }
}
