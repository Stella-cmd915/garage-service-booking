<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/auth.php';

// Ensure user data is loaded
loadUserDataToSession();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Basic validation
        $errors = [];
        if (empty($first_name)) $errors[] = "Το όνομα είναι υποχρεωτικό";
        if (empty($last_name)) $errors[] = "Το επίθετο είναι υποχρεωτικό";
        if (empty($email)) $errors[] = "Το email είναι υποχρεωτικό";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Μη έγκυρο email";
        
        // Check if email exists for other users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Το email χρησιμοποιείται ήδη";
        }
        
        if (empty($errors)) {
            // Update user basic info
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $last_name, $email, $phone, $_SESSION['user_id']]);
            
            // Update customer-specific info if customer
            if (getUserRole() === 'customer' && !empty($_POST['address'])) {
                $address = trim($_POST['address']);
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET address = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$address, $_SESSION['user_id']]);
            }
            
            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            
            setSuccessMessage("Τα στοιχεία σας ενημερώθηκαν επιτυχώς!");
            header('Location: edit_profile.php');
            exit;
        } else {
            setErrorMessage(implode('<br>', $errors));
        }
        
    } catch (Exception $e) {
        setErrorMessage("Σφάλμα κατά την ενημέρωση: " . $e->getMessage());
    }
}

// Get user data
try {
    if (getUserRole() === 'customer') {
        $stmt = $pdo->prepare("
            SELECT u.*, c.address, c.tax_number 
            FROM users u 
            LEFT JOIN customers c ON u.id = c.user_id 
            WHERE u.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    }
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    setErrorMessage("Σφάλμα φόρτωσης δεδομένων: " . $e->getMessage());
    $user = [];
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επεξεργασία Προφίλ - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-person-gear"></i> Επεξεργασία Προφίλ
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php $success = getSuccessMessage(); if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle"></i>
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php $error = getErrorMessage(); if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">Όνομα *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Επίθετο *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Τηλέφωνο</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" 
                                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                                        <div class="form-text">Το username δεν μπορεί να αλλάξει</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="id_number" class="form-label">Αριθμός Ταυτότητας</label>
                                        <input type="text" class="form-control" id="id_number" 
                                               value="<?php echo htmlspecialchars($user['id_number'] ?? ''); ?>" disabled>
                                        <div class="form-text">Το ΑΤ δεν μπορεί να αλλάξει</div>
                                    </div>
                                </div>
                            </div>

                            <?php if (getUserRole() === 'customer'): ?>
                                <hr>
                                <h5 class="text-primary">Πρόσθετα Στοιχεία Πελάτη</h5>
                                
                                <div class="mb-3">
                                    <label for="tax_number" class="form-label">ΑΦΜ</label>
                                    <input type="text" class="form-control" id="tax_number" 
                                           value="<?php echo htmlspecialchars($user['tax_number'] ?? ''); ?>" disabled>
                                    <div class="form-text">Το ΑΦΜ δεν μπορεί να αλλάξει</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Διεύθυνση</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between">
                                <a href="<?php echo getDashboardUrl(); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Ακύρωση
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Αποθήκευση Αλλαγών
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Change Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-key"></i> Αλλαγή Κωδικού
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Για αλλαγή κωδικού επικοινωνήστε με τη γραμματεία.</p>
                        <button class="btn btn-outline-warning" onclick="alert('Επικοινωνήστε με τη γραμματεία για αλλαγή κωδικού')">
                            <i class="bi bi-telephone"></i> Επικοινωνία για Αλλαγή Κωδικού
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>