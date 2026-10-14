<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is mechanic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header('Location: ../auth/login.php');
    exit();
}

$mechanic_id = $_SESSION['user_id'];

// Get mechanic info
$stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, m.specialty 
    FROM users u 
    JOIN mechanics m ON u.id = m.user_id 
    WHERE u.id = ?
");
$stmt->execute([$mechanic_id]);
$mechanic = $stmt->fetch();

// Handle appointment updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? '';
    
    try {
        switch ($action) {
            case 'start_appointment':
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'in_progress' WHERE id = ? AND mechanic_id = ?");
                $stmt->execute([$appointment_id, $mechanic_id]);
                $_SESSION['success'] = "Το ραντεβού ξεκίνησε επιτυχώς.";
                break;
                
            case 'complete_appointment':
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ? AND mechanic_id = ?");
                $stmt->execute([$appointment_id, $mechanic_id]);
                $_SESSION['success'] = "Το ραντεβού ολοκληρώθηκε επιτυχώς.";
                break;
                
            case 'update_services':
                $services = $_POST['services'] ?? '';
                $cost = $_POST['cost'] ?? 0;
                
                $stmt = $pdo->prepare("UPDATE appointments SET services = ?, cost = ? WHERE id = ? AND mechanic_id = ?");
                $stmt->execute([$services, $cost, $appointment_id, $mechanic_id]);
                $_SESSION['success'] = "Οι εργασίες ενημερώθηκαν επιτυχώς.";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Σφάλμα κατά την ενημέρωση: " . $e->getMessage();
    }
    
    header('Location: my_appointments.php');
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';

// Build query conditions
$where_conditions = ["a.mechanic_id = ?"];
$params = [$mechanic_id];

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $date_to;
}

if (!empty($search_customer)) {
    $where_conditions[] = "(cu.first_name LIKE ? OR cu.last_name LIKE ?)";
    $params[] = "%{$search_customer}%";
    $params[] = "%{$search_customer}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get appointments
$query = "
    SELECT a.*, v.brand, v.model, v.serial_number, v.vehicle_type,
           CONCAT(cu.first_name, ' ', cu.last_name) as customer_name,
           cu.id_number as customer_id_number
    FROM appointments a
    LEFT JOIN vehicles v ON a.vehicle_id = v.id
    LEFT JOIN users cu ON a.customer_id = cu.id
    WHERE {$where_clause}
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get appointment counts for badges
$status_counts = [];
$count_queries = [
    'all' => "SELECT COUNT(*) FROM appointments WHERE mechanic_id = ?",
    'created' => "SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND status = 'created'",
    'in_progress' => "SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND status = 'in_progress'",
    'completed' => "SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND status = 'completed'"
];

foreach ($count_queries as $key => $query) {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$mechanic_id]);
    $status_counts[$key] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Τα Ραντεβού μου - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .appointment-card {
            border-left: 4px solid #007bff;
            transition: all 0.2s;
        }
        .appointment-card.status-created { border-left-color: #6c757d; }
        .appointment-card.status-in-progress { border-left-color: #0dcaf0; }
        .appointment-card.status-completed { border-left-color: #198754; }
        .appointment-card.status-cancelled { border-left-color: #dc3545; }
        .appointment-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .filter-form { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .services-textarea { min-height: 100px; }
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
                    <div>
                        <h2><i class="bi bi-calendar-event"></i> Τα Ραντεβού μου</h2>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']); ?> - <?php echo htmlspecialchars($mechanic['specialty']); ?></p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Επιστροφή στο Dashboard
                    </a>
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

                <!-- Status Filter Tabs -->
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link <?php echo empty($status_filter) ? 'active' : ''; ?>" 
                           href="my_appointments.php">
                            Όλα <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'created' ? 'active' : ''; ?>" 
                           href="my_appointments.php?status=created">
                            Δημιουργημένα <span class="badge bg-secondary"><?php echo $status_counts['created']; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>" 
                           href="my_appointments.php?status=in_progress">
                            Σε εξέλιξη <span class="badge bg-info"><?php echo $status_counts['in_progress']; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" 
                           href="my_appointments.php?status=completed">
                            Ολοκληρωμένα <span class="badge bg-success"><?php echo $status_counts['completed']; ?></span>
                        </a>
                    </li>
                </ul>

                <!-- Filters -->
                <form method="GET" class="filter-form">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Από ημερομηνία</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Έως ημερομηνία</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="search_customer" class="form-label">Αναζήτηση πελάτη</label>
                            <input type="text" class="form-control" id="search_customer" name="search_customer" 
                                   value="<?php echo htmlspecialchars($search_customer); ?>" 
                                   placeholder="Όνομα ή επίθετο πελάτη">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Αναζήτηση
                                </button>
                                <a href="my_appointments.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Επαναφορά
                                </a>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                </form>

                <!-- Appointments List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ραντεβού (<?php echo count($appointments); ?> αποτελέσματα)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <p class="mt-3 text-muted">Δεν βρέθηκαν ραντεβού με τα συγκεκριμένα κριτήρια.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($appointments as $appointment): ?>
                                    <div class="list-group-item appointment-card status-<?php echo $appointment['status']; ?>">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="me-3">
                                                        <div class="fw-bold text-primary">
                                                            <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                                        </div>
                                                        <div class="badge bg-dark">
                                                            <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            <i class="bi bi-person-fill text-muted me-1"></i>
                                                            <?php echo htmlspecialchars($appointment['customer_name']); ?>
                                                        </h6>
                                                        <div class="mb-1">
                                                            <i class="bi bi-car-front text-muted me-1"></i>
                                                            <strong><?php echo htmlspecialchars($appointment['brand'] . ' ' . $appointment['model']); ?></strong>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($appointment['serial_number']); ?>)</small>
                                                        </div>
                                                        <div class="d-flex gap-2 mb-2">
                                                            <span class="badge bg-<?php echo $appointment['reason'] === 'repair' ? 'warning' : 'info'; ?>">
                                                                <?php echo $appointment['reason'] === 'repair' ? 'Επιδιόρθωση' : 'Σέρβις'; ?>
                                                            </span>
                                                            <?php
                                                            $status_badges = [
                                                                'created' => '<span class="badge bg-secondary">Δημιουργημένο</span>',
                                                                'in_progress' => '<span class="badge bg-info">Σε εξέλιξη</span>',
                                                                'completed' => '<span class="badge bg-success">Ολοκληρωμένο</span>',
                                                                'cancelled' => '<span class="badge bg-danger">Ακυρωμένο</span>'
                                                            ];
                                                            echo $status_badges[$appointment['status']];
                                                            ?>
                                                        </div>
                                                        
                                                        <?php if ($appointment['problem_description']): ?>
                                                            <div class="text-muted small mb-2">
                                                                <i class="bi bi-info-circle me-1"></i>
                                                                <strong>Πρόβλημα:</strong> <?php echo htmlspecialchars($appointment['problem_description']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($appointment['services'] && $appointment['status'] !== 'created'): ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted"><strong>Εργασίες:</strong></small>
                                                                <div class="small bg-light p-2 rounded">
                                                                    <?php echo nl2br(htmlspecialchars($appointment['services'])); ?>
                                                                </div>
                                                                <?php if ($appointment['cost'] > 0): ?>
                                                                    <div class="mt-1">
                                                                        <span class="badge bg-success">Κόστος: €<?php echo number_format($appointment['cost'], 2); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="btn-group mb-2" role="group">
                                                    <?php if ($appointment['status'] === 'created'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="start_appointment">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-primary" title="Έναρξη ραντεβού">
                                                                <i class="bi bi-play-fill"></i> Έναρξη
                                                            </button>
                                                        </form>
                                                    <?php elseif ($appointment['status'] === 'in_progress'): ?>
                                                        <button class="btn btn-sm btn-warning" onclick="editServices(<?php echo $appointment['id']; ?>)" title="Επεξεργασία εργασιών">
                                                            <i class="bi bi-pencil"></i> Εργασίες
                                                        </button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="complete_appointment">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success" title="Ολοκλήρωση ραντεβού">
                                                                <i class="bi bi-check-lg"></i> Ολοκλήρωση
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-info" onclick="viewAppointment(<?php echo $appointment['id']; ?>)" title="Προβολή λεπτομερειών">
                                                            <i class="bi bi-eye"></i> Προβολή
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="small text-muted">
                                                    Δημιουργήθηκε: <?php echo date('d/m/Y H:i', strtotime($appointment['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Services Modal -->
    <div class="modal fade" id="editServicesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Επεξεργασία Εργασιών</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_services">
                        <input type="hidden" name="appointment_id" id="edit_appointment_id">
                        
                        <div class="mb-3">
                            <label for="services" class="form-label">Εργασίες που πραγματοποιήθηκαν <span class="text-danger">*</span></label>
                            <textarea class="form-control services-textarea" id="services" name="services" 
                                      placeholder="Περιγράψτε αναλυτικά τις εργασίες που έγιναν στο αυτοκίνητο..." required></textarea>
                            <div class="form-text">Περιγράψτε όλες τις εργασίες, τα ανταλλακτικά που χρησιμοποιήθηκαν, κτλ.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cost" class="form-label">Κόστος (€)</label>
                            <input type="number" class="form-control" id="cost" name="cost" 
                                   min="0" step="0.01" placeholder="0.00">
                            <div class="form-text">Το συνολικό κόστος των εργασιών και ανταλλακτικών</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                        <button type="submit" class="btn btn-primary">Αποθήκευση</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Appointment Modal -->
    <div class="modal fade" id="viewAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Λεπτομέρειες Ραντεβού</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentViewContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editServices(appointmentId) {
            // Get current appointment data
            const appointmentCard = document.querySelector(`input[value="${appointmentId}"]`).closest('.list-group-item');
            const servicesDiv = appointmentCard.querySelector('.bg-light');
            const currentServices = servicesDiv ? servicesDiv.textContent.trim() : '';
            
            // Get cost from badge if exists
            const costBadge = appointmentCard.querySelector('.badge.bg-success');
            const currentCost = costBadge ? costBadge.textContent.replace('Κόστος: €', '').replace(',', '') : '0';
            
            // Fill modal
            document.getElementById('edit_appointment_id').value = appointmentId;
            document.getElementById('services').value = currentServices;
            document.getElementById('cost').value = currentCost;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editServicesModal')).show();
        }

        function viewAppointment(appointmentId) {
            // Find appointment data from the page
            const appointmentCard = document.querySelector(`input[value="${appointmentId}"]`).closest('.list-group-item');
            const customerName = appointmentCard.querySelector('h6').textContent.trim();
            const vehicleInfo = appointmentCard.querySelector('strong').textContent;
            const serialNumber = appointmentCard.querySelector('.text-muted').textContent;
            const dateTime = appointmentCard.querySelector('.text-primary').textContent + ' ' + 
                           appointmentCard.querySelector('.badge.bg-dark').textContent;
            
            let content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Στοιχεία Ραντεβού</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Πελάτης:</strong></td><td>${customerName}</td></tr>
                            <tr><td><strong>Ημερομηνία & Ώρα:</strong></td><td>${dateTime}</td></tr>
                            <tr><td><strong>Αυτοκίνητο:</strong></td><td>${vehicleInfo}</td></tr>
                            <tr><td><strong>Σειριακός Αριθμός:</strong></td><td>${serialNumber}</td></tr>
                        </table>
                    </div>
                </div>
            `;
            
            // Add services if exist
            const servicesDiv = appointmentCard.querySelector('.bg-light');
            if (servicesDiv) {
                content += `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-primary">Εργασίες που πραγματοποιήθηκαν</h6>
                            <div class="bg-light p-3 rounded">${servicesDiv.innerHTML}</div>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('appointmentViewContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('viewAppointmentModal')).show();
        }

        // Confirmation dialogs
        document.querySelectorAll('form').forEach(form => {
            const action = form.querySelector('input[name="action"]');
            if (action) {
                if (action.value === 'complete_appointment') {
                    form.addEventListener('submit', function(e) {
                        if (!confirm('Είστε βέβαιοι ότι θέλετε να ολοκληρώσετε αυτό το ραντεβού; Δεν θα μπορείτε να το επεξεργαστείτε ξανά.')) {
                            e.preventDefault();
                        }
                    });
                } else if (action.value === 'start_appointment') {
                    form.addEventListener('submit', function(e) {
                        if (!confirm('Θέλετε να ξεκινήσετε αυτό το ραντεβού;')) {
                            e.preventDefault();
                        }
                    });
                }
            }
        });

        // Auto-resize textarea
        document.getElementById('services').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>