<?php
require_once dirname(__DIR__) . '/includes/db.php';

// Function to convert number to words (same as in vouchers.php)
function numberToWords($number) {
    $ones = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
    );
    
    $tens = array(
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
        60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    
    $hundreds = array(100 => 'Hundred', 1000 => 'Thousand', 1000000 => 'Million');
    
    if ($number < 20) {
        return $ones[$number];
    } elseif ($number < 100) {
        $tens_digit = intval($number / 10) * 10;
        $ones_digit = $number % 10;
        return $tens[$tens_digit] . ($ones_digit > 0 ? ' ' . $ones[$ones_digit] : '');
    } elseif ($number < 1000) {
        $hundreds_digit = intval($number / 100);
        $remainder = $number % 100;
        $result = $ones[$hundreds_digit] . ' ' . $hundreds[100];
        if ($remainder > 0) {
            $result .= ' ' . numberToWords($remainder);
        }
        return $result;
    } elseif ($number < 1000000) {
        $thousands = intval($number / 1000);
        $remainder = $number % 1000;
        $result = numberToWords($thousands) . ' ' . $hundreds[1000];
        if ($remainder > 0) {
            $result .= ' ' . numberToWords($remainder);
        }
        return $result;
    }
    
    return 'Number too large';
}

// Sample voucher data
$sampleVouchers = [
    [
        'voucher_no' => 'V0001',
        'voucher_date' => '2024-01-15',
        'paid_to_received_from' => 'Office Supplies Store',
        'amount' => 250.00,
        'description' => 'Purchase of office stationery and supplies for the month',
        'voucher_type' => 'cash',
        'prepared_by' => 'Ahmed Al-Rashid',
        'checked_by' => 'Sarah Johnson',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'posted',
        'entries' => [
            ['account' => 'Office Expenses', 'debit' => 250.00, 'credit' => 0, 'narration' => 'Office supplies purchase'],
            ['account' => 'Cash in Hand', 'debit' => 0, 'credit' => 250.00, 'narration' => 'Cash payment for office supplies']
        ]
    ],
    [
        'voucher_no' => 'V0002',
        'voucher_date' => '2024-01-18',
        'paid_to_received_from' => 'ABC Construction Materials',
        'amount' => 1500.00,
        'description' => 'Payment for construction materials - cement and steel',
        'voucher_type' => 'cash',
        'prepared_by' => 'Omar Al-Mahmoud',
        'checked_by' => 'Fatima Al-Zahra',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'posted',
        'entries' => [
            ['account' => 'Construction Materials', 'debit' => 1500.00, 'credit' => 0, 'narration' => 'Purchase of cement and steel'],
            ['account' => 'Bank Account', 'debit' => 0, 'credit' => 1500.00, 'narration' => 'Bank transfer payment']
        ]
    ],
    [
        'voucher_no' => 'V0003',
        'voucher_date' => '2024-01-20',
        'paid_to_received_from' => 'Qatar Electricity Company',
        'amount' => 350.75,
        'description' => 'Monthly electricity bill payment',
        'voucher_type' => 'cash',
        'prepared_by' => 'Aisha Al-Mansouri',
        'checked_by' => 'Sarah Johnson',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'approved',
        'entries' => [
            ['account' => 'Utilities', 'debit' => 350.75, 'credit' => 0, 'narration' => 'Electricity bill payment'],
            ['account' => 'Bank Account', 'debit' => 0, 'credit' => 350.75, 'narration' => 'Bank transfer for utilities']
        ]
    ],
    [
        'voucher_no' => 'V0004',
        'voucher_date' => '2024-01-22',
        'paid_to_received_from' => 'Transportation Services Ltd',
        'amount' => 800.00,
        'description' => 'Transportation costs for project materials delivery',
        'voucher_type' => 'cash',
        'prepared_by' => 'Khalid Al-Suwaidi',
        'checked_by' => 'Fatima Al-Zahra',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'posted',
        'entries' => [
            ['account' => 'Transportation Expenses', 'debit' => 800.00, 'credit' => 0, 'narration' => 'Material delivery costs'],
            ['account' => 'Cash in Hand', 'debit' => 0, 'credit' => 800.00, 'narration' => 'Cash payment for transportation']
        ]
    ],
    [
        'voucher_no' => 'V0005',
        'voucher_date' => '2024-01-25',
        'paid_to_received_from' => 'Rent Payment - Landlord',
        'amount' => 5000.00,
        'description' => 'Monthly office rent payment',
        'voucher_type' => 'cash',
        'prepared_by' => 'Ahmed Al-Rashid',
        'checked_by' => 'Sarah Johnson',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'posted',
        'entries' => [
            ['account' => 'Rent', 'debit' => 5000.00, 'credit' => 0, 'narration' => 'Monthly office rent'],
            ['account' => 'Bank Account', 'debit' => 0, 'credit' => 5000.00, 'narration' => 'Bank transfer for rent']
        ]
    ],
    [
        'voucher_no' => 'V0006',
        'voucher_date' => '2024-01-28',
        'paid_to_received_from' => 'Equipment Maintenance Co.',
        'amount' => 1200.50,
        'description' => 'Equipment maintenance and repair services',
        'voucher_type' => 'cash',
        'prepared_by' => 'Omar Al-Mahmoud',
        'checked_by' => 'Fatima Al-Zahra',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'draft',
        'entries' => [
            ['account' => 'Equipment Maintenance', 'debit' => 1200.50, 'credit' => 0, 'narration' => 'Equipment repair services'],
            ['account' => 'Accounts Payable', 'debit' => 0, 'credit' => 1200.50, 'narration' => 'Outstanding payment for maintenance']
        ]
    ],
    [
        'voucher_no' => 'V0007',
        'voucher_date' => '2024-01-30',
        'paid_to_received_from' => 'Client Payment - XYZ Company',
        'amount' => 15000.00,
        'description' => 'Payment received from client for completed project',
        'voucher_type' => 'cash',
        'prepared_by' => 'Aisha Al-Mansouri',
        'checked_by' => 'Sarah Johnson',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'posted',
        'entries' => [
            ['account' => 'Bank Account', 'debit' => 15000.00, 'credit' => 0, 'narration' => 'Client payment received'],
            ['account' => 'Sales Revenue', 'debit' => 0, 'credit' => 15000.00, 'narration' => 'Revenue from completed project']
        ]
    ],
    [
        'voucher_no' => 'V0008',
        'voucher_date' => '2024-02-02',
        'paid_to_received_from' => 'Staff Salary Payment',
        'amount' => 8500.00,
        'description' => 'Monthly salary payment to employees',
        'voucher_type' => 'cash',
        'prepared_by' => 'Khalid Al-Suwaidi',
        'checked_by' => 'Fatima Al-Zahra',
        'approved_by' => 'Mohammed Hassan',
        'status' => 'posted',
        'entries' => [
            ['account' => 'Salary Expenses', 'debit' => 8500.00, 'credit' => 0, 'narration' => 'Monthly staff salaries'],
            ['account' => 'Bank Account', 'debit' => 0, 'credit' => 8500.00, 'narration' => 'Bank transfer for salaries']
        ]
    ]
];

try {
    $pdo->beginTransaction();
    
    echo "Adding sample cash vouchers...\n";
    
    foreach ($sampleVouchers as $voucherData) {
        // Calculate amount in words
        $amountInWords = numberToWords($voucherData['amount']) . ' Riyals Only';
        
        // Insert voucher
        $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_date, paid_to_received_from, amount, amount_in_words, description, voucher_type, prepared_by, checked_by, approved_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $voucherData['voucher_no'],
            $voucherData['voucher_date'],
            $voucherData['paid_to_received_from'],
            $voucherData['amount'],
            $amountInWords,
            $voucherData['description'],
            $voucherData['voucher_type'],
            $voucherData['prepared_by'],
            $voucherData['checked_by'],
            $voucherData['approved_by'],
            $voucherData['status']
        ]);
        
        $voucherId = $pdo->lastInsertId();
        
        // Insert voucher entries
        foreach ($voucherData['entries'] as $entry) {
            $stmt = $pdo->prepare("INSERT INTO voucher_entries (voucher_id, account_head, debit_amount, credit_amount, narration) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $voucherId,
                $entry['account'],
                $entry['debit'],
                $entry['credit'],
                $entry['narration']
            ]);
        }
        
        echo "✓ Added voucher {$voucherData['voucher_no']} - {$voucherData['paid_to_received_from']} - " . number_format($voucherData['amount'], 2) . " ريال\n";
    }
    
    $pdo->commit();
    echo "\n✓ Successfully added " . count($sampleVouchers) . " sample vouchers!\n";
    echo "✓ All vouchers include proper double-entry bookkeeping entries\n";
    echo "✓ Vouchers have different statuses (Draft, Approved, Posted)\n";
    echo "✓ Ready to test the voucher system!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "✗ Error adding sample vouchers: " . $e->getMessage() . "\n";
}
?>
