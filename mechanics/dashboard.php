<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
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

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['users'] = $stmt->fetch()['count'];

// Total customers
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$stats['customers'] = $stmt->fetch()['count'];

// Total vehicles
$stmt = $pdo->query("SELECT COUNT(*) as count FROM vehicles");
$stats['vehicles'] = $stmt->fetch()['count'];

// Today's appointments
$stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()");
$stats['today_appointments'] = $stmt->fetch()['count'];

// Recent appointments
$stmt = $pdo->query("
    SELECT a.*, u.first_name, u.last_name, v.brand, v.model 
    FROM appointments a
    LEFT JOIN users u ON a.customer_id = u.id
    LEFT JOIN vehicles v ON a.vehicle_id = v.id
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$recent_appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AutoCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .navbar-brand, .nav-link { color: white !important; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); color: white; }
        .stat-card-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-card-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .main-content { padding-top: 2rem; }
        .welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-car-side me-2"></i>AutoCare
            </a>
            
            <div class="navbar-nav me-auto">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="../users/manage_users.php">
                    <i class="fas fa-users me-1"></i>Χρήστες
                </a>
                <a class="nav-link" href="../vehicles/manage_vehicles.php">
                    <i class="fas fa-car me-1"></i>Αυτοκίνητα
                </a>
                <a class="nav-link" href="../appointments/mechanic_appointments.php">
                    <i class="fas fa-calendar me-1"></i>Ραντεβού
                </a>
            </div>
            
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../users/edit_profile.php"><i class="fas fa-user-edit me-2"></i>Προφίλ</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Αποσύνδεση</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid main-content">
        <!-- Welcome Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas fa-user-shield fa-3x"></i>
                            </div>
                            <div>
                                <h3 class="mb-1">Καλώς ήρθατε, <?php echo htmlspecialchars($user['first_name']); ?>!</h3>
                                <p class="mb-0 opacity-75">Διαχείριση Μηχανουργείου AutoCare</p>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <a href="../users/manage_users.php" class="btn btn-light btn-lg me-2 mb-2">
                                    <i class="fas fa-users me-2"></i>Διαχείριση Χρηστών
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="../appointments/mechanic_appointments.php" class="btn btn-outline-light btn-lg mb-2">
                                    <i class="fas fa-calendar-alt me-2"></i>Διαχείριση Ραντεβού
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
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Σύνολο Χρηστών</h6>
                                <h2 class="mb-0"><?php echo $stats['users']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-users fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Πελάτες</h6>
                                <h2 class="mb-0"><?php echo $stats['customers']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-user-friends fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Αυτοκίνητα</h6>
                                <h2 class="mb-0"><?php echo $stats['vehicles']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-car fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Σημερινά Ραντεβού</h6>
                                <h2 class="mb-0"><?php echo $stats['today_appointments']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Actions -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Πρόσφατα Ραντεβού
                        </h5>
                        <a href="../appointments/mechanic_appointments.php" class="btn btn-sm btn-primary">Προβολή όλων</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_appointments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Δεν υπάρχουν ραντεβού ακόμα</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Πελάτης</th>
                                            <th>Αυτοκίνητο</th>
                                            <th>Ημερομηνία</th>
                                            <th>Κατάσταση</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['brand'] . ' ' . $appointment['model']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">Δημιουργημένο</span>
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
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>Γρήγορες Ενέργειες
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../users/add_user.php" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Νέος Χρήστης
                            </a>
                            <a href="../vehicles/manage_vehicles.php" class="btn btn-success">
                                <i class="fas fa-car me-2"></i>Διαχείριση Οχημάτων
                            </a>
                            <a href="../book_appointment.php" class="btn btn-info">
                                <i class="fas fa-calendar-plus me-2"></i>Νέο Ραντεβού
                            </a>
                            <hr>
                            <a href="work_progress.php" class="btn btn-warning">
                                <i class="fas fa-tools me-2"></i>Πρόοδος Εργασιών
                            </a>
                            <a href="../reports/" class="btn btn-secondary" onclick="alert('Το module Αναφορές δεν έχει δημιουργηθεί ακόμα.'); return false;">
                                <i class="fas fa-chart-bar me-2"></i>Αναφορές
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>