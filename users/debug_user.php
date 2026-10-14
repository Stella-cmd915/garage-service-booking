<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

try {
    require_once '../config/database.php';
    echo "Database included successfully<br>";
    
    // Test if $pdo variable exists
    if (isset($pdo)) {
        echo "PDO object exists<br>";
        
        // Test simple query
        $stmt = $pdo->query("SELECT 1");
        if ($stmt) {
            echo "Database connection works!<br>";
        } else {
            echo "Query failed<br>";
        }
    } else {
        echo "PDO object not found<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "Testing session...<br>";
session_start();
echo "Session started<br>";

echo "Testing includes existence...<br>";
echo "navbar.php exists: " . (file_exists('../includes/navbar.php') ? 'YES' : 'NO') . "<br>";
echo "sidebar.php exists: " . (file_exists('../includes/sidebar.php') ? 'YES' : 'NO') . "<br>";
?>