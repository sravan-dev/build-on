<?php
require_once __DIR__ . '/../includes/db.php';

echo "Updating sample employees with salary data...\n";

$updates = [
    'Super Admin' => [
        'monthly_salary' => 15000,
        'room_allowance' => 2000,
        'food_allowance' => 500,
        'telephone_allowance' => 200,
        'employee_id' => 'EMP001'
    ],
    'Jane Supervisor' => [
        'monthly_salary' => 8000,
        'room_allowance' => 1500,
        'food_allowance' => 300,
        'telephone_allowance' => 100,
        'employee_id' => 'EMP002'
    ],
    'John Employee' => [
        'monthly_salary' => 3000,
        'room_allowance' => 500,
        'food_allowance' => 200,
        'telephone_allowance' => 50,
        'employee_id' => 'EMP003'
    ]
];

foreach ($updates as $name => $data) {
    $stmt = $pdo->prepare("UPDATE employees SET monthly_salary=?, room_allowance=?, food_allowance=?, telephone_allowance=?, employee_id=? WHERE name=?");
    $stmt->execute([
        $data['monthly_salary'],
        $data['room_allowance'],
        $data['food_allowance'],
        $data['telephone_allowance'],
        $data['employee_id'],
        $name
    ]);
    echo "Updated salary for: $name\n";
}

echo "Done.\n";
