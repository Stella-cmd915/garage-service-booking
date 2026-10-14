<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is mechanic
if (!isLoggedIn() || getUserRole() !== 'mechanic') {
    header('Location: ../auth/login.php');
    exit;
}

$mechanic_id = $_SESSION['user_id'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $appointment_id = $_POST['appointment_id'];
            $new_status = $_POST['status'];
            $work_notes = $_POST['work_notes'] ?? '';
            
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, work_notes = ?, updated_at = NOW()
                WHERE id = ? AND mechanic_id = ?
            ");
            $stmt->execute([$new_status, $work_notes, $appointment_id, $mechanic_id]);
            
            setSuccessMessage("Η κατάσταση ενημερώθηκε επιτυχώς!");
        }
    } catch (Exception $e) {
        setErrorMessage("Σφάλμα ενημέρωσης: " . $e->getMessage());
    }
    header('Location: my_assignments.php');
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';

// Build query based on filters
$where_conditions = ["a.mechanic_id = ?"];
$params = [$mechanic_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter === 'today') {
    $where_conditions[] = "DATE(a.appointment_date) = CURDATE()";
} elseif ($date_filter === 'week') {
    $where_conditions[] = "WEEK(a.appointment_date) = WEEK(CURDATE())";
} elseif ($date_filter === 'month') {
    $where_conditions[] = "MONTH(a.appointment_date) = MONTH(CURDATE())";
}

$where_clause = implode(' AND ', $where_conditions);

try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.phone as customer_phone,
               CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info,
               v.manufacturing_date,
               v.fuel_type
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM appointments 
        WHERE mechanic_id = ?
    ");
    $stmt->execute([$mechanic_id]);
    $stats = $stmt->fetch();

} catch (Exception $e) {
    $assignments = [];
    $error_message = "Σφάλμα φόρτωσης: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Οι Εργασίες μου - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .assignment-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .assignment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .status-created { border-left: 5px solid #ffc107; }
        .status-in_progress { border-left: 5px solid #17a2b8; }
        .status-completed { border-left: 5px solid #28a745; }
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-2">
                <?php include '../includes/sidebar.php'; ?>
            </div>
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-list-check"></i> Οι Εργασίες μου</h2>
                    <a href="../mechanic_dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Πίσω στο Dashboard
                    </a>
                </div>

                <!-- Success/Error Messages -->
                <?php $success = getSuccessMessage(); if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card filter-card">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['total']; ?></h3>
                                <small>Συνολικές</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['pending']; ?></h3>
                                <small>Σε Αναμονή</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['in_progress']; ?></h3>
                                <small>Σε Εξέλιξη</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $stats['completed']; ?></h3>
                                <small>Ολοκληρωμένες</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Κατάσταση</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Όλες</option>
                                    <option value="created" <?php echo $status_filter === 'created' ? 'selected' : ''; ?>>Σε Αναμονή</option>
                                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>Σε Εξέλιξη</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Ολοκληρωμένες</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Περίοδος</label>
                                <select name="date" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>Όλες</option>
                                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Σήμερα</option>
                                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Αυτή την Εβδομάδα</option>
                                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Αυτόν τον Μήνα</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <a href="my_assignments.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Καθαρισμός Φίλτρων
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assignments List -->
                <?php if (empty($assignments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-clipboard-x fs-1 text-muted mb-3"></i>
                        <h4 class="text-muted">Δεν βρέθηκαν εργασίες</h4>
                        <p class="text-muted">Δοκιμάστε να αλλάξετε τα φίλτρα αναζήτησης</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="card assignment-card status-<?php echo $assignment['status']; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0">
                                                <i class="bi bi-person me-2"></i><?php echo htmlspecialchars($assignment['customer_name']); ?>
                                            </h5>
                                            <span class="badge bg-<?php 
                                                echo match($assignment['status']) {
                                                    'created' => 'warning',
                                                    'in_progress' => 'info',
                                                    'completed' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php 
                                                echo match($assignment['status']) {
                                                    'created' => 'Σε Αναμονή',
                                                    'in_progress' => 'Σε Εξέλιξη',
                                                    'completed' => 'Ολοκληρώθηκε',
                                                    default => 'Άγνωστο'
                                                };
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <i class="bi bi-car-front me-2 text-muted"></i>
                                            <strong><?php echo htmlspecialchars($assignment['vehicle_info']); ?></strong>
                                        </div>
                                        
                                        <div class="row text-muted small mb-2">
                                            <div class="col-md-6">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($assignment['appointment_date'])); ?>
                                                στις <?php echo date('H:i', strtotime($assignment['appointment_time'])); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?php echo htmlspecialchars($assignment['customer_phone'] ?? 'Δεν υπάρχει'); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="badge bg-secondary me-2"><?php echo ucfirst($assignment['reason']); ?></span>
                                            <span class="badge bg-light text-dark"><?php echo ucfirst($assignment['fuel_type']); ?></span>
                                        </div>
                                        
                                        <?php if ($assignment['problem_description']): ?>
                                            <div class="alert alert-light mb-2">
                                                <small><strong>Περιγραφή Προβλήματος:</strong><br>
                                                <?php echo htmlspecialchars($assignment['problem_description']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($assignment['work_notes']): ?>
                                            <div class="alert alert-info mb-2">
                                                <small><strong>Σημειώσεις Εργασίας:</strong><br>
                                                <?php echo htmlspecialchars($assignment['work_notes']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="text-end">
                                            <?php if ($assignment['status'] !== 'completed'): ?>
                                                <button class="btn btn-outline-primary btn-sm mb-2" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#updateModal<?php echo $assignment['id']; ?>">
                                                    <i class="bi bi-gear me-1"></i>Ενημέρωση Κατάστασης
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Δημιουργία: <?php echo date('d/m/Y H:i', strtotime($assignment['created_at'])); ?>
                                                </small>
                                                <?php if ($assignment['updated_at']): ?>
                                                    <br><small class="text-muted">
                                                        Τελευταία ενημέρωση: <?php echo date('d/m/Y H:i', strtotime($assignment['updated_at'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Update Status Modal -->
                        <div class="modal fade" id="updateModal<?php echo $assignment['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Ενημέρωση Κατάστασης</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $assignment['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Νέα Κατάσταση</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="created" <?php echo $assignment['status'] === 'created' ? 'selected' : ''; ?>>Σε Αναμονή</option>
                                                    <option value="in_progress" <?php echo $assignment['status'] === 'in_progress' ? 'selected' : ''; ?>>Σε Εξέλιξη</option>
                                                    <option value="completed" <?php echo $assignment['status'] === 'completed' ? 'selected' : ''; ?>>Ολοκληρώθηκε</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Σημειώσεις Εργασίας</label>
                                                <textarea name="work_notes" class="form-control" rows="4" 
                                                          placeholder="Περιγράψτε την εργασία που έγινε..."><?php echo htmlspecialchars($assignment['work_notes']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                                            <button type="submit" class="btn btn-primary">Ενημέρωση</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>