<?php
/**
 * BuildOn Mobile API
 * RESTful API for Android Application
 * 
 * Version: 1.0
 * Author: BuildOn Team
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers for cross-origin// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Debug logging
function logDebug($msg)
{
    $time = date('Y-m-d H:i:s');
    file_put_contents('api_debug.log', "[$time] $msg" . PHP_EOL, FILE_APPEND);
}

// Get Request Data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$requestData = json_decode(file_get_contents('php://input'), true);

// Parse endpoint from query string
$endpoint = $_GET['endpoint'] ?? '';
logDebug("Request: $endpoint");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
include_once 'includes/db.php';

// API Response Helper Functions
function sendResponse($success, $message, $data = null, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $statusCode = 400)
{
    sendResponse(false, $message, null, $statusCode);
}

function sendSuccess($message, $data = null)
{
    sendResponse(true, $message, $data, 200);
}

function touchLastActive($pdo, $table, $id)
{
    try {
        $id = (int) $id;
        if ($id <= 0) {
            return;
        }
        $stmt = $pdo->prepare("UPDATE {$table} SET last_active = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
    } catch (Exception $e) {
        // Ignore activity tracking failures
    }
}

function fetchActiveEmployeeByCode($pdo, $emp_id)
{
    $queries = [
        "SELECT * FROM employees WHERE employee_id = ? AND status = 'active' LIMIT 1",
        "SELECT * FROM employees WHERE emp_id = ? AND status = 'active' LIMIT 1",
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$emp_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($employee) {
                return $employee;
            }
        } catch (Exception $e) {
            // Try the next variant for schema compatibility
        }
    }

    return false;
}

function findLinkedUser($pdo, $employeeId, $preferredUsername = '')
{
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, role, employee_id
            FROM users
            WHERE employee_id = ?
            ORDER BY CASE WHEN username = ? THEN 0 ELSE 1 END, id ASC
            LIMIT 1
        ");
        $stmt->execute([(int) $employeeId, (string) $preferredUsername]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

function normalizeEmployeeContext($employee, $linkedUser = null)
{
    if (!is_array($employee)) {
        return false;
    }

    unset($employee['password']);

    if (empty($employee['emp_id'])) {
        $employee['emp_id'] = $employee['employee_id'] ?? ($employee['emp_id'] ?? '');
    }
    if (empty($employee['emp_id'])) {
        $employee['emp_id'] = (string) ($employee['id'] ?? '');
    }

    $employee['id'] = (int) ($employee['id'] ?? 0);
    $employee['employee_id_ref'] = $employee['id'];
    $employee['is_user_only'] = false;

    if ($linkedUser) {
        $role = $linkedUser['role'] ?? 'employee';
        $employee['role'] = $role;
        $employee['is_admin'] = ($role !== 'employee');
        $employee['user_id'] = (int) ($linkedUser['id'] ?? 0);
        $employee['username'] = $linkedUser['username'] ?? null;
        $employee['employee_name'] = $employee['name'] ?? null;
        if (!empty($linkedUser['username'])) {
            // Mobile app should show username instead of linked employee name.
            $employee['name'] = (string) $linkedUser['username'];
        }
    } else {
        if (empty($employee['role'])) {
            $employee['role'] = 'employee';
        }
        if (!isset($employee['is_admin'])) {
            $employee['is_admin'] = false;
        }
    }

    return $employee;
}

// Authentication Helper
function authenticateEmployee($pdo, $emp_id, $password)
{
    try {
        $employee = fetchActiveEmployeeByCode($pdo, $emp_id);

        if (!$employee) {
            return false;
        }

        // Verify password
        if (!empty($employee['password']) && password_verify($password, $employee['password'])) {
            $linkedUser = findLinkedUser($pdo, (int) $employee['id'], '');
            return normalizeEmployeeContext($employee, $linkedUser ?: null);
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

// Helper to check admin users table
function authenticateAdmin($pdo, $username, $password)
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!empty($user['employee_id'])) {
                try {
                    $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active' LIMIT 1");
                    $empStmt->execute([(int) $user['employee_id']]);
                    $linkedEmp = $empStmt->fetch(PDO::FETCH_ASSOC);
                    if ($linkedEmp) {
                        return normalizeEmployeeContext($linkedEmp, $user);
                    }
                } catch (Exception $e) {
                    // Fallback to user-only context below
                }
            }

            // Employee mobile app requires a linked employee profile
            return false;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

// Get Bearer Token from headers
function getBearerToken()
{
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $matches = [];
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Validate Token and get Employee
function validateToken($pdo, $token)
{
    try {
        // Decode token (simple base64 for now - use JWT in production)
        $decoded = base64_decode($token);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 2) {
            return false;
        }

        list($emp_id, $timestamp) = $parts;
        if (!is_numeric($timestamp)) {
            return false;
        }

        // Check if token is not older than 30 days
        if ((time() - (int) $timestamp) > (30 * 24 * 60 * 60)) {
            return false;
        }

        // Primary path: token maps to employee code
        $employee = fetchActiveEmployeeByCode($pdo, $emp_id);

        if ($employee) {
            $linkedUser = findLinkedUser($pdo, (int) $employee['id'], '');
            touchLastActive($pdo, 'employees', (int) $employee['id']);
            if ($linkedUser) {
                touchLastActive($pdo, 'users', (int) $linkedUser['id']);
            }
            return normalizeEmployeeContext($employee, $linkedUser ?: null);
        }

        // Legacy path: token maps to username from older admin login tokens
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$emp_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            touchLastActive($pdo, 'users', (int) $user['id']);

            if (!empty($user['employee_id'])) {
                try {
                    $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'active' LIMIT 1");
                    $empStmt->execute([(int) $user['employee_id']]);
                    $linkedEmp = $empStmt->fetch(PDO::FETCH_ASSOC);
                    if ($linkedEmp) {
                        touchLastActive($pdo, 'employees', (int) $linkedEmp['id']);
                        return normalizeEmployeeContext($linkedEmp, $user);
                    }
                } catch (Exception $e) {
                    // Fall back to user-only context
                }
            }

            // Token belongs to a user without linked employee profile
            return false;
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Generate Token
function generateToken($emp_id)
{
    return base64_encode($emp_id . ':' . time());
}

// Get Request Data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$requestData = json_decode(file_get_contents('php://input'), true);

// Parse endpoint from query string
$endpoint = $_GET['endpoint'] ?? '';

// ============================================
// API ENDPOINTS
// ============================================

try {
    switch ($endpoint) {

        // ========================================
        // 1. EMPLOYEE LOGIN
        // ========================================
        case 'login':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            if (isset($requestData['emp_id']) && isset($requestData['password'])) {
                $emp_id = $requestData['emp_id'];
                $password = $requestData['password'];

                // Try authenticating as Employee
                $employee = authenticateEmployee($pdo, $emp_id, $password);

                // If not found, try authenticating as Admin (User)
                if (!$employee) {
                    $employee = authenticateAdmin($pdo, $emp_id, $password);
                }

                if ($employee) {
                    // Generate token
                    $token = generateToken($employee['emp_id']);

                    // Mark user as active immediately on login
                    if (!empty($employee['employee_id_ref'])) {
                        touchLastActive($pdo, 'employees', (int) $employee['employee_id_ref']);
                    }
                    if (!empty($employee['user_id'])) {
                        touchLastActive($pdo, 'users', (int) $employee['user_id']);
                    }

                    // Log API Login
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO login_activity (user_id, user_type, username, login_time, ip_address, user_agent, status) VALUES (?, ?, ?, NOW(), ?, ?, 'success')");
                        $logUserId = $employee['user_id'] ?? $employee['id'];
                        $logUserType = $employee['role'] ?? ($employee['is_admin'] ? 'admin' : 'employee');
                        $logUsername = $employee['name'] ?? $emp_id;
                        $logIp = $_SERVER['REMOTE_ADDR'] ?? '';
                        $logUa = $_SERVER['HTTP_USER_AGENT'] ?? 'Mobile App';
                        $logStmt->execute([$logUserId, $logUserType, $logUsername, $logIp, $logUa]);
                    } catch (Exception $e) {
                       // Silently fail
                    }

                    sendSuccess('Login successful', [
                        'token' => $token,
                        'employee' => $employee
                    ]);
                } else {
                    sendError('Invalid credentials', 401);
                }
            } else {
                sendError('Employee ID and password are required', 400);
            }
            break;

        // ========================================
        // 2. GET EMPLOYEE PROFILE
        // ========================================
        case 'profile':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            sendSuccess('Profile retrieved successfully', $employee);
            break;

        // ========================================
        // 3. MARK ATTENDANCE (Clock In/Out)
        // ========================================
        case 'attendance':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $action = $requestData['action'] ?? ''; // 'clock_in' or 'clock_out'
            $date = date('Y-m-d');
            $time = date('H:i:s');

            // Check if attendance record exists for today
            $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
            $stmt->execute([$employee['id'], $date]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            $project_id = $requestData['project_id'] ?? null;

            if ($action === 'clock_in') {
                if ($attendance && $attendance['in_time']) {
                    sendError('Already clocked in for today', 400);
                }

                // Get work site name if project selected
                $work_site = null;
                if ($project_id) {
                    $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                    $stmt->execute([$project_id]);
                    $work_site = $stmt->fetchColumn();
                }

                if ($attendance) {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE daily_attendance SET in_time = ?, work_site = ? WHERE id = ?");
                    $stmt->execute([$time, $work_site, $attendance['id']]);
                    $daily_id = $attendance['id'];
                } else {
                    // Create new record
                    $stmt = $pdo->prepare("INSERT INTO daily_attendance (employee_id, attendance_date, in_time, status, work_site) VALUES (?, ?, ?, 'present', ?)");
                    $stmt->execute([$employee['id'], $date, $time, $work_site]);
                    $daily_id = $pdo->lastInsertId();
                }

                // Create initial work log
                $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, start_time, activity_type, project_id) VALUES (?, ?, 'work', ?)");
                $stmt->execute([$daily_id, $time, $project_id]);

                sendSuccess('Clocked in successfully', [
                    'date' => $date,
                    'time' => $time,
                    'action' => 'clock_in',
                    'work_site' => $work_site
                ]);
            } elseif ($action === 'clock_out') {
                if (!$attendance || !$attendance['in_time']) {
                    sendError('You must clock in first', 400);
                }

                if ($attendance['out_time']) {
                    sendError('Already clocked out for today', 400);
                }

                // Close the last open work log FIRST
                $stmt = $pdo->prepare("SELECT id FROM attendance_logs WHERE daily_attendance_id = ? AND end_time IS NULL ORDER BY id DESC LIMIT 1");
                $stmt->execute([$attendance['id']]);
                $lastLogId = $stmt->fetchColumn();

                if ($lastLogId) {
                    $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
                    $stmt->execute([$time, $lastLogId]);
                }

                // Calculate total working hours from logs (excluding breaks)
                $stmt = $pdo->prepare("
                    SELECT SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)) as total_seconds
                    FROM attendance_logs
                    WHERE daily_attendance_id = ? AND activity_type = 'work' AND end_time IS NOT NULL
                ");
                $stmt->execute([$attendance['id']]);
                $total_seconds = $stmt->fetchColumn();
                
                $working_hours = round(($total_seconds ?: 0) / 3600, 2);

                $stmt = $pdo->prepare("UPDATE daily_attendance SET out_time = ?, working_hours = ? WHERE id = ?");
                $stmt->execute([$time, $working_hours, $attendance['id']]);



                sendSuccess('Clocked out successfully', [
                    'date' => $date,
                    'in_time' => $attendance['in_time'],
                    'out_time' => $time,
                    'working_hours' => $working_hours,
                    'action' => 'clock_out'
                ]);
            } else {
                sendError('Invalid action. Use clock_in or clock_out', 400);
            }
            break;

        // ========================================
        // 4. GET ATTENDANCE HISTORY
        // ========================================
        case 'attendance_history':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $month = $_GET['month'] ?? date('Y-m');
            $limit = $_GET['limit'] ?? 30;

            $stmt = $pdo->prepare("
                SELECT * FROM daily_attendance 
                WHERE employee_id = ? 
                AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
                ORDER BY attendance_date DESC 
                LIMIT ?
            ");
            $stmt->execute([$employee['id'], $month, intval($limit)]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendSuccess('Attendance history retrieved', [
                'month' => $month,
                'records' => $records,
                'total' => count($records)
            ]);
            break;

        // ========================================
        // 5. APPLY FOR LEAVE
        // ========================================
        case 'leave_apply':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $leave_type = $requestData['leave_type'] ?? '';
            $start_date = $requestData['start_date'] ?? '';
            $end_date = $requestData['end_date'] ?? '';
            $reason = $requestData['reason'] ?? '';

            if (empty($leave_type) || empty($start_date) || empty($end_date)) {
                sendError('Leave type, start date, and end date are required', 400);
            }

            // Calculate days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $days = $start->diff($end)->days + 1;

            $stmt = $pdo->prepare("
                INSERT INTO leave_applications (employee_id, leave_type, start_date, end_date, days, reason, status, applied_date) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([$employee['id'], $leave_type, $start_date, $end_date, $days, $reason, date('Y-m-d')]);

            sendSuccess('Leave application submitted successfully', [
                'leave_type' => $leave_type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'days' => $days,
                'status' => 'pending'
            ]);
            break;

        // ========================================
        // 6. GET LEAVE HISTORY
        // ========================================
        case 'leave_history':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $limit = $_GET['limit'] ?? 50;

            $stmt = $pdo->prepare("
                SELECT * FROM leave_applications 
                WHERE employee_id = ? 
                ORDER BY applied_date DESC 
                LIMIT ?
            ");
            $stmt->execute([$employee['id'], intval($limit)]);
            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendSuccess('Leave history retrieved', [
                'leaves' => $leaves,
                'total' => count($leaves)
            ]);
            break;

        // ========================================
        // 7. GET TODAY'S ATTENDANCE STATUS
        // ========================================
        case 'attendance_today':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $date = date('Y-m-d');

            $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
            $stmt->execute([$employee['id'], $date]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($attendance) {
                // Get current/latest log to determine state
                $stmt = $pdo->prepare("
                    SELECT al.id, al.project_id, al.activity_type, al.end_time, p.name as project_name 
                    FROM attendance_logs al 
                    LEFT JOIN projects p ON al.project_id = p.id 
                    WHERE daily_attendance_id = ? 
                    ORDER BY al.id DESC 
                    LIMIT 1
                ");
                $stmt->execute([$attendance['id']]);
                $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

                $on_break = false;
                $project_id = null;
                $work_site = $attendance['work_site'];

                if ($lastLog) {
                    if ($lastLog['activity_type'] === 'break' && empty($lastLog['end_time'])) {
                        $on_break = true;
                    }
                    $project_id = $lastLog['project_id'];
                    if ($lastLog['project_name']) {
                        $work_site = $lastLog['project_name'];
                    }
                }

                // If on break or project_id is null, try to find the last active project
                if (empty($project_id)) {
                     $stmt = $pdo->prepare("
                        SELECT al.project_id, p.name as project_name 
                        FROM attendance_logs al 
                        LEFT JOIN projects p ON al.project_id = p.id 
                        WHERE daily_attendance_id = ? AND al.project_id IS NOT NULL
                        ORDER BY al.id DESC 
                        LIMIT 1
                    ");
                    $stmt->execute([$attendance['id']]);
                    $lastProject = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($lastProject) {
                        $project_id = $lastProject['project_id'];
                        // Don't overwrite work_site if we are currently on break, but maybe relevant for UI
                        if (!$work_site) {
                            $work_site = $lastProject['project_name'];
                        }
                    }
                }

                sendSuccess('Today\'s attendance status', [
                    'clocked_in' => !empty($attendance['in_time']),
                    'clocked_out' => !empty($attendance['out_time']),
                    'in_time' => $attendance['in_time'] ?? null,
                    'out_time' => $attendance['out_time'] ?? null,
                    'working_hours' => $attendance['working_hours'] ?? null,
                    'status' => $attendance['status'] ?? 'absent',
                    'project_id' => $project_id,
                    'work_site' => $work_site,
                    'on_break' => $on_break
                ]);
            } else {
                sendSuccess('No attendance record for today', [
                    'clocked_in' => false,
                    'clocked_out' => false,
                    'in_time' => null,
                    'out_time' => null,
                    'working_hours' => null,
                    'status' => 'absent',
                    'project_id' => null,
                    'work_site' => null,
                    'on_break' => false
                ]);
            }
            break;

        // ========================================
        // 8. GET SALARY/PAYROLL INFO
        // ========================================
        case 'salary_info':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            sendSuccess('Salary information retrieved', [
                'basic_salary' => $employee['basic_salary'] ?? null,
                'allowances' => $employee['allowances'] ?? null,
                'position' => $employee['position'] ?? null,
                'department' => $employee['department'] ?? null
            ]);
            break;

        // ========================================
        // 9. GET PROJECTS LIST (for work site selection)
        // ========================================
        case 'projects':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $stmt = $pdo->query("SELECT id, name, status FROM projects ORDER BY name");
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendSuccess('Projects list retrieved', [
                'projects' => $projects,
                'total' => count($projects)
            ]);
            break;

        // ========================================
        // 10. SWITCH WORK SITE/PROJECT
        // ========================================
        case 'switch_site':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $project_id = $requestData['project_id'] ?? null;
            $is_offsite = $requestData['is_offsite'] ?? false;
            $note = $requestData['note'] ?? '';
            $date = date('Y-m-d');
            $time = date('H:i:s');

            try {
                $pdo->beginTransaction();

                // Get today's attendance
                $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                $stmt->execute([$employee['id'], $date]);
                $daily = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$daily || $daily['out_time']) {
                    throw new Exception('No active work session found');
                }

                // Get last active log
                $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE daily_attendance_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$daily['id']]);
                $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lastLog) {
                    // Self-healing: If no logs exist but daily attendance is open
                    $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, start_time, activity_type) VALUES (?, ?, 'work')");
                    $stmt->execute([$daily['id'], $daily['in_time']]);

                    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE daily_attendance_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$daily['id']]);
                    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$lastLog || $lastLog['end_time'] || $lastLog['activity_type'] === 'break') {
                    throw new Exception('Must be working to switch site');
                }

                // End current log
                $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
                $stmt->execute([$time, $lastLog['id']]);

                // Get work site name
                $work_site = null;
                if ($is_offsite) {
                    $work_site = 'Offsite / Outside';
                    $activity_type = 'offsite';
                } elseif ($project_id) {
                    $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
                    $stmt->execute([$project_id]);
                    $work_site = $stmt->fetchColumn();
                    $activity_type = 'work';
                } else {
                    $activity_type = 'work';
                }

                // Update daily attendance work_site
                $stmt = $pdo->prepare("UPDATE daily_attendance SET work_site = ? WHERE id = ?");
                $stmt->execute([$work_site, $daily['id']]);

                // Create new log
                $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, project_id, start_time, activity_type, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$daily['id'], $project_id, $time, $activity_type, $note]);

                $pdo->commit();

                sendSuccess('Work site switched successfully', [
                    'new_site' => $work_site ?? 'Unknown',
                    'is_offsite' => $is_offsite,
                    'time' => $time
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                sendError($e->getMessage(), 400);
            }
            break;

        // ========================================
        // 11. START BREAK
        // ========================================
        case 'start_break':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $date = date('Y-m-d');
            $time = date('H:i:s');

            try {
                $pdo->beginTransaction();

                // Get today's attendance
                $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                $stmt->execute([$employee['id'], $date]);
                $daily = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$daily || $daily['out_time']) {
                    throw new Exception('No active work session found');
                }

                // Get last active log
                $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE daily_attendance_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$daily['id']]);
                $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lastLog) {
                    // Self-healing: If no logs exist but daily attendance is open, assume work started at in_time
                    $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, start_time, activity_type) VALUES (?, ?, 'work')");
                    $stmt->execute([$daily['id'], $daily['in_time']]);

                    // Fetch the newly created log to continue logic typically
                    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE daily_attendance_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$daily['id']]);
                    $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$lastLog || $lastLog['end_time'] || $lastLog['activity_type'] === 'break') {
                    throw new Exception('Must be working to start break');
                }

                // End current work log
                $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
                $stmt->execute([$time, $lastLog['id']]);

                // Start break log
                $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, start_time, activity_type) VALUES (?, ?, 'break')");
                $stmt->execute([$daily['id'], $time]);

                $pdo->commit();

                sendSuccess('Break started successfully', [
                    'break_start' => $time
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                sendError($e->getMessage(), 400);
            }
            break;

        // ========================================
        // 12. END BREAK
        // ========================================
        case 'end_break':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $project_id = $requestData['project_id'] ?? null;
            $date = date('Y-m-d');
            $time = date('H:i:s');

            try {
                $pdo->beginTransaction();

                // Get today's attendance
                $stmt = $pdo->prepare("SELECT * FROM daily_attendance WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
                $stmt->execute([$employee['id'], $date]);
                $daily = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$daily) {
                    throw new Exception('No attendance record found');
                }

                // Get last active log
                $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE daily_attendance_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$daily['id']]);
                $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lastLog || $lastLog['activity_type'] !== 'break' || $lastLog['end_time']) {
                    throw new Exception('No active break session found');
                }

                // End break log
                $stmt = $pdo->prepare("UPDATE attendance_logs SET end_time = ? WHERE id = ?");
                $stmt->execute([$time, $lastLog['id']]);

                // Resume work
                $stmt = $pdo->prepare("INSERT INTO attendance_logs (daily_attendance_id, project_id, start_time, activity_type) VALUES (?, ?, ?, 'work')");
                $stmt->execute([$daily['id'], $project_id, $time]);

                $pdo->commit();

                sendSuccess('Break ended, back to work', [
                    'break_end' => $time,
                    'work_resumed' => $time
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                sendError($e->getMessage(), 400);
            }
            break;

        // ========================================
        // 13. GET DETAILED ATTENDANCE LOGS
        // ========================================
        case 'attendance_logs':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $date = $_GET['date'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT al.*, p.name as project_name, da.work_site
                FROM attendance_logs al
                LEFT JOIN daily_attendance da ON al.daily_attendance_id = da.id
                LEFT JOIN projects p ON al.project_id = p.id
                WHERE da.employee_id = ? AND da.attendance_date = ?
                ORDER BY al.start_time ASC
            ");
            $stmt->execute([$employee['id'], $date]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendSuccess('Attendance logs retrieved', [
                'date' => $date,
                'logs' => $logs,
                'total' => count($logs)
            ]);
            break;

        // ========================================
        // 14. GET MONTHLY ATTENDANCE SUMMARY
        // ========================================
        case 'attendance_summary':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $month = $_GET['month'] ?? date('Y-m');

            // Count attendance statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
                    SUM(COALESCE(working_hours, 0)) as total_hours
                FROM daily_attendance
                WHERE employee_id = ?
                AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
            ");
            $stmt->execute([$employee['id'], $month]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            sendSuccess('Monthly attendance summary', [
                'month' => $month,
                'summary' => $summary
            ]);
            break;

        // ========================================
        // 15. UPDATE PROFILE (Change Password)
        // ========================================
        case 'update_password':
            if ($requestMethod !== 'POST') {
                sendError('Method not allowed. Use POST', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $old_password = $requestData['old_password'] ?? '';
            $new_password = $requestData['new_password'] ?? '';

            if (empty($old_password) || empty($new_password)) {
                sendError('Old password and new password are required', 400);
            }

            // Verify old password
            $stmt = $pdo->prepare("SELECT password FROM employees WHERE id = ?");
            $stmt->execute([$employee['id']]);
            $current_password = $stmt->fetchColumn();

            if (!password_verify($old_password, $current_password)) {
                sendError('Old password is incorrect', 401);
            }

            // Update password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $employee['id']]);

            sendSuccess('Password updated successfully');
            break;

        // ========================================
        // 16. GET ADVANCE PAYMENTS
        // ========================================
        case 'advance_payments':
            if ($requestMethod !== 'GET') {
                sendError('Method not allowed. Use GET', 405);
            }

            $token = getBearerToken();
            if (!$token) {
                sendError('Authorization token required', 401);
            }

            $employee = validateToken($pdo, $token);
            if (!$employee) {
                sendError('Invalid or expired token', 401);
            }

            $limit = $_GET['limit'] ?? 50;

            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM advance_payments 
                    WHERE employee_id = ? 
                    ORDER BY advance_date DESC 
                    LIMIT ?
                ");
                $stmt->execute([$employee['id'], intval($limit)]);
                $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate total advance
                $stmt = $pdo->prepare("
                    SELECT SUM(amount) as total_advance
                    FROM advance_payments
                    WHERE employee_id = ?
                ");
                $stmt->execute([$employee['id']]);
                $total = $stmt->fetch(PDO::FETCH_ASSOC);

                sendSuccess('Advance payments retrieved', [
                    'advances' => $advances,
                    'total_advance' => $total['total_advance'] ?? 0,
                    'count' => count($advances)
                ]);
            } catch (PDOException $e) {
                sendError('Table not found or database error', 500);
            }
            break;

        // ========================================
        // DEFAULT - Invalid Endpoint
        // ========================================
        default:
            sendError('Invalid API endpoint', 404);
            break;
    }
} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
