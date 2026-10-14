<?php
// includes/auth_check.php - Authentication Middleware

require_once __DIR__ . '/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for valid session and authentication
requireLogin();

// Helper function to check if current user can access secretary functions
function requireSecretary() {
    if (!isSecretary()) {
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
        exit();
    }
}

// Helper function to check if current user can access customer functions  
function requireCustomer() {
    if (!isCustomer()) {
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
        exit();
    }
}

// Helper function to check if current user can access mechanic functions
function requireMechanic() {
    if (!isMechanic()) {
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
        exit();
    }
}

// Helper function to check multiple role access
function requireAnyRole($roles) {
    if (!hasAnyRole($roles)) {
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
        exit();
    }
}
?>