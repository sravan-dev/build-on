<?php
/**
 * Multi-site attendance.
 *
 * One worker on one date keeps a single daily_attendance record; that record
 * carries any number of attendance_site_entries rows, one per stretch of work
 * at a site. A worker who moves from a villa in Al Waab to a majlis in Duhail
 * has two entries and the day's hours are the sum.
 */

if (!function_exists('normalWorkingHours')) {
    /** Hours past which a day counts as overtime. */
    function normalWorkingHours(): float
    {
        $v = getenv('NORMAL_WORK_HOURS');
        $v = is_string($v) ? (float) $v : 0.0;
        return $v > 0 ? $v : 8.0;
    }
}

if (!function_exists('siteEntryHours')) {
    /**
     * Worked hours for one site entry: Time Out − Time In − break.
     * Times that wrap past midnight are treated as the next day rather than a
     * negative shift. Returns 0 while the entry is still open.
     */
    function siteEntryHours(?string $timeIn, ?string $timeOut, ?string $breakOut, ?string $breakIn): float
    {
        if (!$timeIn || !$timeOut) {
            return 0.0;
        }

        $base = strtotime('1970-01-01 ' . $timeIn);
        $end = strtotime('1970-01-01 ' . $timeOut);
        if ($base === false || $end === false) {
            return 0.0;
        }
        if ($end < $base) {
            $end += 86400;
        }
        $seconds = $end - $base;

        if ($breakOut && $breakIn) {
            $bOut = strtotime('1970-01-01 ' . $breakOut);
            $bIn = strtotime('1970-01-01 ' . $breakIn);
            if ($bOut !== false && $bIn !== false) {
                if ($bOut < $base) {
                    $bOut += 86400;
                }
                if ($bIn < $bOut) {
                    $bIn += 86400;
                }
                $break = $bIn - $bOut;
                // A break recorded outside the shift is a data-entry slip; ignore
                // it rather than producing negative hours.
                if ($break > 0 && $break <= $seconds) {
                    $seconds -= $break;
                }
            }
        }

        return round(max(0, $seconds) / 3600, 2);
    }
}

if (!function_exists('siteEntryBreakHours')) {
    function siteEntryBreakHours(?string $breakOut, ?string $breakIn): float
    {
        if (!$breakOut || !$breakIn) {
            return 0.0;
        }
        $out = strtotime('1970-01-01 ' . $breakOut);
        $in = strtotime('1970-01-01 ' . $breakIn);
        if ($out === false || $in === false) {
            return 0.0;
        }
        if ($in < $out) {
            $in += 86400;
        }
        return round(max(0, $in - $out) / 3600, 2);
    }
}

if (!function_exists('ensureDailyAttendance')) {
    /**
     * The single daily record for a worker and date, created if absent.
     * Site entries hang off this, so there is still exactly one row per day.
     */
    function ensureDailyAttendance(PDO $pdo, int $employeeId, string $date, string $status = 'Present'): int
    {
        $stmt = $pdo->prepare("SELECT id FROM daily_attendance WHERE employee_id = ? AND attendance_date = ?");
        $stmt->execute([$employeeId, $date]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $ins = $pdo->prepare("INSERT INTO daily_attendance (employee_id, attendance_date, status) VALUES (?, ?, ?)");
        $ins->execute([$employeeId, $date, $status]);
        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('syncDailyFromSiteEntries')) {
    /**
     * Roll the day's entries up onto daily_attendance so existing screens and
     * payroll queries — which read in_time/out_time/working_hours — stay right.
     * First check-in and last check-out of the day, total hours across sites.
     */
    function syncDailyFromSiteEntries(PDO $pdo, int $dailyId): void
    {
        $stmt = $pdo->prepare("SELECT MIN(time_in) AS first_in, MAX(time_out) AS last_out,
                                      COALESCE(SUM(working_hours), 0) AS total_hours,
                                      COUNT(*) AS entries
                               FROM attendance_site_entries WHERE daily_attendance_id = ?");
        $stmt->execute([$dailyId]);
        $agg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ((int) ($agg['entries'] ?? 0) === 0) {
            return;
        }

        // The day's site is the last one worked, which is what "currently at" means.
        $siteStmt = $pdo->prepare("SELECT site_name FROM attendance_site_entries
                                   WHERE daily_attendance_id = ? ORDER BY time_in DESC, id DESC LIMIT 1");
        $siteStmt->execute([$dailyId]);
        $site = $siteStmt->fetchColumn() ?: null;

        $upd = $pdo->prepare("UPDATE daily_attendance
                              SET in_time = ?, out_time = ?, working_hours = ?, work_site = ?, status = 'Present'
                              WHERE id = ?");
        $upd->execute([
            $agg['first_in'],
            $agg['last_out'],
            $agg['total_hours'],
            $site,
            $dailyId,
        ]);
    }
}

if (!function_exists('saveSiteEntry')) {
    /**
     * Insert or update one site entry, recomputing its hours and the day's roll-up.
     * Returns the entry id.
     */
    function saveSiteEntry(PDO $pdo, array $data): int
    {
        $employeeId = (int) $data['employee_id'];
        $date = $data['attendance_date'];
        $dailyId = ensureDailyAttendance($pdo, $employeeId, $date);

        $projectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $siteName = trim((string) ($data['site_name'] ?? ''));
        if ($siteName === '' && $projectId) {
            $p = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
            $p->execute([$projectId]);
            $siteName = (string) ($p->fetchColumn() ?: '');
        }

        $timeIn = $data['time_in'] ?: null;
        $timeOut = $data['time_out'] ?: null;
        $breakOut = $data['break_out'] ?: null;
        $breakIn = $data['break_in'] ?: null;
        $hours = siteEntryHours($timeIn, $timeOut, $breakOut, $breakIn);

        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE attendance_site_entries
                                   SET project_id = ?, site_name = ?, time_in = ?, break_out = ?,
                                       break_in = ?, time_out = ?, working_hours = ?, notes = ?
                                   WHERE id = ?");
            $stmt->execute([
                $projectId, $siteName ?: null, $timeIn, $breakOut, $breakIn, $timeOut,
                $hours, $data['notes'] ?? null, (int) $data['id'],
            ]);
            $entryId = (int) $data['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO attendance_site_entries
                (daily_attendance_id, employee_id, attendance_date, project_id, site_name,
                 time_in, break_out, break_in, time_out, working_hours, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $dailyId, $employeeId, $date, $projectId, $siteName ?: null,
                $timeIn, $breakOut, $breakIn, $timeOut, $hours,
                $data['notes'] ?? null, $data['created_by'] ?? null,
            ]);
            $entryId = (int) $pdo->lastInsertId();
        }

        syncDailyFromSiteEntries($pdo, $dailyId);
        return $entryId;
    }
}

if (!function_exists('deleteSiteEntry')) {
    function deleteSiteEntry(PDO $pdo, int $entryId): void
    {
        $stmt = $pdo->prepare("SELECT daily_attendance_id FROM attendance_site_entries WHERE id = ?");
        $stmt->execute([$entryId]);
        $dailyId = (int) $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM attendance_site_entries WHERE id = ?")->execute([$entryId]);

        if ($dailyId) {
            syncDailyFromSiteEntries($pdo, $dailyId);
        }
    }
}

if (!function_exists('siteEntriesForDay')) {
    function siteEntriesForDay(PDO $pdo, int $employeeId, string $date): array
    {
        $stmt = $pdo->prepare("SELECT e.*, p.name AS project_name
                               FROM attendance_site_entries e
                               LEFT JOIN projects p ON e.project_id = p.id
                               WHERE e.employee_id = ? AND e.attendance_date = ?
                               ORDER BY e.time_in ASC, e.id ASC");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
