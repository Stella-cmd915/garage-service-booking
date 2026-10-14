<?php
session_start();

require_once '../config/database.php';

// Handle vehicle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    
    try {
        switch ($action) {
            case 'add_vehicle':
                $customer_id = $_POST['customer_id'];
                $serial_number = $_POST['serial_number'];
                $brand = $_POST['brand'];
                $model = $_POST['model'];
                $vehicle_type = $_POST['vehicle_type'];
                $fuel_type = $_POST['fuel_type'];
                $doors_count = $_POST['doors_count'];
                $wheels_count = $_POST['wheels_count'];
                $manufacturing_date = $_POST['manufacturing_date'];
                $acquisition_year = $_POST['acquisition_year'];
                
                // Check for duplicate serial number
                $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE serial_number = ?");
                $stmt->execute([$serial_number]);
                if ($stmt->fetch()) {
                    throw new Exception("Ο σειριακός αριθμός υπάρχει ήδη.");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO vehicles (customer_id, serial_number, brand, model, vehicle_type, 
                                        fuel_type, doors_count, wheels_count, manufacturing_date, acquisition_year, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $customer_id, $serial_number, $brand, $model, $vehicle_type,
                    $fuel_type, $doors_count, $wheels_count, $manufacturing_date, $acquisition_year
                ]);
                
                $_SESSION['success'] = "Το αυτοκίνητο προστέθηκε επιτυχώς.";
                break;
                
            case 'delete_vehicle':
                // Delete vehicle and related appointments
                $pdo->beginTransaction();
                
                // First, delete related appointments if table exists
                try {
                    $stmt = $pdo->prepare("DELETE FROM appointments WHERE vehicle_id = ?");
                    $stmt->execute([$vehicle_id]);
                } catch (Exception $e) {
                    // appointments table might not exist yet
                }
                
                // Then delete the vehicle
                $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
                $stmt->execute([$vehicle_id]);
                
                $pdo->commit();
                $_SESSION['success'] = "Το αυτοκίνητο διαγράφηκε επιτυχώς.";
                break;
                
            case 'update_vehicle':
                $brand = $_POST['brand'];
                $model = $_POST['model'];
                $vehicle_type = $_POST['vehicle_type'];
                $fuel_type = $_POST['fuel_type'];
                $doors_count = $_POST['doors_count'];
                $wheels_count = $_POST['wheels_count'];
                $manufacturing_date = $_POST['manufacturing_date'];
                $acquisition_year = $_POST['acquisition_year'];
                
                $stmt = $pdo->prepare("
                    UPDATE vehicles 
                    SET brand = ?, model = ?, vehicle_type = ?, fuel_type = ?, 
                        doors_count = ?, wheels_count = ?, manufacturing_date = ?, acquisition_year = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $brand, $model, $vehicle_type, $fuel_type,
                    $doors_count, $wheels_count, $manufacturing_date, $acquisition_year, $vehicle_id
                ]);
                
                $_SESSION['success'] = "Το αυτοκίνητο ενημερώθηκε επιτυχώς.";
                break;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: manage_vehicles.php');
    exit();
}

// Get search parameters
$search_serial = $_GET['search_serial'] ?? '';
$search_brand = $_GET['search_brand'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';
$vehicle_type_filter = $_GET['vehicle_type_filter'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($search_serial)) {
    $where_conditions[] = "v.serial_number LIKE ?";
    $params[] = "%{$search_serial}%";
}

if (!empty($search_brand)) {
    $where_conditions[] = "v.brand LIKE ?";
    $params[] = "%{$search_brand}%";
}

if (!empty($search_customer)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%{$search_customer}%";
    $params[] = "%{$search_customer}%";
}

if (!empty($vehicle_type_filter)) {
    $where_conditions[] = "v.vehicle_type = ?";
    $params[] = $vehicle_type_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get vehicles with customer info
$query = "
    SELECT v.*, 
           CONCAT(u.first_name, ' ', u.last_name) as customer_name,
           u.id as customer_user_id
    FROM vehicles v
    LEFT JOIN users u ON v.customer_id = u.id
    {$where_clause}
    ORDER BY v.created_at DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();
} catch (Exception $e) {
    $vehicles = [];
    $_SESSION['info'] = "Πρόβλημα με τη βάση δεδομένων: " . $e->getMessage();
}

// Get customers for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'customer' ORDER BY first_name, last_name");
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = [];
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Αυτοκινήτων - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .vehicle-card {
            border-left: 4px solid #007bff;
            transition: all 0.2s;
        }
        .vehicle-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .search-form { 
            background-color: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        .vehicle-type-badge {
            font-size: 0.85rem;
        }
    </style>
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
                    <h2><i class="bi bi-car-front"></i> Διαχείριση Αυτοκινήτων</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                        <i class="bi bi-plus-circle"></i> Νέο Αυτοκίνητο
                    </button>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['info'])): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search Form -->
                <form method="GET" class="search-form">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="search_serial" class="form-label">Σειριακός Αριθμός</label>
                            <input type="text" class="form-control" id="search_serial" name="search_serial" 
                                   value="<?php echo htmlspecialchars($search_serial); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="search_brand" class="form-label">Μάρκα</label>
                            <input type="text" class="form-control" id="search_brand" name="search_brand" 
                                   value="<?php echo htmlspecialchars($search_brand); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="search_customer" class="form-label">Πελάτης</label>
                            <input type="text" class="form-control" id="search_customer" name="search_customer" 
                                   value="<?php echo htmlspecialchars($search_customer); ?>" 
                                   placeholder="Όνομα ή επίθετο">
                        </div>
                        <div class="col-md-3">
                            <label for="vehicle_type_filter" class="form-label">Τύπος Αυτοκινήτου</label>
                            <select class="form-control" id="vehicle_type_filter" name="vehicle_type_filter">
                                <option value="">Όλοι</option>
                                <option value="passenger" <?php echo $vehicle_type_filter === 'passenger' ? 'selected' : ''; ?>>Επιβατικό</option>
                                <option value="truck" <?php echo $vehicle_type_filter === 'truck' ? 'selected' : ''; ?>>Φορτηγό</option>
                                <option value="bus" <?php echo $vehicle_type_filter === 'bus' ? 'selected' : ''; ?>>Λεωφορείο</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Αναζήτηση
                            </button>
                            <a href="manage_vehicles.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Επαναφορά
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Vehicles Display -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Αυτοκίνητα (<?php echo count($vehicles); ?> αποτελέσματα)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($vehicles)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-car-front" style="font-size: 3rem; color: #ccc;"></i>
                                <p class="mt-3 text-muted">Δεν βρέθηκαν αυτοκίνητα με τα συγκεκριμένα κριτήρια.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                                    <i class="bi bi-plus-circle"></i> Προσθήκη πρώτου αυτοκινήτου
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row g-3 p-3">
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card vehicle-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">
                                                        <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                                                    </h6>
                                                    <span class="badge vehicle-type-badge bg-<?php 
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
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">Σειριακός:</small><br>
                                                    <code><?php echo htmlspecialchars($vehicle['serial_number']); ?></code>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted">Ιδιοκτήτης:</small><br>
                                                    <strong><?php echo htmlspecialchars($vehicle['customer_name'] ?? 'Άγνωστος'); ?></strong>
                                                </div>
                                                
                                                <div class="row text-center mb-3">
                                                    <div class="col">
                                                        <small class="text-muted">Κίνηση</small><br>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <div class="col">
                                                        <small class="text-muted">Θύρες</small><br>
                                                        <span class="badge bg-info"><?php echo $vehicle['doors_count'] ?? 'N/A'; ?></span>
                                                    </div>
                                                    <div class="col">
                                                        <small class="text-muted">Ρόδες</small><br>
                                                        <span class="badge bg-success"><?php echo $vehicle['wheels_count'] ?? 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="text-muted small mb-3">
                                                    <div>Παραγωγή: <?php echo isset($vehicle['manufacturing_date']) ? date('d/m/Y', strtotime($vehicle['manufacturing_date'])) : 'N/A'; ?></div>
                                                    <div>Κτήση: <?php echo htmlspecialchars($vehicle['acquisition_year'] ?? 'N/A'); ?></div>
                                                </div>
                                                
                                                <div class="btn-group w-100" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewVehicle(<?php echo $vehicle['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editVehicle(<?php echo $vehicle['id']; ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" onclick="bookAppointment(<?php echo $vehicle['id']; ?>)">
                                                        <i class="bi bi-calendar-plus"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteVehicle(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Προσθήκη Νέου Αυτοκινήτου</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_vehicle">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customer_id" class="form-label">Πελάτης <span class="text-danger">*</span></label>
                                    <select class="form-control" id="customer_id" name="customer_id" required>
                                        <option value="">Επιλέξτε πελάτη...</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>">
                                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="serial_number" class="form-label">Σειριακός Αριθμός <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Μάρκα <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="brand" name="brand" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="model" class="form-label">Μοντέλο <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="model" name="model" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="vehicle_type" class="form-label">Τύπος Αυτοκινήτου <span class="text-danger">*</span></label>
                                    <select class="form-control" id="vehicle_type" name="vehicle_type" required>
                                        <option value="">Επιλέξτε τύπο...</option>
                                        <option value="passenger">Επιβατικό</option>
                                        <option value="truck">Φορτηγό</option>
                                        <option value="bus">Λεωφορείο</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fuel_type" class="form-label">Είδος Κίνησης <span class="text-danger">*</span></label>
                                    <select class="form-control" id="fuel_type" name="fuel_type" required>
                                        <option value="">Επιλέξτε κίνηση...</option>
                                        <option value="electric">Ηλεκτροκίνητο</option>
                                        <option value="diesel">Πετρελαιοκίνητο</option>
                                        <option value="gas">Κινούμενο με υγραέριο</option>
                                        <option value="hybrid">Μεικτό</option>
                                        <option value="petrol">Βενζίνη</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="doors_count" class="form-label">Αριθμός Θυρών <span class="text-danger">*</span></label>
                                    <select class="form-control" id="doors_count" name="doors_count" required>
                                        <option value="">Επιλέξτε...</option>
                                        <option value="2">2 θύρες</option>
                                        <option value="3">3 θύρες</option>
                                        <option value="4">4 θύρες</option>
                                        <option value="5">5 θύρες</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="wheels_count" class="form-label">Αριθμός Ροδών <span class="text-danger">*</span></label>
                                    <select class="form-control" id="wheels_count" name="wheels_count" required>
                                        <option value="">Επιλέξτε...</option>
                                        <option value="4">4 ρόδες</option>
                                        <option value="6">6 ρόδες</option>
                                        <option value="8">8 ρόδες</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="manufacturing_date" class="form-label">Ημερομηνία Παραγωγής <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="acquisition_year" class="form-label">Έτος Κτήσης <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="acquisition_year" name="acquisition_year" 
                                           min="1900" max="<?php echo date('Y'); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                        <button type="submit" class="btn btn-primary">Προσθήκη Αυτοκινήτου</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewVehicle(vehicleId) {
            alert('Προβολή αυτοκινήτου ID: ' + vehicleId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }

        function editVehicle(vehicleId) {
            alert('Επεξεργασία αυτοκινήτου ID: ' + vehicleId + '\n(Θα υλοποιηθεί σε επόμενο στάδιο)');
        }

        function bookAppointment(vehicleId) {
            alert('Κλείσιμο ραντεβού για αυτοκίνητο ID: ' + vehicleId + '\n(Θα υλοποιηθεί μαζί με το Appointment System)');
        }

        function deleteVehicle(vehicleId, vehicleName) {
            if (confirm(`Είστε βέβαιοι ότι θέλετε να διαγράψετε το αυτοκίνητο "${vehicleName}"?\nΘα διαγραφούν και όλα τα σχετικά ραντεβού.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_vehicle">
                    <input type="hidden" name="vehicle_id" value="${vehicleId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>