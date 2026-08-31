-- MySQL Database Schema
-- Generated from SQLite: 2025-12-13 08:37:37

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `account_balances`;
CREATE TABLE `account_balances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_type` TEXT NULL ,
  `balance` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account_code` TEXT NOT NULL ,
  `account_name` TEXT NOT NULL ,
  `account_type` TEXT NOT NULL ,
  `parent_id` INT NULL ,
  `is_active` INT NULL DEFAULT 1,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `advance_payments`;
CREATE TABLE `advance_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL ,
  `payment_date` DATE NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `reason` TEXT NULL ,
  `approved_by` TEXT NULL ,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NULL ,
  `month` INT NULL ,
  `year` INT NULL ,
  `working_days` DECIMAL(10,2) NULL ,
  `overtime_hours` DECIMAL(10,2) NULL ,
  `total_earnings` DECIMAL(10,2) NULL ,
  `project_id` INT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attendance_logs`;
CREATE TABLE `attendance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `daily_attendance_id` INT NOT NULL ,
  `project_id` INT NULL ,
  `start_time` TIME NOT NULL ,
  `end_time` TIME NULL ,
  `activity_type` TEXT NOT NULL DEFAULT 'work',
  `description` TEXT NULL ,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `contact` TEXT NULL ,
  `email` TEXT NULL ,
  `phone` TEXT NULL ,
  `address` TEXT NULL ,
  `total_invoice` DECIMAL(10,2) NULL ,
  `total_paid` DECIMAL(10,2) NULL ,
  `balance` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `daily_attendance`;
CREATE TABLE `daily_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL ,
  `attendance_date` DATE NOT NULL ,
  `in_time` TIME NULL ,
  `out_time` TIME NULL ,
  `working_hours` DECIMAL(5,2) NULL ,
  `status` TEXT NULL DEFAULT 'Present',
  `remarks` TEXT NULL ,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `break_start` TIME NULL ,
  `break_end` TIME NULL ,
  `site_id` INT NULL ,
  `activity_description` TEXT NULL ,
  `is_offsite` INT NULL ,
  `approval_status` TEXT NULL DEFAULT 'pending',
  `supervisor_note` TEXT NULL ,
  `approved_by` INT NULL ,
  `approved_at` DATETIME NULL ,
  `work_site` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `employee_id` TEXT NULL ,
  `qatar_id` TEXT NULL ,
  `qatar_id_expiry` TEXT NULL ,
  `email` TEXT NULL ,
  `phone` TEXT NULL ,
  `address` TEXT NULL ,
  `position` TEXT NULL ,
  `department` TEXT NULL ,
  `hire_date` TEXT NULL ,
  `status` TEXT NULL DEFAULT 'active',
  `emergency_contact` TEXT NULL ,
  `emergency_phone` TEXT NULL ,
  `bank_account` TEXT NULL ,
  `bank_name` TEXT NULL ,
  `monthly_salary` DECIMAL(10,2) NULL ,
  `per_day_rate` DECIMAL(10,2) NULL ,
  `per_hour_rate` DECIMAL(10,2) NULL ,
  `advances` DECIMAL(10,2) NULL ,
  `deductions` DECIMAL(10,2) NULL ,
  `notes` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `passport_number` TEXT NULL ,
  `passport_expiry` DATE NULL ,
  `visa_expiry` DATE NULL ,
  `ticket_frequency_years` INT NULL DEFAULT 2,
  `last_ticket_date` DATE NULL ,
  `next_ticket_date` DATE NULL ,
  `room_allowance` DECIMAL(10,2) NULL ,
  `food_allowance` DECIMAL(10,2) NULL ,
  `telephone_allowance` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NULL ,
  `expense_type` VARCHAR(100) NULL ,
  `description` TEXT NULL ,
  `amount` DECIMAL(10,2) NULL ,
  `remarks` TEXT NULL ,
  `date` TEXT NULL ,
  `paid_by` VARCHAR(255) NULL ,
  `attachment_path` VARCHAR(500) NULL ,
  `payment_method` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `followup_records`;
CREATE TABLE `followup_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `record_type` TEXT NOT NULL ,
  `record_id` INT NOT NULL ,
  `followup_type` TEXT NOT NULL ,
  `channel` TEXT NOT NULL ,
  `status` TEXT NULL DEFAULT 'pending',
  `subject` TEXT NULL ,
  `message` TEXT NULL ,
  `sent_by` TEXT NULL ,
  `sent_at` TEXT NULL ,
  `scheduled_at` TEXT NULL ,
  `notes` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `followup_rules`;
CREATE TABLE `followup_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `record_type` TEXT NOT NULL ,
  `trigger_event` TEXT NOT NULL ,
  `days_offset` INT NULL ,
  `template_id` INT NULL ,
  `channel` TEXT NOT NULL ,
  `is_active` INT NULL DEFAULT 1,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `followup_templates`;
CREATE TABLE `followup_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `type` TEXT NOT NULL ,
  `channel` TEXT NOT NULL ,
  `subject` TEXT NULL ,
  `message` TEXT NOT NULL ,
  `is_active` INT NULL DEFAULT 1,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` DECIMAL(10,2) NULL ,
  `description` TEXT NULL ,
  `quantity` DECIMAL(10,2) NULL ,
  `price` DECIMAL(10,2) NULL ,
  `total` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quotation_id` INT NULL ,
  `client_id` INT NULL ,
  `date` TEXT NULL ,
  `lpo_number` TEXT NULL ,
  `status` TEXT NULL DEFAULT 'unpaid',
  `total_amount` DECIMAL(10,2) NULL ,
  `discount` DECIMAL(10,2) NULL ,
  `paid_amount` DECIMAL(10,2) NULL ,
  `balance` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lpo_approvals`;
CREATE TABLE `lpo_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lpo_id` INT NOT NULL ,
  `approver_name` TEXT NOT NULL ,
  `approval_level` INT NULL DEFAULT 1,
  `status` TEXT NULL DEFAULT 'pending',
  `approved_at` TEXT NULL ,
  `comments` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lpo_audit_log`;
CREATE TABLE `lpo_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lpo_id` INT NOT NULL ,
  `action` TEXT NOT NULL ,
  `performed_by` TEXT NOT NULL ,
  `performed_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_values` TEXT NULL ,
  `new_values` TEXT NULL ,
  `notes` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lpo_items`;
CREATE TABLE `lpo_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lpo_id` INT NOT NULL ,
  `item_description` TEXT NOT NULL ,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
  `unit_of_measure` TEXT NULL DEFAULT 'pcs',
  `unit_price` DECIMAL(10,2) NOT NULL ,
  `total_price` DECIMAL(10,2) NOT NULL ,
  `notes` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lpo_sequence`;
CREATE TABLE `lpo_sequence` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `current_number` INT NULL DEFAULT 1000,
  `prefix` TEXT NULL DEFAULT 'LPO',
  `year` INT NULL DEFAULT 2025
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lpos`;
CREATE TABLE `lpos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `lpo_number` TEXT NOT NULL ,
  `date` TEXT NOT NULL ,
  `supplier_id` INT NULL ,
  `supplier_name` TEXT NULL ,
  `project_id` INT NULL ,
  `department` TEXT NULL ,
  `payment_terms` TEXT NULL DEFAULT 'Credit',
  `delivery_date` TEXT NULL ,
  `reference` TEXT NULL ,
  `subtotal` DECIMAL(10,2) NULL ,
  `tax_amount` DECIMAL(10,2) NULL ,
  `tax_percentage` DECIMAL(10,2) NULL ,
  `discount_amount` DECIMAL(10,2) NULL ,
  `discount_percentage` DECIMAL(10,2) NULL ,
  `grand_total` DECIMAL(10,2) NULL ,
  `status` TEXT NULL DEFAULT 'draft',
  `created_by` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` TEXT NULL ,
  `approved_at` TEXT NULL ,
  `issued_by` TEXT NULL ,
  `issued_at` TEXT NULL ,
  `notes` TEXT NULL ,
  `supplier_acknowledgment` INT NULL ,
  `acknowledgment_date` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `outside_labours`;
CREATE TABLE `outside_labours` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `trade` TEXT NULL ,
  `daily_rate` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` DECIMAL(10,2) NULL ,
  `amount` DECIMAL(10,2) NULL ,
  `date` TEXT NULL ,
  `payment_method` TEXT NULL ,
  `cheque_number` TEXT NULL ,
  `bank_name` TEXT NULL ,
  `notes` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `client_id` INT NULL ,
  `total_income` DECIMAL(10,2) NULL ,
  `total_expenses` DECIMAL(10,2) NULL ,
  `profit` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_audit_log`;
CREATE TABLE `purchase_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL ,
  `action` TEXT NOT NULL ,
  `performed_by` TEXT NOT NULL ,
  `performed_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_values` TEXT NULL ,
  `new_values` TEXT NULL ,
  `notes` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL ,
  `description` TEXT NOT NULL ,
  `quantity` DECIMAL(10,2) NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NULL ,
  `total` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_payments`;
CREATE TABLE `purchase_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL ,
  `payment_date` TEXT NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `payment_method` TEXT NOT NULL ,
  `payment_account` TEXT NULL ,
  `cheque_number` TEXT NULL ,
  `bank_name` TEXT NULL ,
  `paid_by` DECIMAL(10,2) NULL ,
  `employee_id` INT NULL ,
  `is_reimbursable` INT NULL ,
  `reimbursement_status` TEXT NULL DEFAULT 'pending',
  `notes` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL ,
  `vendor_id` INT NULL ,
  `purchase_date` TEXT NOT NULL ,
  `description` TEXT NULL ,
  `invoice_number` DECIMAL(10,2) NULL ,
  `attachment_path` TEXT NULL ,
  `subtotal` DECIMAL(10,2) NULL ,
  `tax_amount` DECIMAL(10,2) NULL ,
  `total_amount` DECIMAL(10,2) NULL ,
  `status` TEXT NULL DEFAULT 'draft',
  `created_by` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` TEXT NULL ,
  `approved_at` TEXT NULL ,
  `rejection_reason` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quotation_id` INT NULL ,
  `description` TEXT NULL ,
  `quantity` DECIMAL(10,2) NULL ,
  `price` DECIMAL(10,2) NULL ,
  `total` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NULL ,
  `project_id` INT NULL ,
  `date` TEXT NULL ,
  `status` TEXT NULL DEFAULT 'pending',
  `total_amount` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reimbursements`;
CREATE TABLE `reimbursements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_payment_id` INT NOT NULL ,
  `employee_id` INT NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `request_date` TEXT NOT NULL ,
  `approval_date` TEXT NULL ,
  `payment_date` TEXT NULL ,
  `status` TEXT NULL DEFAULT 'pending',
  `approved_by` TEXT NULL ,
  `payment_method` TEXT NULL ,
  `rejection_reason` TEXT NULL ,
  `notes` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sqlite_sequence`;
CREATE TABLE `sqlite_sequence` (
  `name` VARCHAR(255) NULL ,
  `seq` VARCHAR(255) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tool_assignments`;
CREATE TABLE `tool_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tool_id` INT NOT NULL ,
  `employee_id` INT NOT NULL ,
  `assigned_date` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `returned_date` TEXT NULL ,
  `notes` TEXT NULL ,
  `condition_on_issue` TEXT NULL ,
  `condition_on_return` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tools`;
CREATE TABLE `tools` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `category` TEXT NULL ,
  `serial_number` TEXT NULL ,
  `purchase_date` TEXT NULL ,
  `supplier` TEXT NULL ,
  `cost` DECIMAL(10,2) NULL ,
  `warranty_expiry` TEXT NULL ,
  `status` TEXT NULL DEFAULT 'in_store',
  `assigned_to` INT NULL ,
  `image_path` TEXT NULL ,
  `notes` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `date` TEXT NULL ,
  `description` TEXT NULL ,
  `amount` DECIMAL(10,2) NULL ,
  `type` TEXT NULL ,
  `source` TEXT NULL ,
  `payment_method` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `units_of_measure`;
CREATE TABLE `units_of_measure` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `unit_code` TEXT NOT NULL ,
  `unit_name` TEXT NOT NULL ,
  `is_active` INT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` TEXT NOT NULL ,
  `password_hash` TEXT NOT NULL ,
  `role` TEXT NOT NULL DEFAULT 'employee',
  `employee_id` INT NULL ,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicle_alerts`;
CREATE TABLE `vehicle_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT NOT NULL ,
  `alert_type` TEXT NOT NULL ,
  `alert_message` TEXT NOT NULL ,
  `due_date` TEXT NULL ,
  `due_km` DECIMAL(10,2) NULL ,
  `is_active` INT NULL DEFAULT 1,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `dismissed_at` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicle_daily_logs`;
CREATE TABLE `vehicle_daily_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT NOT NULL ,
  `log_date` TEXT NOT NULL ,
  `opening_km` DECIMAL(10,2) NOT NULL ,
  `closing_km` DECIMAL(10,2) NOT NULL ,
  `total_km` DECIMAL(10,2) NOT NULL ,
  `driver_name` TEXT NULL ,
  `route_trip` TEXT NULL ,
  `remarks` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicle_expenses`;
CREATE TABLE `vehicle_expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT NOT NULL ,
  `expense_date` TEXT NOT NULL ,
  `expense_type` TEXT NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `vendor_garage` TEXT NULL ,
  `invoice_number` DECIMAL(10,2) NULL ,
  `description` TEXT NULL ,
  `attachment_path` TEXT NULL ,
  `odometer_reading` DECIMAL(10,2) NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_by` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicle_fuel_records`;
CREATE TABLE `vehicle_fuel_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT NOT NULL ,
  `fuel_date` TEXT NOT NULL ,
  `liters` DECIMAL(10,2) NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `price_per_liter` DECIMAL(10,2) NOT NULL ,
  `odometer_reading` DECIMAL(10,2) NOT NULL ,
  `driver_name` TEXT NULL ,
  `fuel_station` TEXT NULL ,
  `mileage_km_per_liter` DECIMAL(10,2) NULL ,
  `previous_odometer` DECIMAL(10,2) NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_by` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicle_income`;
CREATE TABLE `vehicle_income` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT NOT NULL ,
  `income_date` TEXT NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `description` TEXT NULL ,
  `project_id` INT NULL ,
  `invoice_number` DECIMAL(10,2) NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicle_maintenance`;
CREATE TABLE `vehicle_maintenance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vehicle_id` INT NOT NULL ,
  `service_date` TEXT NOT NULL ,
  `service_type` TEXT NOT NULL ,
  `details` TEXT NULL ,
  `km_reading` DECIMAL(10,2) NOT NULL ,
  `amount` DECIMAL(10,2) NULL ,
  `next_due_km` DECIMAL(10,2) NULL ,
  `garage_name` TEXT NULL ,
  `invoice_number` DECIMAL(10,2) NULL ,
  `attachment_path` TEXT NULL ,
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_by` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `vehicle_number` VARCHAR(100) NULL ,
  `make` VARCHAR(100) NULL ,
  `model` VARCHAR(100) NULL ,
  `year` INT NULL ,
  `chassis_number` VARCHAR(100) NULL ,
  `engine_number` VARCHAR(100) NULL ,
  `fuel_type` VARCHAR(50) DEFAULT 'Petrol' ,
  `assigned_driver` VARCHAR(255) NULL ,
  `registration_renewal_date` DATE NULL ,
  `insurance_renewal_date` DATE NULL ,
  `purchase_date` DATE NULL ,
  `purchase_price` DECIMAL(10,2) DEFAULT 0 ,
  `current_mileage` DECIMAL(10,2) DEFAULT 0 ,
  `vehicle_status` VARCHAR(50) DEFAULT 'Active' ,
  `type` TEXT NULL ,
  `registration` TEXT NULL ,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP ,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vendor_payments`;
CREATE TABLE `vendor_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NULL ,
  `amount` DECIMAL(10,2) NULL ,
  `date` TEXT NULL ,
  `payment_method` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vendors`;
CREATE TABLE `vendors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` TEXT NOT NULL ,
  `contact` TEXT NULL ,
  `email` TEXT NULL ,
  `phone` TEXT NULL ,
  `address` TEXT NULL ,
  `total_business` DECIMAL(10,2) NULL ,
  `total_paid` DECIMAL(10,2) NULL ,
  `balance` DECIMAL(10,2) NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `voucher_entries`;
CREATE TABLE `voucher_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voucher_id` INT NOT NULL ,
  `account_head` TEXT NOT NULL ,
  `debit_amount` DECIMAL(10,2) NULL ,
  `credit_amount` DECIMAL(10,2) NULL ,
  `narration` TEXT NULL 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vouchers`;
CREATE TABLE `vouchers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `voucher_no` TEXT NOT NULL ,
  `voucher_date` TEXT NOT NULL ,
  `paid_to_received_from` DECIMAL(10,2) NOT NULL ,
  `amount` DECIMAL(10,2) NOT NULL ,
  `amount_in_words` DECIMAL(10,2) NOT NULL ,
  `description` TEXT NULL ,
  `voucher_type` TEXT NULL DEFAULT 'cash',
  `prepared_by` TEXT NULL ,
  `checked_by` TEXT NULL ,
  `approved_by` TEXT NULL ,
  `status` TEXT NULL DEFAULT 'draft',
  `created_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TEXT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
