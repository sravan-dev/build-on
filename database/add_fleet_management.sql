-- Vehicle Fleet Management (Vehicle_Fleet_Management_Developer_Requirements.docx).
--
-- Extends the vehicle tables that already hold live data rather than adding a
-- parallel set, so existing daily logs and fuel records stay in the same history.

-- ---------------------------------------------------------------- vehicles (§2)
ALTER TABLE vehicles ADD COLUMN color VARCHAR(50) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN fuel_tank_capacity DECIMAL(8,2) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN project_id INT DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN driver_id INT DEFAULT NULL;
-- §10: expected mileage per vehicle; entries outside it raise Warning/Critical.
ALTER TABLE vehicles ADD COLUMN expected_kmpl_min DECIMAL(6,2) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN expected_kmpl_max DECIMAL(6,2) DEFAULT NULL;
-- §9: a daily distance above this is flagged as unusual.
ALTER TABLE vehicles ADD COLUMN max_daily_km INT DEFAULT 600;

-- ---------------------------------------------------------------- drivers (§13)
CREATE TABLE IF NOT EXISTS fleet_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    mobile VARCHAR(40) DEFAULT NULL,
    license_number VARCHAR(80) DEFAULT NULL,
    license_expiry DATE DEFAULT NULL,
    assigned_vehicle_id INT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fleet_drivers_employee (employee_id),
    KEY idx_fleet_drivers_vehicle (assigned_vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing free-text driver names become driver records, so history keeps a link.
INSERT INTO fleet_drivers (name, status)
SELECT DISTINCT TRIM(v.assigned_driver), 'active'
FROM vehicles v
WHERE v.assigned_driver IS NOT NULL AND TRIM(v.assigned_driver) <> ''
  AND NOT EXISTS (SELECT 1 FROM fleet_drivers d WHERE d.name = TRIM(v.assigned_driver));

UPDATE vehicles v
JOIN fleet_drivers d ON d.name = TRIM(v.assigned_driver)
SET v.driver_id = d.id
WHERE v.driver_id IS NULL;

-- ------------------------------------------------------- daily records (§3, §9)
ALTER TABLE vehicle_daily_logs ADD COLUMN driver_id INT DEFAULT NULL;
ALTER TABLE vehicle_daily_logs ADD COLUMN project_id INT DEFAULT NULL;
ALTER TABLE vehicle_daily_logs ADD COLUMN alert_status VARCHAR(20) NOT NULL DEFAULT 'OK';
ALTER TABLE vehicle_daily_logs ADD COLUMN alert_note VARCHAR(255) DEFAULT NULL;
ALTER TABLE vehicle_daily_logs ADD COLUMN created_by VARCHAR(100) DEFAULT NULL;
ALTER TABLE vehicle_daily_logs ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
-- log_date is TEXT in this schema; dates are always YYYY-MM-DD, so a 10-char
-- prefix index covers them exactly.
CREATE INDEX idx_daily_logs_vehicle_date ON vehicle_daily_logs (vehicle_id, log_date(10));

-- ------------------------------------------------------ fuel entries (§4, §14)
ALTER TABLE vehicle_fuel_records ADD COLUMN daily_log_id INT DEFAULT NULL;
ALTER TABLE vehicle_fuel_records ADD COLUMN fuel_type VARCHAR(20) DEFAULT NULL;
ALTER TABLE vehicle_fuel_records ADD COLUMN receipt_number VARCHAR(80) DEFAULT NULL;
ALTER TABLE vehicle_fuel_records ADD COLUMN receipt_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE vehicle_fuel_records ADD COLUMN remarks TEXT;
ALTER TABLE vehicle_fuel_records ADD COLUMN created_by VARCHAR(100) DEFAULT NULL;
CREATE INDEX idx_fuel_vehicle_date ON vehicle_fuel_records (vehicle_id, fuel_date(10));
CREATE INDEX idx_fuel_daily_log ON vehicle_fuel_records (daily_log_id);

-- Link existing fuel records to the daily log for the same vehicle and date.
UPDATE vehicle_fuel_records f
JOIN vehicle_daily_logs l ON l.vehicle_id = f.vehicle_id AND l.log_date = f.fuel_date
SET f.daily_log_id = l.id
WHERE f.daily_log_id IS NULL;

-- ---------------------------------------------------------- audit trail (§17)
CREATE TABLE IF NOT EXISTS fleet_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity VARCHAR(40) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    field VARCHAR(60) DEFAULT NULL,
    old_value TEXT,
    new_value TEXT,
    performed_by VARCHAR(100) DEFAULT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fleet_audit_entity (entity, entity_id),
    KEY idx_fleet_audit_time (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
