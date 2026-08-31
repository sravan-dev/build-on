<?php
require_once 'includes/auth.php';

// Router for Attendance Module

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? 'employee';

// Superadmin/Supervisor can switch views via GET parameter
$view = $_GET['view'] ?? '';

if ($view === 'report') {
    include 'pages/attendance_report.php';
} elseif ($view === 'approvals') {
    include 'pages/attendance_approvals.php'; // To be created
} elseif ($role === 'employee') {
    include 'pages/attendance_employee.php';
} else {
    // Default for Admin/Supervisor
    // Maybe show a Dashboard with options?
    // For now, default to Report view or Approvals
    include 'pages/attendance_report.php';
}
