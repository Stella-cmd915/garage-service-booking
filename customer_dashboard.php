<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: auth/login.php');
    exit;
}

require_once 'config/database.php';

// Get customer info
try {
    $stmt = $pdo->prepare("
        SELECT u.*, c.tax_number, c.address 
        FROM users u 
        LEFT JOIN customers c ON u.id = c.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        die("User record not found. Please contact administrator.");
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Get customer's vehicles
try {
    $stmt = $pdo->prepare("
        SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $vehicles = $stmt->fetchAll();
} catch (Exception $e) {
    $vehicles = [];
    error_log("Error loading vehicles: " . $e->getMessage());
}

// Get customer's appointments
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(v.brand, ' ', v.model) as vehicle_name, v.serial_number,
               CONCAT(u.first_name, ' ', u.last_name) as mechanic_name
        FROM appointments a
        LEFT JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users u ON a.mechanic_id = u.id
        WHERE a.customer_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {
    $appointments = [];
    error_log("Error loading appointments: " . $e->getMessage());
}

// Get upcoming appointments
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM appointments 
        WHERE customer_id = ? AND appointment_date >= CURDATE() AND status != 'cancelled'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $upcoming_appointments = $stmt->fetch()['count'];
} catch (Exception $e) {
    $upcoming_appointments = 0;
}

// Get total spent (completed appointments)
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_cost), 0) as total FROM appointments 
        WHERE customer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_spent = $stmt->fetch()['total'];
} catch (Exception $e) {
    $total_spent = 0;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ο Χώρος μου - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand, .nav-link {
            color: white !important;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stat-card-customer {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stat-card-vehicles {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .stat-card-appointments {
            background: linear-gradient(135deg, #fd7e14 0%, #e55a4f 100%);
            color: white;
        }
        .stat-card-spent {
            background: linear-gradient(135deg, #6610f2 0%, #6f42c1 100%);
            color: white;
        }
        .welcome-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 20px;
        }
        .main-content {
            padding-top: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-2">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            <div class="col-md-10">
                <!-- Welcome Card -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card welcome-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-1">Καλώς ήρθατε, <?php echo htmlspecialchars($user['first_name']); ?>!</h3>
                                        <p class="mb-0 opacity-75">Διαχειριστείτε τα αυτοκίνητά σας και τα ραντεβού σας</p>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <a href="appointments/book_appointment.php" class="btn btn-light btn-lg me-2 mb-2">
                                            <i class="bi bi-calendar-plus me-2"></i>Νέο Ραντεβού
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="vehicles/add_vehicle.php" class="btn btn-outline-light btn-lg me-2 mb-2">
                                            <i class="bi bi-car-front-fill me-2"></i>Νέο Αυτοκίνητο
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="services/service_history.php" class="btn btn-outline-light btn-lg mb-2">
                                            <i class="bi bi-clock-history me-2"></i>Ιστορικό
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-vehicles">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-1">Αυτοκίνητά μου</h6>
                                        <h2 class="mb-0"><?php echo count($vehicles); ?></h2>
                                    </div>
                                    <div>
                                        <i class="bi bi-car-front" style="font-size: 2rem; opacity: 0.75;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-appointments">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-1">Επερχόμενα Ραντεβού</h6>
                                        <h2 class="mb-0"><?php echo $upcoming_appointments; ?></h2>
                                    </div>
                                    <div>
                                        <i class="bi bi-calendar-event" style="font-size: 2rem; opacity: 0.75;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-spent">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-1">Συνολικό Κόστος</h6>
                                        <h2 class="mb-0">€<?php echo number_format($total_spent, 2); ?></h2>
                                    </div>
                                    <div>
                                        <i class="bi bi-currency-euro" style="font-size: 2rem; opacity: 0.75;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-customer">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-1">Σύνολο Ραντεβού</h6>
                                        <h2 class="mb-0"><?php echo count($appointments); ?></h2>
                                    </div>
                                    <div>
                                        <i class="bi bi-calendar-check" style="font-size: 2rem; opacity: 0.75;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock me-2"></i>Πρόσφατα Ραντεβού
                                </h5>
                                <a href="appointments/customer_appointments.php" class="btn btn-sm btn-success">Προβολή όλων</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($appointments)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                                        <p class="text-muted mt-3">Δεν έχετε κάνει κανένα ραντεβού ακόμα</p>
                                        <a href="appointments/book_appointment.php" class="btn btn-success">Κλείσιμο Ραντεβού</a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Αυτοκίνητο</th>
                                                    <th>Ημερομηνία</th>
                                                    <th>Λόγος</th>
                                                    <th>Κατάσταση</th>
                                                    <th>Μηχανικός</th>
                                                    <th>Κόστος</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($appointments as $appointment): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-car-front me-2 text-success" style="font-size: 1.2rem;"></i>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($appointment['vehicle_name'] ?? 'N/A'); ?></strong><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['serial_number'] ?? ''); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?><br>
                                                            <small class="text-muted"><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $appointment['reason'] == 'repair' ? 'warning' : 'info'; ?>">
                                                                <?php echo $appointment['reason'] == 'repair' ? 'Επιδιόρθωση' : 'Σέρβις'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            $status_text = '';
                                                            switch($appointment['status']) {
                                                                case 'created':
                                                                    $status_class = 'bg-primary';
                                                                    $status_text = 'Δημιουργήθηκε';
                                                                    break;
                                                                case 'in_progress':
                                                                    $status_class = 'bg-warning';
                                                                    $status_text = 'Σε Εξέλιξη';
                                                                    break;
                                                                case 'completed':
                                                                    $status_class = 'bg-success';
                                                                    $status_text = 'Ολοκληρώθηκε';
                                                                    break;
                                                                case 'cancelled':
                                                                    $status_class = 'bg-danger';
                                                                    $status_text = 'Ακυρώθηκε';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($appointment['mechanic_name']): ?>
                                                                <small><?php echo htmlspecialchars($appointment['mechanic_name']); ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">Δεν ανατέθηκε</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($appointment['total_cost'] > 0): ?>
                                                                <strong class="text-success">€<?php echo number_format($appointment['total_cost'], 2); ?></strong>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
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

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-car-front me-2"></i>Τα Αυτοκίνητά μου
                                </h5>
                                <a href="vehicles/customer_vehicles.php" class="btn btn-sm btn-outline-primary">Όλα</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($vehicles)): ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-car-front" style="font-size: 3rem; color: #ccc;"></i>
                                        <p class="text-muted mt-2">Δεν έχετε εγγράψει αυτοκίνητα</p>
                                        <a href="vehicles/add_vehicle.php" class="btn btn-success btn-sm">Προσθήκη Αυτοκινήτου</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($vehicles, 0, 3) as $vehicle): ?>
                                        <div class="d-flex align-items-center mb-3 p-2 bg-light rounded">
                                            <i class="bi bi-car-front text-success me-3" style="font-size: 2rem;"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($vehicle['serial_number']); ?></small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $vehicle['vehicle_type'] === 'passenger' ? 'primary' : 
                                                    ($vehicle['vehicle_type'] === 'truck' ? 'warning' : 'info'); 
                                            ?>"><?php 
                                                $type_labels = [
                                                    'passenger' => 'Επιβατικό',
                                                    'truck' => 'Φορτηγό', 
                                                    'bus' => 'Λεωφορείο'
                                                ];
                                                echo $type_labels[$vehicle['vehicle_type']] ?? $vehicle['vehicle_type'];
                                            ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($vehicles) > 3): ?>
                                        <div class="text-center">
                                            <a href="vehicles/customer_vehicles.php" class="btn btn-sm btn-outline-success">
                                                Προβολή όλων (<?php echo count($vehicles); ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-lightning me-2"></i>Γρήγορες Ενέργειες
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="appointments/book_appointment.php" class="btn btn-success btn-sm">
                                        <i class="bi bi-calendar-plus me-2"></i>Κλείσιμο Ραντεβού
                                    </a>
                                    <a href="vehicles/add_vehicle.php" class="btn btn-primary btn-sm">
                                        <i class="bi bi-car-front-fill me-2"></i>Προσθήκη Αυτοκινήτου
                                    </a>
                                    <a href="users/edit_profile.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-person-gear me-2"></i>Επεξεργασία Προφίλ
                                    </a>
                                </div>
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