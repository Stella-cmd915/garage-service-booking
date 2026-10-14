<?php
session_start();

// ΠΡΟΣΩΡΙΝΟ - Προσομοίωση σύνδεσης ως γραμματέας
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'secretary';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Secretary';

require_once '../config/database.php';

// Get appointments with filters
$where_conditions = [];
$params = [];

if (!empty($_GET['date_from'])) {
    $where_conditions[] = "a.appointment_date >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $where_conditions[] = "a.appointment_date <= ?";
    $params[] = $_GET['date_to'];
}

if (!empty($_GET['customer_search'])) {
    $where_conditions[] = "(c.last_name LIKE ? OR c.vat_number LIKE ?)";
    $params[] = '%' . $_GET['customer_search'] . '%';
    $params[] = '%' . $_GET['customer_search'] . '%';
}

if (!empty($_GET['status'])) {
    $where_conditions[] = "a.status = ?";
    $params[] = $_GET['status'];
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

try {
    $sql = "
        SELECT 
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.reason,
            a.status,
            a.problem_description,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            CONCAT(v.brand, ' ', v.model) as vehicle_info,
            v.serial_number,
            CONCAT(m.first_name, ' ', m.last_name) as mechanic_name
        FROM appointments a
        JOIN users c ON a.customer_id = c.id
        JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users m ON a.mechanic_id = m.id
        $where_clause
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    $appointments = [];
    error_log("Error loading appointments: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Ραντεβού - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
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
                    <h2><i class="bi bi-calendar-check"></i> Διαχείριση Ραντεβού</h2>
                    <a href="book_appointment.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Νέο Ραντεβού
                    </a>
                </div>

                <!-- Φίλτρα Αναζήτησης -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Φίλτρα Αναζήτησης</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Από Ημερομηνία</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo $_GET['date_from'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Έως Ημερομηνία</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo $_GET['date_to'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="customer_search" class="form-label">Πελάτης</label>
                                <input type="text" class="form-control" id="customer_search" name="customer_search" 
                                       placeholder="Επίθετο ή ΑΦΜ" value="<?php echo $_GET['customer_search'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Κατάσταση</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Όλες</option>
                                    <option value="created" <?php echo ($_GET['status'] ?? '') == 'created' ? 'selected' : ''; ?>>Δημιουργημένο</option>
                                    <option value="in_progress" <?php echo ($_GET['status'] ?? '') == 'in_progress' ? 'selected' : ''; ?>>Σε Εξέλιξη</option>
                                    <option value="completed" <?php echo ($_GET['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Περαιωμένο</option>
                                    <option value="cancelled" <?php echo ($_GET['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Ακυρωμένο</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search"></i> Αναζήτηση
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i> Καθαρισμός
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Πίνακας Ραντεβού -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Λίστα Ραντεβού (<?php echo count($appointments); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Δεν βρέθηκαν ραντεβού</h4>
                                <p class="text-muted">Δεν υπάρχουν ραντεβού που να ταιριάζουν με τα κριτήρια αναζήτησης.</p>
                                <a href="book_appointment.php" class="btn btn-primary">
                                    <i class="bi bi-plus-lg"></i> Κλείσιμο Πρώτου Ραντεβού
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Ημερομηνία</th>
                                            <th>Ώρα</th>
                                            <th>Πελάτης</th>
                                            <th>Αυτοκίνητο</th>
                                            <th>Μηχανικός</th>
                                            <th>Λόγος</th>
                                            <th>Κατάσταση</th>
                                            <th>Ενέργειες</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo $appointment['id']; ?></td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['customer_name']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($appointment['vehicle_info']); ?>
                                                    <br><small class="text-muted"><?php echo $appointment['serial_number']; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['mechanic_name'] ?? 'Μη ανατεθέν'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $appointment['reason'] == 'service' ? 'info' : 'warning'; ?>">
                                                        <?php echo $appointment['reason'] == 'service' ? 'Σέρβις' : 'Επιδιόρθωση'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = match($appointment['status']) {
                                                        'created' => 'bg-secondary',
                                                        'in_progress' => 'bg-primary',
                                                        'completed' => 'bg-success',
                                                        'cancelled' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    
                                                    $status_text = match($appointment['status']) {
                                                        'created' => 'Δημιουργημένο',
                                                        'in_progress' => 'Σε Εξέλιξη',
                                                        'completed' => 'Περαιωμένο',
                                                        'cancelled' => 'Ακυρωμένο',
                                                        default => 'Άγνωστο'
                                                    };
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-primary" title="Προβολή">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" title="Επεξεργασία">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" title="Ακύρωση">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>