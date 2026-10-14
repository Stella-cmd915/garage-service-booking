<?php
require_once '../includes/auth_check.php';
requireSecretary(); // Ensure only secretaries can access this page

require_once __DIR__ . '/../config/database.php';

// Get current user info
$current_user = getCurrentUser();

// Get statistics
try {
    // Get total users by role
    $stmt = $pdo->query("SELECT role, is_active, COUNT(*) as count FROM users GROUP BY role, is_active");
    $user_stats = [];
    while ($row = $stmt->fetch()) {
        $user_stats[$row['role']][$row['is_active'] ? 'active' : 'inactive'] = $row['count'];
    }
    
    // Get vehicle count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vehicles");
    $vehicle_count = $stmt->fetch()['count'];
    
    // Get appointment counts by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    $appointment_stats = [];
    while ($row = $stmt->fetch()) {
        $appointment_stats[$row['status']] = $row['count'];
    }
    
    // Get recent activities
    $stmt = $pdo->query("
        SELECT 'appointment' as type, id, created_at, 
               CONCAT('Νέο ραντεβού #', id) as description
        FROM appointments 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'vehicle' as type, id, created_at,
               CONCAT('Νέο αυτοκίνητο: ', brand, ' ', model) as description
        FROM vehicles 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'user' as type, id, created_at,
               CONCAT('Νέος χρήστης: ', first_name, ' ', last_name) as description
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $user_stats = [];
    $vehicle_count = 0;
    $appointment_stats = [];
    $recent_activities = [];
}

// Calculate totals
$total_users = ($user_stats['customer']['active'] ?? 0) + ($user_stats['mechanic']['active'] ?? 0) + ($user_stats['secretary']['active'] ?? 0);
$total_customers = ($user_stats['customer']['active'] ?? 0);
$total_mechanics = ($user_stats['mechanic']['active'] ?? 0);
$total_appointments = array_sum($appointment_stats);
$pending_appointments = ($appointment_stats['created'] ?? 0);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Γραμματέα - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .metric-card.users { border-left-color: #007bff; }
        .metric-card.customers { border-left-color: #28a745; }
        .metric-card.mechanics { border-left-color: #fd7e14; }
        .metric-card.appointments { border-left-color: #dc3545; }
        .metric-card.vehicles { border-left-color: #6f42c1; }
        
        .metric-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .metric-icon {
            font-size: 2.5rem;
            opacity: 0.7;
            float: right;
            margin-top: -10px;
        }
        
        .quick-actions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-decoration: none;
            color: #495057;
            display: block;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .action-btn:hover {
            border-color: #007bff;
            color: #007bff;
            transform: translateX(5px);
        }
        
        .activity-item {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 0.8rem;
        }
        
        .activity-appointment { background: #e3f2fd; color: #1976d2; }
        .activity-vehicle { background: #f3e5f5; color: #7b1fa2; }
        .activity-user { background: #e8f5e8; color: #388e3c; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Welcome Header -->
                <div class="stats-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="mb-2">
                                <i class="bi bi-speedometer2 me-3"></i>Dashboard Γραμματέα
                            </h1>
                            <p class="mb-0 opacity-90">
                                Καλώς ήρθατε, <?php echo htmlspecialchars($current_user['full_name']); ?>! 
                                Διαχειριστείτε το σύστημα από εδώ.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-white opacity-75">
                                <i class="bi bi-calendar3 me-2"></i>
                                <?php echo date('d/m/Y'); ?>
                                <br>
                                <i class="bi bi-clock me-2"></i>
                                <?php echo date('H:i'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Metrics Row -->
                <div class="row">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                        <div class="metric-card users">
                            <div class="metric-number"><?php echo $total_users; ?></div>
                            <div class="metric-label">Συνολικοί Χρήστες</div>
                            <i class="bi bi-people metric-icon"></i>
                            <div class="small text-muted mt-2">
                                <?php echo ($user_stats['customer']['active'] ?? 0); ?> ενεργοί
                                | <?php echo ($user_stats['customer']['inactive'] ?? 0); ?> εκκρεμείς
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                        <div class="metric-card customers">
                            <div class="metric-number"><?php echo $total_customers; ?></div>
                            <div class="metric-label">Πελάτες</div>
                            <i class="bi bi-person metric-icon"></i>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                        <div class="metric-card mechanics">
                            <div class="metric-number"><?php echo $total_mechanics; ?></div>
                            <div class="metric-label">Μηχανικοί</div>
                            <i class="bi bi-tools metric-icon"></i>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                        <div class="metric-card appointments">
                            <div class="metric-number"><?php echo $total_appointments; ?></div>
                            <div class="metric-label">Ραντεβού</div>
                            <i class="bi bi-calendar-check metric-icon"></i>
                            <div class="small text-muted mt-2">
                                <?php echo $pending_appointments; ?> σήμερα
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                        <div class="metric-card vehicles">
                            <div class="metric-number"><?php echo $vehicle_count; ?></div>
                            <div class="metric-label">Αυτοκίνητα</div>
                            <i class="bi bi-car-front metric-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions and Recent Activity -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightning me-2"></i>Γρήγορες Ενέργειες
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-people me-2"></i>Διαχείριση Χρηστών</h6>
                                        <a href="manage_users.php" class="action-btn">
                                            <i class="bi bi-person-gear me-2"></i>
                                            Προβολή και επεξεργασία χρηστών
                                        </a>
                                        <a href="add_user.php" class="action-btn">
                                            <i class="bi bi-person-plus me-2"></i>
                                            Εισαγωγή/εγγραφή νέου χρήστη
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-car-front me-2"></i>Διαχείριση Αυτοκινήτων</h6>
                                        <a href="../vehicles/index.php" class="action-btn">
                                            <i class="bi bi-list me-2"></i>
                                            Προβολή και διαχείριση αυτοκινήτων πελατών
                                        </a>
                                        <a href="../vehicles/add_vehicle.php" class="action-btn">
                                            <i class="bi bi-plus-square me-2"></i>
                                            Εισαγωγή νέου αυτοκινήτου
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-calendar-check me-2"></i>Διαχείριση Ραντεβού</h6>
                                        <a href="../appointments/manage_appointments.php" class="action-btn">
                                            <i class="bi bi-calendar-event me-2"></i>
                                            Προγραμματισμός και διαχείριση ραντεβού
                                        </a>
                                        <a href="../appointments/book_appointment.php" class="action-btn">
                                            <i class="bi bi-calendar-plus me-2"></i>
                                            Κλείσιμο νέου ραντεβού
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-upload me-2"></i>Μαζική Εισαγωγή</h6>
                                        <a href="../vehicles/bulk_import.php" class="action-btn">
                                            <i class="bi bi-file-earmark-arrow-up me-2"></i>
                                            Εισαγωγή χρηστών και δεδομένων από αρχεία
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history me-2"></i>Πρόσφατη Δραστηριότητα
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_activities)): ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox display-4"></i>
                                        <p class="mb-0 mt-2">Δεν υπάρχει πρόσφατη δραστηριότητα</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon activity-<?php echo $activity['type']; ?>">
                                                <i class="bi bi-<?php 
                                                    echo match($activity['type']) {
                                                        'appointment' => 'calendar-check',
                                                        'vehicle' => 'car-front',
                                                        'user' => 'person',
                                                        default => 'circle'
                                                    };
                                                ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="small fw-medium">
                                                    <?php echo htmlspecialchars($activity['description']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?php echo date('d/m H:i', strtotime($activity['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>