<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user info for display
$current_user = null;
if (isset($_SESSION['role'])) {
    $current_user = [
        'role' => $_SESSION['role'],
        'name' => ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')
    ];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Δεν επιτρέπεται η πρόσβαση - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .error-description {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .user-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .role-badge {
            background: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <i class="bi bi-shield-exclamation error-icon"></i>
            
            <h1 class="error-title">403</h1>
            <h2 class="h4 mb-3">Δεν επιτρέπεται η πρόσβαση</h2>
            
            <p class="error-description">
                Δεν έχετε τα απαραίτητα δικαιώματα για να προσπελάσετε αυτή τη σελίδα. 
                Παρακαλώ επικοινωνήστε με τον διαχειριστή αν πιστεύετε ότι πρόκειται για λάθος.
            </p>
            
            <?php if ($current_user): ?>
                <div class="user-info">
                    <div class="mb-2">
                        <strong>Τρέχων χρήστης:</strong> <?php echo htmlspecialchars($current_user['name']); ?>
                    </div>
                    <div>
                        <strong>Ρόλος:</strong> 
                        <span class="role-badge">
                            <?php 
                            $role_names = [
                                'secretary' => 'Γραμματέας',
                                'customer' => 'Πελάτης',
                                'mechanic' => 'Μηχανικός'
                            ];
                            echo $role_names[$current_user['role']] ?? ucfirst($current_user['role']);
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div>
                <a href="javascript:history.back()" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-2"></i>Πίσω
                </a>
                
                <?php if ($current_user): ?>
                    <a href="<?php 
                        switch($current_user['role']) {
                            case 'secretary':
                                echo '/garage_management/users/secretary_dashboard.php';
                                break;
                            case 'customer':
                                echo '/garage_management/customers/dashboard.php';
                                break;
                            case 'mechanic':
                                echo '/garage_management/mechanics/dashboard.php';
                                break;
                            default:
                                echo '/garage_management/login.php';
                        }
                    ?>" class="btn-home">
                        <i class="bi bi-house me-2"></i>Αρχική Σελίδα
                    </a>
                <?php else: ?>
                    <a href="/garage_management/login.php" class="btn-home">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Σύνδεση
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>