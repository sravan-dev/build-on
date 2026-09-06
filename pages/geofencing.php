<?php
/**
 * Geofencing module.
 *
 * Lists every project with its location and fence, and lets an admin set the
 * limit for one: drop the pin, set the radius, switch the fence on. The app
 * refuses to start work outside an enabled fence.
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/geofence.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'supervisor'], true)) {
    echo '<div class="p-6 bg-red-50 text-red-700 rounded">You do not have access to geofencing.</div>';
    return;
}

if (empty($_SESSION['geofence_csrf'])) {
    $_SESSION['geofence_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['geofence_csrf'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_geofence'])) {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new Exception('This form expired. Reload the page and try again.');
        }

        $projectId = (int) ($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            throw new Exception('Select a site.');
        }

        $enabled = isset($_POST['geofence_enabled']) ? 1 : 0;
        $lat = trim((string) ($_POST['latitude'] ?? ''));
        $lon = trim((string) ($_POST['longitude'] ?? ''));
        $radius = (int) ($_POST['geofence_radius'] ?? 200);
        $label = trim((string) ($_POST['location_label'] ?? ''));

        if ($lat === '' && $lon === '') {
            $lat = null;
            $lon = null;
        } else {
            if (!isValidLatitude($lat) || !isValidLongitude($lon)) {
                throw new Exception('Latitude must be between -90 and 90, longitude between -180 and 180.');
            }
        }

        // Switching a fence on without a position would block every worker at
        // that site, so require the pin first.
        if ($enabled && ($lat === null || $lon === null)) {
            throw new Exception('Set the location before switching the fence on.');
        }

        if ($radius < 20 || $radius > 20000) {
            throw new Exception('Radius must be between 20 m and 20 km.');
        }

        $stmt = $pdo->prepare("UPDATE projects
                               SET latitude = ?, longitude = ?, geofence_radius = ?,
                                   geofence_enabled = ?, location_label = ?
                               WHERE id = ?");
        $stmt->execute([$lat, $lon, $radius, $enabled, $label ?: null, $projectId]);

        $message = 'Geofence saved.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT id, name, latitude, longitude, geofence_radius, geofence_enabled, location_label
        FROM projects";
$params = [];
if ($search !== '') {
    $sql .= " WHERE name LIKE ?";
    $params[] = '%' . $search . '%';
}
$sql .= " ORDER BY geofence_enabled DESC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$withFence = 0;
$positioned = 0;
foreach ($projects as $p) {
    if ((int) $p['geofence_enabled'] === 1) {
        $withFence++;
    }
    if ($p['latitude'] !== null && $p['longitude'] !== null) {
        $positioned++;
    }
}
?>

<!-- Leaflet, pinned and integrity-checked: a compromised CDN must not be able to
     run its own script on a page that edits site data. Hashes computed from the
     1.9.4 files themselves. -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H"
    crossorigin="anonymous" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH"
    crossorigin="anonymous"></script>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Geofencing</h1>
    <p class="text-gray-600 mt-2">Set how close a worker must be before the app will let them start at a site.</p>
</div>

<?php if ($message): ?>
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-md">
    <div class="p-6 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex gap-8">
            <div>
                <div class="text-sm text-gray-500">Sites</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo count($projects); ?></div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Positioned</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $positioned; ?></div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Fence on</div>
                <div class="text-2xl font-bold text-primary"><?php echo $withFence; ?></div>
            </div>
        </div>
        <form method="get" class="flex gap-2">
            <input type="hidden" name="page" value="geofencing">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search sites..."
                class="px-3 py-2 border border-gray-300 rounded-md">
            <button class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded-md">Search</button>
        </form>
    </div>

    <div class="p-4 md:p-6 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Site</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Radius</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fence</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($projects as $p): ?>
                    <?php $hasPin = $p['latitude'] !== null && $p['longitude'] !== null; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($p['name']); ?></div>
                            <?php if ($p['location_label']): ?>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($p['location_label']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($hasPin): ?>
                                <a href="https://maps.google.com/?q=<?php echo $p['latitude']; ?>,<?php echo $p['longitude']; ?>"
                                    target="_blank" rel="noopener" class="text-primary hover:underline font-mono text-xs">
                                    <?php echo number_format((float) $p['latitude'], 5); ?>,
                                    <?php echo number_format((float) $p['longitude'], 5); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400">not set</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?php echo (int) $p['geofence_radius']; ?> m</td>
                        <td class="px-4 py-3">
                            <?php if ((int) $p['geofence_enabled'] === 1): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Enforced</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Off</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button type="button"
                                onclick='openGeofenceModal(<?php echo json_encode([
                                    "id" => (int) $p["id"],
                                    "name" => $p["name"],
                                    "latitude" => $p["latitude"],
                                    "longitude" => $p["longitude"],
                                    "radius" => (int) $p["geofence_radius"],
                                    "enabled" => (int) $p["geofence_enabled"],
                                    "label" => $p["location_label"],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                class="bg-primary hover:bg-secondary text-white px-3 py-1.5 rounded-md text-xs font-medium">
                                <i class="fas fa-map-marker-alt mr-1"></i>Set Limit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No sites found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Set Limit modal -->
<div id="geofenceModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 py-6">
        <div class="fixed inset-0" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeGeofenceModal()"></div>
        </div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <form method="post">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Set Site Limit</h3>
                        <p id="gf-site-name" class="text-sm text-gray-500"></p>
                    </div>
                    <button type="button" onclick="closeGeofenceModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="project_id" id="gf-project-id">

                    <div id="gf-map" class="w-full h-64 rounded-md border"></div>
                    <p class="text-xs text-gray-500">
                        Click the map to drop the pin, or use the button to take this device's position.
                        The circle is the area where workers may start.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                            <input type="text" name="latitude" id="gf-lat" class="w-full px-3 py-2 border border-gray-300 rounded-md font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                            <input type="text" name="longitude" id="gf-lon" class="w-full px-3 py-2 border border-gray-300 rounded-md font-mono text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Radius (metres)</label>
                            <input type="number" name="geofence_radius" id="gf-radius" min="20" max="20000" step="10"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location name (optional)</label>
                        <input type="text" name="location_label" id="gf-label" placeholder="Al Waab, Street 12"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>

                    <label class="flex items-center gap-3 bg-gray-50 border rounded-md px-4 py-3 cursor-pointer">
                        <input type="checkbox" name="geofence_enabled" id="gf-enabled" class="h-4 w-4">
                        <span>
                            <span class="font-medium text-gray-900">Enforce this fence</span>
                            <span class="block text-xs text-gray-500">
                                Workers outside the circle cannot start at this site. Leave off while surveying.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="px-5 py-4 border-t flex justify-between items-center">
                    <button type="button" onclick="useMyLocation()" class="px-4 py-2 bg-white border rounded-md text-sm">
                        <i class="fas fa-crosshairs mr-2"></i>Use my location
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeGeofenceModal()" class="px-4 py-2 bg-white border rounded-md">Cancel</button>
                        <button type="submit" name="save_geofence" value="1" class="px-5 py-2 bg-primary hover:bg-secondary text-white rounded-md font-medium">
                            Save Limit
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Doha, used only as the starting view for a site with no pin yet.
    const GF_DEFAULT = [25.2854, 51.5310];
    let gfMap = null, gfMarker = null, gfCircle = null;

    function openGeofenceModal(project) {
        document.getElementById('geofenceModal').classList.remove('hidden');
        document.getElementById('gf-site-name').textContent = project.name;
        document.getElementById('gf-project-id').value = project.id;
        document.getElementById('gf-lat').value = project.latitude ?? '';
        document.getElementById('gf-lon').value = project.longitude ?? '';
        document.getElementById('gf-radius').value = project.radius || 200;
        document.getElementById('gf-label').value = project.label ?? '';
        document.getElementById('gf-enabled').checked = project.enabled === 1;

        const start = (project.latitude && project.longitude)
            ? [parseFloat(project.latitude), parseFloat(project.longitude)]
            : GF_DEFAULT;

        // Leaflet needs the container visible and sized before it will draw.
        setTimeout(function () {
            if (!gfMap) {
                gfMap = L.map('gf-map').setView(start, 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(gfMap);
                gfMap.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng); });
            }
            gfMap.invalidateSize();
            gfMap.setView(start, project.latitude ? 16 : 12);
            if (project.latitude && project.longitude) {
                setPin(parseFloat(project.latitude), parseFloat(project.longitude));
            } else if (gfMarker) {
                gfMap.removeLayer(gfMarker); gfMarker = null;
                if (gfCircle) { gfMap.removeLayer(gfCircle); gfCircle = null; }
            }
        }, 60);
    }

    function setPin(lat, lon) {
        document.getElementById('gf-lat').value = lat.toFixed(7);
        document.getElementById('gf-lon').value = lon.toFixed(7);
        const radius = parseInt(document.getElementById('gf-radius').value, 10) || 200;

        if (gfMarker) { gfMap.removeLayer(gfMarker); }
        if (gfCircle) { gfMap.removeLayer(gfCircle); }
        gfMarker = L.marker([lat, lon], { draggable: true }).addTo(gfMap);
        gfMarker.on('dragend', function (e) {
            const p = e.target.getLatLng();
            setPin(p.lat, p.lng);
        });
        gfCircle = L.circle([lat, lon], { radius: radius, color: '#EE7B00', fillOpacity: 0.12 }).addTo(gfMap);
    }

    document.getElementById('gf-radius').addEventListener('input', function () {
        const lat = parseFloat(document.getElementById('gf-lat').value);
        const lon = parseFloat(document.getElementById('gf-lon').value);
        if (!isNaN(lat) && !isNaN(lon)) { setPin(lat, lon); }
    });

    function useMyLocation() {
        if (!navigator.geolocation) {
            alert('This browser cannot report a location.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                gfMap.setView([pos.coords.latitude, pos.coords.longitude], 17);
                setPin(pos.coords.latitude, pos.coords.longitude);
            },
            function (err) { alert('Could not get your location: ' + err.message); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    function closeGeofenceModal() {
        document.getElementById('geofenceModal').classList.add('hidden');
    }
</script>
