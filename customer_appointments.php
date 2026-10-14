<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
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

// Get customer info
$stmt = $pdo->prepare("
    SELECT u.*, c.id as customer_id 
    FROM users u 
    LEFT JOIN customers c ON u.id = c.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user['customer_id']) {
    die("Customer record not found.");
}

$success_message = '';
$error_message = '';

// Handle appointment cancellation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $appointment_id = $_POST['appointment_id'];
    
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled' 
        WHERE id = ? AND customer_id = ? AND status = 'created'
    ");
    
    if ($stmt->execute([$appointment_id, $user['customer_id']])) {
        $success_message = "Το ραντεβού ακυρώθηκε επιτυχώς.";
    } else {
        $error_message = "Δεν ήταν δυνατή η ακύρωση του ραντεβού.";
    }
}

// Get customer's appointments with filters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = ["a.customer_id = ?"];
$params = [$user['customer_id']];

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

$where_clause = implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT a.*, 
           v.brand, v.model, v.serial_number,
           u.first_name as mechanic_first_name, u.last_name as mechanic_last_name,
           m.specialty
    FROM appointments a
    LEFT JOIN vehicles v ON a.vehicle_id = v.id
    LEFT JOIN mechanics m ON a.mechanic_id = m.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE $where_clause
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get appointment statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_cost ELSE 0 END), 0) as total_cost
    FROM appointments 
    WHERE customer_id = ?
");
$stmt->execute([$user['customer_id']]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Τα Ραντεβού μου - AutoCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        }
        .main-content {
            padding-top: 2rem;
        }
        .appointment-card {
            border-left: 4px solid #28a745;
            margin-bottom: 1rem;
        }
        .appointment-card.pending {
            border-left-color: #007bff;
        }
        .appointment-card.in-progress {
            border-left-color: #ffc107;
        }
        .appointment-card.completed {
            border-left-color: #28a745;
        }
        .appointment-card.cancelled {
            border-left-color: #dc3545;
        }
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-car-side me-2"></i>AutoCare
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="customer_dashboard.php">
                            <i class="fas fa-home me-1"></i>Ο Χώρος μου
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_vehicles.php">
                            <i class="fas fa-car me-1"></i>Τα Αυτοκίνητά μου
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="customer_appointments.php">
                            <i class="fas fa-calendar me-1"></i>Τα Ραντεβού μου
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="customer_profile.php"><i class="fas fa-user-edit me-2"></i>Προφίλ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Αποσύνδεση</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid main-content">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Σύνολο</h6>
                                <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-calendar fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Εκκρεμή</h6>
                                <h2 class="mb-0"><?php echo $stats['pending']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-clock fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Ολοκληρωμένα</h6>
                                <h2 class="mb-0"><?php echo $stats['completed']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-check-circle fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card" style="background: linear-gradient(135deg, #6610f2 0%, #6f42c1 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-1">Συνολικό Κόστος</h6>
                                <h2 class="mb-0">€<?php echo number_format($stats['total_cost'], 2); ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-euro-sign fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>Τα Ραντεβού μου
                </h4>
                <a href="book_appointment.php" class="btn btn-success">
                    <i class="fas fa-calendar-plus me-2"></i>Νέο Ραντεβού
                </a>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Κατάσταση</label>
                                <select class="form-control" name="status">
                                    <option value="">Όλα</option>
                                    <option value="created" <?php echo $status_filter === 'created' ? 'selected' : ''; ?>>Εκκρεμή</option>
                                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>Σε Εξέλιξη</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Ολοκληρωμένα</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Ακυρωμένα</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Από Ημερομηνία</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Έως Ημερομηνία</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Φιλτράρισμα
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appointments List -->
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Δεν βρέθηκαν ραντεβού</h5>
                        <p class="text-muted">Δεν έχετε κάνει κανένα ραντεβού ακόμα ή δεν ταιριάζουν τα φίλτρα.</p>
                        <a href="book_appointment.php" class="btn btn-success">
                            <i class="fas fa-calendar-plus me-2"></i>Κλείσιμο Πρώτου Ραντεβού
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card appointment-card <?php echo $appointment['status']; ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            <i class="fas fa-car me-2"></i>
                                            <?php echo htmlspecialchars($appointment['brand'] . ' ' . $appointment['model']); ?>
                                        </h6>
                                        <?php
                                        $status_classes = [
                                            'created' => 'bg-primary',
                                            'in_progress' => 'bg-warning',
                                            'completed' => 'bg-success',
                                            'cancelled' => 'bg-danger'
                                        ];
                                        $status_texts = [
                                            'created' => 'Εκκρεμές',
                                            'in_progress' => 'Σε Εξέλιξη',
                                            'completed' => 'Ολοκληρώθηκε',
                                            'cancelled' => 'Ακυρώθηκε'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_classes[$appointment['status']]; ?>">
                                            <?php echo $status_texts[$appointment['status']]; ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <i class="fas fa-calendar text-primary me-2"></i>
                                            <strong><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></strong>
                                        </div>
                                        <div class="mb-2">
                                            <i class="fas fa-clock text-info me-2"></i>
                                            <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                        <div class="mb-2">
                                            <i class="fas fa-wrench text-warning me-2"></i>
                                            <?php echo $appointment['reason'] == 'repair' ? 'Επιδιόρθωση' : 'Σέρβις'; ?>
                                        </div>
                                        
                                        <?php if ($appointment['mechanic_first_name']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-user text-success me-2"></i>
                                            <?php echo htmlspecialchars($appointment['mechanic_first_name'] . ' ' . $appointment['mechanic_last_name']); ?>
                                            <?php if ($appointment['specialty']): ?>
                                                <br><small class="text-muted ms-4"><?php echo htmlspecialchars($appointment['specialty']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($appointment['problem_description']): ?>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <?php echo htmlspecialchars($appointment['problem_description']); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($appointment['total_cost'] > 0): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-euro-sign me-1"></i><?php echo number_format($appointment['total_cost'], 2); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($appointment['status'] === 'created'): ?>
                                        <div class="d-grid">
                                            <button class="btn btn-outline-danger btn-sm" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-times me-1"></i>Ακύρωση Ραντεβού
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cancelAppointment(appointmentId) {
            if (confirm('Είστε σίγουροι ότι θέλετε να ακυρώσετε αυτό το ραντεβού;')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'cancel';
                form.appendChild(actionInput);
                
                const appointmentInput = document.createElement('input');
                appointmentInput.type = 'hidden';
                appointmentInput.name = 'appointment_id';
                appointmentInput.value = appointmentId;
                form.appendChild(appointmentInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>