<?php
/**
 * Excel Import Helper for Employee Management
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Generate and download sample employee Excel template
 */
function generateEmployeeSampleExcel()
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = [
        'Name*',
        'Employee ID',
        'Qatar ID',
        'Qatar ID Expiry',
        'Passport Number',
        'Passport Expiry',
        'Visa Expiry',
        'Email',
        'Phone',
        'Address',
        'Position',
        'Department',
        'Hire Date',
        'Status',
        'Emergency Contact',
        'Emergency Phone',
        'Bank Account',
        'Bank Name',
        'Monthly Salary',
        'Room Allowance',
        'Food Allowance',
        'Telephone Allowance',
        'Per Day Rate',
        'Per Hour Rate',
        'Notes'
    ];

    // Style header row
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:Y1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Add sample data
    $sampleData = [
        [
            'John Doe',
            'EMP001',
            '12345678901',
            '2025-12-31',
            'P1234567',
            '2026-06-30',
            '2025-12-31',
            'john@example.com',
            '+974 12345678',
            '123 Main St, Doha',
            'Manager',
            'Sales',
            '2024-01-15',
            'active',
            'Jane Doe',
            '+974 87654321',
            '1234567890',
            'Commercial Bank of Qatar',
            '15000',
            '1000',
            '500',
            '200',
            '500',
            '50',
            'Sample employee'
        ],
        [
            'Sarah Smith',
            'EMP002',
            '98765432109',
            '2026-03-15',
            'P9876543',
            '2027-01-20',
            '2026-03-15',
            'sarah@example.com',
            '+974 23456789',
            '456 Park Ave, Doha',
            'Engineer',
            'Technical',
            '2024-02-01',
            'active',
            'Mike Smith',
            '+974 98765432',
            '0987654321',
            'Qatar National Bank',
            '12000',
            '800',
            '400',
            '150',
            '400',
            '40',
            ''
        ]
    ];

    $sheet->fromArray($sampleData, NULL, 'A2');

    // Auto-size columns
    foreach (range('A', 'Y') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Add notes
    $lastRow = $sheet->getHighestRow() + 2;
    $sheet->setCellValue('A' . $lastRow, 'INSTRUCTIONS:');
    $sheet->getStyle('A' . $lastRow)->getFont()->setBold(true);
    $sheet->setCellValue('A' . ($lastRow + 1), '* Name is required');
    $sheet->setCellValue('A' . ($lastRow + 2), '* Dates should be in YYYY-MM-DD format (e.g., 2024-12-31)');
    $sheet->setCellValue('A' . ($lastRow + 3), '* Status should be either "active" or "inactive"');
    $sheet->setCellValue('A' . ($lastRow + 4), '* Numeric fields (salary, allowances, rates) should be numbers only');

    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="employee_import_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Import employees from uploaded Excel file
 * @param array $file - $_FILES['excel_file']
 * @return array - ['success' => count, 'errors' => array, 'imported' => array]
 */
function importEmployeesFromExcel($file, $pdo)
{
    $result = [
        'success' => 0,
        'errors' => [],
        'imported' => []
    ];

    try {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'File upload error';
            return $result;
        }

        $allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            $result['errors'][] = 'Invalid file type. Please upload .xlsx or .xls file';
            return $result;
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $result['errors'][] = 'File too large. Maximum size is 5MB';
            return $result;
        }

        // Load spreadsheet
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Skip header row
        array_shift($rows);

        $rowNum = 2; // Start from row 2 (after header)
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                $rowNum++;
                continue;
            }

            // Validate required field
            if (empty($row[0])) {
                $result['errors'][] = "Row $rowNum: Name is required";
                $rowNum++;
                continue;
            }

            try {
                // Insert employee
                $stmt = $pdo->prepare("INSERT INTO employees (
                    name, employee_id, qatar_id, qatar_id_expiry, passport_number, passport_expiry, 
                    visa_expiry, email, phone, address, position, department, hire_date, status, 
                    emergency_contact, emergency_phone, bank_account, bank_name, monthly_salary, 
                    room_allowance, food_allowance, telephone_allowance, per_day_rate, per_hour_rate, 
                    notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $currentTime = date('Y-m-d H:i:s');
                $stmt->execute([
                    $row[0],  // name
                    $row[1] ?? null,  // employee_id
                    $row[2] ?? null,  // qatar_id
                    $row[3] ?? null,  // qatar_id_expiry
                    $row[4] ?? null,  // passport_number
                    $row[5] ?? null,  // passport_expiry
                    $row[6] ?? null,  // visa_expiry
                    $row[7] ?? null,  // email
                    $row[8] ?? null,  // phone
                    $row[9] ?? null,  // address
                    $row[10] ?? null, // position
                    $row[11] ?? null, // department
                    $row[12] ?? null, // hire_date
                    $row[13] ?? 'active', // status
                    $row[14] ?? null, // emergency_contact
                    $row[15] ?? null, // emergency_phone
                    $row[16] ?? null, // bank_account
                    $row[17] ?? null, // bank_name
                    $row[18] ?? 0,    // monthly_salary
                    $row[19] ?? 0,    // room_allowance
                    $row[20] ?? 0,    // food_allowance
                    $row[21] ?? 0,    // telephone_allowance
                    $row[22] ?? 0,    // per_day_rate
                    $row[23] ?? 0,    // per_hour_rate
                    $row[24] ?? null, // notes
                    $currentTime,
                    $currentTime
                ]);

                $result['success']++;
                $result['imported'][] = $row[0];

            } catch (PDOException $e) {
                $result['errors'][] = "Row $rowNum ({$row[0]}): " . $e->getMessage();
            }

            $rowNum++;
        }

    } catch (Exception $e) {
        $result['errors'][] = 'Error reading file: ' . $e->getMessage();
    }

    return $result;
}

/**
 * Generate and download sample client Excel template
 */
function generateClientSampleExcel()
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = [
        'Name*',
        'Contact Person',
        'Email',
        'Phone',
        'Address'
    ];

    // Style header row
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:E1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Add sample data
    $sampleData = [
        ['EXCELLENCE TOWER FLOOR 502', 'MOATAZ', 'moataz@example.com', '33212185', 'DOHA QATAR'],
        ['Al Jazeera Trading LLC', 'Ahmed Ali', 'ahmed@aljazeera.qa', '+974 44556677', 'West Bay, Doha'],
        ['Qatar Construction Co.', 'Sarah Mohammed', 'sarah@qcc.qa', '+974 55667788', 'Industrial Area, Doha']
    ];

    $sheet->fromArray($sampleData, NULL, 'A2');

    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Add notes
    $lastRow = $sheet->getHighestRow() + 2;
    $sheet->setCellValue('A' . $lastRow, 'INSTRUCTIONS:');
    $sheet->getStyle('A' . $lastRow)->getFont()->setBold(true);
    $sheet->setCellValue('A' . ($lastRow + 1), '* Name is required');
    $sheet->setCellValue('A' . ($lastRow + 2), '* All other fields are optional');

    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="client_import_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Import clients from uploaded Excel file
 * @param array $file - $_FILES['excel_file']
 * @param PDO $pdo - Database connection
 * @return array - ['success' => count, 'errors' => array, 'imported' => array]
 */
function importClientsFromExcel($file, $pdo)
{
    $result = [
        'success' => 0,
        'errors' => [],
        'imported' => []
    ];

    try {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'File upload error';
            return $result;
        }

        $allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            $result['errors'][] = 'Invalid file type. Please upload .xlsx or .xls file';
            return $result;
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $result['errors'][] = 'File too large. Maximum size is 5MB';
            return $result;
        }

        // Load spreadsheet
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Skip header row
        array_shift($rows);

        $rowNum = 2; // Start from row 2 (after header)
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                $rowNum++;
                continue;
            }

            // Validate required field
            if (empty($row[0])) {
                $result['errors'][] = "Row $rowNum: Name is required";
                $rowNum++;
                continue;
            }

            try {
                // Insert client
                $stmt = $pdo->prepare("INSERT INTO clients (
                    name, contact, email, phone, address
                ) VALUES (?, ?, ?, ?, ?)");

                $stmt->execute([
                    $row[0],  // name
                    $row[1] ?? null,  // contact
                    $row[2] ?? null,  // email
                    $row[3] ?? null,  // phone
                    $row[4] ?? null   // address
                    // Note: row[5] (notes) is ignored as the table doesn't have this column
                ]);

                $result['success']++;
                $result['imported'][] = $row[0];

            } catch (PDOException $e) {
                $result['errors'][] = "Row $rowNum ({$row[0]}): " . $e->getMessage();
            }

            $rowNum++;
        }

    } catch (Exception $e) {
        $result['errors'][] = 'Error reading file: ' . $e->getMessage();
    }

    return $result;
}

/**
 * Generate and download sample vendor Excel template
 */
function generateVendorSampleExcel()
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers - matching vendors table structure
    $headers = [
        'Name*',
        'Contact Person',
        'Email',
        'Phone',
        'Address'
    ];

    // Style header row
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:E1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);

    // Add sample data
    $sampleData = [
        ['ABC Trading LLC', 'Ahmed Ali', 'ahmed@abctrading.qa', '+974 44556677', 'Industrial Area, Doha'],
        ['Qatar Building Materials', 'Mohammed Hassan', 'info@qbm.qa', '+974 44112233', 'Street 45, Doha'],
        ['Al Jazeera Suppliers', 'Fatima Ahmed', 'sales@aljazeera.qa', '+974 44998877', 'West Bay, Doha']
    ];

    $sheet->fromArray($sampleData, NULL, 'A2');

    // Auto-size columns
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Add notes
    $lastRow = $sheet->getHighestRow() + 2;
    $sheet->setCellValue('A' . $lastRow, 'INSTRUCTIONS:');
    $sheet->getStyle('A' . $lastRow)->getFont()->setBold(true);
    $sheet->setCellValue('A' . ($lastRow + 1), '* Name is required');
    $sheet->setCellValue('A' . ($lastRow + 2), '* All other fields are optional');

    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="vendor_import_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Import vendors from uploaded Excel file
 * @param array $file - $_FILES['excel_file']
 * @param PDO $pdo - Database connection
 * @return array - ['success' => count, 'errors' => array, 'imported' => array]
 */
function importVendorsFromExcel($file, $pdo)
{
    $result = [
        'success' => 0,
        'errors' => [],
        'imported' => []
    ];

    try {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'File upload error';
            return $result;
        }

        $allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (!in_array($file['type'], $allowedTypes)) {
            $result['errors'][] = 'Invalid file type. Please upload .xlsx or .xls file';
            return $result;
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $result['errors'][] = 'File too large. Maximum size is 5MB';
            return $result;
        }

        // Load spreadsheet
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Skip header row
        array_shift($rows);

        $rowNum = 2; // Start from row 2 (after header)
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                $rowNum++;
                continue;
            }

            // Validate required field
            if (empty($row[0])) {
                $result['errors'][] = "Row $rowNum: Name is required";
                $rowNum++;
                continue;
            }

            try {
                // Insert vendor - matching vendors table structure
                $stmt = $pdo->prepare("INSERT INTO vendors (
                    name, contact, email, phone, address
                ) VALUES (?, ?, ?, ?, ?)");

                $stmt->execute([
                    $row[0],  // name
                    $row[1] ?? null,  // contact
                    $row[2] ?? null,  // email
                    $row[3] ?? null,  // phone
                    $row[4] ?? null   // address
                ]);

                $result['success']++;
                $result['imported'][] = $row[0];

            } catch (PDOException $e) {
                $result['errors'][] = "Row $rowNum ({$row[0]}): " . $e->getMessage();
            }

            $rowNum++;
        }

    } catch (Exception $e) {
        $result['errors'][] = 'Error reading file: ' . $e->getMessage();
    }

    return $result;
}
