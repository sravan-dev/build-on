<?php
/**
 * Geofencing for site attendance.
 *
 * Each project can carry a location and a radius in metres. When the fence is
 * enabled, a worker may only start at that site while standing inside the
 * circle. Sites without a fence behave exactly as before.
 */

if (!function_exists('geoDistanceMetres')) {
    /**
     * Great-circle distance between two points, in metres (haversine).
     * Accurate to well under a metre at the distances a site fence cares about.
     */
    function geoDistanceMetres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

if (!function_exists('isValidLatitude')) {
    function isValidLatitude($value): bool
    {
        return is_numeric($value) && $value >= -90 && $value <= 90;
    }
}

if (!function_exists('isValidLongitude')) {
    function isValidLongitude($value): bool
    {
        return is_numeric($value) && $value >= -180 && $value <= 180;
    }
}

if (!function_exists('projectGeofence')) {
    /** The fence for one project, or null when the project has none. */
    function projectGeofence(PDO $pdo, ?int $projectId): ?array
    {
        if (!$projectId) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT id, name, latitude, longitude, geofence_radius, geofence_enabled
                               FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('checkGeofence')) {
    /**
     * Decide whether a worker at ($lat, $lon) may start at this project.
     *
     * Returns ['allowed' => bool, 'reason' => string, 'distance' => ?int].
     *
     * The rules are deliberately explicit rather than permissive: if a site has
     * a fence turned on, a missing or unusable position is a refusal, otherwise
     * turning location off would be an easy way around it.
     */
    function checkGeofence(?array $project, $lat, $lon): array
    {
        if (!$project || (int) ($project['geofence_enabled'] ?? 0) !== 1) {
            return ['allowed' => true, 'reason' => 'No fence on this site.', 'distance' => null];
        }

        if (!isValidLatitude($project['latitude'] ?? null) || !isValidLongitude($project['longitude'] ?? null)) {
            // Fence switched on but never positioned: allow, and say so, rather
            // than blocking work because of an incomplete setup.
            return ['allowed' => true, 'reason' => 'Site has no coordinates set.', 'distance' => null];
        }

        if (!isValidLatitude($lat) || !isValidLongitude($lon)) {
            return [
                'allowed' => false,
                'reason' => 'Location is required to start at this site. Turn location on and try again.',
                'distance' => null,
            ];
        }

        $radius = (int) ($project['geofence_radius'] ?? 0);
        if ($radius <= 0) {
            $radius = 200;
        }

        $distance = (int) round(geoDistanceMetres(
            (float) $project['latitude'],
            (float) $project['longitude'],
            (float) $lat,
            (float) $lon
        ));

        if ($distance <= $radius) {
            return ['allowed' => true, 'reason' => 'Inside the site area.', 'distance' => $distance];
        }

        return [
            'allowed' => false,
            'reason' => sprintf(
                'You are %s from %s, outside the %s allowed. Move closer and try again.',
                formatMetres($distance),
                $project['name'] ?? 'this site',
                formatMetres($radius)
            ),
            'distance' => $distance,
        ];
    }
}

if (!function_exists('formatMetres')) {
    function formatMetres(int $metres): string
    {
        return $metres >= 1000
            ? number_format($metres / 1000, 1) . ' km'
            : $metres . ' m';
    }
}
