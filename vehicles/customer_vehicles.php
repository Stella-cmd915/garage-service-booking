<?php
session_start();

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';

// Get customer's vehicles
try {
    $stmt = $pdo->prepare("
        SELECT * FROM vehicles 
        WHERE customer_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $vehicles = $stmt->fetchAll();
} catch (Exception $e) {
    $vehicles = [];
    error_log("Error loading vehicles: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Τα Αυτοκίνητά Μου - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-car-front"></i> Τα Αυτοκίνητά Μου</h2>
            <a href="../vehicles/add_vehicle.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Προσθήκη Αυτοκινήτου
            </a>
        </div>

        <?php if (empty($vehicles)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-car-front" style="font-size: 3rem; color: #ccc;"></i>
                    <h4 class="mt-3 text-muted">Δεν έχετε καταχωρημένα αυτοκίνητα</h4>
                    <p class="text-muted">Προσθέστε το πρώτο σας αυτοκίνητο στο σύστημα</p>
                    <a href="../vehicles/add_vehicle.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Προσθήκη Αυτοκινήτου
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($vehicles as $vehicle): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></h5>
                                <span class="badge bg-<?php 
                                    echo $vehicle['vehicle_type'] === 'passenger' ? 'primary' : 
                                        ($vehicle['vehicle_type'] === 'truck' ? 'warning' : 'info'); 
                                ?>">
                                    <?php 
                                    $type_labels = [
                                        'passenger' => 'Επιβατικό',
                                        'truck' => 'Φορτηγό', 
                                        'bus' => 'Λεωφορείο'
                                    ];
                                    echo $type_labels[$vehicle['vehicle_type']] ?? htmlspecialchars($vehicle['vehicle_type']);
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    <strong>Σειριακός Αριθμός:</strong><br>
                                    <code><?php echo htmlspecialchars($vehicle['serial_number']); ?></code>
                                </p>
                                
                                <div class="row text-center mb-3">
                                    <div class="col">
                                        <small class="text-muted">Κίνηση</small><br>
                                        <span class="badge bg-secondary"><?php 
                                            $fuel_labels = [
                                                'petrol' => 'Βενζίνη',
                                                'diesel' => 'Πετρέλαιο',
                                                'electric' => 'Ηλεκτρικό',
                                                'hybrid' => 'Υβριδικό',
                                                'gas' => 'Υγραέριο'
                                            ];
                                            echo $fuel_labels[$vehicle['fuel_type']] ?? htmlspecialchars($vehicle['fuel_type']);
                                        ?></span>
                                    </div>
                                    <div class="col">
                                        <small class="text-muted">Θύρες</small><br>
                                        <span class="badge bg-info"><?php echo $vehicle['doors_count']; ?></span>
                                    </div>
                                    <div class="col">
                                        <small class="text-muted">Ρόδες</small><br>
                                        <span class="badge bg-success"><?php echo $vehicle['wheels_count']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="text-muted small">
                                    <div><strong>Παραγωγή:</strong> <?php echo date('d/m/Y', strtotime($vehicle['manufacturing_date'])); ?></div>
                                    <div><strong>Κτήση:</strong> <?php echo htmlspecialchars($vehicle['acquisition_year']); ?></div>
                                    <div><strong>Καταχώρηση:</strong> <?php echo date('d/m/Y', strtotime($vehicle['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="btn-group w-100">
                                    <button class="btn btn-sm btn-outline-success" onclick="bookAppointment(<?php echo $vehicle['id']; ?>)">
                                        <i class="bi bi-calendar-plus"></i> Ραντεβού
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editVehicle(<?php echo $vehicle['id']; ?>)">
                                        <i class="bi bi-pencil"></i> Επεξεργασία
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewHistory(<?php echo $vehicle['id']; ?>)">
                                        <i class="bi bi-clock-history"></i> Ιστορικό
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="customer_dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Πίσω στο Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function bookAppointment(vehicleId) {
            window.location.href = '../appointments/book_appointment.php?vehicle_id=' + vehicleId;
        }

        function editVehicle(vehicleId) {
            alert('Επεξεργασία αυτοκινήτου ID: ' + vehicleId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }

        function viewHistory(vehicleId) {
            alert('Ιστορικό αυτοκινήτου ID: ' + vehicleId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }
    </script>
</body>
</html>