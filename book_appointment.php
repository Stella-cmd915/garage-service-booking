<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

$error_message = '';
$success_message = '';

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// For customers, get their customer record
$customer_id = null;
if ($user['role'] === 'customer') {
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $customer_record = $stmt->fetch();
    $customer_id = $customer_record['id'];
}

// Get all customers (for secretary)
$customers = [];
if ($user['role'] === 'secretary') {
    $stmt = $pdo->query("
        SELECT c.id, u.first_name, u.last_name, u.email 
        FROM customers c 
        JOIN users u ON c.user_id = u.id 
        WHERE u.is_active = 1
        ORDER BY u.first_name, u.last_name
    ");
    $customers = $stmt->fetchAll();
}

// Get available mechanics
$stmt = $pdo->query("
    SELECT m.id, u.first_name, u.last_name, m.specialty 
    FROM mechanics m 
    JOIN users u ON m.user_id = u.id 
    WHERE u.is_active = 1
    ORDER BY u.first_name, u.last_name
");
$mechanics = $stmt->fetchAll();

// Function to check mechanic availability
function isMechanicAvailable($pdo, $mechanic_id, $date, $time) {
    // A mechanic is busy for 2 hours from appointment time
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE mechanic_id = ? 
        AND appointment_date = ? 
        AND (
            (appointment_time <= ? AND ADDTIME(appointment_time, '02:00:00') > ?) OR
            (appointment_time < ADDTIME(?, '02:00:00') AND appointment_time >= ?)
        )
        AND status NOT IN ('cancelled')
    ");
    $stmt->execute([$mechanic_id, $date, $time, $time, $time, $time]);
    return $stmt->fetch()['count'] == 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_customer_id = $user['role'] === 'customer' ? $customer_id : $_POST['customer_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason = $_POST['reason'];
    $problem_description = $_POST['problem_description'] ?? '';
    
    // Validation
    if (empty($selected_customer_id) || empty($vehicle_id) || empty($appointment_date) || empty($appointment_time) || empty($reason)) {
        $error_message = 'Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.';
    } else {
        // Validate date and time
        $appointment_datetime = strtotime($appointment_date . ' ' . $appointment_time);
        $now = time();
        
        if ($appointment_datetime <= $now) {
            $error_message = 'Το ραντεβού πρέπει να είναι στο μέλλον.';
        } else {
            // Validate business hours (08:00 - 16:00)
            $hour = date('H', strtotime($appointment_time));
            if ($hour < 8 || $hour >= 16) {
                $error_message = 'Το ωράριο λειτουργίας είναι από 08:00 έως 16:00.';
            } else {
                // Find available mechanic
                $available_mechanic = null;
                foreach ($mechanics as $mechanic) {
                    if (isMechanicAvailable($pdo, $mechanic['id'], $appointment_date, $appointment_time)) {
                        $available_mechanic = $mechanic['id'];
                        break;
                    }
                }
                
                if (!$available_mechanic) {
                    $error_message = 'Δεν υπάρχει διαθέσιμος μηχανικός για την επιλεγμένη ώρα. Παρακαλώ επιλέξτε άλλη ώρα.';
                } else {
                    // Create appointment
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO appointments (
                                customer_id, vehicle_id, mechanic_id, appointment_date, 
                                appointment_time, reason, problem_description, status, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'created', NOW())
                        ");
                        
                        $stmt->execute([
                            $selected_customer_id,
                            $vehicle_id,
                            $available_mechanic,
                            $appointment_date,
                            $appointment_time,
                            $reason,
                            $problem_description
                        ]);
                        
                        $appointment_id = $pdo->lastInsertId();
                        
                        $success_message = "Το ραντεβού δημιουργήθηκε επιτυχώς! (ID: #$appointment_id)";
                        
                        // Get assigned mechanic name for confirmation
                        $stmt = $pdo->prepare("
                            SELECT u.first_name, u.last_name 
                            FROM mechanics m 
                            JOIN users u ON m.user_id = u.id 
                            WHERE m.id = ?
                        ");
                        $stmt->execute([$available_mechanic]);
                        $assigned_mechanic = $stmt->fetch();
                        
                        $success_message .= " Ανατέθηκε στον μηχανικό: " . $assigned_mechanic['first_name'] . ' ' . $assigned_mechanic['last_name'];
                        
                    } catch (PDOException $e) {
                        $error_message = 'Σφάλμα κατά τη δημιουργία του ραντεβού.';
                    }
                }
            }
        }
    }
}

// Get customer's vehicles (AJAX endpoint)
if (isset($_GET['get_vehicles']) && isset($_GET['customer_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY brand, model");
    $stmt->execute([$_GET['customer_id']]);
    $vehicles = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode($vehicles);
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Κλείσιμο Ραντεβού - AutoCare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            background-color: #e9ecef;
            color: #6c757d;
            font-weight: bold;
        }
        .step.active {
            background-color: #667eea;
            color: white;
        }
        .step.completed {
            background-color: #28a745;
            color: white;
        }
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .availability-indicator {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9em;
            display: inline-block;
            margin-left: 10px;
        }
        .available {
            background-color: #d4edda;
            color: #155724;
        }
        .unavailable {
            background-color: #f8d7da;
            color: #721c24;
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
            
            <div class="navbar-nav ms-auto">
                <?php if ($user['role'] === 'customer'): ?>
                    <a class="nav-link" href="customer_dashboard.php">
                        <i class="fas fa-arrow-left me-1"></i>Πίσω στον Χώρο μου
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-arrow-left me-1"></i>Πίσω στο Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <h2><i class="fas fa-calendar-plus me-2"></i>Κλείσιμο Νέου Ραντεβού</h2>
                    <p class="text-muted">Συμπληρώστε τα στοιχεία για το ραντεβού σας</p>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <div class="mt-2">
                        <?php if ($user['role'] === 'customer'): ?>
                            <a href="customer_appointments.php" class="btn btn-success btn-sm">Προβολή Ραντεβού</a>
                            <a href="customer_dashboard.php" class="btn btn-outline-success btn-sm">Πίσω στον Χώρο μου</a>
                        <?php else: ?>
                            <a href="appointments.php" class="btn btn-success btn-sm">Προβολή Ραντεβού</a>
                            <a href="dashboard.php" class="btn btn-outline-success btn-sm">Πίσω στο Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="appointmentForm">
                    <!-- Customer Selection (for secretary) -->
                    <?php if ($user['role'] === 'secretary'): ?>
                    <div class="form-section">
                        <h5><i class="fas fa-user me-2"></i>Επιλογή Πελάτη</h5>
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Πελάτης *</label>
                            <select class="form-control" id="customer_id" name="customer_id" required onchange="loadCustomerVehicles()">
                                <option value="">Επιλέξτε πελάτη...</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>">
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        (<?php echo htmlspecialchars($customer['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Vehicle Selection -->
                    <div class="form-section">
                        <h5><i class="fas fa-car me-2"></i>Επιλογή Αυτοκινήτου</h5>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Αυτοκίνητο *</label>
                            <select class="form-control" id="vehicle_id" name="vehicle_id" required>
                                <option value="">Επιλέξτε αυτοκίνητο...</option>
                                <?php if ($user['role'] === 'customer'): ?>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY brand, model");
                                    $stmt->execute([$customer_id]);
                                    $customer_vehicles = $stmt->fetchAll();
                                    foreach ($customer_vehicles as $vehicle):
                                    ?>
                                        <option value="<?php echo $vehicle['id']; ?>">
                                            <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['serial_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if ($user['role'] === 'customer' && empty($customer_vehicles)): ?>
                                <div class="mt-2 text-muted">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Δεν έχετε καταχωρημένα αυτοκίνητα. 
                                    <a href="customer_vehicles.php?action=add">Προσθέστε ένα αυτοκίνητο</a> πρώτα.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Date and Time Selection -->
                    <div class="form-section">
                        <h5><i class="fas fa-calendar me-2"></i>Ημερομηνία και Ώρα</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_date" class="form-label">Ημερομηνία *</label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                           min="<?php echo date('Y-m-d', strtotime('tomorrow')); ?>" 
                                           required onchange="checkAvailability()">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="appointment_time" class="form-label">Ώρα *</label>
                                    <select class="form-control" id="appointment_time" name="appointment_time" required onchange="checkAvailability()">
                                        <option value="">Επιλέξτε ώρα...</option>
                                        <?php for ($hour = 8; $hour < 16; $hour++): ?>
                                            <option value="<?php echo sprintf('%02d:00:00', $hour); ?>"><?php echo sprintf('%02d:00', $hour); ?></option>
                                            <option value="<?php echo sprintf('%02d:30:00', $hour); ?>"><?php echo sprintf('%02d:30', $hour); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div id="availabilityStatus" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Details -->
                    <div class="form-section">
                        <h5><i class="fas fa-wrench me-2"></i>Λεπτομέρειες Υπηρεσίας</h5>
                        <div class="mb-3">
                            <label for="reason" class="form-label">Λόγος Ραντεβού *</label>
                            <select class="form-control" id="reason" name="reason" required onchange="toggleProblemDescription()">
                                <option value="">Επιλέξτε λόγο...</option>
                                <option value="service">Σέρβις</option>
                                <option value="repair">Επιδιόρθωση</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="problemDescriptionDiv" style="display: none;">
                            <label for="problem_description" class="form-label">Περιγραφή Προβλήματος</label>
                            <textarea class="form-control" id="problem_description" name="problem_description" 
                                      rows="3" placeholder="Περιγράψτε το πρόβλημα με το αυτοκίνητο..."></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-calendar-plus me-2"></i>Κλείσιμο Ραντεβού
                        </button>
                        <div class="mt-3">
                            <?php if ($user['role'] === 'customer'): ?>
                                <a href="customer_dashboard.php" class="btn btn-outline-secondary">Ακύρωση</a>
                            <?php else: ?>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Ακύρωση</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadCustomerVehicles() {
            const customerId = document.getElementById('customer_id').value;
            const vehicleSelect = document.getElementById('vehicle_id');
            
            if (!customerId) {
                vehicleSelect.innerHTML = '<option value="">Επιλέξτε αυτοκίνητο...</option>';
                return;
            }
            
            fetch(`?get_vehicles=1&customer_id=${customerId}`)
                .then(response => response.json())
                .then(vehicles => {
                    vehicleSelect.innerHTML = '<option value="">Επιλέξτε αυτοκίνητο...</option>';
                    vehicles.forEach(vehicle => {
                        const option = document.createElement('option');
                        option.value = vehicle.id;
                        option.textContent = `${vehicle.brand} ${vehicle.model} (${vehicle.serial_number})`;
                        vehicleSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading vehicles:', error);
                });
        }
        
        function checkAvailability() {
            const date = document.getElementById('appointment_date').value;
            const time = document.getElementById('appointment_time').value;
            const statusDiv = document.getElementById('availabilityStatus');
            
            if (!date || !time) {
                statusDiv.innerHTML = '';
                return;
            }
            
            // Simple availability check (in real implementation, this would be an AJAX call)
            statusDiv.innerHTML = '<div class="availability-indicator available"><i class="fas fa-check-circle me-1"></i>Διαθέσιμο</div>';
        }
        
        function toggleProblemDescription() {
            const reason = document.getElementById('reason').value;
            const problemDiv = document.getElementById('problemDescriptionDiv');
            const problemTextarea = document.getElementById('problem_description');
            
            if (reason === 'repair') {
                problemDiv.style.display = 'block';
                problemTextarea.required = true;
            } else {
                problemDiv.style.display = 'none';
                problemTextarea.required = false;
                problemTextarea.value = '';
            }
        }
        
        // Form validation
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            const date = document.getElementById('appointment_date').value;
            const time = document.getElementById('appointment_time').value;
            
            if (!date || !time) {
                e.preventDefault();
                alert('Παρακαλώ επιλέξτε ημερομηνία και ώρα.');
                return;
            }
            
            const appointmentDateTime = new Date(date + ' ' + time);
            const now = new Date();
            
            if (appointmentDateTime <= now) {
                e.preventDefault();
                alert('Το ραντεβού πρέπει να είναι στο μέλλον.');
                return;
            }
            
            const hour = parseInt(time.split(':')[0]);
            if (hour < 8 || hour >= 16) {
                e.preventDefault();
                alert('Το ωράριο λειτουργίας είναι από 08:00 έως 16:00.');
                return;
            }
        });
    </script>
</body>
</html>