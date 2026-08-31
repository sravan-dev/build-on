-- MySQL Data Import
-- Generated from SQLite: 2025-12-13 08:37:37

SET FOREIGN_KEY_CHECKS=0;

-- Data for table `accounts`
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('1', '1001', 'Cash in Hand', 'asset', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('2', '1002', 'Bank Account', 'asset', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('3', '2001', 'Accounts Payable', 'liability', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('4', '2002', 'Accounts Receivable', 'asset', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('5', '3001', 'Capital', 'equity', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('6', '4001', 'Sales Revenue', 'income', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('7', '5001', 'Office Expenses', 'expense', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('8', '5002', 'Travel Expenses', 'expense', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('9', '5003', 'Utilities', 'expense', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('10', '5004', 'Rent', 'expense', NULL, '1', '2025-10-01 08:13:12');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('11', '1003', 'Petty Cash', 'asset', NULL, '1', '2025-10-01 08:46:09');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('12', '1004', 'Accounts Receivable', 'asset', NULL, '1', '2025-10-01 08:46:09');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('13', '2003', 'Accrued Expenses', 'liability', NULL, '1', '2025-10-01 08:46:09');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('14', '2004', 'Tax Payable', 'liability', NULL, '1', '2025-10-01 08:46:09');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('15', '3002', 'Retained Earnings', 'equity', NULL, '1', '2025-10-01 08:46:09');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('16', '4002', 'Service Revenue', 'income', NULL, '1', '2025-10-01 08:46:10');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('17', '5005', 'Construction Materials', 'expense', NULL, '1', '2025-10-01 08:46:10');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('18', '5006', 'Transportation Expenses', 'expense', NULL, '1', '2025-10-01 08:46:10');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('19', '5007', 'Equipment Maintenance', 'expense', NULL, '1', '2025-10-01 08:46:10');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('20', '5008', 'Salary Expenses', 'expense', NULL, '1', '2025-10-01 08:46:10');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('21', '5009', 'Professional Services', 'expense', NULL, '1', '2025-10-01 08:46:10');
INSERT INTO `accounts` (`id`, `account_code`, `account_name`, `account_type`, `parent_id`, `is_active`, `created_at`) VALUES ('22', '5010', 'Marketing Expenses', 'expense', NULL, '1', '2025-10-01 08:46:10');

-- Data for table `attendance_logs`
INSERT INTO `attendance_logs` (`id`, `daily_attendance_id`, `project_id`, `start_time`, `end_time`, `activity_type`, `description`, `created_at`) VALUES ('1', '1', '2', '06:58:57', NULL, 'work', 'fg', '2025-12-08 06:58:57');
INSERT INTO `attendance_logs` (`id`, `daily_attendance_id`, `project_id`, `start_time`, `end_time`, `activity_type`, `description`, `created_at`) VALUES ('2', '2', '2', '19:13:11', '19:50:10', 'work', 'start ', '2025-12-10 19:13:11');
INSERT INTO `attendance_logs` (`id`, `daily_attendance_id`, `project_id`, `start_time`, `end_time`, `activity_type`, `description`, `created_at`) VALUES ('3', '2', '1', '19:50:10', NULL, 'work', 'dhh', '2025-12-10 19:50:10');

-- Data for table `daily_attendance`
INSERT INTO `daily_attendance` (`id`, `employee_id`, `attendance_date`, `in_time`, `out_time`, `working_hours`, `status`, `remarks`, `created_at`, `break_start`, `break_end`, `site_id`, `activity_description`, `is_offsite`, `approval_status`, `supervisor_note`, `approved_by`, `approved_at`, `work_site`) VALUES ('1', '5', '2025-12-08', '06:58:57', NULL, NULL, 'Present', NULL, '2025-12-08 06:58:57', NULL, NULL, NULL, NULL, '0', 'rejected', NULL, '3', '2025-12-10 19:42:20', NULL);
INSERT INTO `daily_attendance` (`id`, `employee_id`, `attendance_date`, `in_time`, `out_time`, `working_hours`, `status`, `remarks`, `created_at`, `break_start`, `break_end`, `site_id`, `activity_description`, `is_offsite`, `approval_status`, `supervisor_note`, `approved_by`, `approved_at`, `work_site`) VALUES ('2', '5', '2025-12-10', '19:13:11', NULL, NULL, 'Present', NULL, '2025-12-10 19:13:11', NULL, NULL, NULL, NULL, '0', 'rejected', NULL, '3', '2025-12-10 19:42:35', NULL);

-- Data for table `employees`
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('9', 'RAHEES THANDUPARAKKAL', 'BUE001', '28935646792', '26-01-2026', 'reju.wandoor@gmail.com', '30659993', 'AL WAKRAH', 'MANAGER COO', 'HR DEPARTMENT', '23-09-2021', 'active', 'SALMAN', '77721423', '1234567890', 'DOHA BANK QATAR', '4000', '154', '19', '0', '0', 'COMPANY MANAGEMENT', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'V5283516', '15-01-2032', '26-01-2026', '2', NULL, NULL, '500', '500', '80');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('10', 'GULAB PURUSHOTTAM KEWAT', 'BUE002', '28635683423', '3/11/2026', 'hellobuildon@gmail.com', '30005281', 'AL WAKRAH', 'SUPERVISOR', 'TECHNICAL', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '0987654321', 'OOREEDOO MONEY', '2000', '77', '10', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'Y4223476', '10/8/2034', '3/11/2026', '2', NULL, NULL, '200', '300', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('11', 'ANIL KUMAR NISHAD', 'BUE003', '28935619169', '23-06-2026', 'hellobuildon@gmail.com', '55712013', 'AL WAKRAH', 'PAINTER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000013', 'OOREEDOO MONEY', '1500', '58', '8', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'V1393625', '13-07-2031', '23-06-2026', '2', NULL, NULL, '100', '200', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('12', 'GAJENDRA MALLAH', 'BUE004', '28452422382', '5/6/2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'GYPSUM', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1300', '47', '6', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', '937632', '25-04-2026', '5/6/2026', '2', NULL, NULL, '100', '200', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('13', 'SINTHUJAN RASATHURAI', 'BUE005', '29114408360', '27-08-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'PAINTER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1500', '58', '8', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'N5577858', '23-09-2025', '27-08-2026', '2', NULL, NULL, '100', '200', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('14', 'MUHAMMED JABIR KANNIYAN', 'BUE006', '30035613052', '27-10-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'ELECTRICIAN', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1800', '70', '9', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'V2204979', '31-08-2031', '27-10-2026', '2', NULL, NULL, '100', '100', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('15', 'SHIRIN KOMBAN MUHAMMEDKUTTY', 'BUE007', '29435649204', '18-10-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'DRAFTMAN', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1800', '70', '9', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'P9049756', '10/4/2027', '18-10-2026', '2', NULL, NULL, '100', '100', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('16', 'VINOTHARAJAH THAVARAJAH', 'BUE008', '28314416974', '27-08-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'PAINTER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1300', '47', '6', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'N8632724', '8/1/2030', '27-0802026', '2', NULL, NULL, '100', '200', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('17', 'MOHAMMED IRSHAD VARIYATHODI', 'BUE009', '29235647305', '24-12-2025', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'CARPENTER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1600', '62', '8', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'N4524673', '2/12/2025', '24-12-2025', '2', NULL, NULL, '100', '200', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('18', 'ARUN BHAGAT HARILAL BHAGAT', 'BUE010', '28535632934', '25-09-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'MASON', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1700', '65', '8', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'V4826470', '6/1/2032', '25-09-2026', '2', NULL, NULL, '100', '200', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('19', 'AMEER GOPALAN', 'BUE011', '27535628056', '3/11/2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'SUPERVISOR', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1800', '70', '9', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'U0582613', '22-09-2029', '3/11/2026', '2', NULL, NULL, '200', '200', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('20', 'NIROJ JEYARAM', 'BUTEMP001', '28714401063', '20-07-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'DRIVER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1800', '70', '9', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'N10568649', '30-05-2033', '20-07-2026', '2', NULL, NULL, '200', '200', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('21', 'SHAFI ULLAH WAZIR JAN', 'BUTEMP002', '30158603223', '9/1/2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'DRIVER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '2000', '77', '10', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'NL2740361', '3/12/2024', '9/1/2026', '2', NULL, NULL, '100', '200', '40');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('22', 'SHYAM DEV SARDAR', 'BUTEMP003', '29152416645', '29-09-2026', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'PAINTER', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '1200', '47', '6', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'PA4034105', NULL, NULL, '2', NULL, NULL, '100', '200', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('23', 'MOHAMMAD IJAHAR', 'BUTEMP004', '29035647623', '27-07-2028', 'hellobuildon@gmail.com', NULL, 'AL WAKRAH', 'GYPSUM', 'MAINTAINANCE', '23-09-2021', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '9000014', 'OOREEDOO MONEY', '2000', '0', '0', '0', '0', 'EMPLOYEE', '2025-12-11 17:13:57', '2025-12-11 17:13:57', 'T2060601', '12/7/2029', '27-07-2028', '2', NULL, NULL, '100', '100', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('24', 'INSTRUCTIONS:', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, '0', '0', '0', '0', '0', NULL, '2025-12-11 17:13:57', '2025-12-11 17:13:57', NULL, NULL, NULL, '2', NULL, NULL, '0', '0', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('25', '* Name is required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, '0', '0', '0', '0', '0', NULL, '2025-12-11 17:13:57', '2025-12-11 17:13:57', NULL, NULL, NULL, '2', NULL, NULL, '0', '0', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('26', '* Dates should be in YYYY-MM-DD format (e.g., 2024-12-31)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, '0', '0', '0', '0', '0', NULL, '2025-12-11 17:13:57', '2025-12-11 17:13:57', NULL, NULL, NULL, '2', NULL, NULL, '0', '0', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('27', '* Status should be either "active" or "inactive"', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, '0', '0', '0', '0', '0', NULL, '2025-12-11 17:13:57', '2025-12-11 17:13:57', NULL, NULL, NULL, '2', NULL, NULL, '0', '0', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('28', '* Numeric fields (salary, allowances, rates) should be numbers only', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, NULL, NULL, '0', '0', '0', '0', '0', NULL, '2025-12-11 17:13:57', '2025-12-11 17:13:57', NULL, NULL, NULL, '2', NULL, NULL, '0', '0', '0');
INSERT INTO `employees` (`id`, `name`, `employee_id`, `qatar_id`, `qatar_id_expiry`, `email`, `phone`, `address`, `position`, `department`, `hire_date`, `status`, `emergency_contact`, `emergency_phone`, `bank_account`, `bank_name`, `monthly_salary`, `per_day_rate`, `per_hour_rate`, `advances`, `deductions`, `notes`, `created_at`, `updated_at`, `passport_number`, `passport_expiry`, `visa_expiry`, `ticket_frequency_years`, `last_ticket_date`, `next_ticket_date`, `room_allowance`, `food_allowance`, `telephone_allowance`) VALUES ('29', 'SALMAN PATHUTHARA', 'BUE000', '28535642538', '2027-03-14', 'salmanckd123@gmail.com', '70687104', 'AL WAKRAH', 'MANAGER AND CEO', 'HR DEPARTMENT', '2021-09-23', 'active', 'RAHEES THANDUPARAKKAL', '30659993', '000001', 'DOHA BANK', '2000', '77', '10', '0', '0', 'MANAGEMENT', '2025-12-11 17:36:25', '2025-12-11 17:36:25', 'T2431971', '2029-02-03', '2027-03-14', '2', '', '', '300', '200', '40');

-- Data for table `followup_rules`
INSERT INTO `followup_rules` (`id`, `name`, `record_type`, `trigger_event`, `days_offset`, `template_id`, `channel`, `is_active`, `created_at`) VALUES ('1', 'Quotation Follow-up (3 days)', 'quotation', 'sent', '3', '1', 'email', '1', '2025-10-02 06:12:01');
INSERT INTO `followup_rules` (`id`, `name`, `record_type`, `trigger_event`, `days_offset`, `template_id`, `channel`, `is_active`, `created_at`) VALUES ('2', 'Quotation Expiry Reminder', 'quotation', 'sent', '-2', '2', 'email', '1', '2025-10-02 06:12:01');
INSERT INTO `followup_rules` (`id`, `name`, `record_type`, `trigger_event`, `days_offset`, `template_id`, `channel`, `is_active`, `created_at`) VALUES ('3', 'Invoice Payment Reminder', 'invoice', 'due_date', '-5', '3', 'email', '1', '2025-10-02 06:12:01');
INSERT INTO `followup_rules` (`id`, `name`, `record_type`, `trigger_event`, `days_offset`, `template_id`, `channel`, `is_active`, `created_at`) VALUES ('4', 'Invoice Overdue Notice', 'invoice', 'overdue', '3', '4', 'email', '1', '2025-10-02 06:12:01');

-- Data for table `followup_templates`
INSERT INTO `followup_templates` (`id`, `name`, `type`, `channel`, `subject`, `message`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'Quotation Follow-up (3 days)', 'quotation', 'email', 'Follow-up: Quotation #{quotation_id} - {project_name}', 'Dear {client_name},

I hope this email finds you well. I wanted to follow up on the quotation we sent you 3 days ago for {project_name}.

Quotation Details:
- Quotation ID: #{quotation_id}
- Amount: {total_amount}
- Date: {quotation_date}

If you have any questions or need clarification on any aspect of our proposal, please don''t hesitate to reach out. We''re here to help and would be happy to discuss this further.

We look forward to hearing from you soon.

Best regards,
{company_name}', '1', '2025-10-02 06:12:01', '2025-10-02 06:12:01');
INSERT INTO `followup_templates` (`id`, `name`, `type`, `channel`, `subject`, `message`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'Quotation Expiry Reminder', 'quotation', 'email', 'Reminder: Quotation #{quotation_id} expires soon', 'Dear {client_name},

This is a friendly reminder that your quotation #{quotation_id} for {project_name} will expire on {expiry_date}.

To avoid any delays and secure the current pricing, we encourage you to confirm your acceptance before the expiry date.

If you need an extension or have any questions, please let us know.

Best regards,
{company_name}', '1', '2025-10-02 06:12:01', '2025-10-02 06:12:01');
INSERT INTO `followup_templates` (`id`, `name`, `type`, `channel`, `subject`, `message`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'Invoice Payment Reminder (5 days before due)', 'invoice', 'email', 'Payment Reminder: Invoice #{invoice_id} due in 5 days', 'Dear {client_name},

This is a friendly reminder that payment for Invoice #{invoice_id} is due in 5 days ({due_date}).

Invoice Details:
- Invoice ID: #{invoice_id}
- Amount: {total_amount}
- Due Date: {due_date}
- Outstanding Balance: {balance}

Please ensure payment is made by the due date to avoid any late fees.

If you have already made the payment, please disregard this message.

Best regards,
{company_name}', '1', '2025-10-02 06:12:01', '2025-10-02 06:12:01');
INSERT INTO `followup_templates` (`id`, `name`, `type`, `channel`, `subject`, `message`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'Invoice Overdue Notice', 'invoice', 'email', 'OVERDUE: Invoice #{invoice_id} - Immediate Payment Required', 'Dear {client_name},

Our records show that Invoice #{invoice_id} is now overdue by {days_overdue} days.

Invoice Details:
- Invoice ID: #{invoice_id}
- Amount: {total_amount}
- Due Date: {due_date}
- Outstanding Balance: {balance}

Please arrange for immediate payment to avoid any further action. If there are any issues preventing payment, please contact us immediately to discuss payment arrangements.

Best regards,
{company_name}', '1', '2025-10-02 06:12:01', '2025-10-02 06:12:01');

-- Data for table `invoice_items`
INSERT INTO `invoice_items` (`id`, `invoice_id`, `description`, `quantity`, `price`, `total`) VALUES ('4', '1', '1Gypsum Partition Work – Supply and installation of fire-rated gypsum board with rockwool partitions including GI tube with metal studs, channels, joints finishing as per approved drawings.  161.5	﷼117.50	﷼18,976.25 Electrical Works (Lighting Installation) – Supply and installation of light fixtures including cabling, conduit laying, wire pulling, and connection.  32	﷼220.00	﷼7,040.00 Electrical Points – Supply and installation of power points including cabling, conduit, wire pulling, and connection as per layout.  38	﷼300.00	﷼11,400.00 Data Points Installation – Supply and installation of data points including cabling, conduit, wire pulling, and module end termination complete.  15	﷼220.00	﷼3,300.00 Wall Painting Works – Surface preparation, primer, and two coats of emulsion paint (approved color and finish).  539	﷼22.00	﷼11,858.00 Waste Removal from Work Site – Complete removal and disposal of all debris and waste materials from the site after completion of works.', '1', '54000', '54000');

-- Data for table `lpo_audit_log`
INSERT INTO `lpo_audit_log` (`id`, `lpo_id`, `action`, `performed_by`, `performed_at`, `old_values`, `new_values`, `notes`) VALUES ('1', '1', 'approved', 'Admin', '2025-10-02 13:58:56', NULL, NULL, 'LPO approved');
INSERT INTO `lpo_audit_log` (`id`, `lpo_id`, `action`, `performed_by`, `performed_at`, `old_values`, `new_values`, `notes`) VALUES ('2', '1', 'approved', 'Admin', '2025-10-02 13:59:03', NULL, NULL, 'LPO approved');
INSERT INTO `lpo_audit_log` (`id`, `lpo_id`, `action`, `performed_by`, `performed_at`, `old_values`, `new_values`, `notes`) VALUES ('3', '1', 'approved', 'Admin', '2025-10-02 13:59:37', NULL, NULL, 'LPO approved');
INSERT INTO `lpo_audit_log` (`id`, `lpo_id`, `action`, `performed_by`, `performed_at`, `old_values`, `new_values`, `notes`) VALUES ('4', '1', 'created', 'Admin', '2025-10-02 14:00:06', NULL, NULL, 'LPO created');
INSERT INTO `lpo_audit_log` (`id`, `lpo_id`, `action`, `performed_by`, `performed_at`, `old_values`, `new_values`, `notes`) VALUES ('5', '1', 'approved', 'Admin', '2025-10-02 14:00:06', NULL, NULL, 'LPO approved');

-- Data for table `lpo_items`
INSERT INTO `lpo_items` (`id`, `lpo_id`, `item_description`, `quantity`, `unit_of_measure`, `unit_price`, `total_price`, `notes`) VALUES ('1', '1', 'test', '1', 'bag', '10', '10', '');

-- Data for table `lpo_sequence`
INSERT INTO `lpo_sequence` (`id`, `current_number`, `prefix`, `year`) VALUES ('1', '1001', 'LPO', '2025');

-- Data for table `lpos`
INSERT INTO `lpos` (`id`, `lpo_number`, `date`, `supplier_id`, `supplier_name`, `project_id`, `department`, `payment_terms`, `delivery_date`, `reference`, `subtotal`, `tax_amount`, `tax_percentage`, `discount_amount`, `discount_percentage`, `grand_total`, `status`, `created_by`, `created_at`, `approved_by`, `approved_at`, `issued_by`, `issued_at`, `notes`, `supplier_acknowledgment`, `acknowledgment_date`) VALUES ('1', 'LPO-2025-1001', '2025-10-02', '1', '', '1', 'Construction', 'Credit', '2025-10-03', '123', '10', '0', '0', '0', '0', '10', 'approved', 'Admin', '2025-10-02 14:00:06', 'Admin', '2025-10-02 14:00:06', NULL, NULL, '', '0', NULL);

-- Data for table `sqlite_sequence`
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('invoice_items', '4');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('accounts', '22');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('vouchers', '8');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('voucher_entries', '16');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('followup_templates', '4');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('followup_rules', '4');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('units_of_measure', '14');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('lpo_audit_log', '5');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('lpos', '1');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('lpo_items', '1');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('users', '23');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('daily_attendance', '2');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('attendance_logs', '3');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('employees', '29');
INSERT INTO `sqlite_sequence` (`name`, `seq`) VALUES ('clients', '2');

-- Data for table `units_of_measure`
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('1', 'pcs', 'Pieces', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('2', 'kg', 'Kilograms', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('3', 'ltr', 'Liters', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('4', 'mtr', 'Meters', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('5', 'sqm', 'Square Meters', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('6', 'cum', 'Cubic Meters', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('7', 'box', 'Boxes', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('8', 'set', 'Sets', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('9', 'roll', 'Rolls', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('10', 'bag', 'Bags', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('11', 'ton', 'Tons', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('12', 'hr', 'Hours', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('13', 'day', 'Days', '1');
INSERT INTO `units_of_measure` (`id`, `unit_code`, `unit_name`, `is_active`) VALUES ('14', 'lot', 'Lots', '1');

-- Data for table `users`
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('2', 'BUE000', '$2y$10$Ncwxp3bocDSuLZ4WLO.pWuvNtqD0FwDpiOWdYfiJmqJBhsQC./e5S', 'superadmin', '29', '2025-12-06 14:34:56');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('6', 'sravan', '$2y$10$3pTkC6KQpIujs5J66WTWO.LcjDUYN9Xb.NM2elbA5cveZnlaV.9G.', 'employee', NULL, '2025-12-07 17:42:27');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('7', 'BUE002', '$2y$10$BR.BVS5eItMyWn2MfbvS5.v/dg5ZkLNBLFuH5JuE.DLQfPESXYHmm', 'employee', '10', '2025-12-11 17:18:56');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('8', 'SUPER1', '$2y$10$riH7rijdlB9GU./S3trxdeBU3o83m6I/.VvuEB3MwB8PJnpL0GU3S', 'supervisor', '10', '2025-12-11 17:20:41');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('9', 'BUE011', '$2y$10$ycTHvSw0cFOfve/cFa/Li.6.zECkzC5ckdhcs0rYUh4kfUo.liNqS', 'employee', '19', '2025-12-11 17:21:36');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('10', 'SUPER2', '$2y$10$GrAo.n2TpVDddU6wbsHo3.dQuXQyUlHTq7ZVKUCKJQaoRRz.iVicW', 'supervisor', '19', '2025-12-11 17:22:35');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('11', 'BUE003', '$2y$10$006UEvN6C6XYq3feH0xLPubOGWPFXvB1qEhkyVk2vhQZDJqSvEn5K', 'employee', '11', '2025-12-11 17:23:04');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('12', 'BUE004', '$2y$10$vR.T/4AqDoxxJb4VdFd9J.bBGxAWfP9fr13UnjmAGjW7O9nRjn6gW', 'employee', '12', '2025-12-11 17:23:26');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('13', 'BUE005', '$2y$10$HLbUmEDZ3Wh.qoAdUdbF9.I5xsbELyhgHj0xv.5nuG/GJIM0.CPxG', 'employee', '13', '2025-12-11 17:23:56');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('14', 'BUE006', '$2y$10$bcwVt2niWLA2F1kQUOk5uuTDhEDyfHmWHg1Bg1uccdTZtiNu5l5k6', 'employee', '14', '2025-12-11 17:24:25');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('15', 'BUE007', '$2y$10$T6F4z/lVsrjb42mZyDKFd.n8EuMbp5dxGfB.t7h2vHgeO2XVqyNfW', 'employee', '15', '2025-12-11 17:24:47');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('16', 'BUE008', '$2y$10$CphVfksm4UJlmkRdgIX9ZuKyKXAyd0T1jBVCpdRYOnGVB/j2La//y', 'employee', '16', '2025-12-11 17:25:19');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('17', 'BUE009', '$2y$10$Hc4AQBiie.zWEmNkedcr5upg7scThuAI0EmF4RLkAfurjrsJP21Na', 'employee', '17', '2025-12-11 17:25:58');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('18', 'BUE010', '$2y$10$tCxULtvWgF0d2giwpR/BVe/dryKGUIZPPak.7g.R1yD3pvpAssjjC', 'employee', '18', '2025-12-11 17:26:38');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('19', 'BUTEMP001', '$2y$10$1l2av.vHQADBNrkymM7kCe56AcKTdqLxeLen6WKmwRrMUBg0mzyHS', 'employee', '20', '2025-12-11 17:28:29');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('20', 'BUTEMP002', '$2y$10$mkmAHnXYHCSVaVgdv6y9yeIU4Tk8y2KThMNucqldw9qwHmfqFhrVe', 'employee', '21', '2025-12-11 17:28:58');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('21', 'BUTEMP003', '$2y$10$O3UOjXk9eUC7ymojMLZAsOnm8XQWMhYcAwj.A/nMk5T5bgnFrhBL.', 'employee', '22', '2025-12-11 17:29:27');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('22', 'BUTEMP004', '$2y$10$jx1DpMY2wI0ys1YXTXVgm.a7g.taSmY15XXt0gif092p1g5gLD8JG', 'employee', '23', '2025-12-11 17:29:58');
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `employee_id`, `created_at`) VALUES ('23', 'BUE001', '$2y$10$cq84.uAR7J6FTy.Fbwi1DOvonJp0cgCacQHeaiIKL/U8LPFHKh9t.', 'superadmin', '9', '2025-12-11 17:30:43');

-- Data for table `voucher_entries`
INSERT INTO `voucher_entries` (`id`, `voucher_id`, `account_head`, `debit_amount`, `credit_amount`, `narration`) VALUES ('5', '3', 'Utilities', '350.75', '0', 'Electricity bill payment');
INSERT INTO `voucher_entries` (`id`, `voucher_id`, `account_head`, `debit_amount`, `credit_amount`, `narration`) VALUES ('6', '3', 'Bank Account', '0', '350.75', 'Bank transfer for utilities');

SET FOREIGN_KEY_CHECKS=1;
