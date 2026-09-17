<?php

declare(strict_types=1);

namespace App\Modules\Analytics;

use App\Core\Database\Database;
use App\Core\Http\Request;

final class AnalyticsService
{
    public function recordPublicVisit(Request $request): void
    {
        try {
            $headers = $request->headers;
            $city = $this->header($headers, 'X-Geo-City') ?? $this->header($headers, 'CF-IPCity');
            $region = $this->header($headers, 'X-Geo-Region') ?? $this->header($headers, 'CF-Region');
            $country = $this->header($headers, 'X-Geo-Country') ?? $this->header($headers, 'CF-IPCountry');
            $ip = $request->clientIp();
            $stmt = Database::connection()->prepare(
                'INSERT INTO site_visits (path,ip_hash,city,region,country) VALUES (:path,:ip_hash,:city,:region,:country)'
            );
            $stmt->execute([
                'path' => substr((string)($request->path ?? '/'), 0, 190),
                'ip_hash' => $ip ? hash('sha256', $ip) : null,
                'city' => $this->clean($city),
                'region' => $this->clean($region),
                'country' => $this->clean($country),
            ]);
        } catch (\Throwable $e) {
            // Analytics can never break the storefront.
            error_log('analytics visit failed: ' . $e->getMessage());
        }
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) {
                return is_array($value) ? (string)reset($value) : (string)$value;
            }
        }
        return null;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 120) return null;
        return $value;
    }
}
