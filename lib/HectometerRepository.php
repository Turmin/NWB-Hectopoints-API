<?php

declare(strict_types=1);

require_once __DIR__ . '/Nwb.php';

final class HectometerRepository
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function search(array $filters): array
    {
        $road = nwbNormalizeRoad($filters['weg'] ?? $filters['road'] ?? '');
        $side = nwbNormalizeSide($filters['zijde'] ?? $filters['side'] ?? '');
        $letter = strtoupper(trim((string)($filters['letter'] ?? '')));
        $hmCandidates = nwbHectometerCandidates((string)($filters['hectometer'] ?? $filters['hm'] ?? ''));
        $limit = max(1, min((int)($filters['limit'] ?? 25), 100));

        $where = [];
        $params = [':limit' => $limit];

        if ($road !== '') {
            $where[] = $this->roadExpression() . ' = :road';
            $params[':road'] = $road;
        }

        if ($side !== '') {
            $aliases = $side === 'R' ? ['R', 'RE', 'RECHTS'] : ($side === 'L' ? ['L', 'LI', 'LINKS'] : [$side]);
            $placeholders = [];
            foreach ($aliases as $index => $alias) {
                $key = ':side_' . $index;
                $placeholders[] = $key;
                $params[$key] = $alias;
            }
            $where[] = 'UPPER(COALESCE(h.zijde, \'\')) IN (' . implode(', ', $placeholders) . ')';
        }

        if ($letter !== '') {
            $where[] = 'UPPER(COALESCE(h.hectoletter, \'\')) = :letter';
            $params[':letter'] = $letter;
        }

        if ($hmCandidates !== []) {
            $placeholders = [];
            foreach ($hmCandidates as $index => $candidate) {
                $key = ':hm_' . $index;
                $placeholders[] = $key;
                $params[$key] = $candidate;
            }
            $where[] = 'h.hectometrering IN (' . implode(', ', $placeholders) . ')';
        }

        if ($where === []) {
            return [];
        }

        $sql = $this->baseSelectSql()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY road_number IS NULL, road_number ASC, h.hectometrering IS NULL, h.hectometrering ASC, h.zijde ASC'
            . ' LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll());
    }

    public function findByPermalink(string $road, string $side, string $hectometer, ?string $letter = null): ?array
    {
        $road = nwbNormalizeRoad($road);
        $side = nwbNormalizeSide($side);
        $letter = strtoupper(trim((string)$letter));
        $hmCandidates = nwbHectometerCandidates($hectometer);

        if ($road === '' || $hmCandidates === []) {
            return null;
        }

        $where = [$this->roadExpression() . ' = :road'];
        $params = [':road' => $road];

        if ($side !== '' && $side !== '-') {
            $aliases = $side === 'R' ? ['R', 'RE', 'RECHTS'] : ($side === 'L' ? ['L', 'LI', 'LINKS'] : [$side]);
            $placeholders = [];
            foreach ($aliases as $index => $alias) {
                $key = ':side_' . $index;
                $placeholders[] = $key;
                $params[$key] = $alias;
            }
            $where[] = 'UPPER(COALESCE(h.zijde, \'\')) IN (' . implode(', ', $placeholders) . ')';
        }

        if ($letter !== '') {
            $where[] = 'UPPER(COALESCE(h.hectoletter, \'\')) = :letter';
            $params[':letter'] = $letter;
        }

        $placeholders = [];
        $orderCases = [];
        foreach ($hmCandidates as $index => $candidate) {
            $key = ':hm_' . $index;
            $placeholders[] = $key;
            $params[$key] = $candidate;
            $orderKey = ':hm_order_' . $index;
            $orderCases[] = 'WHEN ' . $orderKey . ' THEN ' . $index;
            $params[$orderKey] = $candidate;
        }
        $where[] = 'h.hectometrering IN (' . implode(', ', $placeholders) . ')';

        $sql = $this->baseSelectSql()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY CASE h.hectometrering ' . implode(' ', $orderCases) . ' ELSE 99 END, h.updated_at DESC'
            . ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function nearest(float $latitude, float $longitude): ?array
    {
        $distanceSql = '
            (
                6371000 * 2 * ASIN(LEAST(1, SQRT(
                    POWER(SIN(RADIANS(h.latitude - :lat_a) / 2), 2)
                    + COS(RADIANS(:lat_b)) * COS(RADIANS(h.latitude))
                    * POWER(SIN(RADIANS(h.longitude - :lon_a) / 2), 2)
                )))
            ) AS distance_meters
        ';

        $sql = $this->baseSelectSql($distanceSql)
            . ' WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL'
            . ' ORDER BY distance_meters ASC'
            . ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':lat_a' => $latitude,
            ':lat_b' => $latitude,
            ':lon_a' => $longitude,
        ]);
        $row = $stmt->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function bbox(float $minLon, float $minLat, float $maxLon, float $maxLat, int $limit = 500): array
    {
        $limit = max(1, min($limit, 2000));
        $sql = $this->baseSelectSql()
            . ' WHERE h.longitude BETWEEN :min_lon AND :max_lon'
            . ' AND h.latitude BETWEEN :min_lat AND :max_lat'
            . ' ORDER BY h.hectometrering IS NULL, h.hectometrering ASC'
            . ' LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':min_lon', $minLon);
        $stmt->bindValue(':min_lat', $minLat);
        $stmt->bindValue(':max_lon', $maxLon);
        $stmt->bindValue(':max_lat', $maxLat);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll());
    }

    public function stats(): array
    {
        $stats = [
            'hectopunten' => 0,
            'wegvakken' => 0,
            'roads' => 0,
            'last_updated' => null,
        ];

        foreach (['hectopunten', 'wegvakken'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $stats[$table] = (int)$this->db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }

        if ($this->tableExists('wegvakken')) {
            $stats['roads'] = (int)$this->db->query('
                SELECT COUNT(DISTINCT COALESCE(NULLIF(wegnr_hmp, \'\'), NULLIF(CONCAT(COALESCE(routeltr, \'\'), COALESCE(CAST(routenr AS CHAR), \'\')), \'\'), NULLIF(wegnummer, \'\'), NULLIF(wegnr_aw, \'\')))
                FROM wegvakken
            ')->fetchColumn();
        }

        if ($this->tableExists('hectopunten')) {
            $stats['last_updated'] = $this->db->query('SELECT MAX(updated_at) FROM hectopunten')->fetchColumn() ?: null;
        }

        return $stats;
    }

    public function roads(int $limit = 80): array
    {
        if (!$this->tableExists('wegvakken')) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $roadExpression = $this->roadExpression();
        $sql = '
            SELECT
                ' . $roadExpression . ' AS road,
                COUNT(DISTINCT h.id) AS hectopunten
            FROM wegvakken w
            LEFT JOIN hectopunten h ON h.wvk_id = w.wvk_id
            WHERE ' . $roadExpression . ' IS NOT NULL
            GROUP BY road
            ORDER BY
                CASE
                    WHEN road LIKE \'A%\' THEN 0
                    WHEN road LIKE \'N%\' THEN 1
                    ELSE 2
                END,
                LENGTH(road),
                road
            LIMIT :limit
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): array {
            return [
                'road' => nwbNormalizeRoad((string)$row['road']),
                'hectopunten' => (int)$row['hectopunten'],
            ];
        }, $stmt->fetchAll());
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
        ');
        $stmt->execute([':table' => $table]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function toGeoJsonFeature(array $item): array
    {
        return [
            'type' => 'Feature',
            'id' => $item['id'],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$item['longitude'], $item['latitude']],
            ],
            'properties' => [
                'road' => $item['road'],
                'side' => $item['side'],
                'side_label' => $item['side_label'],
                'hectometer' => $item['hectometer'],
                'letter' => $item['letter'],
                'place' => $item['place'],
                'municipality' => $item['municipality'],
                'road_manager' => $item['road_manager'],
                'permalink' => $item['permalink'],
            ],
        ];
    }

    private function mapRow(array $row): array
    {
        $road = nwbNormalizeRoad((string)($row['road_number'] ?? ''));
        $side = nwbNormalizeSide((string)($row['zijde'] ?? ''));
        $letter = strtoupper(trim((string)($row['hectoletter'] ?? '')));
        $hectometrering = $row['hectometrering'] !== null ? (int)$row['hectometrering'] : null;
        $latitude = $row['latitude'] !== null ? (float)$row['latitude'] : null;
        $longitude = $row['longitude'] !== null ? (float)$row['longitude'] : null;

        return [
            'id' => $row['bron_id'],
            'road' => $road,
            'side' => $side,
            'side_label' => nwbSideLabel($side),
            'hectometrering' => $hectometrering,
            'hectometer' => nwbFormatHectometer($hectometrering),
            'letter' => $letter,
            'distance_along_road_meters' => $row['afstand'] !== null ? (int)$row['afstand'] : null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'place' => $row['wpsnaam'] ?: null,
            'municipality' => $row['gme_naam'] ?: null,
            'street' => $row['stt_naam'] ?: null,
            'road_manager' => $row['wegbehnaam'] ?: null,
            'road_manager_type' => $row['wegbehsrt'] ?: null,
            'district' => $row['distrnaam'] ?: null,
            'service_area' => $row['dienstnaam'] ?: null,
            'road_type' => $row['wegtype'] ?: null,
            'road_type_description' => $row['wgtype_oms'] ?: null,
            'wvk_id' => $row['wvk_id'] !== null ? (string)$row['wvk_id'] : null,
            'wvk_begdat' => $row['wvk_begdat'] ?: null,
            'distance_meters' => isset($row['distance_meters']) ? round((float)$row['distance_meters']) : null,
            'permalink' => nwbPermalink($road, $side, $hectometrering, $letter),
            'source' => 'PDOK NWB',
        ];
    }

    private function baseSelectSql(string $extraSelect = ''): string
    {
        $extraSelect = $extraSelect !== '' ? ', ' . $extraSelect : '';

        return '
            SELECT
                h.bron_id,
                h.hectometrering,
                h.hectoletter,
                h.zijde,
                h.afstand,
                h.wvk_id,
                h.wvk_begdat,
                h.latitude,
                h.longitude,
                ' . $this->roadExpression() . ' AS road_number,
                w.stt_naam,
                w.gme_naam,
                w.wpsnaam,
                w.wegbehnaam,
                w.wegbehsrt,
                w.distrnaam,
                w.dienstnaam,
                w.wegtype,
                w.wgtype_oms
                ' . $extraSelect . '
            FROM hectopunten h
            LEFT JOIN wegvakken w ON w.id = (
                SELECT w2.id
                FROM wegvakken w2
                WHERE w2.wvk_id = h.wvk_id
                ORDER BY
                    CASE
                        WHEN h.wvk_begdat IS NOT NULL
                            AND w2.wvk_begdat IS NOT NULL
                            AND DATE(w2.wvk_begdat) = DATE(h.wvk_begdat)
                        THEN 0
                        ELSE 1
                    END,
                    w2.wvk_begdat DESC,
                    w2.id DESC
                LIMIT 1
                )
        ';
    }

    private function roadExpression(): string
    {
        return "
            UPPER(
                COALESCE(
                    NULLIF(w.wegnr_hmp, ''),
                    NULLIF(CONCAT(COALESCE(w.routeltr, ''), COALESCE(CAST(w.routenr AS CHAR), '')), ''),
                    NULLIF(w.wegnummer, ''),
                    NULLIF(w.wegnr_aw, '')
                )
            )
        ";
    }
}
