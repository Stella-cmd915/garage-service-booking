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

$success_message = '';
$error_message = '';

// Handle actions
if ($_POST && isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    
    if ($_POST['action'] === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_message = "Ο χρήστης ενεργοποιήθηκε επιτυχώς.";
        
    } elseif ($_POST['action'] === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_message = "Ο χρήστης απενεργοποιήθηκε επιτυχώς.";
        
    } elseif ($_POST['action'] === 'delete') {
        // Delete user and related records
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_message = "Ο χρήστης διαγράφηκε επιτυχώς.";
        
    } elseif ($_POST['action'] === 'reset_password') {
        $new_password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        $success_message = "Ο κωδικός επαναφέρθηκε σε: password";
    }
}

// Get search filters
$search_name = $_GET['search_name'] ?? '';
$search_email = $_GET['search_email'] ?? '';
$filter_role = $_GET['role'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search_name)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search_name%";
    $params[] = "%$search_name%";
}

if (!empty($search_email)) {
    $where_conditions[] = "u.email LIKE ?";
    $params[] = "%$search_email%";
}

if (!empty($filter_role)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $filter_role;
}

$where_clause = implode(" AND ", $where_conditions);

// Get users with pagination
$page = $_GET['page'] ?? 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT u.*, 
           c.tax_number, c.address, m.specialty
    FROM users u
    LEFT JOIN customers c ON u.id = c.user_id
    LEFT JOIN mechanics m ON u.id = m.user_id
    WHERE $where_clause
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where_clause");
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Χρηστών - AutoCare</title>
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
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .avatar-customer { background-color: #28a745; }
        .avatar-secretary { background-color: #dc3545; }
        .avatar-mechanic { background-color: #fd7e14; }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
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
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-arrow-left me-1"></i>Πίσω στο Dashboard
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Αποσύνδεση
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid main-content">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-users me-2"></i>Διαχείριση Χρηστών
                </h4>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Νέος Χρήστης
                </a>
            </div>
            
            <div class="card-body">
                <!-- Search Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Όνομα/Επίθετο</label>
                                <input type="text" class="form-control" name="search_name" 
                                       value="<?php echo htmlspecialchars($search_name); ?>" 
                                       placeholder="Αναζήτηση...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="search_email" 
                                       value="<?php echo htmlspecialchars($search_email); ?>" 
                                       placeholder="Αναζήτηση...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ρόλος</label>
                                <select class="form-control" name="role">
                                    <option value="">Όλοι</option>
                                    <option value="customer" <?php echo $filter_role === 'customer' ? 'selected' : ''; ?>>Πελάτες</option>
                                    <option value="secretary" <?php echo $filter_role === 'secretary' ? 'selected' : ''; ?>>Γραμματείς</option>
                                    <option value="mechanic" <?php echo $filter_role === 'mechanic' ? 'selected' : ''; ?>>Μηχανικοί</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Αναζήτηση
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users List -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Χρήστης</th>
                                <th>Στοιχεία Επικοινωνίας</th>
                                <th>Ρόλος</th>
                                <th>Κατάσταση</th>
                                <th>Επιπλέον Πληροφορίες</th>
                                <th>Ημ/νία Εγγραφής</th>
                                <th>Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-2x mb-2"></i><br>
                                        Δεν βρέθηκαν χρήστες
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar avatar-<?php echo $user['role']; ?> me-3">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong><br>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small><br>
                                                    <small class="text-muted">ΑΤ: <?php echo htmlspecialchars($user['id_number']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-envelope text-primary me-1"></i>
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                            <?php if ($user['phone'] || $user['customer_phone']): ?>
                                            <div class="mt-1">
                                                <i class="fas fa-phone text-success me-1"></i>
                                                <?php echo htmlspecialchars($user['phone'] ?: $user['customer_phone']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $role_badges = [
                                                'customer' => '<span class="badge bg-success">Πελάτης</span>',
                                                'secretary' => '<span class="badge bg-danger">Γραμματέας</span>',
                                                'mechanic' => '<span class="badge bg-warning">Μηχανικός</span>'
                                            ];
                                            echo $role_badges[$user['role']];
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i>Ενεργός
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-pause-circle me-1"></i>Ανενεργός
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] === 'customer'): ?>
                                                <?php if ($user['tax_number']): ?>
                                                    <small><strong>ΑΦΜ:</strong> <?php echo htmlspecialchars($user['tax_number']); ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($user['address']): ?>
                                                    <small><strong>Διεύθυνση:</strong> <?php echo htmlspecialchars(substr($user['address'], 0, 30) . (strlen($user['address']) > 30 ? '...' : '')); ?></small>
                                                <?php endif; ?>
                                            <?php elseif ($user['role'] === 'mechanic' && $user['specialty']): ?>
                                                <small><strong>Ειδικότητα:</strong> <?php echo htmlspecialchars($user['specialty']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button class="btn btn-outline-info" onclick="viewUser(<?php echo $user['id']; ?>)" title="Προβολή">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <button class="btn btn-outline-warning" onclick="editUser(<?php echo $user['id']; ?>)" title="Επεξεργασία">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <?php if ($user['is_active']): ?>
                                                    <button class="btn btn-outline-secondary" onclick="toggleStatus(<?php echo $user['id']; ?>, 'deactivate')" title="Απενεργοποίηση">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success" onclick="toggleStatus(<?php echo $user['id']; ?>, 'activate')" title="Ενεργοποίηση">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-outline-primary" onclick="resetPassword(<?php echo $user['id']; ?>)" title="Επαναφορά Κωδικού">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                
                                                <?php if ($user['username'] !== 'admin'): ?>
                                                <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')" title="Διαγραφή">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search_name=<?php echo urlencode($search_name); ?>&search_email=<?php echo urlencode($search_email); ?>&role=<?php echo urlencode($filter_role); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>

                <div class="row mt-4">
                    <div class="col-12 text-muted text-center">
                        Σύνολο χρηστών: <?php echo $total_users; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewUser(userId) {
            // Placeholder for view user functionality
            alert('Λειτουργία προβολής χρήστη - User ID: ' + userId);
        }
        
        function editUser(userId) {
            // Redirect to edit user page
            window.location.href = 'edit_user.php?id=' + userId;
        }
        
        function toggleStatus(userId, action) {
            const actionText = action === 'activate' ? 'ενεργοποίηση' : 'απενεργοποίηση';
            if (confirm(`Είστε σίγουροι για την ${actionText} του χρήστη;`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = action;
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resetPassword(userId) {
            if (confirm('Θα γίνει επαναφορά του κωδικού σε "password". Συνεχεία;')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reset_password';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteUser(userId, userName) {
            if (confirm(`Είστε σίγουροι ότι θέλετε να διαγράψετε τον χρήστη "${userName}"?\n\nΑυτή η ενέργεια δεν μπορεί να αναιρεθεί!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>