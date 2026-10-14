<?php
session_start();

// ΠΡΟΣΩΡΙΝΟ - Προσομοίωση σύνδεσης ως μηχανικός
$_SESSION['user_id'] = 5; // ID του μηχανικού (π.χ. petros.mechanic)
$_SESSION['role'] = 'mechanic';
$_SESSION['first_name'] = 'Πέτρος';
$_SESSION['last_name'] = 'Μηχανικός';

require_once '../config/database.php';

// Get mechanic info από τον users πίνακα
$stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$mechanic = $stmt->fetch();

// Mock statistics για δοκιμή
$stats = [
    'today_appointments' => 3,
    'pending_appointments' => 5, 
    'completed_appointments' => 12,
    'total_appointments' => 25
];
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Μηχανικού - AutoService</title>
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
                    <div>
                        <h2><i class="bi bi-speedometer2"></i> Dashboard Μηχανικού</h2>
                        <p class="text-muted mb-0">Καλωσήρθες, <?php echo htmlspecialchars($mechanic['first_name'] . ' ' . $mechanic['last_name']); ?></p>
                    </div>
                    <div class="text-end">
                        <div class="text-muted"><?php echo date('l, d M Y'); ?></div>
                        <div class="fs-5"><?php echo date('H:i'); ?></div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?php echo $stats['today_appointments']; ?></h3>
                                        <p class="mb-0">Σημερινά Ραντεβού</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-calendar-day" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?php echo $stats['pending_appointments']; ?></h3>
                                        <p class="mb-0">Εκκρεμή Ραντεβού</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock-history" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?php echo $stats['completed_appointments']; ?></h3>
                                        <p class="mb-0">Ολοκληρωμένα</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3><?php echo $stats['total_appointments']; ?></h3>
                                        <p class="mb-0">Συνολικά Ραντεβού</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-calendar-check" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle"></i> Δοκιμαστικό Dashboard</h5>
                    <p>Αυτό είναι το δοκιμαστικό dashboard για μηχανικούς. Τα πραγματικά δεδομένα θα εμφανίζονται όταν δημιουργηθούν οι πίνακες appointments και vehicles.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>