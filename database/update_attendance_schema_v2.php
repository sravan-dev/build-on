<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        daily_attendance_id INTEGER NOT NULL,
        project_id INTEGER, -- Nullable for breaks or general work
        start_time TIME NOT NULL,
        end_time TIME,
        activity_type TEXT NOT NULL DEFAULT 'work', -- work, break, offsite
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(daily_attendance_id) REFERENCES daily_attendance(id) ON DELETE CASCADE
    )");
    echo "✅ Created attendance_logs table\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
