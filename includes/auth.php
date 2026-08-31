<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt to log in a user
 */
function attempt_login($pdo, $username, $password)
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // regenerate session id for security
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['employee_id'] = $user['employee_id'];
        $_SESSION['logged_in'] = true;

        // Update last_active
        $upd = $pdo->prepare("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = ?");
        $upd->execute([$user['id']]);

        return true;
    }
    return false;
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    $loggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    if ($loggedIn) {
        // Optimistic update: only update tracking every 5 minutes to save writes
        if (!isset($_SESSION['last_active_update']) || (time() - $_SESSION['last_active_update'] > 300)) {
            global $pdo; // Ensure $pdo is available or pass it
            if (isset($pdo) && isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['last_active_update'] = time();
            }
        }
    }
    return $loggedIn;
}

/**
 * Require specific role(s) to access page
 */
function require_role($roles)
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    // specific role or superadmin usually has access
    if (!in_array($_SESSION['role'], $roles) && $_SESSION['role'] !== 'superadmin') {
        // Redirect to unauthorized page or dashboard with error
        // For now, just fatal error or redirect
        header('Location: index.php?error=unauthorized');
        exit;
    }
}

/**
 * Get current user data
 */
function current_user()
{
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'employee_id' => $_SESSION['employee_id'] ?? null
    ];
}
