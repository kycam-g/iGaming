<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Support\Env;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly array $query,
        public readonly array $body,
        public readonly string $rawBody = '',
        public readonly ?string $remoteAddress = null,
    ) {}

    public static function capture(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = is_array($headers) ? $headers : [];
        if (!isset($headers['Authorization']) && isset($_SERVER['HTTP_AUTHORIZATION'])) $headers['Authorization']=(string)$_SERVER['HTTP_AUTHORIZATION'];
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) $path = substr($path, strlen($basePath));
        elseif ($basePath !== '' && $path === $basePath) $path = '/';
        if ($path !== '/' && str_ends_with($path, '/')) $path = rtrim($path, '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),$path,$headers,$_GET,is_array($json)?$json:$_POST,$raw,isset($_SERVER['REMOTE_ADDR'])?(string)$_SERVER['REMOTE_ADDR']:null);
    }

    public function bearerToken(): ?string
    {
        $authorization=$this->headers['Authorization']??$this->headers['authorization']??null;
        if(!$authorization||!preg_match('/^Bearer\s+(.+)$/i',$authorization,$m)) return null;
        return trim($m[1]);
    }
    public function clientIp(): ?string { return $this->remoteAddress; }
}
