<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';

// Get customer's appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info,
               CONCAT(u.first_name, ' ', u.last_name) as mechanic_name
        FROM appointments a
        JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users u ON a.mechanic_id = u.id
        WHERE a.customer_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
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
    <title>Τα Ραντεβού Μου - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
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
                    <h2><i class="bi bi-calendar-check"></i> Τα Ραντεβού Μου</h2>
                    <a href="book_appointment.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Νέο Ραντεβού
                    </a>
                </div>

                <?php if (empty($appointments)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                            <h4 class="mt-3 text-muted">Δεν έχετε ραντεβού</h4>
                            <p class="text-muted">Κλείστε το πρώτο σας ραντεβού για το αυτοκίνητό σας</p>
                            <a href="book_appointment.php" class="btn btn-primary">
                                <i class="bi bi-calendar-plus"></i> Κλείσιμο Ραντεβού
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                                <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                            </h6>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] === 'created' ? 'primary' : 
                                                    ($appointment['status'] === 'in_progress' ? 'warning' : 
                                                    ($appointment['status'] === 'completed' ? 'success' : 'secondary')); 
                                            ?>">
                                                <?php 
                                                $status_labels = [
                                                    'created' => 'Δημιουργημένο',
                                                    'in_progress' => 'Σε εξέλιξη',
                                                    'completed' => 'Ολοκληρωμένο',
                                                    'cancelled' => 'Ακυρωμένο'
                                                ];
                                                echo $status_labels[$appointment['status']] ?? $appointment['status'];
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <strong>Αυτοκίνητο:</strong><br>
                                            <?php echo htmlspecialchars($appointment['vehicle_info']); ?>
                                        </p>
                                        
                                        <p class="card-text">
                                            <strong>Λόγος:</strong>
                                            <span class="badge bg-<?php echo $appointment['reason'] === 'service' ? 'info' : 'warning'; ?>">
                                                <?php echo $appointment['reason'] === 'service' ? 'Σέρβις' : 'Επιδιόρθωση'; ?>
                                            </span>
                                        </p>

                                        <?php if (!empty($appointment['problem_description'])): ?>
                                        <p class="card-text">
                                            <strong>Περιγραφή:</strong><br>
                                            <small><?php echo htmlspecialchars($appointment['problem_description']); ?></small>
                                        </p>
                                        <?php endif; ?>

                                        <?php if (!empty($appointment['mechanic_name'])): ?>
                                        <p class="card-text">
                                            <strong>Μηχανικός:</strong><br>
                                            <?php echo htmlspecialchars($appointment['mechanic_name']); ?>
                                        </p>
                                        <?php endif; ?>

                                        <?php if ($appointment['total_cost'] > 0): ?>
                                        <p class="card-text">
                                            <strong>Κόστος:</strong>
                                            <span class="text-success fw-bold"><?php echo number_format($appointment['total_cost'], 2); ?>€</span>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group w-100">
                                            <?php if ($appointment['status'] === 'created'): ?>
                                                <button class="btn btn-sm btn-outline-warning" onclick="editAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Επεξεργασία
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="bi bi-x-circle"></i> Ακύρωση
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-info w-100" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="bi bi-eye"></i> Προβολή
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <a href="../customer_dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Πίσω στο Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editAppointment(appointmentId) {
            alert('Επεξεργασία ραντεβού ID: ' + appointmentId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }

        function cancelAppointment(appointmentId) {
            if (confirm('Είστε σίγουροι ότι θέλετε να ακυρώσετε το ραντεβού;')) {
                alert('Ακύρωση ραντεβού ID: ' + appointmentId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
            }
        }

        function viewAppointment(appointmentId) {
            alert('Προβολή ραντεβού ID: ' + appointmentId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }
    </script>
</body>
</html>