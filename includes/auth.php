<?php
// auth.php - Centralized authentication and user session management

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // If user data not in session, load from database
    if (!isset($_SESSION['user_data_loaded']) || !$_SESSION['user_data_loaded']) {
        loadUserDataToSession();
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'full_name' => ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''),
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'customer',
        'phone' => $_SESSION['phone'] ?? '',
        'is_active' => $_SESSION['is_active'] ?? 1
    ];
}

// Function to get user's full name safely
function getUserFullName() {
    $user = getCurrentUser();
    if (!$user) return 'Guest';
    
    $firstName = $user['first_name'] ?? '';
    $lastName = $user['last_name'] ?? '';
    
    if (empty($firstName) && empty($lastName)) {
        return $user['username'] ?? 'User';
    }
    
    return trim($firstName . ' ' . $lastName);
}

// Function to get user's first name safely
function getUserFirstName() {
    $user = getCurrentUser();
    return $user['first_name'] ?? $user['username'] ?? 'User';
}

// Function to get user role safely
function getUserRole() {
    $user = getCurrentUser();
    return $user['role'] ?? 'customer';
}

// Function to load user data from database to session
function loadUserDataToSession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        global $pdo;
        
        // If $pdo is not available, require database config
        if (!$pdo) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $stmt = $pdo->prepare("
            SELECT id, username, first_name, last_name, email, role, phone, is_active, created_at
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['role'] = $user['role'] ?? 'customer';
            $_SESSION['phone'] = $user['phone'] ?? '';
            $_SESSION['is_active'] = $user['is_active'];
            $_SESSION['user_data_loaded'] = true;
            return true;
        } else {
            // User not found or inactive, logout
            logout();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error loading user data: " . $e->getMessage());
        // Set fallback data to prevent crashes
        $_SESSION['first_name'] = $_SESSION['first_name'] ?? '';
        $_SESSION['last_name'] = $_SESSION['last_name'] ?? '';
        $_SESSION['role'] = $_SESSION['role'] ?? 'customer';
        $_SESSION['user_data_loaded'] = true;
        return false;
    }
}

// Function to check if user has specific role
function hasRole($role) {
    $userRole = getUserRole();
    if (is_array($role)) {
        return in_array($userRole, $role);
    }
    return $userRole === $role;
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getLoginUrl());
        exit;
    }
}

// Function to require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . getDashboardUrl());
        exit;
    }
}

// Function to get login URL based on current location
function getLoginUrl() {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    
    // Determine the correct path to login based on current location
    if (strpos($currentPath, '/auth/') !== false) {
        return 'login.php';
    } elseif (strpos($currentPath, '/vehicles/') !== false || 
              strpos($currentPath, '/appointments/') !== false ||
              strpos($currentPath, '/reports/') !== false ||
              strpos($currentPath, '/users/') !== false) {
        return '../auth/login.php';
    } else {
        return 'auth/login.php';
    }
}

// Function to get dashboard URL based on user role
function getDashboardUrl() {
    $role = getUserRole();
    
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = '';
    
    // Determine base path
    if (strpos($currentPath, '/vehicles/') !== false || 
        strpos($currentPath, '/appointments/') !== false ||
        strpos($currentPath, '/reports/') !== false ||
        strpos($currentPath, '/users/') !== false) {
        $basePath = '../';
    }
    
    switch ($role) {
        case 'admin':
            return $basePath . 'admin_dashboard.php';
        case 'secretary':
            return $basePath . 'secretary_dashboard.php';
        case 'customer':
            return $basePath . 'customer_dashboard.php';
        default:
            return $basePath . 'index.php';
    }
}

// Function to logout user
function logout() {
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    header('Location: ' . getLoginUrl());
    exit;
}

// Function to refresh user session data
function refreshUserSession() {
    if (isLoggedIn()) {
        $_SESSION['user_data_loaded'] = false;
        loadUserDataToSession();
    }
}

// Function to set success message
function setSuccessMessage($message) {
    $_SESSION['success'] = $message;
}

// Function to set error message  
function setErrorMessage($message) {
    $_SESSION['error'] = $message;
}

// Function to get and clear success message
function getSuccessMessage() {
    if (isset($_SESSION['success'])) {
        $message = $_SESSION['success'];
        unset($_SESSION['success']);
        return $message;
    }
    return null;
}

// Function to get and clear error message
function getErrorMessage() {
    if (isset($_SESSION['error'])) {
        $message = $_SESSION['error'];
        unset($_SESSION['error']);
        return $message;
    }
    return null;
}

// Function to check CSRF token
function checkCSRF($token = null) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    $submittedToken = $token ?? $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

// Function to generate CSRF token
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ΠΡΟΣΘΗΚΗ: Missing functions για roles
function isSecretary() {
    return hasRole('secretary');
}

function isCustomer() {
    return hasRole('customer');  
}

function isMechanic() {
    return hasRole('mechanic');
}

// Auto-load user data if logged in but data not loaded
if (isLoggedIn() && (!isset($_SESSION['user_data_loaded']) || !$_SESSION['user_data_loaded'])) {
    loadUserDataToSession();
}
?>