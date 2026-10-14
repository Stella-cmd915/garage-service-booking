<?php
require_once '../config/session.php';
require_once '../config/database.php';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    switch ($role) {
        case 'secretary':
            header('Location: ../users/secretary_dashboard.php');
            break;
        case 'customer':
            header('Location: ../users/customer_dashboard.php');
            break;
        case 'mechanic':
            header('Location: ../users/mechanic_dashboard.php');
            break;
    }
    exit();
}

$error = '';
$success = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture form data for repopulation on error
    $formData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'id_number' => trim($_POST['id_number'] ?? ''),
        'role' => $_POST['role'] ?? '',
        'afm' => trim($_POST['afm'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'specialty' => trim($_POST['specialty'] ?? '')
    ];
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    // Basic validation
    if (empty($formData['first_name'])) $errors[] = 'Το όνομα είναι υποχρεωτικό.';
    if (empty($formData['last_name'])) $errors[] = 'Το επώνυμο είναι υποχρεωτικό.';
    if (empty($formData['username'])) $errors[] = 'Το όνομα χρήστη είναι υποχρεωτικό.';
    if (empty($formData['email'])) $errors[] = 'Το email είναι υποχρεωτικό.';
    if (empty($formData['id_number'])) $errors[] = 'Ο αριθμός ταυτότητας είναι υποχρεωτικός.';
    if (empty($formData['role'])) $errors[] = 'Ο ρόλος είναι υποχρεωτικός.';
    if (empty($password)) $errors[] = 'Ο κωδικός πρόσβασης είναι υποχρεωτικός.';
    
    // Username validation
    if (strlen($formData['username']) < 3) {
        $errors[] = 'Το όνομα χρήστη πρέπει να έχει τουλάχιστον 3 χαρακτήρες.';
    }
    
    // Email validation
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Παρακαλώ εισάγετε έγκυρο email.';
    }
    
    // Password validation
    if (strlen($password) < 6) {
        $errors[] = 'Ο κωδικός πρόσβασης πρέπει να έχει τουλάχιστον 6 χαρακτήρες.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Οι κωδικοί πρόσβασης δεν ταιριάζουν.';
    }
    
    // Role-specific validation
    if ($formData['role'] == 'customer') {
        if (empty($formData['afm'])) $errors[] = 'Το ΑΦΜ είναι υποχρεωτικό για πελάτες.';
        if (empty($formData['address'])) $errors[] = 'Η διεύθυνση είναι υποχρεωτική για πελάτες.';
        
        // AFM validation (simple check for 9 digits)
        if (!empty($formData['afm']) && !preg_match('/^\d{9}$/', $formData['afm'])) {
            $errors[] = 'Το ΑΦΜ πρέπει να αποτελείται από 9 ψηφία.';
        }
    }
    
    if ($formData['role'] == 'mechanic') {
        if (empty($formData['specialty'])) $errors[] = 'Η ειδικότητα είναι υποχρεωτική για μηχανικούς.';
    }
    
    // Secretary role is not allowed for public registration
    if ($formData['role'] == 'secretary') {
        $errors[] = 'Δεν επιτρέπεται η δημόσια εγγραφή γραμματέων.';
    }
    
    // Check for existing username and email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$formData['username'], $formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Το όνομα χρήστη ή το email υπάρχει ήδη.';
        }
        
        // Check for existing ID number
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$formData['id_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'Ο αριθμός ταυτότητας υπάρχει ήδη.';
        }
        
        // Check for existing AFM if customer
        if ($formData['role'] == 'customer' && !empty($formData['afm'])) {
            // Skip AFM check for now since column doesn't exist
            $stmt = $pdo->prepare("SELECT user_id FROM customers WHERE tax_number = ?");
            $stmt->execute([$formData['afm']]);
            if ($stmt->fetch()) {
                $errors[] = 'Το ΑΦΜ υπάρχει ήδη.';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, id_number, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $stmt->execute([
                $formData['username'],
                $formData['email'],
                $hashed_password,
                $formData['first_name'],
                $formData['last_name'],
                $formData['id_number'],
                $formData['role']
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert role-specific data
            if ($formData['role'] == 'customer') {
                $stmt = $pdo->prepare("
                INSERT INTO customers (user_id, tax_number, address) 
                VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $formData['afm'], $formData['address']]);
            } elseif ($formData['role'] == 'mechanic') {
                $stmt = $pdo->prepare("
                    INSERT INTO mechanics (user_id, specialty, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$user_id, $formData['afm'], $formData['address']]);
            }
            
            $pdo->commit();
            
            $success = 'Η εγγραφή σας ολοκληρώθηκε επιτυχώς! Ο λογαριασμός σας είναι ανενεργός και θα ενεργοποιηθεί από γραμματέα.';
            $formData = []; // Clear form data on success
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Σφάλμα κατά την εγγραφή. Παρακαλώ δοκιμάστε ξανά.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$pageTitle = 'Εγγραφή - Μηχανουργείο AutoCare';
include '../includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class="fas fa-user-plus"></i> Εγγραφή</h1>
                <p>Δημιουργήστε νέο λογαριασμό</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <div style="margin-top: 1rem;">
                        <a href="login.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-sign-in-alt"></i>
                            Συνέχεια στη Σύνδεση
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" id="registerForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Βασικά Στοιχεία</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">
                                <i class="fas fa-user"></i>
                                Όνομα *
                            </label>
                            <input 
                                type="text" 
                                id="first_name" 
                                name="first_name" 
                                value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                                required
                                placeholder="Εισάγετε το όνομά σας"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">
                                <i class="fas fa-user"></i>
                                Επώνυμο *
                            </label>
                            <input 
                                type="text" 
                                id="last_name" 
                                name="last_name" 
                                value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                                required
                                placeholder="Εισάγετε το επώνυμό σας"
                            >
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="id_number">
                            <i class="fas fa-id-card"></i>
                            Αριθμός Ταυτότητας *
                        </label>
                        <input 
                            type="text" 
                            id="id_number" 
                            name="id_number" 
                            value="<?php echo htmlspecialchars($formData['id_number'] ?? ''); ?>"
                            required
                            placeholder="π.χ. ΑΒ123456"
                        >
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="form-section">
                    <h3><i class="fas fa-cog"></i> Στοιχεία Λογαριασμού</h3>
                    
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-at"></i>
                            Όνομα Χρήστη *
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>"
                            required
                            autocomplete="username"
                            placeholder="Επιλέξτε όνομα χρήστη"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email *
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                            required
                            autocomplete="email"
                            placeholder="example@email.com"
                        >
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                Κωδικός Πρόσβασης *
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                autocomplete="new-password"
                                placeholder="Τουλάχιστον 6 χαρακτήρες"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i>
                                Επιβεβαίωση Κωδικού *
                            </label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required
                                autocomplete="new-password"
                                placeholder="Επαναλάβετε τον κωδικό"
                            >
                        </div>
                    </div>
                </div>
                
                <!-- Role Selection -->
                <div class="form-section">
                    <h3><i class="fas fa-users"></i> Ρόλος</h3>
                    
                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-user-tag"></i>
                            Επιλέξτε Ρόλο *
                        </label>
                        <select id="role" name="role" required>
                            <option value="">-- Επιλέξτε Ρόλο --</option>
                            <option value="customer" <?php echo ($formData['role'] ?? '') == 'customer' ? 'selected' : ''; ?>>
                                Πελάτης
                            </option>
                            <option value="mechanic" <?php echo ($formData['role'] ?? '') == 'mechanic' ? 'selected' : ''; ?>>
                                Μηχανικός
                            </option>
                        </select>
                        <div class="field-help">
                            Οι γραμματείς δημιουργούνται μόνο από διαχειριστή
                        </div>
                    </div>
                </div>
                
                <!-- Customer-specific fields -->
                <div class="form-section" id="customer-fields" style="display: none;">
                    <h3><i class="fas fa-user"></i> Στοιχεία Πελάτη</h3>
                    
                    <div class="form-group">
                        <label for="afm">
                            <i class="fas fa-receipt"></i>
                            ΑΦΜ *
                        </label>
                        <input 
                            type="text" 
                            id="afm" 
                            name="afm" 
                            value="<?php echo htmlspecialchars($formData['afm'] ?? ''); ?>"
                            placeholder="9ψήφιο ΑΦΜ"
                            maxlength="9"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="address">
                            <i class="fas fa-map-marker-alt"></i>
                            Διεύθυνση *
                        </label>
                        <textarea 
                            id="address" 
                            name="address" 
                            rows="3"
                            placeholder="Εισάγετε τη διεύθυνσή σας"
                        ><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Mechanic-specific fields -->
                <div class="form-section" id="mechanic-fields" style="display: none;">
                    <h3><i class="fas fa-wrench"></i> Στοιχεία Μηχανικού</h3>
                    
                    <div class="form-group">
                        <label for="specialty">
                            <i class="fas fa-tools"></i>
                            Ειδικότητα *
                        </label>
                        <select id="specialty" name="specialty">
                            <option value="">-- Επιλέξτε Ειδικότητα --</option>
                            <option value="Αρχιμηχανικός" <?php echo ($formData['specialty'] ?? '') == 'Αρχιμηχανικός' ? 'selected' : ''; ?>>
                                Αρχιμηχανικός
                            </option>
                            <option value="Ηλεκτρονικός" <?php echo ($formData['specialty'] ?? '') == 'Ηλεκτρονικός' ? 'selected' : ''; ?>>
                                Ηλεκτρονικός
                            </option>
                            <option value="Μηχανικός Κινητήρων" <?php echo ($formData['specialty'] ?? '') == 'Μηχανικός Κινητήρων' ? 'selected' : ''; ?>>
                                Μηχανικός Κινητήρων
                            </option>
                            <option value="Μηχανικός Φρένων" <?php echo ($formData['specialty'] ?? '') == 'Μηχανικός Φρένων' ? 'selected' : ''; ?>>
                                Μηχανικός Φρένων
                            </option>
                            <option value="Μηχανικός Αναρτήσεων" <?php echo ($formData['specialty'] ?? '') == 'Μηχανικός Αναρτήσεων' ? 'selected' : ''; ?>>
                                Μηχανικός Αναρτήσεων
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span class="checkmark"></span>
                        Συμφωνώ με τους όρους χρήσης και την πολιτική απορρήτου
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-user-plus"></i>
                    Εγγραφή
                </button>
            </form>
            
            <div class="auth-links">
                <div class="divider">
                    <span>ή</span>
                </div>
                
                <p>Έχετε ήδη λογαριασμό;</p>
                <a href="login.php" class="btn btn-secondary btn-full">
                    <i class="fas fa-sign-in-alt"></i>
                    Σύνδεση
                </a>
                
                <div class="help-links">
                    <a href="../index.php">
                        <i class="fas fa-home"></i>
                        Αρχική Σελίδα
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.auth-container {
    max-width: 600px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.auth-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 2rem;
    margin-bottom: 2rem;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.auth-header h1 {
    color: #2c5aa0;
    margin-bottom: 0.5rem;
    font-size: 1.8rem;
}

.auth-header p {
    color: #666;
    font-size: 1rem;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #eee;
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h3 {
    color: #2c5aa0;
    margin-bottom: 1rem;
    font-size: 1.1rem;
    font-weight: 600;
}

.form-section h3 i {
    margin-right: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.auth-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #333;
}

.auth-form label i {
    margin-right: 0.5rem;
    color: #2c5aa0;
}

.auth-form input[type="text"],
.auth-form input[type="email"],
.auth-form input[type="password"],
.auth-form select,
.auth-form textarea {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
    box-sizing: border-box;
}

.auth-form input:focus,
.auth-form select:focus,
.auth-form textarea:focus {
    outline: none;
    border-color: #2c5aa0;
    box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
}

.auth-form textarea {
    resize: vertical;
    min-height: 80px;
}

.field-help {
    font-size: 0.8rem;
    color: #666;
    margin-top: 0.25rem;
    font-style: italic;
}

.checkbox-label {
    display: flex !important;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.checkbox-label input[type="checkbox"] {
    width: auto !important;
    margin-right: 0.5rem;
}

.btn {
    display: inline-block;
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.btn-primary {
    background: #2c5aa0;
    color: white;
}

.btn-primary:hover {
    background: #1e3f73;
    color: white;
    text-decoration: none;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
    text-decoration: none;
}

.btn-full {
    width: 100%;
    padding: 0.8rem;
    font-size: 1.1rem;
    font-weight: 600;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.auth-links {
    text-align: center;
    margin-top: 2rem;
}

.divider {
    margin: 1.5rem 0;
    position: relative;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #ddd;
}

.divider span {
    background: white;
    padding: 0 1rem;
    color: #666;
    position: relative;
}

.help-links {
    margin-top: 1.5rem;
    font-size: 0.9rem;
}

.help-links a {
    color: #2c5aa0;
    text-decoration: none;
    margin: 0 0.5rem;
}

.help-links a:hover {
    text-decoration: underline;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

#customer-fields, #mechanic-fields {
    display: none;
}

@media (max-width: 768px) {
    .auth-container {
        padding: 0 0.5rem;
    }
    
    .auth-card {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const customerFields = document.getElementById('customer-fields');
    const mechanicFields = document.getElementById('mechanic-fields');
    
    function toggleRoleFields() {
        const selectedRole = roleSelect.value;
        
        if (selectedRole === 'customer') {
            customerFields.style.display = 'block';
            mechanicFields.style.display = 'none';
        } else if (selectedRole === 'mechanic') {
            customerFields.style.display = 'none';
            mechanicFields.style.display = 'block';
        } else {
            customerFields.style.display = 'none';
            mechanicFields.style.display = 'none';
        }
    }
    
    // Add event listener
    roleSelect.addEventListener('change', toggleRoleFields);
    
    // Initialize on page load
    toggleRoleFields();
});
</script>

<?php include '../includes/footer.php'; ?>