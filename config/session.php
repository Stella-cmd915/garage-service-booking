<?php
// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.cookie_lifetime', 7200); // 2 hours
    ini_set('session.gc_maxlifetime', 7200);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.use_only_cookies', 1);
    
    session_start();
}

// Define session lifetime constant
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

// Session security functions
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
}

// Authentication helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['username']) && 
           isset($_SESSION['user_role']) &&
           isSessionValid();
}

function isSessionValid() {
    // Check if session has expired
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_LIFETIME) {
            destroySession();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /garage_management/auth/login.php');
        exit();
    }
}

function requireRole($requiredRole) {
    requireLogin();
    
    if ($_SESSION['user_role'] !== $requiredRole) {
        header('Location: /garage_management/index.php?error=unauthorized');
        exit();
    }
}

function requireRoles($allowedRoles) {
    requireLogin();
    
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: /garage_management/index.php?error=unauthorized');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'is_active' => $_SESSION['is_active'] ?? 1
    ];
}

function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

function isSecretary() {
    return hasRole('secretary');
}

function isCustomer() {
    return hasRole('customer');
}

function isMechanic() {
    return hasRole('mechanic');
}

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function hasFlashMessages() {
    return !empty($_SESSION['flash_messages']);
}

// Auto-logout check on each page load
if (isLoggedIn() && !isSessionValid()) {
    setFlashMessage('warning', 'Η συνεδρία σας έληξε. Παρακαλώ συνδεθείτε ξανά.');
    header('Location: /garage_management/auth/login.php');
    exit();
}
?>