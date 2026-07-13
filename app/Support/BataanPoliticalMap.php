<?php

namespace App\Support;

class BataanPoliticalMap
{
    /**
     * @return array{viewBox: string, paths: list<array{slug: string, name: string, d: string, centroid: array{0: float, 1: float}}>}
     */
    public static function svgPaths(): array
    {
        $topoPath = public_path('maps/bataan.topo.json');

        if (! is_file($topoPath)) {
            return ['viewBox' => '0 0 100 100', 'paths' => []];
        }

        $topology = json_decode((string) file_get_contents($topoPath), true);

        if (! is_array($topology)) {
            return ['viewBox' => '0 0 100 100', 'paths' => []];
        }

        $objectKey = array_key_first($topology['objects'] ?? []);
        $collection = $topology['objects'][$objectKey] ?? null;
        $arcs = $topology['arcs'] ?? [];
        $transform = $topology['transform'] ?? null;

        if (! is_array($collection) || ! is_array($transform)) {
            return ['viewBox' => '0 0 100 100', 'paths' => []];
        }

        $decodedPaths = [];
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;

        foreach ($collection['geometries'] ?? [] as $geometry) {
            $rings = self::decodeGeometry($geometry, $arcs, $transform);
            $name = (string) ($geometry['properties']['adm3_en'] ?? 'Unknown');
            $slug = self::slugForName($name);

            foreach ($rings as $ring) {
                foreach ($ring as [$lon, $lat]) {
                    $minX = min($minX, $lon);
                    $minY = min($minY, $lat);
                    $maxX = max($maxX, $lon);
                    $maxY = max($maxY, $lat);
                }
            }

            $decodedPaths[] = [
                'slug' => $slug,
                'name' => self::displayName($name),
                'rings' => $rings,
            ];
        }

        $padding = 0.02;
        $width = max(0.001, $maxX - $minX);
        $height = max(0.001, $maxY - $minY);
        $padX = $width * $padding;
        $padY = $height * $padding;
        $viewMinX = $minX - $padX;
        $viewMinY = $minY - $padY;
        $viewWidth = $width + ($padX * 2);
        $viewHeight = $height + ($padY * 2);

        $paths = [];

        foreach ($decodedPaths as $entry) {
            $segments = [];
            $centroidX = 0.0;
            $centroidY = 0.0;
            $pointCount = 0;

            foreach ($entry['rings'] as $ring) {
                if ($ring === []) {
                    continue;
                }

                $segment = [];

                foreach ($ring as [$lon, $lat]) {
                    $x = (($lon - $viewMinX) / $viewWidth) * 1000;
                    $y = (1 - (($lat - $viewMinY) / $viewHeight)) * 1000;
                    $segment[] = sprintf('%.2F,%.2F', $x, $y);
                    $centroidX += $x;
                    $centroidY += $y;
                    $pointCount++;
                }

                $segments[] = 'M '.implode(' L ', $segment).' Z';
            }

            if ($segments === []) {
                continue;
            }

            $paths[] = [
                'slug' => $entry['slug'],
                'name' => $entry['name'],
                'd' => implode(' ', $segments),
                'centroid' => [
                    $pointCount > 0 ? $centroidX / $pointCount : 500,
                    $pointCount > 0 ? $centroidY / $pointCount : 500,
                ],
            ];
        }

        return [
            'viewBox' => '0 0 1000 1000',
            'paths' => $paths,
        ];
    }

    public static function slugForName(string $name): string
    {
        $normalized = preg_replace('/^City of\s+/i', '', trim($name)) ?? trim($name);

        return str($normalized)->slug()->toString();
    }

    public static function displayName(string $name): string
    {
        return preg_replace('/^City of\s+/i', '', trim($name)) ?? trim($name);
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $arcs
     * @param  array{scale: array{0: float, 1: float}, translate: array{0: float, 1: float}}  $transform
     * @return list<list<array{0: float, 1: float}>>
     */
    protected static function decodeGeometry(array $geometry, array $arcs, array $transform): array
    {
        $arcSets = $geometry['arcs'] ?? [];

        if (($geometry['type'] ?? '') === 'Polygon') {
            return array_map(
                fn (array $ringArcs): array => self::decodeArcSet($ringArcs, $arcs, $transform),
                $arcSets
            );
        }

        if (($geometry['type'] ?? '') === 'MultiPolygon') {
            return collect($arcSets)
                ->flatMap(fn (array $polygonArcs): array => array_map(
                    fn (array $ringArcs): array => self::decodeArcSet($ringArcs, $arcs, $transform),
                    $polygonArcs
                ))
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @param  list<int>  $arcSet
     * @param  list<list<array{0: int, 1: int}>>  $arcs
     * @param  array{scale: array{0: float, 1: float}, translate: array{0: float, 1: float}}  $transform
     * @return list<array{0: float, 1: float}>
     */
    protected static function decodeArcSet(array $arcSet, array $arcs, array $transform): array
    {
        $ring = [];

        foreach ($arcSet as $arcIndex) {
            $reverse = $arcIndex < 0;
            $index = $reverse ? ~$arcIndex : $arcIndex;
            $arc = $arcs[$index] ?? [];
            $x = 0.0;
            $y = 0.0;
            $points = [];

            foreach ($arc as $delta) {
                $x += (float) ($delta[0] ?? 0);
                $y += (float) ($delta[1] ?? 0);
                $points[] = [
                    ($x * $transform['scale'][0]) + $transform['translate'][0],
                    ($y * $transform['scale'][1]) + $transform['translate'][1],
                ];
            }

            if ($reverse) {
                $points = array_reverse($points);
            }

            if ($ring !== [] && $points !== [] && $ring[array_key_last($ring)] === $points[0]) {
                array_shift($points);
            }

            $ring = array_merge($ring, $points);
        }

        return $ring;
    }
}
