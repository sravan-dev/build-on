<?php
/**
 * Vehicle Fleet Management — shared rules.
 *
 * Section numbers refer to Vehicle_Fleet_Management_Developer_Requirements.docx.
 * Everything that computes a figure or decides whether an entry is acceptable
 * lives here, so the entry screen, the dashboard and the reports cannot drift
 * apart on what "8.18 KM/L" or "Warning" means.
 */

if (!defined('FLEET_CRITICAL_MARGIN')) {
    // §10: an entry outside the expected range is a Warning; one this far
    // outside (as a share of the nearest bound) is Critical.
    define('FLEET_CRITICAL_MARGIN', 0.25);
}

// ------------------------------------------------------------------ roles (§16)

if (!function_exists('fleetRole')) {
    function fleetRole(): string
    {
        return (string) ($_SESSION['role'] ?? '');
    }
}

if (!function_exists('fleetCan')) {
    /**
     * Permission check for one capability.
     *
     *   view        see the module at all
     *   manage      vehicles, drivers, settings
     *   enter       daily KM / fuel entry and receipts
     *   edit_km     change odometer figures after entry
     *   delete      remove records
     *   reports     reports and exports
     */
    function fleetCan(string $capability): bool
    {
        $role = fleetRole();

        $matrix = [
            // Admin: full access.
            'superadmin' => ['view', 'manage', 'enter', 'edit_km', 'delete', 'reports'],
            'admin' => ['view', 'manage', 'enter', 'edit_km', 'delete', 'reports'],
            // Fleet Manager: vehicles, drivers, records, reports, approvals.
            'fleet_manager' => ['view', 'manage', 'enter', 'edit_km', 'delete', 'reports'],
            // Supervisor: daily KM/fuel entry and receipt upload.
            'supervisor' => ['view', 'enter'],
            // Driver: own/assigned vehicle entries and receipts.
            'driver' => ['view', 'enter'],
            // Accounts: fuel cost, transactions and reports; no KM editing.
            'accounts_manager' => ['view', 'reports'],
        ];

        return in_array($capability, $matrix[$role] ?? [], true);
    }
}

// ------------------------------------------------------------ calculations (§5)

if (!function_exists('fleetKmpl')) {
    /** KM/L = Total KM ÷ Fuel Litres. Null when there is nothing to divide. */
    function fleetKmpl(?float $km, ?float $litres): ?float
    {
        if (!$km || !$litres || $litres <= 0) {
            return null;
        }
        return round($km / $litres, 2);
    }
}

if (!function_exists('fleetLitresPer100')) {
    /** L/100KM = Fuel Litres ÷ Total KM × 100. */
    function fleetLitresPer100(?float $km, ?float $litres): ?float
    {
        if (!$km || $km <= 0 || !$litres) {
            return null;
        }
        return round($litres / $km * 100, 2);
    }
}

if (!function_exists('fleetCostPerKm')) {
    /** Fuel Cost per KM = Total Fuel Cost ÷ Total KM. */
    function fleetCostPerKm(?float $cost, ?float $km): ?float
    {
        if (!$km || $km <= 0 || $cost === null) {
            return null;
        }
        return round($cost / $km, 3);
    }
}

// ----------------------------------------------------------- odometer (§3, §9)

if (!function_exists('fleetPreviousClosingKm')) {
    /**
     * §3: Opening KM uses the previous closing KM. "Previous" means the latest
     * record strictly before this date, so back-dating an entry reads the right
     * odometer rather than today's.
     */
    function fleetPreviousClosingKm(PDO $pdo, int $vehicleId, string $date, ?int $excludeLogId = null): ?float
    {
        $sql = "SELECT closing_km FROM vehicle_daily_logs
                WHERE vehicle_id = ? AND log_date < ? AND closing_km IS NOT NULL";
        $params = [$vehicleId, $date];
        if ($excludeLogId) {
            $sql .= " AND id <> ?";
            $params[] = $excludeLogId;
        }
        $sql .= " ORDER BY log_date DESC, id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $km = $stmt->fetchColumn();

        if ($km !== false && $km !== null) {
            return (float) $km;
        }

        // First record for the vehicle: fall back to the odometer on its master.
        $v = $pdo->prepare("SELECT current_mileage FROM vehicles WHERE id = ?");
        $v->execute([$vehicleId]);
        $current = $v->fetchColumn();
        return $current !== false && $current !== null ? (float) $current : null;
    }
}

if (!function_exists('fleetExistingLogId')) {
    /** §9: the daily record already on file for this vehicle and date, if any. */
    function fleetExistingLogId(PDO $pdo, int $vehicleId, string $date, ?int $excludeLogId = null): ?int
    {
        $sql = "SELECT id FROM vehicle_daily_logs WHERE vehicle_id = ? AND log_date = ?";
        $params = [$vehicleId, $date];
        if ($excludeLogId) {
            $sql .= " AND id <> ?";
            $params[] = $excludeLogId;
        }
        $stmt = $pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}

// ---------------------------------------------------------- validation (§9)

if (!function_exists('fleetValidateEntry')) {
    /**
     * Check a daily entry before it is saved.
     *
     * Returns ['errors' => [...], 'confirm' => [...]]. Errors block the save.
     * Confirmations are the §9 cases the spec says need the user to agree
     * ("require confirmation") — a duplicate day, fuel over tank capacity, an
     * unusually large distance — and are cleared by resubmitting with confirm=1.
     */
    function fleetValidateEntry(PDO $pdo, array $vehicle, array $in, ?int $editingLogId = null): array
    {
        $errors = [];
        $confirm = [];

        $opening = $in['opening_km'];
        $closing = $in['closing_km'];

        if ($closing === null) {
            $errors[] = 'Closing KM is required.';
        } elseif ($opening !== null && $closing < $opening) {
            $errors[] = sprintf(
                'Closing KM (%s) cannot be lower than Opening KM (%s).',
                number_format($closing),
                number_format($opening)
            );
        }

        if (($in['litres'] ?? 0) < 0) {
            $errors[] = 'Fuel quantity cannot be negative.';
        }
        if (($in['rate'] ?? 0) < 0) {
            $errors[] = 'Rate per litre cannot be negative.';
        }

        if (!$errors) {
            $distance = $closing - ($opening ?? $closing);
            $maxDaily = (int) ($vehicle['max_daily_km'] ?? 0);
            if ($maxDaily > 0 && $distance > $maxDaily) {
                $confirm[] = sprintf(
                    '%s km in one day is above this vehicle\'s usual limit of %s km. Check the odometer reading.',
                    number_format($distance),
                    number_format($maxDaily)
                );
            }

            $tank = (float) ($vehicle['fuel_tank_capacity'] ?? 0);
            if ($tank > 0 && ($in['litres'] ?? 0) > $tank) {
                $confirm[] = sprintf(
                    '%s L is more than the %s L tank capacity.',
                    rtrim(rtrim(number_format((float) $in['litres'], 2), '0'), '.'),
                    rtrim(rtrim(number_format($tank, 2), '0'), '.')
                );
            }

            if (fleetExistingLogId($pdo, (int) $vehicle['id'], $in['date'], $editingLogId)) {
                $confirm[] = 'This vehicle already has a record for this date.';
            }
        }

        return ['errors' => $errors, 'confirm' => $confirm];
    }
}

// ------------------------------------------------------------ alerts (§9, §10)

if (!function_exists('fleetMileageAlert')) {
    /**
     * Classify a day's mileage against the vehicle's expected range.
     * Returns [status, note]: status is OK, Warning or Critical.
     */
    function fleetMileageAlert(array $vehicle, ?float $kmpl): array
    {
        $min = isset($vehicle['expected_kmpl_min']) ? (float) $vehicle['expected_kmpl_min'] : 0.0;
        $max = isset($vehicle['expected_kmpl_max']) ? (float) $vehicle['expected_kmpl_max'] : 0.0;

        if ($kmpl === null || ($min <= 0 && $max <= 0)) {
            return ['OK', null];
        }

        $range = sprintf('%s–%s KM/L', $min > 0 ? rtrim(rtrim(number_format($min, 2), '0'), '.') : '?',
            $max > 0 ? rtrim(rtrim(number_format($max, 2), '0'), '.') : '?');

        if ($min > 0 && $kmpl < $min) {
            $status = ($min - $kmpl) / $min >= FLEET_CRITICAL_MARGIN ? 'Critical' : 'Warning';
            return [$status, sprintf('%s KM/L against an expected %s.', $kmpl, $range)];
        }
        if ($max > 0 && $kmpl > $max) {
            $status = ($kmpl - $max) / $max >= FLEET_CRITICAL_MARGIN ? 'Critical' : 'Warning';
            return [$status, sprintf('%s KM/L against an expected %s.', $kmpl, $range)];
        }

        return ['OK', null];
    }
}

// ------------------------------------------------------------- audit (§17)

if (!function_exists('fleetAudit')) {
    /**
     * Record a change. For updates, pass old and new rows and only the fields
     * that actually changed are written, one row each, so the log reads like
     * "Closing KM changed from 25,150 to 25,180 by Fleet Manager".
     */
    function fleetAudit(PDO $pdo, string $entity, int $entityId, string $action, array $old = [], array $new = []): void
    {
        $by = $_SESSION['username'] ?? ($_SESSION['role'] ?? 'system');
        $stmt = $pdo->prepare("INSERT INTO fleet_audit_log
            (entity, entity_id, action, field, old_value, new_value, performed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        if ($action === 'update') {
            foreach ($new as $field => $value) {
                $before = $old[$field] ?? null;
                if ((string) $before === (string) $value) {
                    continue;
                }
                $stmt->execute([$entity, $entityId, $action, $field,
                    $before === null ? null : (string) $before,
                    $value === null ? null : (string) $value, $by]);
            }
            return;
        }

        // create / delete: one summary row.
        $snapshot = $action === 'delete' ? $old : $new;
        $stmt->execute([$entity, $entityId, $action, null,
            $action === 'delete' ? json_encode($snapshot) : null,
            $action === 'create' ? json_encode($snapshot) : null, $by]);
    }
}

// ------------------------------------------------------------ receipts (§14)

if (!function_exists('fleetStoreReceipt')) {
    /**
     * Save an uploaded JPG/PNG/PDF receipt and return its web path.
     *
     * Type is decided from the file's own bytes, not the name or the browser's
     * claim, and the stored name is random — an upload is never trusted to
     * choose where it lands or what it executes as.
     */
    function fleetStoreReceipt(array $file): ?string
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The receipt upload failed. Try again.');
        }
        if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
            throw new RuntimeException('Receipts must be under 8 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];
        if (!isset($extensions[$mime])) {
            throw new RuntimeException('Receipts must be JPG, PNG or PDF.');
        }

        $dir = __DIR__ . '/../uploads/fuel_receipts';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException('Could not create the receipts folder.');
        }

        $name = date('Ymd') . '_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            throw new RuntimeException('Could not save the receipt.');
        }

        return 'uploads/fuel_receipts/' . $name;
    }
}

// ------------------------------------------------------------- formatting

if (!function_exists('fleetNum')) {
    function fleetNum($value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, $decimals);
    }
}
