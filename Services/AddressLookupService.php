<?php

namespace Services;

use Core\Config;
use Core\Container;
use Core\Database\QueryBuilder;

/**
 * Адрес регистрации: кэш t_dadata, при промахе — подсказка DaData.
 * Легаси: Object\Dadata\Dadata::addCache. Токен только из DADATA_TOKEN.
 */
class AddressLookupService
{
    private QueryBuilder $db;

    public function __construct(?QueryBuilder $db = null)
    {
        $this->db = $db ?? Container::getInstance()->get(QueryBuilder::class);
    }

    /**
     * @return array{view_region:int,view_city:int,address_dadata:string}|null
     */
    public function resolve(string $address): ?array
    {
        $address = str_replace(['–'], ['-'], trim($address));
        $address = mb_substr($address, 0, 255);
        if ($address === '') {
            return null;
        }

        $cached = $this->findCache($address);
        if ($cached !== null) {
            return $cached;
        }

        $token = trim((string) Config::getInstance()->get('integration.dadata.token', ''));
        if ($token === '') {
            return null;
        }

        $suggestions = $this->suggest($token, $address);
        if ($suggestions === []) {
            return null;
        }

        $regionId = 0;
        $cityId = 0;
        $stored = null;
        foreach (array_slice($suggestions, 0, 3) as $suggestion) {
            $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];
            $regionName = trim((string) ($data['region'] ?? ''));
            if ($regionName === '') {
                continue;
            }
            $regionId = $this->regionId($regionName, trim((string) ($data['region_type'] ?? '')));
            if ($regionId <= 0) {
                continue;
            }
            $cityName = trim((string) ($data['city_with_type'] ?? ''));
            if ($cityName === '') {
                $cityName = trim((string) ($data['settlement_with_type'] ?? ''));
            }
            $cityId = $cityName !== '' ? $this->cityId($cityName) : 0;
            $stored = $suggestion;
            break;
        }

        if ($regionId <= 0 || $stored === null) {
            return null;
        }

        $json = json_encode($stored, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '';
        }

        $this->db->execute(
            'INSERT INTO `t_dadata` (`varc_address`, `address_dadata`, `view_region`, `view_city`, `created_add`) VALUES (?, ?, ?, ?, ?)',
            [$address, $json, $regionId, $cityId, date('Y-m-d H:i:s')]
        );

        return [
            'view_region' => $regionId,
            'view_city' => $cityId,
            'address_dadata' => $json,
        ];
    }

    /**
     * @return array{view_region:int,view_city:int,address_dadata:string}|null
     */
    private function findCache(string $address): ?array
    {
        $stmt = $this->db->execute(
            'SELECT view_region, view_city, address_dadata FROM t_dadata WHERE varc_address = ? AND view_region > 0 LIMIT 1',
            [$address]
        );
        if (!$stmt instanceof \PDOStatement) {
            return null;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row) || (int) ($row['view_region'] ?? 0) <= 0) {
            return null;
        }

        return [
            'view_region' => (int) $row['view_region'],
            'view_city' => (int) ($row['view_city'] ?? 0),
            'address_dadata' => (string) ($row['address_dadata'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggest(string $token, string $address): array
    {
        $payload = json_encode([
            'query' => $address,
            'locations' => [
                ['country' => 'KZ'],
                ['country_iso_code' => 'RU'],
                ['country_iso_code' => 'BY'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return [];
        }

        $ch = curl_init('https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address');
        if ($ch === false) {
            return [];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Token ' . $token,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        $suggestions = is_array($decoded) ? ($decoded['suggestions'] ?? null) : null;

        return is_array($suggestions) ? $suggestions : [];
    }

    private function regionId(string $name, string $type): int
    {
        $stmt = $this->db->execute('SELECT cat FROM t_geo_region WHERE varc_name = ? LIMIT 1', [$name]);
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        if (is_array($row) && (int) ($row['cat'] ?? 0) > 0) {
            return (int) $row['cat'];
        }

        $id = $this->db->execute(
            'INSERT INTO `t_geo_region` (`varc_name`, `varc_type`) VALUES (?, ?)',
            [$name, $type],
            true
        );

        return (int) $id;
    }

    private function cityId(string $name): int
    {
        $name = mb_substr($name, 0, 255);
        $stmt = $this->db->execute('SELECT cat FROM t_geo_city WHERE varc_name = ? LIMIT 1', [$name]);
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : false;
        if (is_array($row) && (int) ($row['cat'] ?? 0) > 0) {
            return (int) $row['cat'];
        }

        $id = $this->db->execute(
            'INSERT INTO `t_geo_city` (`varc_name`) VALUES (?)',
            [$name],
            true
        );

        return (int) $id;
    }
}
