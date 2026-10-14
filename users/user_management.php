<?php
session_start();

// ΠΡΟΣΩΡΙΝΟ - Προσομοίωση σύνδεσης ως γραμματέας
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'secretary';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Secretary';

require_once '../config/database.php';

// Σχολίασε το authentication check προσωρινά
/*
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header('Location: ../auth/login.php');
    exit();
}
*/

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "Η κατάσταση του χρήστη ενημερώθηκε επιτυχώς.";
                break;
                
            case 'delete_user':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "Ο χρήστης διαγράφηκε επιτυχώς.";
                break;
                
            case 'update_user':
                $username = $_POST['username'];
                $email = $_POST['email'];
                $first_name = $_POST['first_name'];
                $last_name = $_POST['last_name'];
                $id_number = $_POST['id_number'];
                
                // Check for duplicate username/email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Το όνομα χρήστη ή το email υπάρχει ήδη.");
                }
                
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, id_number = ? WHERE id = ?");
                $stmt->execute([$username, $email, $first_name, $last_name, $id_number, $user_id]);
                
                $_SESSION['success'] = "Τα στοιχεία του χρήστη ενημερώθηκαν επιτυχώς.";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: user_management.php');
    exit();
}

// Get search parameters
$search_username = $_GET['search_username'] ?? '';
$search_lastname = $_GET['search_lastname'] ?? '';
$search_afm = $_GET['search_afm'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($search_username)) {
    $where_conditions[] = "u.username LIKE ?";
    $params[] = "%{$search_username}%";
}

if (!empty($search_lastname)) {
    $where_conditions[] = "u.last_name LIKE ?";
    $params[] = "%{$search_lastname}%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Simple query - μόνο από τον πίνακα users
$query = "
    SELECT u.*,
           CASE 
               WHEN u.role = 'customer' THEN CONCAT(u.first_name, ' ', u.last_name, ' (Πελάτης)')
               WHEN u.role = 'mechanic' THEN CONCAT(u.first_name, ' ', u.last_name, ' (Μηχανικός)')
               WHEN u.role = 'secretary' THEN CONCAT(u.first_name, ' ', u.last_name, ' (Γραμματέας)')
           END as display_name
    FROM users u
    {$where_clause}
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Χρηστών - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .table th { background-color: #f8f9fa; }
        .search-form { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-2">
                <?php include '../includes/sidebar.php'; ?>
            </div>
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-people-fill"></i> Διαχείριση Χρηστών</h2>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search Form -->
                <form method="GET" class="search-form">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="search_username" class="form-label">Όνομα χρήστη</label>
                            <input type="text" class="form-control" id="search_username" name="search_username" 
                                   value="<?php echo htmlspecialchars($search_username); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="search_lastname" class="form-label">Επίθετο</label>
                            <input type="text" class="form-control" id="search_lastname" name="search_lastname" 
                                   value="<?php echo htmlspecialchars($search_lastname); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="role_filter" class="form-label">Ρόλος</label>
                            <select class="form-control" id="role_filter" name="role_filter">
                                <option value="">Όλοι</option>
                                <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Πελάτης</option>
                                <option value="mechanic" <?php echo $role_filter === 'mechanic' ? 'selected' : ''; ?>>Μηχανικός</option>
                                <option value="secretary" <?php echo $role_filter === 'secretary' ? 'selected' : ''; ?>>Γραμματέας</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Αναζήτηση
                                </button>
                                <a href="user_management.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Επαναφορά
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Λίστα Χρηστών (<?php echo count($users); ?> χρήστες)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people" style="font-size: 3rem; color: #ccc;"></i>
                                <p class="mt-3 text-muted">Δεν βρέθηκαν χρήστες με τα κριτήρια αναζήτησης.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Όνομα χρήστη</th>
                                            <th>Ονοματεπώνυμο</th>
                                            <th>Email</th>
                                            <th>Ρόλος</th>
                                            <th>ΑΤ</th>
                                            <th>Κατάσταση</th>
                                            <th>Ημ/νία εγγραφής</th>
                                            <th>Ενέργειες</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php
                                                    $role_labels = [
                                                        'customer' => '<span class="badge bg-info">Πελάτης</span>',
                                                        'mechanic' => '<span class="badge bg-warning">Μηχανικός</span>',
                                                        'secretary' => '<span class="badge bg-success">Γραμματέας</span>'
                                                    ];
                                                    echo $role_labels[$user['role']];
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['id_number'] ?? ''); ?></td>
                                                <td>
                                                    <span class="<?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <i class="bi bi-circle-fill"></i>
                                                        <?php echo $user['is_active'] ? 'Ενεργός' : 'Ανενεργός'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-info" 
                                                                onclick="viewUser(<?php echo $user['id']; ?>)"
                                                                title="Προβολή">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editUser(<?php echo $user['id']; ?>)"
                                                                title="Επεξεργασία">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" 
                                                                    class="btn btn-sm <?php echo $user['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                                                    title="<?php echo $user['is_active'] ? 'Απενεργοποίηση' : 'Ενεργοποίηση'; ?>">
                                                                <i class="bi <?php echo $user['is_active'] ? 'bi-pause-fill' : 'bi-play-fill'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <?php if ($user['role'] !== 'secretary'): ?>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')"
                                                                    title="Διαγραφή">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Στοιχεία Χρήστη</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userViewContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Επεξεργασία Χρήστη</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <div class="modal-body" id="userEditContent">
                        <!-- Content loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                        <button type="submit" class="btn btn-primary">Αποθήκευση</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewUser(userId) {
            document.getElementById('userViewContent').innerHTML = '<div class="text-center"><i class="bi bi-hourglass-split"></i> Φόρτωση...</div>';
            new bootstrap.Modal(document.getElementById('viewUserModal')).show();
            
            // Simple user info display (since AJAX files might not work)
            setTimeout(() => {
                document.getElementById('userViewContent').innerHTML = `
                    <div class="alert alert-info">
                        <h6>User ID: ${userId}</h6>
                        <p>Τα πλήρη στοιχεία θα εμφανίζονται όταν δημιουργηθούν τα AJAX αρχεία.</p>
                    </div>
                `;
            }, 500);
        }

        function editUser(userId) {
            document.getElementById('userEditContent').innerHTML = '<div class="text-center"><i class="bi bi-hourglass-split"></i> Φόρτωση...</div>';
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
            
            // Simple edit form (since AJAX files might not work)
            setTimeout(() => {
                document.getElementById('userEditContent').innerHTML = `
                    <div class="alert alert-info">
                        <h6>Edit User ID: ${userId}</h6>
                        <p>Η φόρμα επεξεργασίας θα εμφανίζεται όταν δημιουργηθούν τα AJAX αρχεία.</p>
                    </div>
                `;
            }, 500);
        }

        function deleteUser(userId, userName) {
            if (confirm(`Είστε βέβαιοι ότι θέλετε να διαγράψετε τον χρήστη "${userName}"; Η ενέργεια αυτή δεν μπορεί να αναιρεθεί.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>