<?php
// Database configuration
$host = 'localhost';
$dbname = 'garage_management';
$username = 'root';
$password = '';

// Global PDO variable
$pdo = null;

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database connection failed: " . $e->getMessage());
    die("Σφάλμα σύνδεσης με τη βάση δεδομένων. Παρακαλώ επικοινωνήστε με τον διαχειριστή.");
}

// Helper functions
function executeQuery($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        throw new Exception("Σφάλμα κατά την εκτέλεση του ερωτήματος.");
    }
}

function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetch();
}

function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt->fetchAll();
}

function getLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

function beginTransaction() {
    global $pdo;
    return $pdo->beginTransaction();
}

function commit() {
    global $pdo;
    return $pdo->commit();
}

function rollback() {
    global $pdo;
    return $pdo->rollBack();
}

// Test connection function
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Initialize connection test
if (!testDatabaseConnection()) {
    error_log("Database connection test failed");
    die("Δεν είναι δυνατή η σύνδεση με τη βάση δεδομένων.");
}
?>