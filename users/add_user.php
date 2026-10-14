<?php
session_start();

// Check if user is logged in and has secretary role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Location: ../login.php');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'garage_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password_input = $_POST['password'];
    $role = $_POST['role'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $id_number = trim($_POST['id_number']);
    $phone = trim($_POST['phone']) ?: null;
    $address = trim($_POST['address']) ?: null;
    $tax_number = trim($_POST['vat_number']) ?: null;
    $specialty = trim($_POST['specialty']) ?: null;
    
    // Validation
    if (empty($username_input) || empty($email) || empty($password_input) || empty($role) || 
        empty($first_name) || empty($last_name) || empty($id_number)) {
        $error_message = 'Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ? OR id_number = ?");
            $stmt->execute([$username_input, $email, $id_number]);
            
            if ($stmt->fetchColumn() > 0) {
                $error_message = 'Το όνομα χρήστη, email ή αριθμός ταυτότητας υπάρχει ήδη.';
            } else {
                // Hash password
                $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role, first_name, last_name, id_number, phone, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                
                $stmt->execute([
                    $username_input, $email, $hashed_password, $role, 
                    $first_name, $last_name, $id_number, $phone
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insert role-specific data
                if ($role === 'customer') {
                    $stmt = $pdo->prepare("INSERT INTO customers (user_id, tax_number, address, phone) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $tax_number, $address, $phone]);
                } elseif ($role === 'mechanic') {
                    $stmt = $pdo->prepare("INSERT INTO mechanics (user_id, specialty) VALUES (?, ?)");
                    $stmt->execute([$user_id, $specialty]);
                }
                
                $success_message = 'Ο χρήστης δημιουργήθηκε επιτυχώς!';
                
                // Clear form
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error_message = 'Σφάλμα κατά τη δημιουργία του χρήστη: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Νέος Χρήστης - AutoCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .main-content {
            padding-top: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="fas fa-car-side me-2"></i>AutoCare
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Πίσω στο Dashboard
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Αποσύνδεση
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>Προσθήκη Νέου Χρήστη
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <div class="mt-2">
                                <a href="../dashboard.php" class="btn btn-success btn-sm">Πίσω στο Dashboard</a>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="resetForm()">Νέος Χρήστης</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="userForm">
                            <!-- Basic Info -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Βασικά Στοιχεία</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Όνομα Χρήστη *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Κωδικός Πρόσβασης *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="role" class="form-label">Ρόλος *</label>
                                    <select class="form-control" id="role" name="role" required onchange="toggleRoleFields()">
                                        <option value="">Επιλέξτε ρόλο...</option>
                                        <option value="customer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'customer') ? 'selected' : ''; ?>>Πελάτης</option>
                                        <option value="mechanic" <?php echo (isset($_POST['role']) && $_POST['role'] == 'mechanic') ? 'selected' : ''; ?>>Μηχανικός</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Personal Info -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Προσωπικά Στοιχεία</h5>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Όνομα *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Επίθετο *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="id_number" class="form-label">Αριθμός Ταυτότητας *</label>
                                    <input type="text" class="form-control" id="id_number" name="id_number" 
                                           value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Τηλέφωνο</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Customer Fields -->
                            <div id="customerFields" style="display: none;">
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="border-bottom pb-2">Στοιχεία Πελάτη</h5>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="vat_number" class="form-label">ΑΦΜ</label>
                                        <input type="text" class="form-control" id="vat_number" name="vat_number" 
                                               value="<?php echo isset($_POST['vat_number']) ? htmlspecialchars($_POST['vat_number']) : ''; ?>">
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="address" class="form-label">Διεύθυνση</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Mechanic Fields -->
                            <div id="mechanicFields" style="display: none;">
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5 class="border-bottom pb-2">Στοιχεία Μηχανικού</h5>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="specialty" class="form-label">Ειδικότητα</label>
                                        <input type="text" class="form-control" id="specialty" name="specialty" 
                                               placeholder="π.χ. Αρχιμηχανικός, Ηλεκτρονικός, κλπ."
                                               value="<?php echo isset($_POST['specialty']) ? htmlspecialchars($_POST['specialty']) : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg me-2">
                                    <i class="fas fa-save me-2"></i>Αποθήκευση Χρήστη
                                </button>
                                <a href="../dashboard.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Ακύρωση
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const customerFields = document.getElementById('customerFields');
            const mechanicFields = document.getElementById('mechanicFields');
            
            customerFields.style.display = 'none';
            mechanicFields.style.display = 'none';
            
            if (role === 'customer') {
                customerFields.style.display = 'block';
            } else if (role === 'mechanic') {
                mechanicFields.style.display = 'block';
            }
        }
        
        function resetForm() {
            document.getElementById('userForm').reset();
            toggleRoleFields();
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields();
        });
    </script>
</body>
</html>