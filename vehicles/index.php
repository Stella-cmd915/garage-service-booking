<?php
session_start();

// ΠΡΟΣΩΡΙΝΟ - Προσομοίωση σύνδεσης ως γραμματέας
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'secretary';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Secretary';

require_once '../config/database.php';

// Handle delete request
if (isset($_POST['delete_vehicle_id'])) {
    try {
        $vehicle_id = $_POST['delete_vehicle_id'];
        
        // Check if vehicle has appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) as appointment_count FROM appointments WHERE vehicle_id = ?");
        $stmt->execute([$vehicle_id]);
        $appointment_count = $stmt->fetch()['appointment_count'];
        
        if ($appointment_count > 0) {
            // Delete related appointments first
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE vehicle_id = ?");
            $stmt->execute([$vehicle_id]);
        }
        
        // Delete vehicle
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicle_id]);
        
        $_SESSION['success'] = "Το αυτοκίνητο διαγράφηκε επιτυχώς!" . 
                              ($appointment_count > 0 ? " (Διαγράφηκαν και $appointment_count σχετικά ραντεβού)" : "");
    } catch (Exception $e) {
        $_SESSION['error'] = "Σφάλμα κατά τη διαγραφή: " . $e->getMessage();
    }
    header('Location: index.php');
    exit();
}

// Get search parameters
$search_brand = $_GET['brand'] ?? '';
$search_model = $_GET['model'] ?? '';
$search_serial = $_GET['serial'] ?? '';
$search_customer = $_GET['customer'] ?? '';
$search_vehicle_type = $_GET['vehicle_type'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search_brand)) {
    $where_conditions[] = "v.brand LIKE ?";
    $params[] = '%' . $search_brand . '%';
}

if (!empty($search_model)) {
    $where_conditions[] = "v.model LIKE ?";
    $params[] = '%' . $search_model . '%';
}

if (!empty($search_serial)) {
    $where_conditions[] = "v.serial_number LIKE ?";
    $params[] = '%' . $search_serial . '%';
}

if (!empty($search_customer)) {
    $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.vat_number LIKE ?)";
    $params[] = '%' . $search_customer . '%';
    $params[] = '%' . $search_customer . '%';
    $params[] = '%' . $search_customer . '%';
}

if (!empty($search_vehicle_type)) {
    $where_conditions[] = "v.vehicle_type = ?";
    $params[] = $search_vehicle_type;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get vehicles with pagination
$page = $_GET['page'] ?? 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    // Count total vehicles
    $count_sql = "
        SELECT COUNT(*) as total
        FROM vehicles v
        JOIN users c ON v.customer_id = c.id
        $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_vehicles = $stmt->fetch()['total'];
    $total_pages = ceil($total_vehicles / $per_page);

    // Get vehicles
    $sql = "
        SELECT 
            v.*,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.vat_number,
            (SELECT COUNT(*) FROM appointments WHERE vehicle_id = v.id) as appointment_count
        FROM vehicles v
        JOIN users c ON v.customer_id = c.id
        $where_clause
        ORDER BY v.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll();
} catch (Exception $e) {
    $vehicles = [];
    $total_vehicles = 0;
    $total_pages = 0;
    error_log("Error loading vehicles: " . $e->getMessage());
}

// Get customers for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY first_name, last_name");
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
            transition: transform 0.2s ease;
            border-left: 4px solid #007bff;
        }
        .vehicle-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .vehicle-type-badge {
            font-size: 0.8rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }
        .search-card {
            background: #f8f9fa;
            border-radius: 12px;
        }
        .action-buttons .btn {
            margin: 2px;
        }
        .vehicle-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
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
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-car-front"></i> Διαχείριση Αυτοκινήτων</h2>
                    <div class="btn-group">
                        <a href="add_vehicle.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Νέο Αυτοκίνητο
                        </a>
                        <a href="bulk_import.php" class="btn btn-success">
                            <i class="bi bi-upload"></i> Μαζική Εισαγωγή
                        </a>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="stats-card p-3">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h4><?php echo $total_vehicles; ?></h4>
                                    <small>Συνολικά Αυτοκίνητα</small>
                                </div>
                                <div class="col-md-3">
                                    <h4><?php echo count(array_filter($vehicles, fn($v) => $v['vehicle_type'] == 'passenger')); ?></h4>
                                    <small>Επιβατικά</small>
                                </div>
                                <div class="col-md-3">
                                    <h4><?php echo count(array_filter($vehicles, fn($v) => $v['vehicle_type'] == 'truck')); ?></h4>
                                    <small>Φορτηγά</small>
                                </div>
                                <div class="col-md-3">
                                    <h4><?php echo count($customers); ?></h4>
                                    <small>Ενεργοί Πελάτες</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Filters -->
                <div class="search-card p-3 mb-4">
                    <h5><i class="bi bi-funnel"></i> Φίλτρα Αναζήτησης</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="brand" placeholder="Μάρκα" value="<?php echo htmlspecialchars($search_brand); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="model" placeholder="Μοντέλο" value="<?php echo htmlspecialchars($search_model); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="serial" placeholder="Σειριακός Αριθμός" value="<?php echo htmlspecialchars($search_serial); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="customer" placeholder="Πελάτης/ΑΦΜ" value="<?php echo htmlspecialchars($search_customer); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="vehicle_type">
                                <option value="">Όλοι οι Τύποι</option>
                                <option value="passenger" <?php echo $search_vehicle_type == 'passenger' ? 'selected' : ''; ?>>Επιβατικό</option>
                                <option value="truck" <?php echo $search_vehicle_type == 'truck' ? 'selected' : ''; ?>>Φορτηγό</option>
                                <option value="bus" <?php echo $search_vehicle_type == 'bus' ? 'selected' : ''; ?>>Λεωφορείο</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-search"></i> Αναζήτηση
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Vehicles List -->
                <?php if (empty($vehicles)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-car-front display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">Δεν βρέθηκαν αυτοκίνητα</h4>
                        <p class="text-muted">Δεν υπάρχουν αυτοκίνητα που να ταιριάζουν με τα κριτήρια αναζήτησης.</p>
                        <a href="add_vehicle.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Προσθήκη Πρώτου Αυτοκινήτου
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($vehicles as $vehicle): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card vehicle-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                                                </h5>
                                                <span class="badge bg-<?php 
                                                    echo match($vehicle['vehicle_type']) {
                                                        'passenger' => 'primary',
                                                        'truck' => 'warning',
                                                        'bus' => 'info',
                                                        default => 'secondary'
                                                    };
                                                ?> vehicle-type-badge">
                                                    <?php 
                                                    echo match($vehicle['vehicle_type']) {
                                                        'passenger' => 'Επιβατικό',
                                                        'truck' => 'Φορτηγό', 
                                                        'bus' => 'Λεωφορείο',
                                                        default => ucfirst($vehicle['vehicle_type'])
                                                    };
                                                    ?>
                                                </span>
                                            </div>
                                            <i class="bi bi-car-front text-muted"></i>
                                        </div>

                                        <div class="mb-3">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-hash"></i> <?php echo htmlspecialchars($vehicle['serial_number']); ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($vehicle['customer_name']); ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-fuel-pump"></i> <?php 
                                                echo match($vehicle['fuel_type']) {
                                                    'electric' => 'Ηλεκτροκίνητο',
                                                    'diesel' => 'Πετρέλαιο',
                                                    'gas' => 'Υγραέριο',
                                                    'hybrid' => 'Υβριδικό',
                                                    default => 'Βενζίνη'
                                                };
                                                ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-calendar"></i> Έτος: <?php echo $vehicle['year_acquired']; ?>
                                            </small>
                                        </div>

                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <small class="text-muted d-block">Θύρες</small>
                                                <strong><?php echo $vehicle['doors']; ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Ρόδες</small>
                                                <strong><?php echo $vehicle['wheels']; ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Ραντεβού</small>
                                                <strong><?php echo $vehicle['appointment_count']; ?></strong>
                                            </div>
                                        </div>

                                        <div class="action-buttons text-center">
                                            <a href="edit_vehicle.php?id=<?php echo $vehicle['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Επεξεργασία">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    title="Προβολή" onclick="viewVehicle(<?php echo $vehicle['id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    title="Διαγραφή" onclick="confirmDelete(<?php echo $vehicle['id']; ?>, '<?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <nav aria-label="Σελιδοποίηση αυτοκινήτων">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vehicle Details Modal -->
    <div class="modal fade" id="vehicleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-car-front"></i> Στοιχεία Αυτοκινήτου</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="vehicleDetails">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="delete_vehicle_id" id="deleteVehicleId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewVehicle(vehicleId) {
            fetch(`ajax_handlers.php?action=get_vehicle&id=${vehicleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('vehicleDetails').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('vehicleModal')).show();
                    } else {
                        alert('Σφάλμα φόρτωσης στοιχείων αυτοκινήτου');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Σφάλμα φόρτωσης στοιχείων αυτοκινήτου');
                });
        }

        function confirmDelete(vehicleId, vehicleName) {
            if (confirm(`Είστε σίγουροι ότι θέλετε να διαγράψετε το αυτοκίνητο "${vehicleName}";\n\nΣημείωση: Θα διαγραφούν και όλα τα σχετικά ραντεβού.`)) {
                document.getElementById('deleteVehicleId').value = vehicleId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>