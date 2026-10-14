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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password)) {
        $error = 'Παρακαλώ συμπληρώστε όλα τα πεδία.';
    } else {
        // Simple query - just get user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['is_active'] = $user['is_active'];
            $_SESSION['last_activity'] = time();
            
            // Redirect based on role
            switch ($user['role']) {
                case 'secretary':
                    header('Location: ../index.php?login=success&role=secretary');
                    break;
                case 'customer':
                    header('Location: ../index.php?login=success&role=customer');
                    break;
                case 'mechanic':
                    header('Location: ../index.php?login=success&role=mechanic');
                    break;
                default:
                    header('Location: ../index.php?login=success');
            }
            exit();
        } else {
            $error = 'Λάθος όνομα χρήστη ή κωδικός πρόσβασης.';
        }
    }
}

$pageTitle = 'Σύνδεση - Μηχανουργείο AutoCare';
include '../includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class="fas fa-sign-in-alt"></i> Σύνδεση</h1>
                <p>Συνδεθείτε στον λογαριασμό σας</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" novalidate>
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        Όνομα Χρήστη
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                        required
                        autocomplete="username"
                        placeholder="Εισάγετε το όνομα χρήστη σας"
                    >
                    <span class="field-error" id="username-error"></span>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Κωδικός Πρόσβασης
                    </label>
                    <div class="password-input">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            autocomplete="current-password"
                            placeholder="Εισάγετε τον κωδικό σας"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </button>
                    </div>
                    <span class="field-error" id="password-error"></span>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember_me" id="remember_me">
                        <span class="checkmark"></span>
                        Θυμήσου με
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-sign-in-alt"></i>
                    Σύνδεση
                </button>
            </form>
            
            <div class="auth-links">
                <div class="divider">
                    <span>ή</span>
                </div>
                
                <p>Δεν έχετε λογαριασμό;</p>
                <a href="register.php" class="btn btn-secondary btn-full">
                    <i class="fas fa-user-plus"></i>
                    Εγγραφή
                </a>
                
                <div class="help-links">
                    <a href="../index.php">
                        <i class="fas fa-home"></i>
                        Αρχική Σελίδα
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Demo Accounts Info -->
        <div class="demo-accounts">
            <h3><i class="fas fa-info-circle"></i> Δοκιμαστικοί Λογαριασμοί</h3>
            <div class="demo-grid">
                <div class="demo-card">
                    <h4><i class="fas fa-user-cog"></i> Γραμματέας</h4>
                    <p><strong>Username:</strong> admin</p>
                    <p><strong>Password:</strong> admin123</p>
                </div>
                <div class="demo-card">
                    <h4><i class="fas fa-user"></i> Πελάτης</h4>
                    <p><strong>Username:</strong> jpapa</p>
                    <p><strong>Password:</strong> customer123</p>
                </div>
                <div class="demo-card">
                    <h4><i class="fas fa-wrench"></i> Μηχανικός</h4>
                    <p><strong>Username:</strong> npetr</p>
                    <p><strong>Password:</strong> mechanic123</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.auth-container {
    max-width: 500px;
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

.auth-form .form-group {
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
.auth-form input[type="password"] {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
    box-sizing: border-box;
}

.auth-form input:focus {
    outline: none;
    border-color: #2c5aa0;
    box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
}

.password-input {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    padding: 0.5rem;
}

.password-toggle:hover {
    color: #2c5aa0;
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
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.btn-full {
    width: 100%;
    padding: 0.8rem;
    font-size: 1.1rem;
    font-weight: 600;
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

.demo-accounts {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    border-left: 4px solid #2c5aa0;
}

.demo-accounts h3 {
    color: #2c5aa0;
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.demo-grid {
    display: grid;
    gap: 1rem;
}

.demo-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}

.demo-card h4 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.demo-card p {
    margin: 0.2rem 0;
    font-size: 0.8rem;
    color: #666;
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

@media (max-width: 768px) {
    .auth-container {
        padding: 0 0.5rem;
    }
    
    .auth-card {
        padding: 1.5rem;
    }
    
    .demo-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.auth-form');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    
    // Form submission
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });
    
    function validateForm() {
        let isValid = true;
        
        if (usernameInput.value.trim().length === 0) {
            isValid = false;
        }
        
        if (passwordInput.value.length === 0) {
            isValid = false;
        }
        
        return isValid;
    }
});

// Password visibility toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const eye = document.getElementById(inputId + '-eye');
    
    if (input.type === 'password') {
        input.type = 'text';
        eye.classList.remove('fa-eye');
        eye.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        eye.classList.remove('fa-eye-slash');
        eye.classList.add('fa-eye');
    }
}
</script>

<?php include '../includes/footer.php'; ?>