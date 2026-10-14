<?php
session_start();

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

// Check if user is logged in and is mechanic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mechanic') {
    header('Location: auth/login.php');
    exit;
}

$mechanic_id = $_SESSION['user_id'];

try {
    // Get today's assignments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE a.mechanic_id = ? 
        AND DATE(a.appointment_date) = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$mechanic_id]);
    $today_assignments = $stmt->fetchAll();

    // Get pending assignments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE a.mechanic_id = ? 
        AND a.status = 'created'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$mechanic_id]);
    $pending_assignments = $stmt->fetchAll();

    // Get in-progress assignments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE a.mechanic_id = ? 
        AND a.status = 'in_progress'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$mechanic_id]);
    $in_progress_assignments = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_assignments,
            SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM appointments 
        WHERE mechanic_id = ?
    ");
    $stmt->execute([$mechanic_id]);
    $stats = $stmt->fetch();

} catch (Exception $e) {
    $error_message = "Σφάλμα φόρτωσης δεδομένων: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Μηχανικού - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .assignment-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 0.5rem;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }
        .quick-action {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .quick-action:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(40,167,69,0.4);
            color: white;
            text-decoration: none;
        }
        .bg-primary { background: linear-gradient(135deg, #2c5aa0 0%, #1a4480 100%) !important; }
    </style>
</head>
<body class="bg-light">
    <!-- Simple Navbar -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #2c5aa0 0%, #1a4480 100%);">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="index.php">
                <i class="bi bi-tools me-2"></i>AutoCare
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Πέτρος Μιχανλίδης'); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="users/edit_profile.php"><i class="bi bi-person-gear me-2"></i>Προφίλ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Αποσύνδεση</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <i class="bi bi-person-circle fs-1 text-primary"></i>
                            <h6 class="mt-2">Πέτρος Μιχανλίδης</h6>
                            <small class="text-muted">Μηχανικός</small>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="mechanics/dashboard.php" class="btn btn-primary">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard Μηχανικού
                            </a>
                            <a href="appointments/mechanic_appointments.php" class="btn btn-outline-primary">
                                <i class="bi bi-list-check me-2"></i>Τα Ραντεβού μου
                            </a>
                            <a href="mechanics/work_progress.php" class="btn btn-outline-primary">
                                <i class="bi bi-tools me-2"></i>Πρόοδος Εργασιών
                            </a>
                            <a href="auth/logout.php" class="btn btn-outline-danger mt-3">
                                <i class="bi bi-box-arrow-right me-2"></i>Αποσύνδεση
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-10">
                <!-- Welcome Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="bg-primary text-white p-4 rounded-3">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h2 class="mb-2">
                                        <i class="bi bi-tools me-3"></i>Καλώς ήρθατε, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Πέτρος'); ?>!
                                    </h2>
                                    <p class="mb-0 opacity-75">Διαχειριστείτε τις εργασίες και το πρόγραμμά σας</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="text-white">
                                        <div class="fs-4"><?php echo date('d/m/Y'); ?></div>
                                        <div class="fs-6"><?php echo date('H:i'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-clipboard-check fs-1"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['total_assignments'] ?? 3; ?></h3>
                                    <small>Συνολικές Εργασίες</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-clock fs-1"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['pending_count'] ?? 0; ?></h3>
                                    <small>Σε Αναμονή</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-gear fs-1"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['in_progress_count'] ?? 1; ?></h3>
                                    <small>Σε Εξέλιξη</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-success">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo $stats['completed_count'] ?? 0; ?></h3>
                                    <small>Ολοκληρωμένες</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Γρήγορες Ενέργειες</h5>
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <a href="appointments/mechanic_appointments.php" class="btn quick-action w-100">
                                            <i class="bi bi-list-check me-2"></i>Οι Εργασίες μου
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="appointments/mechanic_appointments.php" class="btn quick-action w-100 bg-info">
                                            <i class="bi bi-calendar-week me-2"></i>Πρόγραμμα
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="mechanics/work_progress.php" class="btn quick-action w-100 bg-warning">
                                            <i class="bi bi-tools me-2"></i>Πρόοδος Εργασιών
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <a href="users/edit_profile.php" class="btn quick-action w-100 bg-secondary">
                                            <i class="bi bi-person-gear me-2"></i>Προφίλ
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Today's Assignments -->
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-calendar-day me-2"></i>Σημερινές Εργασίες</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($today_assignments)): ?>
                                    <!-- Sample data for demo -->
                                    <div class="assignment-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">Γιάννης Παπαδόπουλος</h6>
                                                <small class="text-muted">Toyota Corolla (VIN1HGBH41JXMN109186)</small>
                                                <div class="mt-1">
                                                    <span class="badge status-badge bg-warning">Σε Ανάμονη</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold">09:00</div>
                                                <small class="text-muted">Service</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="assignment-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">Ελένη Δημητρίου</h6>
                                                <small class="text-muted">Audi A4 (VIN5HGBH41JXMN109190)</small>
                                                <div class="mt-1">
                                                    <span class="badge status-badge bg-info">Σε Εξέλιξη</span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold">10:00</div>
                                                <small class="text-muted">Service</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($today_assignments as $assignment): ?>
                                        <div class="assignment-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['customer_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['vehicle_info']); ?></small>
                                                    <div class="mt-1">
                                                        <span class="badge status-badge bg-<?php 
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
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold"><?php echo date('H:i', strtotime($assignment['appointment_time'])); ?></div>
                                                    <small class="text-muted"><?php echo ucfirst($assignment['reason']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="text-center mt-3">
                                    <a href="appointments/mechanic_appointments.php" class="btn btn-outline-primary">
                                        Προβολή όλων <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending & In Progress -->
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="bi bi-exclamation-circle me-2"></i>Εργασίες σε Εξέλιξη</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($in_progress_assignments)): ?>
                                    <div class="text-center text-muted py-2">
                                        <i class="bi bi-check-circle fs-3 mb-2"></i>
                                        <p class="mb-0">Δεν έχετε εργασίες σε εξέλιξη</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($in_progress_assignments as $assignment): ?>
                                        <div class="assignment-item border-start border-warning">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['customer_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['vehicle_info']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <small><?php echo date('d/m', strtotime($assignment['appointment_date'])); ?></small>
                                                    <div class="fw-bold"><?php echo date('H:i', strtotime($assignment['appointment_time'])); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <hr>
                                
                                <h6 class="text-muted">Επόμενες Εργασίες:</h6>
                                <?php if (empty($pending_assignments)): ?>
                                    <p class="text-muted mb-0">Δεν έχετε επόμενες εργασίες</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($pending_assignments, 0, 3) as $assignment): ?>
                                        <div class="assignment-item border-start border-info">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <small class="fw-bold"><?php echo htmlspecialchars($assignment['customer_name']); ?></small>
                                                    <div><small class="text-muted"><?php echo htmlspecialchars($assignment['vehicle_info']); ?></small></div>
                                                </div>
                                                <div class="text-end">
                                                    <small><?php echo date('d/m', strtotime($assignment['appointment_date'])); ?></small>
                                                    <div><small class="fw-bold"><?php echo date('H:i', strtotime($assignment['appointment_time'])); ?></small></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div class="text-center mt-3">
                                    <a href="appointments/mechanic_appointments.php" class="btn btn-outline-warning">
                                        Προβολή Προγράμματος <i class="bi bi-arrow-right"></i>
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