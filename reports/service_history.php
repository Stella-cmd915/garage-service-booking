<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';

// Get service history based on user role
try {
    if ($_SESSION['role'] === 'customer') {
        // Customer sees only their own service history
        $stmt = $pdo->prepare("
            SELECT s.*, a.appointment_date, a.reason as appointment_reason,
                   CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info,
                   CONCAT(u.first_name, ' ', u.last_name) as mechanic_name
            FROM services s
            JOIN appointments a ON s.appointment_id = a.id
            JOIN vehicles v ON a.vehicle_id = v.id
            LEFT JOIN users u ON a.mechanic_id = u.id
            WHERE a.customer_id = ?
            ORDER BY s.completion_time DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Secretary/mechanic sees all service history
        $stmt = $pdo->prepare("
            SELECT s.*, a.appointment_date, a.reason as appointment_reason,
                   CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info,
                   CONCAT(u.first_name, ' ', u.last_name) as mechanic_name,
                   CONCAT(cu.first_name, ' ', cu.last_name) as customer_name
            FROM services s
            JOIN appointments a ON s.appointment_id = a.id
            JOIN vehicles v ON a.vehicle_id = v.id
            JOIN users cu ON a.customer_id = cu.id
            LEFT JOIN users u ON a.mechanic_id = u.id
            ORDER BY s.completion_time DESC
        ");
        $stmt->execute();
    }
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $services = [];
    error_log("Error loading service history: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ιστορικό Εργασιών - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-clock-history"></i> Ιστορικό Εργασιών</h2>
            <?php if ($_SESSION['role'] !== 'customer'): ?>
                <div class="btn-group">
                    <button class="btn btn-outline-primary" onclick="exportHistory('pdf')">
                        <i class="bi bi-file-earmark-pdf"></i> PDF
                    </button>
                    <button class="btn btn-outline-success" onclick="exportHistory('excel')">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Excel
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($services)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                    <h4 class="mt-3 text-muted">
                        <?php echo $_SESSION['role'] === 'customer' ? 'Δεν έχετε ιστορικό εργασιών' : 'Δεν υπάρχουν εργασίες στο σύστημα'; ?>
                    </h4>
                    <p class="text-muted">
                        <?php echo $_SESSION['role'] === 'customer' ? 'Κλείστε το πρώτο σας ραντεβού' : 'Οι εργασίες θα εμφανιστούν εδώ μετά την ολοκλήρωσή τους'; ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Συνολικές Εργασίες: <?php echo count($services); ?>
                        <?php if ($_SESSION['role'] === 'customer'): ?>
                            | Συνολικό Κόστος: <span class="text-success fw-bold"><?php echo number_format(array_sum(array_column($services, 'cost')), 2); ?>€</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ημερομηνία</th>
                                    <th>Περιγραφή Εργασίας</th>
                                    <th>Αυτοκίνητο</th>
                                    <?php if ($_SESSION['role'] !== 'customer'): ?>
                                        <th>Πελάτης</th>
                                    <?php endif; ?>
                                    <th>Μηχανικός</th>
                                    <th>Υλικά</th>
                                    <th>Κόστος</th>
                                    <th>Ενέργειες</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo date('d/m/Y', strtotime($service['completion_time'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($service['completion_time'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($service['description']); ?></strong>
                                                <?php if ($service['appointment_reason']): ?>
                                                    <br><span class="badge bg-<?php echo $service['appointment_reason'] === 'service' ? 'info' : 'warning'; ?> text-xs">
                                                        <?php echo $service['appointment_reason'] === 'service' ? 'Σέρβις' : 'Επιδιόρθωση'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($service['vehicle_info']); ?></small>
                                        </td>
                                        <?php if ($_SESSION['role'] !== 'customer'): ?>
                                            <td>
                                                <small><?php echo htmlspecialchars($service['customer_name'] ?? 'N/A'); ?></small>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <small><?php echo htmlspecialchars($service['mechanic_name'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($service['materials_used'])): ?>
                                                <small><?php echo htmlspecialchars($service['materials_used']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success"><?php echo number_format($service['cost'], 2); ?>€</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewServiceDetails(<?php echo $service['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="<?php echo $_SESSION['role']; ?>_dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Πίσω στο Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewServiceDetails(serviceId) {
            alert('Προβολή λεπτομερειών εργασίας ID: ' + serviceId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }

        function exportHistory(format) {
            alert('Εξαγωγή ιστορικού σε ' + format.toUpperCase() + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }
    </script>
</body>
</html>