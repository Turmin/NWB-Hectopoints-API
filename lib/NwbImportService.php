<?php

declare(strict_types=1);

require_once __DIR__ . '/Nwb.php';

final class NwbImportService
{
    private $db;
    private $baseUrl;

    public function __construct(PDO $db, string $baseUrl = NWB_API_BASE_URL)
    {
        $this->db = $db;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function importAll(int $pageSize = 1000, ?int $maxPages = null): array
    {
        return [
            'wegvakken' => $this->importCollection('wegvakken', $pageSize, $maxPages),
            'hectopunten' => $this->importCollection('hectopunten', $pageSize, $maxPages),
        ];
    }

    public function importCollection(string $collection, int $pageSize = 1000, ?int $maxPages = null): array
    {
        if (!in_array($collection, ['wegvakken', 'hectopunten'], true)) {
            throw new InvalidArgumentException('Unknown NWB collection: ' . $collection);
        }

        $pageSize = max(1, min($pageSize, 1000));
        $url = $this->baseUrl . '/collections/' . rawurlencode($collection) . '/items?' . http_build_query([
            'f' => 'json',
            'limit' => $pageSize,
        ]);

        $pages = 0;
        $imported = 0;
        $startedAt = microtime(true);

        while ($url !== null) {
            $payload = $this->fetchJson($url);
            $features = $payload['features'] ?? [];
            if (!is_array($features)) {
                throw new RuntimeException('Unexpected PDOK response for ' . $collection);
            }

            $this->db->beginTransaction();
            try {
                foreach ($features as $feature) {
                    if (!is_array($feature)) {
                        continue;
                    }

                    if ($collection === 'wegvakken') {
                        $this->upsertWegvak($feature);
                    } else {
                        $this->upsertHectopunt($feature);
                    }
                    $imported++;
                }
                $this->db->commit();
            } catch (Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }

            $pages++;
            if ($maxPages !== null && $pages >= $maxPages) {
                break;
            }

            $url = $this->nextUrl($payload);
        }

        return [
            'collection' => $collection,
            'pages' => $pages,
            'features' => $imported,
            'seconds' => round(microtime(true) - $startedAt, 2),
        ];
    }

    private function upsertHectopunt(array $feature): void
    {
        $properties = $feature['properties'] ?? [];
        if (!is_array($properties)) {
            $properties = [];
        }

        $coordinate = nwbFirstCoordinate($feature['geometry'] ?? null);
        if ($coordinate === null) {
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO ' . HM_TABLE_HECTOPUNTEN . ' (
                bron_id, objectid, wvk_id, wvk_begdat, afstand, hectometrering,
                hectometer, zijde, hectoletter, longitude, latitude, geometry_geojson,
                properties_json, updated_at
            )
            VALUES (
                :bron_id, :objectid, :wvk_id, :wvk_begdat, :afstand, :hectometrering,
                :hectometer, :zijde, :hectoletter, :lon, :lat, :geometry,
                :properties,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                objectid = VALUES(objectid),
                wvk_id = VALUES(wvk_id),
                wvk_begdat = VALUES(wvk_begdat),
                afstand = VALUES(afstand),
                hectometrering = VALUES(hectometrering),
                hectometer = VALUES(hectometer),
                zijde = VALUES(zijde),
                hectoletter = VALUES(hectoletter),
                longitude = VALUES(longitude),
                latitude = VALUES(latitude),
                geometry_geojson = VALUES(geometry_geojson),
                properties_json = VALUES(properties_json),
                updated_at = NOW()
        ');

        $hectometrering = $this->nullableInt($properties['hectomtrng'] ?? null);
        $stmt->execute([
            ':bron_id' => $this->nullableString($feature['id'] ?? null),
            ':objectid' => $this->nullableInt($properties['objectid'] ?? null),
            ':wvk_id' => $this->nullableNumeric($properties['wvk_id'] ?? null),
            ':wvk_begdat' => $this->nullableDateTime($properties['wvk_begdat'] ?? null),
            ':afstand' => $this->nullableInt($properties['afstand'] ?? null),
            ':hectometrering' => $hectometrering,
            ':hectometer' => $hectometrering !== null ? $hectometrering / 10 : null,
            ':zijde' => $this->nullableString($properties['zijde'] ?? null),
            ':hectoletter' => $this->nullableString($properties['hecto_lttr'] ?? null),
            ':lon' => $coordinate[0],
            ':lat' => $coordinate[1],
            ':geometry' => json_encode($feature['geometry'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':properties' => json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function upsertWegvak(array $feature): void
    {
        $properties = $feature['properties'] ?? [];
        if (!is_array($properties)) {
            $properties = [];
        }

        $geometry = $feature['geometry'] ?? null;
        if (!is_array($geometry)) {
            return;
        }

        $stmt = $this->db->prepare('
            INSERT INTO ' . HM_TABLE_WEGVAKKEN . ' (
                bron_id, objectid, wvk_id, wvk_begdat, wegnummer, wegnr_hmp, wegnr_aw,
                routeltr, routenr, wegdeelltr, wegtype, wgtype_oms, stt_naam,
                gme_id, gme_naam, wpsnaam, wegbehcode, wegbehnaam, wegbehsrt,
                dienstnaam, distrnaam, beginkm, eindkm, begafstand, endafstand,
                geometry_geojson, properties_json, updated_at
            )
            VALUES (
                :bron_id, :objectid, :wvk_id, :wvk_begdat, :wegnummer, :wegnr_hmp, :wegnr_aw,
                :routeltr, :routenr, :wegdeelltr, :wegtype, :wgtype_oms, :stt_naam,
                :gme_id, :gme_naam, :wpsnaam, :wegbehcode, :wegbehnaam, :wegbehsrt,
                :dienstnaam, :distrnaam, :beginkm, :eindkm, :begafstand, :endafstand,
                :geometry,
                :properties,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                objectid = VALUES(objectid),
                wvk_id = VALUES(wvk_id),
                wvk_begdat = VALUES(wvk_begdat),
                wegnummer = VALUES(wegnummer),
                wegnr_hmp = VALUES(wegnr_hmp),
                wegnr_aw = VALUES(wegnr_aw),
                routeltr = VALUES(routeltr),
                routenr = VALUES(routenr),
                wegdeelltr = VALUES(wegdeelltr),
                wegtype = VALUES(wegtype),
                wgtype_oms = VALUES(wgtype_oms),
                stt_naam = VALUES(stt_naam),
                gme_id = VALUES(gme_id),
                gme_naam = VALUES(gme_naam),
                wpsnaam = VALUES(wpsnaam),
                wegbehcode = VALUES(wegbehcode),
                wegbehnaam = VALUES(wegbehnaam),
                wegbehsrt = VALUES(wegbehsrt),
                dienstnaam = VALUES(dienstnaam),
                distrnaam = VALUES(distrnaam),
                beginkm = VALUES(beginkm),
                eindkm = VALUES(eindkm),
                begafstand = VALUES(begafstand),
                endafstand = VALUES(endafstand),
                geometry_geojson = VALUES(geometry_geojson),
                properties_json = VALUES(properties_json),
                updated_at = NOW()
        ');

        $stmt->execute([
            ':bron_id' => $this->nullableString($feature['id'] ?? null),
            ':objectid' => $this->nullableInt($properties['objectid'] ?? null),
            ':wvk_id' => $this->nullableNumeric($properties['wvk_id'] ?? null),
            ':wvk_begdat' => $this->nullableDateTime($properties['wvk_begdat'] ?? null),
            ':wegnummer' => $this->nullableString($properties['wegnummer'] ?? null),
            ':wegnr_hmp' => $this->nullableString($properties['wegnr_hmp'] ?? null),
            ':wegnr_aw' => $this->nullableString($properties['wegnr_aw'] ?? null),
            ':routeltr' => $this->nullableString($properties['routeltr'] ?? null),
            ':routenr' => $this->nullableInt($properties['routenr'] ?? null),
            ':wegdeelltr' => $this->nullableString($properties['wegdeelltr'] ?? null),
            ':wegtype' => $this->nullableString($properties['wegtype'] ?? null),
            ':wgtype_oms' => $this->nullableString($properties['wgtype_oms'] ?? null),
            ':stt_naam' => $this->nullableString($properties['stt_naam'] ?? null),
            ':gme_id' => $this->nullableInt($properties['gme_id'] ?? null),
            ':gme_naam' => $this->nullableString($properties['gme_naam'] ?? null),
            ':wpsnaam' => $this->nullableString($properties['wpsnaam'] ?? null),
            ':wegbehcode' => $this->nullableString($properties['wegbehcode'] ?? null),
            ':wegbehnaam' => $this->nullableString($properties['wegbehnaam'] ?? null),
            ':wegbehsrt' => $this->nullableString($properties['wegbehsrt'] ?? null),
            ':dienstnaam' => $this->nullableString($properties['dienstnaam'] ?? null),
            ':distrnaam' => $this->nullableString($properties['distrnaam'] ?? null),
            ':beginkm' => $this->nullableFloat($properties['beginkm'] ?? null),
            ':eindkm' => $this->nullableFloat($properties['eindkm'] ?? null),
            ':begafstand' => $this->nullableInt($properties['begafstand'] ?? null),
            ':endafstand' => $this->nullableInt($properties['endafstand'] ?? null),
            ':geometry' => json_encode($geometry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':properties' => json_encode($properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/geo+json, application/json',
                    'User-Agent: Hectometerpaaltjes/1.0',
                ]),
                'timeout' => 60,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('PDOK request failed: ' . $url);
        }

        $payload = json_decode($response, true);
        if (!is_array($payload)) {
            throw new RuntimeException('PDOK returned invalid JSON.');
        }

        return $payload;
    }

    private function nextUrl(array $payload): ?string
    {
        foreach (($payload['links'] ?? []) as $link) {
            if (is_array($link) && ($link['rel'] ?? '') === 'next' && !empty($link['href'])) {
                return (string)$link['href'];
            }
        }

        return null;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float)$value : null;
    }

    private function nullableDateTime($value): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function nullableNumeric($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string)$value : null;
    }
}
