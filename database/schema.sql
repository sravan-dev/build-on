-- Buildon Accounts Database Schema

-- Clients table
CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    contact TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    total_invoice REAL DEFAULT 0,
    total_paid REAL DEFAULT 0,
    balance REAL DEFAULT 0
);

-- Vendors table
CREATE TABLE vendors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    contact TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    total_business REAL DEFAULT 0,
    total_paid REAL DEFAULT 0,
    balance REAL DEFAULT 0
);

-- Projects table
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    client_id INTEGER,
    total_income REAL DEFAULT 0,
    total_expenses REAL DEFAULT 0,
    profit REAL DEFAULT 0,
    FOREIGN KEY(client_id) REFERENCES clients(id)
);

-- Quotations table
CREATE TABLE quotations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER,
    project_id INTEGER,
    date TEXT,
    status TEXT DEFAULT 'pending',
    total_amount REAL,
    FOREIGN KEY(client_id) REFERENCES clients(id),
    FOREIGN KEY(project_id) REFERENCES projects(id)
);

-- Quotation Items table
CREATE TABLE quotation_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quotation_id INTEGER,
    description TEXT,
    quantity REAL,
    price REAL,
    total REAL,
    FOREIGN KEY(quotation_id) REFERENCES quotations(id)
);

-- Invoices table
CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quotation_id INTEGER,
    client_id INTEGER,
    date TEXT,
    lpo_number TEXT,
    status TEXT DEFAULT 'unpaid',
    total_amount REAL,
    discount REAL DEFAULT 0,
    paid_amount REAL DEFAULT 0,
    balance REAL,
    FOREIGN KEY(quotation_id) REFERENCES quotations(id),
    FOREIGN KEY(client_id) REFERENCES clients(id)
);

-- Payments table
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER,
    amount REAL,
    date TEXT,
    payment_method TEXT,
    cheque_number TEXT,
    bank_name TEXT,
    notes TEXT,
    FOREIGN KEY(invoice_id) REFERENCES invoices(id)
);

-- Employees table
CREATE TABLE employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    employee_id TEXT,
    qatar_id TEXT,
    qatar_id_expiry TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    position TEXT,
    department TEXT,
    hire_date TEXT,
    status TEXT DEFAULT 'active',
    emergency_contact TEXT,
    emergency_phone TEXT,
    bank_account TEXT,
    bank_name TEXT,
    monthly_salary REAL,
    per_day_rate REAL,
    per_hour_rate REAL,
    advances REAL DEFAULT 0,
    deductions REAL DEFAULT 0,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER,
    month INTEGER,
    year INTEGER,
    working_days REAL,
    overtime_hours REAL,
    total_earnings REAL,
    project_id INTEGER,
    FOREIGN KEY(employee_id) REFERENCES employees(id),
    FOREIGN KEY(project_id) REFERENCES projects(id)
);

-- Outside Labours table
CREATE TABLE outside_labours (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    trade TEXT,
    daily_rate REAL
);

-- Vehicles table
CREATE TABLE vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    type TEXT,
    registration TEXT
);

-- Transactions table (General Ledger)
CREATE TABLE transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT,
    description TEXT,
    amount REAL,
    type TEXT, -- 'income' or 'expense'
    source TEXT, -- e.g., 'client_payment', 'vendor_payment', 'project_expense'
    payment_method TEXT
);

-- Account Balances table
CREATE TABLE account_balances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_type TEXT, -- 'bank', 'savings', 'credit'
    balance REAL DEFAULT 0
);

-- Expenses table (for projects)
CREATE TABLE expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER,
    description TEXT,
    amount REAL,
    date TEXT,
    payment_method TEXT,
    FOREIGN KEY(project_id) REFERENCES projects(id)
);

-- Vendor Payments table
CREATE TABLE vendor_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vendor_id INTEGER,
    amount REAL,
    date TEXT,
    payment_method TEXT,
    FOREIGN KEY(vendor_id) REFERENCES vendors(id)
);

-- Project Purchases table
CREATE TABLE purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    vendor_id INTEGER,
    purchase_date TEXT NOT NULL,
    description TEXT,
    invoice_number TEXT,
    attachment_path TEXT,
    subtotal REAL DEFAULT 0,
    tax_amount REAL DEFAULT 0,
    total_amount REAL DEFAULT 0,
    status TEXT DEFAULT 'draft', -- 'draft', 'pending', 'approved', 'rejected'
    created_by TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    approved_by TEXT,
    approved_at TEXT,
    rejection_reason TEXT,
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY(vendor_id) REFERENCES vendors(id)
);

-- Purchase Items table
CREATE TABLE purchase_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    quantity REAL DEFAULT 1,
    unit_price REAL DEFAULT 0,
    total REAL DEFAULT 0,
    FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
);

-- Purchase Payments table
CREATE TABLE purchase_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER NOT NULL,
    payment_date TEXT NOT NULL,
    amount REAL NOT NULL,
    payment_method TEXT NOT NULL, -- 'company_cash', 'company_bank', 'company_card', 'company_cheque', 'personal'
    payment_account TEXT, -- specific account/card details
    cheque_number TEXT, -- cheque number for cheque payments
    bank_name TEXT, -- bank name for cheque payments
    paid_by TEXT, -- employee name if personal payment
    employee_id INTEGER, -- if paid personally for reimbursement tracking
    is_reimbursable INTEGER DEFAULT 0, -- 1 if personal payment needs reimbursement
    reimbursement_status TEXT DEFAULT 'pending', -- 'pending', 'approved', 'paid', 'rejected'
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY(employee_id) REFERENCES employees(id)
);

-- Reimbursements table
CREATE TABLE reimbursements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_payment_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    amount REAL NOT NULL,
    request_date TEXT NOT NULL,
    approval_date TEXT,
    payment_date TEXT,
    status TEXT DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'paid'
    approved_by TEXT,
    payment_method TEXT, -- how reimbursement was paid
    rejection_reason TEXT,
    notes TEXT,
    FOREIGN KEY(purchase_payment_id) REFERENCES purchase_payments(id) ON DELETE CASCADE,
    FOREIGN KEY(employee_id) REFERENCES employees(id)
);

-- Purchase Audit Log table
CREATE TABLE purchase_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER NOT NULL,
    action TEXT NOT NULL, -- 'created', 'updated', 'approved', 'rejected', 'payment_added'
    performed_by TEXT NOT NULL,
    performed_at TEXT DEFAULT CURRENT_TIMESTAMP,
    old_values TEXT, -- JSON string of old values
    new_values TEXT, -- JSON string of new values
    notes TEXT,
    FOREIGN KEY(purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
);