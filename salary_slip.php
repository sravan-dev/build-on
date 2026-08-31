<?php
// Standalone salary slip page
session_start();
include_once 'includes/db.php';

// Get employee ID
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch employee details
$stmt = $pdo->prepare("
    SELECT e.*, u.username 
    FROM employees e
    LEFT JOIN users u ON e.id = u.employee_id
    WHERE e.id = ?
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die('Employee not found');
}

// Get current month details
$current_month = date('F Y');
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

// Get total working hours for current month
$stmt = $pdo->prepare("SELECT SUM(working_hours) as total_hours FROM daily_attendance WHERE employee_id = ? AND attendance_date BETWEEN ? AND ?");
$stmt->execute([$employee_id, $current_month_start, $current_month_end]);
$attendance = $stmt->fetch();
$total_hours = $attendance['total_hours'] ?? 0;

// Get advances for current month
$stmt = $pdo->prepare("SELECT SUM(amount) as total_advance FROM advance_payments WHERE employee_id = ? AND payment_date BETWEEN ? AND ?");
$stmt->execute([$employee_id, $current_month_start, $current_month_end]);
$advance_data = $stmt->fetch();
$total_advance = $advance_data['total_advance'] ?? 0;

// Get salary payments for current month
$stmt = $pdo->prepare("SELECT * FROM salary_payments WHERE employee_id = ? AND payment_date BETWEEN ? AND ? ORDER BY payment_date DESC LIMIT 1");
$stmt->execute([$employee_id, $current_month_start, $current_month_end]);
$salary_payment = $stmt->fetch();

// Calculate salary components
$basic_salary = floatval($employee['monthly_salary'] ?? 0);
$hra = floatval($employee['room_allowance'] ?? 0);
$transport = floatval($employee['food_allowance'] ?? 0);
$phone = floatval($employee['telephone_allowance'] ?? 0);
$overtime = 0; // Can be calculated if needed

$total_earnings = $basic_salary + $hra + $transport + $phone + $overtime;
$total_deductions = $total_advance + floatval($employee['deductions'] ?? 0);
$net_salary = $total_earnings - $total_deductions;

// Get company info
$company_name = getenv('COMPANY_NAME') ?: 'BUILDON TRADING & CONTRACTING W.L.L';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?php echo htmlspecialchars($employee['name']); ?> - <?php echo $current_month; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .slip-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #f07d00 0%, #ff6600 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 20px;
            font-weight: bold;
        }
        
        .header .month {
            font-size: 18px;
        }
        
        .section-header {
            background: #f07d00;
            color: white;
            padding: 10px 20px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            padding: 20px;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-label {
            color: #666;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .salary-table th {
            background: #f07d00;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        
        .salary-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .salary-table .amount {
            text-align: right;
            font-weight: 500;
        }
        
        .total-row {
            background: #fff5e6;
            font-weight: bold;
        }
        
        .net-salary {
            background: #f07d00;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }
        
        .payment-details {
            padding: 20px;
            background: #f9f9f9;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
        }
        
        .footer {
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 12px;
            border-top: 2px solid #eee;
        }
        
        .buttons {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 12px 30px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print {
            background: #f07d00;
            color: white;
        }
        
        .btn-print:hover {
            background: #d96d00;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .slip-container {
                box-shadow: none;
            }
            
            .buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="buttons">
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print A4
        </button>
        <a href="index.php?page=payroll" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    
    <div class="slip-container">
        <div class="header">
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <div class="month">Salary Slip - <?php echo $current_month; ?></div>
        </div>
        
        <div class="section-header">Employee Information</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Employee Name</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Employee ID</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Designation</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Department</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pay Period</span>
                <span class="info-value"><?php echo $current_month; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Paid Days</span>
                <span class="info-value"><?php echo round($total_hours / 8, 1); ?> days</span>
            </div>
        </div>
        
        <div class="section-header">Salary Details</div>
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Earnings</th>
                    <th class="amount">Amount</th>
                    <th>Deductions</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Salary</td>
                    <td class="amount"><?php echo number_format($basic_salary, 2); ?></td>
                    <td>Advance</td>
                    <td class="amount"><?php echo number_format($total_advance, 2); ?></td>
                </tr>
                <tr>
                    <td>HRA</td>
                    <td class="amount"><?php echo number_format($hra, 2); ?></td>
                    <td>Loan</td>
                    <td class="amount">0.00</td>
                </tr>
                <tr>
                    <td>Transport Allowance</td>
                    <td class="amount"><?php echo number_format($transport, 2); ?></td>
                    <td>Leave Deduction</td>
                    <td class="amount">0.00</td>
                </tr>
                <tr>
                    <td>Other Allowances</td>
                    <td class="amount"><?php echo number_format($phone, 2); ?></td>
                    <td>Other Deductions</td>
                    <td class="amount"><?php echo number_format(floatval($employee['deductions'] ?? 0), 2); ?></td>
                </tr>
                <tr>
                    <td>Overtime</td>
                    <td class="amount"><?php echo number_format($overtime, 2); ?></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr class="total-row">
                    <td>Total Earnings</td>
                    <td class="amount"><?php echo number_format($total_earnings, 2); ?></td>
                    <td>Total Deductions</td>
                    <td class="amount"><?php echo number_format($total_deductions, 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="net-salary">
            NET SALARY: QAR <?php echo number_format($net_salary, 2); ?>
        </div>
        
        <?php if ($salary_payment): ?>
        <div class="section-header">Payment Details</div>
        <div class="payment-details">
            <div class="payment-grid">
                <div class="info-item">
                    <span class="info-label">Payment Mode</span>
                    <span class="info-value"><?php 
                        $payment_labels = [
                            'company_cash' => 'Cash',
                            'company_bank' => 'Bank Transfer',
                            'company_card' => 'Card',
                            'credit_card' => 'Credit Card',
                            'company_cheque' => 'Cheque'
                        ];
                        echo $payment_labels[$salary_payment['payment_method']] ?? 'Bank Transfer';
                    ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Date</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($salary_payment['payment_date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Bank Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['bank_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Transaction Ref</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['bank_account'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>This is a system-generated salary slip. No signature required.</p>
            <p style="margin-top: 10px;">Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
    </div>
</body>
</html>
