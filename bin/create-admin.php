<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database\Database;
use App\Core\Support\Env;

Env::load(dirname(__DIR__) . '/.env');
$email = strtolower(trim((string) ($argv[1] ?? '')));
$name = trim((string) ($argv[2] ?? 'Administrador'));
$password = (string) ($argv[3] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Usage: php bin/create-admin.php admin@example.com \"Administrador\" \"SenhaForte123!\"\nSenha mínima: 12 caracteres.\n");
    exit(1);
}
$data = random_bytes(16); $data[6]=chr((ord($data[6])&0x0f)|0x40); $data[8]=chr((ord($data[8])&0x3f)|0x80); $hex=bin2hex($data);
$id=sprintf('%s-%s-%s-%s-%s',substr($hex,0,8),substr($hex,8,4),substr($hex,12,4),substr($hex,16,4),substr($hex,20));
$hash=password_hash($password, PASSWORD_ARGON2ID);
$stmt=Database::connection()->prepare("INSERT INTO admin_users (id,email,name,password_hash,status) VALUES (:id,:email,:name,:hash,'ACTIVE') ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),status='ACTIVE'");
$stmt->execute(['id'=>$id,'email'=>$email,'name'=>$name,'hash'=>$hash]);
echo "Admin ready: {$email}\n";
