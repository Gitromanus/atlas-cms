<?php

namespace App\Services\Geo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Определение города посетителя по IP (ip-api.com) + сессия + ручной выбор.
 */
class GeoIpService
{
    public function cityFromRequest(Request $request): array
    {
        $manual = trim((string) $request->input('city', ''));
        if ($manual !== '') {
            $city = $this->normalizeCity($manual);
            $request->session()->put('visitor_city', $city);

            return [
                'city' => $city,
                'region' => null,
                'country' => 'Россия',
                'source' => 'manual',
            ];
        }

        $sessionCity = $request->session()->get('visitor_city');
        if (is_string($sessionCity) && $sessionCity !== '') {
            return [
                'city' => $sessionCity,
                'region' => $request->session()->get('visitor_region'),
                'country' => 'Россия',
                'source' => 'session',
            ];
        }

        $ip = $this->clientIp($request);
        $geo = $this->lookupIp($ip);

        if ($geo !== null && filled($geo['city'] ?? null)) {
            $request->session()->put('visitor_city', $geo['city']);
            if (! empty($geo['region'])) {
                $request->session()->put('visitor_region', $geo['region']);
            }

            return $geo + ['source' => 'ip'];
        }

        return [
            'city' => 'Москва',
            'region' => 'Москва',
            'country' => 'Россия',
            'source' => 'default',
        ];
    }

    public function clientIp(Request $request): string
    {
        $ip = $request->header('CF-Connecting-IP')
            ?: $request->header('X-Real-IP')
            ?: $request->ip();

        if (! is_string($ip) || $ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return '77.88.55.77';
        }

        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return $ip;
    }

    public function lookupIp(string $ip): ?array
    {
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return Cache::remember('geoip:'.$ip, 86400, function () use ($ip) {
            try {
                $response = Http::timeout(3)
                    ->get('http://ip-api.com/json/'.$ip, [
                        'lang' => 'ru',
                        'fields' => 'status,country,countryCode,regionName,city,lat,lon',
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $json = $response->json() ?? [];
                if (($json['status'] ?? '') !== 'success') {
                    return null;
                }

                $city = $this->normalizeCity((string) ($json['city'] ?? ''));
                if ($city === '') {
                    return null;
                }

                return [
                    'city' => $city,
                    'region' => $json['regionName'] ?? null,
                    'country' => $json['country'] ?? 'Россия',
                    'lat' => isset($json['lat']) ? (float) $json['lat'] : null,
                    'lon' => isset($json['lon']) ? (float) $json['lon'] : null,
                ];
            } catch (\Throwable $e) {
                Log::debug('GeoIP failed: '.$e->getMessage());

                return null;
            }
        });
    }

    public function normalizeCity(string $city): string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $city) ?? '');
        $city = preg_replace('/^г\.?\s*/ui', '', $city) ?? $city;

        return $city;
    }

    public function destinationAddress(string $city, ?string $street = null): string
    {
        $street = trim((string) $street);
        if ($street !== '') {
            return $city.', '.$street;
        }

        return match (mb_strtolower($city)) {
            'москва' => 'Москва, Тверская улица 1',
            'санкт-петербург', 'петербург', 'спб' => 'Санкт-Петербург, Невский проспект 28',
            'казань' => 'Казань, улица Баумана 9',
            'новосибирск' => 'Новосибирск, Красный проспект 1',
            'екатеринбург' => 'Екатеринбург, улица Малышева 36',
            default => $city.', центральная улица 1',
        };
    }
}
