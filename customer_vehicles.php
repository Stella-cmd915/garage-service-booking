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
$show_form = isset($_GET['action']) && $_GET['action'] === 'add';

// Handle vehicle addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial_number = trim($_POST['serial_number']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $vehicle_type = $_POST['vehicle_type'];
    $fuel_type = $_POST['fuel_type'];
    $doors_count = $_POST['doors_count'];
    $wheels_count = $_POST['wheels_count'];
    $manufacturing_date = $_POST['manufacturing_date'];
    $acquisition_year = $_POST['acquisition_year'];
    
    // Validation
    if (empty($serial_number) || empty($brand) || empty($model) || empty($vehicle_type) || 
        empty($fuel_type) || empty($doors_count) || empty($wheels_count) || 
        empty($manufacturing_date) || empty($acquisition_year)) {
        $error_message = 'Παρακαλώ συμπληρώστε όλα τα πεδία.';
    } else {
        try {
            // Check if serial number already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE serial_number = ?");
            $stmt->execute([$serial_number]);
            
            if ($stmt->fetchColumn() > 0) {
                $error_message = 'Ο σειριακός αριθμός υπάρχει ήδη.';
            } else {
                // Insert vehicle
                $stmt = $pdo->prepare("
                    INSERT INTO vehicles (
                        customer_id, serial_number, brand, model, vehicle_type, fuel_type,
                        doors_count, wheels_count, manufacturing_date, acquisition_year, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $user['customer_id'], $serial_number, $brand, $model, $vehicle_type,
                    $fuel_type, $doors_count, $wheels_count, $manufacturing_date, $acquisition_year
                ]);
                
                $success_message = 'Το αυτοκίνητο προστέθηκε επιτυχώς!';
                $show_form = false;
                $_POST = []; // Clear form
            }
        } catch (PDOException $e) {
            $error_message = 'Σφάλμα κατά την προσθήκη του αυτοκινήτου: ' . $e->getMessage();
        }
    }
}

// Handle vehicle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $vehicle_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND customer_id = ?");
        $stmt->execute([$vehicle_id, $user['customer_id']]);
        $success_message = 'Το αυτοκίνητο διαγράφηκε επιτυχώς.';
    } catch (PDOException $e) {
        $error_message = 'Σφάλμα κατά τη διαγραφή του αυτοκινήτου.';
    }
}

// Get customer's vehicles
$stmt = $pdo->prepare("
    SELECT v.*, 
           COUNT(a.id) as appointment_count,
           MAX(a.appointment_date) as last_appointment
    FROM vehicles v
    LEFT JOIN appointments a ON v.id = a.vehicle_id
    WHERE v.customer_id = ?
    GROUP BY v.id
    ORDER BY v.created_at DESC
");
$stmt->execute([$user['customer_id']]);
$vehicles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Τα Αυτοκίνητά μου - AutoCare</title>
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
        .vehicle-card {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .vehicle-card:hover {
            transform: translateY(-2px);
        }
        .vehicle-icon {
            font-size: 3rem;
            color: #28a745;
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
                        <a class="nav-link active" href="customer_vehicles.php">
                            <i class="fas fa-car me-1"></i>Τα Αυτοκίνητά μου
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_appointments.php">
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-car me-2"></i>Τα Αυτοκίνητά μου
                        </h4>
                        <?php if (!$show_form): ?>
                        <a href="?action=add" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Νέο Αυτοκίνητο
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($show_form): ?>
                        <!-- Add Vehicle Form -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="mb-3">
                                    <i class="fas fa-plus-circle me-2"></i>Προσθήκη Νέου Αυτοκινήτου
                                </h5>
                                
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="brand" class="form-label">Μάρκα *</label>
                                            <input type="text" class="form-control" id="brand" name="brand" 
                                                   value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="model" class="form-label">Μοντέλο *</label>
                                            <input type="text" class="form-control" id="model" name="model" 
                                                   value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="serial_number" class="form-label">Σειριακός Αριθμός *</label>
                                            <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                                   value="<?php echo isset($_POST['serial_number']) ? htmlspecialchars($_POST['serial_number']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="vehicle_type" class="form-label">Τύπος Αυτοκινήτου *</label>
                                            <select class="form-control" id="vehicle_type" name="vehicle_type" required>
                                                <option value="">Επιλέξτε τύπο...</option>
                                                <option value="passenger" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'passenger') ? 'selected' : ''; ?>>Επιβατικό</option>
                                                <option value="truck" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'truck') ? 'selected' : ''; ?>>Φορτηγό</option>
                                                <option value="bus" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'bus') ? 'selected' : ''; ?>>Λεωφορείο</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="fuel_type" class="form-label">Τύπος Καυσίμου *</label>
                                            <select class="form-control" id="fuel_type" name="fuel_type" required>
                                                <option value="">Επιλέξτε καύσιμο...</option>
                                                <option value="electric" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'electric') ? 'selected' : ''; ?>>Ηλεκτροκίνητο</option>
                                                <option value="diesel" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'diesel') ? 'selected' : ''; ?>>Πετρέλαιο</option>
                                                <option value="lpg" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'lpg') ? 'selected' : ''; ?>>Υγραέριο</option>
                                                <option value="hybrid" <?php echo (isset($_POST['fuel_type']) && $_POST['fuel_type'] == 'hybrid') ? 'selected' : ''; ?>>Μεικτό</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="doors_count" class="form-label">Αριθμός Θυρών *</label>
                                            <select class="form-control" id="doors_count" name="doors_count" required>
                                                <option value="">Επιλέξτε...</option>
                                                <option value="2" <?php echo (isset($_POST['doors_count']) && $_POST['doors_count'] == '2') ? 'selected' : ''; ?>>2</option>
                                                <option value="4" <?php echo (isset($_POST['doors_count']) && $_POST['doors_count'] == '4') ? 'selected' : ''; ?>>4</option>
                                                <option value="5" <?php echo (isset($_POST['doors_count']) && $_POST['doors_count'] == '5') ? 'selected' : ''; ?>>5</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="wheels_count" class="form-label">Αριθμός Ροδών *</label>
                                            <input type="number" class="form-control" id="wheels_count" name="wheels_count" 
                                                   value="<?php echo isset($_POST['wheels_count']) ? htmlspecialchars($_POST['wheels_count']) : '4'; ?>" min="4" max="8" required>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="manufacturing_date" class="form-label">Έτος Παραγωγής *</label>
                                            <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" 
                                                   value="<?php echo isset($_POST['manufacturing_date']) ? htmlspecialchars($_POST['manufacturing_date']) : ''; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <label for="acquisition_year" class="form-label">Έτος Κτήσης *</label>
                                            <input type="number" class="form-control" id="acquisition_year" name="acquisition_year" 
                                                   value="<?php echo isset($_POST['acquisition_year']) ? htmlspecialchars($_POST['acquisition_year']) : date('Y'); ?>" min="1900" max="<?php echo date('Y'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <a href="customer_vehicles.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Ακύρωση
                                        </a>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Αποθήκευση
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <hr>
                        <?php endif; ?>

                        <!-- Vehicles List -->
                        <?php if (empty($vehicles)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-car-side fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Δεν έχετε καταχωρημένα αυτοκίνητα</h5>
                                <p class="text-muted">Προσθέστε το πρώτο σας αυτοκίνητο για να μπορείτε να κλείνετε ραντεβού.</p>
                                <?php if (!$show_form): ?>
                                <a href="?action=add" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Προσθήκη Αυτοκινήτου
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <div class="col-lg-6 col-xl-4 mb-4">
                                        <div class="card vehicle-card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-car me-2"></i>
                                                    <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                                                </h6>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="book_appointment.php?vehicle_id=<?php echo $vehicle['id']; ?>">
                                                            <i class="fas fa-calendar-plus me-2"></i>Νέο Ραντεβού
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="?delete=<?php echo $vehicle['id']; ?>" 
                                                               onclick="return confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το αυτοκίνητο;')">
                                                            <i class="fas fa-trash me-2"></i>Διαγραφή
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center mb-3">
                                                    <?php
                                                    $icon_map = [
                                                        'passenger' => 'fa-car',
                                                        'truck' => 'fa-truck',
                                                        'bus' => 'fa-bus'
                                                    ];
                                                    $icon = $icon_map[$vehicle['vehicle_type']] ?? 'fa-car';
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?> vehicle-icon"></i>
                                                </div>
                                                
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted">Σειριακός</small>
                                                        <div><strong><?php echo htmlspecialchars($vehicle['serial_number']); ?></strong></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Τύπος</small>
                                                        <div>
                                                            <?php 
                                                            $types = [
                                                                'passenger' => 'Επιβατικό',
                                                                'truck' => 'Φορτηγό', 
                                                                'bus' => 'Λεωφορείο'
                                                            ];
                                                            echo $types[$vehicle['vehicle_type']] ?? $vehicle['vehicle_type'];
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <hr class="my-3">
                                                
                                                <div class="row">
                                                    <div class="col-6 mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-gas-pump me-1"></i>Καύσιμο
                                                        </small>
                                                        <div>
                                                            <?php 
                                                            $fuels = [
                                                                'electric' => 'Ηλεκτρικό',
                                                                'diesel' => 'Πετρέλαιο',
                                                                'lpg' => 'Υγραέριο',
                                                                'hybrid' => 'Μεικτό'
                                                            ];
                                                            echo $fuels[$vehicle['fuel_type']] ?? $vehicle['fuel_type'];
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-door-open me-1"></i>Θύρες/Ρόδες
                                                        </small>
                                                        <div><?php echo $vehicle['doors_count'] . '/' . $vehicle['wheels_count']; ?></div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-calendar me-1"></i>Παραγωγή
                                                        </small>
                                                        <div><?php echo date('Y', strtotime($vehicle['manufacturing_date'])); ?></div>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-shopping-cart me-1"></i>Κτήση
                                                        </small>
                                                        <div><?php echo $vehicle['acquisition_year']; ?></div>
                                                    </div>
                                                </div>
                                                
                                                <hr class="my-3">
                                                
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <small class="text-muted">Ραντεβού</small>
                                                        <div><span class="badge bg-primary"><?php echo $vehicle['appointment_count']; ?></span></div>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted">Τελευταίο</small>
                                                        <div>
                                                            <?php if ($vehicle['last_appointment']): ?>
                                                                <?php echo date('d/m/Y', strtotime($vehicle['last_appointment'])); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">Ποτέ</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-grid mt-3">
                                                    <a href="book_appointment.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-calendar-plus me-2"></i>Νέο Ραντεβού
                                                    </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>