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

// Handle work logging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'];
        
        if ($action === 'log_work') {
            $appointment_id = $_POST['appointment_id'];
            $work_description = trim($_POST['work_description']);
            $time_spent = (int)$_POST['time_spent'];
            $parts_used = trim($_POST['parts_used']);
            $labor_cost = (float)$_POST['labor_cost'];
            $parts_cost = (float)$_POST['parts_cost'];
            $status = $_POST['status'];
            
            // Insert work log
            $stmt = $pdo->prepare("
                INSERT INTO work_logs (appointment_id, mechanic_id, work_description, time_spent, 
                                     parts_used, labor_cost, parts_cost, logged_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$appointment_id, $mechanic_id, $work_description, $time_spent, 
                           $parts_used, $labor_cost, $parts_cost]);
            
            // Update appointment status
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, work_notes = CONCAT(COALESCE(work_notes, ''), '\n', ?), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $work_description, $appointment_id]);
            
            setSuccessMessage("Η εργασία καταγράφηκε επιτυχώς!");
            
        } elseif ($action === 'update_status') {
            $appointment_id = $_POST['appointment_id'];
            $status = $_POST['status'];
            $notes = trim($_POST['notes']);
            
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, work_notes = ?, updated_at = NOW()
                WHERE id = ? AND mechanic_id = ?
            ");
            $stmt->execute([$status, $notes, $appointment_id, $mechanic_id]);
            
            setSuccessMessage("Η κατάσταση ενημερώθηκε επιτυχώς!");
        }
    } catch (Exception $e) {
        setErrorMessage("Σφάλμα: " . $e->getMessage());
    }
    
    header('Location: work_progress.php');
    exit;
}

try {
    // Get active assignments with work logs
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.phone as customer_phone,
               CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info,
               v.manufacturing_date,
               (SELECT COUNT(*) FROM work_logs wl WHERE wl.appointment_id = a.id) as log_count,
               (SELECT SUM(time_spent) FROM work_logs wl WHERE wl.appointment_id = a.id) as total_time,
               (SELECT SUM(labor_cost + parts_cost) FROM work_logs wl WHERE wl.appointment_id = a.id) as total_cost
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE a.mechanic_id = ? 
        AND a.status IN ('created', 'in_progress')
        ORDER BY 
            CASE WHEN a.status = 'in_progress' THEN 0 ELSE 1 END,
            a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$mechanic_id]);
    $active_assignments = $stmt->fetchAll();

    // Get recent work logs
    $stmt = $pdo->prepare("
        SELECT wl.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               CONCAT(v.brand, ' ', v.model) as vehicle_info,
               a.appointment_date
        FROM work_logs wl
        JOIN appointments a ON wl.appointment_id = a.id
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE wl.mechanic_id = ?
        ORDER BY wl.logged_at DESC
        LIMIT 10
    ");
    $stmt->execute([$mechanic_id]);
    $recent_logs = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_jobs,
            SUM(CASE WHEN a.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_jobs,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            COALESCE(SUM(wl.time_spent), 0) as total_hours,
            COALESCE(SUM(wl.labor_cost + wl.parts_cost), 0) as total_revenue
        FROM appointments a
        LEFT JOIN work_logs wl ON a.id = wl.appointment_id
        WHERE a.mechanic_id = ?
    ");
    $stmt->execute([$mechanic_id]);
    $stats = $stmt->fetch();

} catch (Exception $e) {
    $error_message = "Σφάλμα φόρτωσης: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πρόοδος Εργασιών - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .work-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .work-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
        }
        .priority-high { border-left: 5px solid #dc3545; }
        .priority-medium { border-left: 5px solid #ffc107; }
        .priority-low { border-left: 5px solid #28a745; }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }
        .work-log-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid #007bff;
        }
        .time-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
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
                    <h2><i class="bi bi-tools"></i> Πρόοδος Εργασιών</h2>
                    <div class="d-flex gap-2">
                        <a href="../appointments/my_assignments.php" class="btn btn-outline-primary">
                            <i class="bi bi-list-check"></i> Οι Εργασίες μου
                        </a>
                        <a href="../mechanic_dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php $success = getSuccessMessage(); if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php $error = getErrorMessage(); if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stats-card text-center">
                            <h3><?php echo $stats['total_jobs']; ?></h3>
                            <small>Συνολικές Εργασίες</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card bg-warning text-center">
                            <h3><?php echo $stats['in_progress_jobs']; ?></h3>
                            <small>Σε Εξέλιξη</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card bg-success text-center">
                            <h3><?php echo $stats['completed_jobs']; ?></h3>
                            <small>Ολοκληρωμένες</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-info text-center">
                            <h3><?php echo number_format($stats['total_hours'], 1); ?>h</h3>
                            <small>Συνολικές Ώρες</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-dark text-center">
                            <h3>€<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                            <small>Συνολικά Έσοδα</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Active Assignments -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Ενεργές Εργασίες</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($active_assignments)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle fs-1 text-success mb-3"></i>
                                        <h4>Δεν έχετε ενεργές εργασίες</h4>
                                        <p class="text-muted">Όλες οι εργασίες έχουν ολοκληρωθεί!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($active_assignments as $assignment): ?>
                                        <div class="work-card priority-<?php echo $assignment['status'] === 'in_progress' ? 'high' : 'medium'; ?>">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="card-title">
                                                            <i class="bi bi-person me-2"></i><?php echo htmlspecialchars($assignment['customer_name']); ?>
                                                        </h6>
                                                        <p class="text-muted mb-2">
                                                            <i class="bi bi-car-front me-2"></i><?php echo htmlspecialchars($assignment['vehicle_info']); ?>
                                                        </p>
                                                        <div class="mb-2">
                                                            <span class="badge bg-<?php echo $assignment['status'] === 'in_progress' ? 'info' : 'warning'; ?>">
                                                                <?php echo $assignment['status'] === 'in_progress' ? 'Σε Εξέλιξη' : 'Σε Αναμονή'; ?>
                                                            </span>
                                                            <span class="badge bg-secondary ms-2"><?php echo ucfirst($assignment['reason']); ?></span>
                                                        </div>
                                                        
                                                        <div class="row text-muted small">
                                                            <div class="col-6">
                                                                <i class="bi bi-calendar me-1"></i>
                                                                <?php echo date('d/m/Y H:i', strtotime($assignment['appointment_date'] . ' ' . $assignment['appointment_time'])); ?>
                                                            </div>
                                                            <div class="col-6">
                                                                <i class="bi bi-telephone me-1"></i>
                                                                <?php echo htmlspecialchars($assignment['customer_phone'] ?? 'Δεν υπάρχει'); ?>
                                                            </div>
                                                        </div>

                                                        <?php if ($assignment['problem_description']): ?>
                                                            <div class="alert alert-light mt-2 mb-2">
                                                                <small><strong>Πρόβλημα:</strong> <?php echo htmlspecialchars($assignment['problem_description']); ?></small>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($assignment['log_count'] > 0): ?>
                                                            <div class="mt-2">
                                                                <span class="time-badge me-2">
                                                                    <i class="bi bi-clock me-1"></i><?php echo $assignment['total_time']; ?>h
                                                                </span>
                                                                <span class="badge bg-success">
                                                                    €<?php echo number_format($assignment['total_cost'], 2); ?>
                                                                </span>
                                                                <span class="badge bg-info ms-1">
                                                                    <?php echo $assignment['log_count']; ?> καταγραφές
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="col-md-4 text-end">
                                                        <div class="d-grid gap-2">
                                                            <button class="btn btn-success btn-sm" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#logWorkModal<?php echo $assignment['id']; ?>">
                                                                <i class="bi bi-plus-circle me-1"></i>Καταγραφή Εργασίας
                                                            </button>
                                                            <button class="btn btn-outline-primary btn-sm" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#statusModal<?php echo $assignment['id']; ?>">
                                                                <i class="bi bi-gear me-1"></i>Αλλαγή Κατάστασης
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Log Work Modal -->
                                        <div class="modal fade" id="logWorkModal<?php echo $assignment['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Καταγραφή Εργασίας</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="log_work">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $assignment['id']; ?>">
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Περιγραφή Εργασίας *</label>
                                                                    <textarea name="work_description" class="form-control" rows="3" required></textarea>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Χρόνος Εργασίας (ώρες) *</label>
                                                                    <input type="number" name="time_spent" class="form-control" min="0.5" step="0.5" required>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Κόστος Εργασίας (€)</label>
                                                                    <input type="number" name="labor_cost" class="form-control" min="0" step="0.01" value="0">
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Κόστος Ανταλλακτικών (€)</label>
                                                                    <input type="number" name="parts_cost" class="form-control" min="0" step="0.01" value="0">
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Ανταλλακτικά που Χρησιμοποιήθηκαν</label>
                                                                    <textarea name="parts_used" class="form-control" rows="2" placeholder="Λίστα ανταλλακτικών..."></textarea>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Νέα Κατάσταση</label>
                                                                    <select name="status" class="form-select" required>
                                                                        <option value="in_progress" <?php echo $assignment['status'] === 'in_progress' ? 'selected' : ''; ?>>Σε Εξέλιξη</option>
                                                                        <option value="completed">Ολοκληρώθηκε</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                                                            <button type="submit" class="btn btn-success">Καταγραφή Εργασίας</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Status Update Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $assignment['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Αλλαγή Κατάστασης</h5>
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
                                                                    <option value="completed">Ολοκληρώθηκε</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Σημειώσεις</label>
                                                                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($assignment['work_notes']); ?></textarea>
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

                    <!-- Recent Work Logs -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Πρόσφατες Καταγραφές</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_logs)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-journal-x fs-3 mb-2"></i>
                                        <p class="mb-0">Δεν υπάρχουν καταγραφές</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_logs as $log): ?>
                                        <div class="work-log-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0 small"><?php echo htmlspecialchars($log['customer_name']); ?></h6>
                                                <span class="time-badge small"><?php echo $log['time_spent']; ?>h</span>
                                            </div>
                                            <p class="small text-muted mb-1"><?php echo htmlspecialchars($log['vehicle_info']); ?></p>
                                            <p class="small mb-2"><?php echo htmlspecialchars(substr($log['work_description'], 0, 80)); ?>...</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php echo date('d/m H:i', strtotime($log['logged_at'])); ?>
                                                </small>
                                                <span class="badge bg-success small">
                                                    €<?php echo number_format($log['labor_cost'] + $log['parts_cost'], 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>