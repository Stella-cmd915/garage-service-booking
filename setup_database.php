<?php
// Complete database setup script

$host = 'localhost';
$db_username = 'root';
$db_password = '';
$dbname = 'garage_management';

echo "<h2>Database Setup</h2>";

try {
    // Connect to MySQL (without specifying database first)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connected to MySQL server</p>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    echo "<p>✅ Database '$dbname' created/exists</p>";
    
    // Use the database
    $pdo->exec("USE $dbname");
    echo "<p>✅ Using database '$dbname'</p>";
    
    // Drop existing users table if it exists (fresh start)
    $pdo->exec("DROP TABLE IF EXISTS users");
    echo "<p>✅ Dropped old users table</p>";
    
    // Create users table
    $createUsersTable = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('customer', 'secretary', 'mechanic') NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            id_number VARCHAR(20) UNIQUE NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    
    $pdo->exec($createUsersTable);
    echo "<p>✅ Created users table</p>";
    
    // Create the admin user with exactly the credentials you want
    $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, first_name, last_name, id_number, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        'admin',
        'admin@garage.com',
        $hashedPassword,
        'secretary',
        'Administrator',
        'System',
        'ADM001',
        1
    ]);
    
    echo "<p>✅ Created admin user</p>";
    
    // Verify the user was created correctly
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ SUCCESS! Admin user created successfully</h3>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> password</p>";
        echo "<p><strong>Role:</strong> " . $user['role'] . "</p>";
        echo "<p><strong>Active:</strong> " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
        echo "</div>";
        
        // Test password verification
        if (password_verify('password', $user['password'])) {
            echo "<p style='color: green;'>✅ Password verification test: PASSED</p>";
        } else {
            echo "<p style='color: red;'>❌ Password verification test: FAILED</p>";
        }
    }
    
    // Create other necessary tables
    echo "<h3>Creating additional tables...</h3>";
    
    // Customers table
    $pdo->exec("DROP TABLE IF EXISTS customers");
    $pdo->exec("
        CREATE TABLE customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            vat_number VARCHAR(20),
            address TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Created customers table</p>";
    
    // Mechanics table
    $pdo->exec("DROP TABLE IF EXISTS mechanics");
    $pdo->exec("
        CREATE TABLE mechanics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            specialty VARCHAR(100),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Created mechanics table</p>";
    
    // Vehicles table
    $pdo->exec("DROP TABLE IF EXISTS vehicles");
    $pdo->exec("
        CREATE TABLE vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT,
            serial_number VARCHAR(50) UNIQUE NOT NULL,
            model VARCHAR(50) NOT NULL,
            brand VARCHAR(50) NOT NULL,
            type ENUM('passenger', 'truck', 'bus') NOT NULL,
            fuel_type ENUM('electric', 'diesel', 'lpg', 'hybrid') NOT NULL,
            doors INT NOT NULL,
            wheels INT NOT NULL,
            production_date DATE NOT NULL,
            acquisition_year YEAR NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Created vehicles table</p>";
    
    // Appointments table
    $pdo->exec("DROP TABLE IF EXISTS appointments");
    $pdo->exec("
        CREATE TABLE appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT,
            vehicle_id INT,
            mechanic_id INT,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            reason ENUM('repair', 'service') NOT NULL,
            problem_description TEXT,
            status ENUM('created', 'in_progress', 'completed', 'cancelled') DEFAULT 'created',
            total_cost DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON DELETE SET NULL
        )
    ");
    echo "<p>✅ Created appointments table</p>";
    
    // Services table
    $pdo->exec("DROP TABLE IF EXISTS services");
    $pdo->exec("
        CREATE TABLE services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT,
            description TEXT NOT NULL,
            materials TEXT,
            completion_time TIME,
            cost DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
        )
    ");
    echo "<p>✅ Created services table</p>";
    
    echo "<div style='background: #cff4fc; padding: 20px; border: 1px solid #b6effb; border-radius: 10px; margin: 20px 0;'>";
    echo "<h2>🎉 SETUP COMPLETE!</h2>";
    echo "<p><strong>Database:</strong> garage_management</p>";
    echo "<p><strong>Tables created:</strong> users, customers, mechanics, vehicles, appointments, services</p>";
    echo "<p><strong>Admin user created:</strong> admin / password</p>";
    echo "<br>";
    echo "<h3>Next steps:</h3>";
    echo "<ol>";
    echo "<li><a href='login.php'>Go to Login Page</a></li>";
    echo "<li>Use: <strong>admin</strong> / <strong>password</strong></li>";
    echo "<li>You should be able to login successfully now!</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h4>Common fixes:</h4>";
    echo "<ul>";
    echo "<li>Make sure XAMPP is running (Apache + MySQL)</li>";
    echo "<li>Check if MySQL is accessible at localhost</li>";
    echo "<li>Verify MySQL root user has no password</li>";
    echo "</ul>";
    echo "</div>";
}
?>