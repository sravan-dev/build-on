-- Enhanced Vehicle Management Schema

-- Drop existing vehicles table if needed
DROP TABLE IF EXISTS vehicles;

-- Vehicle Master Data (Registration)
CREATE TABLE vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_number TEXT NOT NULL UNIQUE,
    model TEXT NOT NULL,
    make TEXT NOT NULL,
    year INTEGER,
    chassis_number TEXT,
    engine_number TEXT,
    fuel_type TEXT DEFAULT 'Petrol', -- Petrol, Diesel, Electric, Hybrid
    assigned_driver TEXT,
    registration_renewal_date TEXT,
    insurance_renewal_date TEXT,
    purchase_date TEXT,
    purchase_price REAL DEFAULT 0,
    current_mileage REAL DEFAULT 0, -- Auto-updated from daily logs
    vehicle_status TEXT DEFAULT 'Active', -- Active, Under Repair, Sold
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Daily Vehicle Log (KM per day)
CREATE TABLE vehicle_daily_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    log_date TEXT NOT NULL,
    opening_km REAL NOT NULL,
    closing_km REAL NOT NULL,
    total_km REAL NOT NULL, -- Auto calculated: closing_km - opening_km
    driver_name TEXT,
    route_trip TEXT,
    remarks TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    UNIQUE(vehicle_id, log_date) -- One log per vehicle per day
);

-- Vehicle Expenses
CREATE TABLE vehicle_expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    expense_date TEXT NOT NULL,
    expense_type TEXT NOT NULL, -- Fuel, Oil Change, Tyres, Battery, Maintenance, Registration, Insurance, Fines, Washing, Other
    amount REAL NOT NULL,
    vendor_garage TEXT,
    invoice_number TEXT,
    description TEXT,
    attachment_path TEXT,
    odometer_reading REAL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Fuel Records (Detailed tracking)
CREATE TABLE vehicle_fuel_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    fuel_date TEXT NOT NULL,
    liters REAL NOT NULL,
    amount REAL NOT NULL,
    price_per_liter REAL NOT NULL, -- Auto calculated: amount / liters
    odometer_reading REAL NOT NULL,
    driver_name TEXT,
    fuel_station TEXT,
    mileage_km_per_liter REAL, -- Calculated: (current_km - previous_km) / liters
    previous_odometer REAL, -- For mileage calculation
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Maintenance Tracking
CREATE TABLE vehicle_maintenance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    service_date TEXT NOT NULL,
    service_type TEXT NOT NULL, -- Oil Change, Tyre Change, Battery Replacement, General Service, etc.
    details TEXT,
    km_reading REAL NOT NULL,
    amount REAL DEFAULT 0,
    next_due_km REAL, -- When next service is due
    garage_name TEXT,
    invoice_number TEXT,
    attachment_path TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Maintenance Reminders/Alerts
CREATE TABLE vehicle_alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    alert_type TEXT NOT NULL, -- Insurance Renewal, Registration Renewal, Oil Change, Service Due, Tyre Change, Battery Replacement
    alert_message TEXT NOT NULL,
    due_date TEXT, -- For date-based alerts
    due_km REAL, -- For km-based alerts
    is_active INTEGER DEFAULT 1, -- 1 = active, 0 = dismissed
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    dismissed_at TEXT,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
);

-- Vehicle Income (for commercial vehicles)
CREATE TABLE vehicle_income (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER NOT NULL,
    income_date TEXT NOT NULL,
    amount REAL NOT NULL,
    description TEXT,
    project_id INTEGER,
    invoice_number TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY(project_id) REFERENCES projects(id)
);

-- Indexes for better performance
CREATE INDEX idx_daily_logs_vehicle ON vehicle_daily_logs(vehicle_id);
CREATE INDEX idx_daily_logs_date ON vehicle_daily_logs(log_date);
CREATE INDEX idx_expenses_vehicle ON vehicle_expenses(vehicle_id);
CREATE INDEX idx_expenses_date ON vehicle_expenses(expense_date);
CREATE INDEX idx_fuel_vehicle ON vehicle_fuel_records(vehicle_id);
CREATE INDEX idx_fuel_date ON vehicle_fuel_records(fuel_date);
CREATE INDEX idx_maintenance_vehicle ON vehicle_maintenance(vehicle_id);
CREATE INDEX idx_alerts_vehicle ON vehicle_alerts(vehicle_id);
CREATE INDEX idx_alerts_active ON vehicle_alerts(is_active);
