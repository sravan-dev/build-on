-- Multi-site attendance: one worker + one date keeps a single daily_attendance
-- record, and that record carries unlimited site entries.
--
-- Each entry is a stretch of work at one site with its own break, so a worker
-- who moves from a villa in Al Waab to a majlis in Duhail has two rows for the
-- day and the day's hours are the sum of both.

CREATE TABLE IF NOT EXISTS attendance_site_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_attendance_id INT NOT NULL,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    project_id INT DEFAULT NULL,
    -- Snapshot of the site name as it was entered: projects get renamed, and a
    -- historic timesheet should keep saying where the work happened.
    site_name VARCHAR(255) DEFAULT NULL,
    time_in TIME NOT NULL,
    break_out TIME DEFAULT NULL,
    break_in TIME DEFAULT NULL,
    time_out TIME DEFAULT NULL,
    working_hours DECIMAL(6,2) DEFAULT 0,
    notes TEXT,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_site_entries_daily (daily_attendance_id),
    KEY idx_site_entries_employee_date (employee_id, attendance_date),
    KEY idx_site_entries_project (project_id),
    CONSTRAINT fk_site_entries_daily FOREIGN KEY (daily_attendance_id)
        REFERENCES daily_attendance(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: every existing day becomes its single site entry, so the new
-- reports show history rather than starting empty.
INSERT INTO attendance_site_entries
    (daily_attendance_id, employee_id, attendance_date, project_id, site_name,
     time_in, time_out, working_hours, created_by)
SELECT
    da.id,
    da.employee_id,
    da.attendance_date,
    (SELECT al.project_id FROM attendance_logs al
      WHERE al.daily_attendance_id = da.id AND al.project_id IS NOT NULL
      ORDER BY al.id LIMIT 1),
    da.work_site,
    da.in_time,
    da.out_time,
    GREATEST(0, ROUND(
        TIME_TO_SEC(TIMEDIFF(COALESCE(da.out_time, da.in_time), da.in_time)) / 3600
        - COALESCE((SELECT SUM(TIME_TO_SEC(TIMEDIFF(al.end_time, al.start_time))) / 3600
                    FROM attendance_logs al
                    WHERE al.daily_attendance_id = da.id
                      AND al.activity_type = 'break'
                      AND al.end_time IS NOT NULL), 0)
    , 2)),
    'migration'
FROM daily_attendance da
WHERE da.in_time IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM attendance_site_entries e WHERE e.daily_attendance_id = da.id);
