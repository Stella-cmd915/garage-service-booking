<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';

// Έλεγχος αν υπάρχει το role στο session
if (!isset($_SESSION['role'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        $_SESSION['role'] = $user_data['role'] ?? 'customer';
    } catch (Exception $e) {
        $_SESSION['role'] = 'customer';
        error_log("Error loading user role: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customer_id = $_POST['customer_id'];
        $serial_number = trim($_POST['serial_number']);
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $vehicle_type = $_POST['vehicle_type'];
        $fuel_type = $_POST['fuel_type'];
        $doors_count = (int)$_POST['doors_count'];
        $wheels_count = (int)$_POST['wheels_count'];
        $manufacturing_date = $_POST['manufacturing_date'];
        $acquisition_year = (int)$_POST['acquisition_year'];
        
        // Validation
        $errors = [];
        
        if (empty($customer_id)) $errors[] = "Επιλέξτε πελάτη";
        if (empty($brand)) $errors[] = "Η μάρκα είναι υποχρεωτική";
        if (empty($model)) $errors[] = "Το μοντέλο είναι υποχρεωτικό";
        if (empty($serial_number)) $errors[] = "Ο σειριακός αριθμός είναι υποχρεωτικός";
        if (empty($vehicle_type)) $errors[] = "Επιλέξτε τύπο αυτοκινήτου";
        if (empty($fuel_type)) $errors[] = "Επιλέξτε τύπο καυσίμου";
        
        if ($doors_count < 2 || $doors_count > 6) $errors[] = "Οι θύρες πρέπει να είναι μεταξύ 2 και 6";
        if ($wheels_count < 4 || $wheels_count > 18) $errors[] = "Οι ρόδες πρέπει να είναι μεταξύ 4 και 18";
        if ($acquisition_year < 1900 || $acquisition_year > date('Y')) 
            $errors[] = "Μη έγκυρο έτος κτήσης";
        
        // Check for duplicate serial number
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vehicles WHERE serial_number = ?");
        $stmt->execute([$serial_number]);
        if ($stmt->fetch()['count'] > 0) {
            $errors[] = "Ο σειριακός αριθμός υπάρχει ήδη";
        }
        
        // For customers, ensure they can only add vehicles to themselves
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer' && $customer_id != $_SESSION['user_id']) {
            $errors[] = "Δεν μπορείτε να προσθέσετε αυτοκίνητο σε άλλον πελάτη";
        }
        
        if (empty($errors)) {
            // Insert new vehicle
            $stmt = $pdo->prepare("
                INSERT INTO vehicles (
                    customer_id, serial_number, brand, model, vehicle_type, fuel_type,
                    doors_count, wheels_count, manufacturing_date, acquisition_year, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $customer_id, $serial_number, $brand, $model, $vehicle_type, $fuel_type,
                $doors_count, $wheels_count, $manufacturing_date, $acquisition_year
            ]);
            
            $_SESSION['success'] = "Το αυτοκίνητο προστέθηκε επιτυχώς!";
            
            // Redirect based on role
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
                header('Location: customer_vehicles.php');
            } else {
                header('Location: manage_vehicles.php');
            }
            exit();
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Σφάλμα κατά την αποθήκευση: " . $e->getMessage();
    }
}

// Get customers for dropdown based on user role
try {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
        // Customer sees only themselves
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, c.tax_number 
            FROM users u 
            JOIN customers c ON u.id = c.user_id 
            WHERE u.id = ? AND u.role = 'customer' AND u.is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        // Secretary/admin sees all customers
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, c.tax_number 
            FROM users u 
            JOIN customers c ON u.id = c.user_id 
            WHERE u.role = 'customer' AND u.is_active = 1 
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
    }
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = [];
    error_log("Error loading customers: " . $e->getMessage());
}

// Pre-fill form data if there were validation errors
$form_data = [
    'customer_id' => $_POST['customer_id'] ?? (
        (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') ? $_SESSION['user_id'] : ''
    ),
    'brand' => $_POST['brand'] ?? '',
    'model' => $_POST['model'] ?? '',
    'serial_number' => $_POST['serial_number'] ?? '',
    'vehicle_type' => $_POST['vehicle_type'] ?? '',
    'fuel_type' => $_POST['fuel_type'] ?? '',
    'doors_count' => $_POST['doors_count'] ?? '4',
    'wheels_count' => $_POST['wheels_count'] ?? '4',
    'manufacturing_date' => $_POST['manufacturing_date'] ?? '',
    'acquisition_year' => $_POST['acquisition_year'] ?? date('Y')
];
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσθήκη Αυτοκινήτου - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .section-header {
            color: #007bff;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .vehicle-preview {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
                    <h2><i class="bi bi-plus-square"></i> Προσθήκη Νέου Αυτοκινήτου</h2>
                    <a href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') ? '../customer_dashboard.php' : 'manage_vehicles.php'; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Πίσω στη Λίστα
                    </a>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <form method="POST" id="vehicleForm">
                                <!-- Customer Selection Section -->
                                <div class="mb-4">
                                    <h5 class="section-header"><i class="bi bi-person"></i> Στοιχεία Πελάτη</h5>
                                    
                                    <div class="form-floating">
                                        <select class="form-select" id="customer_id" name="customer_id" required 
                                                <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') ? 'readonly' : ''; ?>
                                                onchange="showCustomerInfo()">
                                            <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer'): ?>
                                                <option value="">Επιλέξτε πελάτη...</option>
                                            <?php endif; ?>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>"
                                                        data-vat="<?php echo htmlspecialchars($customer['tax_number']); ?>"
                                                        <?php echo $form_data['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' (' . $customer['tax_number'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="customer_id">Πελάτης <span class="text-danger">*</span></label>
                                    </div>
                                    
                                    <div id="customerInfo" class="customer-info d-none mt-3 p-3 bg-light border rounded">
                                        <!-- Customer info populated via JavaScript -->
                                    </div>
                                </div>

                                <!-- Vehicle Basic Info Section -->
                                <div class="mb-4">
                                    <h5 class="section-header"><i class="bi bi-car-front"></i> Βασικά Στοιχεία Αυτοκινήτου</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="brand" name="brand" 
                                                       value="<?php echo htmlspecialchars($form_data['brand']); ?>" 
                                                       required maxlength="50" onchange="updatePreview()">
                                                <label for="brand">Μάρκα <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="model" name="model" 
                                                       value="<?php echo htmlspecialchars($form_data['model']); ?>" 
                                                       required maxlength="50" onchange="updatePreview()">
                                                <label for="model">Μοντέλο <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                               value="<?php echo htmlspecialchars($form_data['serial_number']); ?>" 
                                               required maxlength="30">
                                        <label for="serial_number">Σειριακός Αριθμός <span class="text-danger">*</span></label>
                                        <div class="form-text">Μοναδικός αναγνωριστικός αριθμός του αυτοκινήτου (π.χ. VIN)</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="vehicle_type" name="vehicle_type" required onchange="updatePreview()">
                                                    <option value="">Επιλέξτε τύπο...</option>
                                                    <option value="passenger" <?php echo $form_data['vehicle_type'] == 'passenger' ? 'selected' : ''; ?>>Επιβατικό</option>
                                                    <option value="truck" <?php echo $form_data['vehicle_type'] == 'truck' ? 'selected' : ''; ?>>Φορτηγό</option>
                                                    <option value="bus" <?php echo $form_data['vehicle_type'] == 'bus' ? 'selected' : ''; ?>>Λεωφορείο</option>
                                                </select>
                                                <label for="vehicle_type">Τύπος Αυτοκινήτου <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="fuel_type" name="fuel_type" required onchange="updatePreview()">
                                                    <option value="">Επιλέξτε καύσιμο...</option>
                                                    <option value="petrol" <?php echo $form_data['fuel_type'] == 'petrol' ? 'selected' : ''; ?>>Βενζίνη</option>
                                                    <option value="diesel" <?php echo $form_data['fuel_type'] == 'diesel' ? 'selected' : ''; ?>>Πετρέλαιο</option>
                                                    <option value="electric" <?php echo $form_data['fuel_type'] == 'electric' ? 'selected' : ''; ?>>Ηλεκτροκίνητο</option>
                                                    <option value="hybrid" <?php echo $form_data['fuel_type'] == 'hybrid' ? 'selected' : ''; ?>>Υβριδικό</option>
                                                    <option value="gas" <?php echo $form_data['fuel_type'] == 'gas' ? 'selected' : ''; ?>>Υγραέριο</option>
                                                </select>
                                                <label for="fuel_type">Τύπος Καυσίμου <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Technical Specifications Section -->
                                <div class="mb-4">
                                    <h5 class="section-header"><i class="bi bi-gear"></i> Τεχνικά Χαρακτηριστικά</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="doors_count" name="doors_count" 
                                                       value="<?php echo $form_data['doors_count']; ?>" 
                                                       min="2" max="6" required onchange="updatePreview()">
                                                <label for="doors_count">Αριθμός Θυρών <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="wheels_count" name="wheels_count" 
                                                       value="<?php echo $form_data['wheels_count']; ?>" 
                                                       min="4" max="18" required onchange="updatePreview()">
                                                <label for="wheels_count">Αριθμός Ροδών <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" 
                                                       value="<?php echo $form_data['manufacturing_date']; ?>" 
                                                       required onchange="updatePreview()">
                                                <label for="manufacturing_date">Ημερομηνία Παραγωγής <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="acquisition_year" name="acquisition_year" 
                                                       value="<?php echo $form_data['acquisition_year']; ?>" 
                                                       min="1900" max="<?php echo date('Y'); ?>" required onchange="updatePreview()">
                                                <label for="acquisition_year">Έτος Κτήσης <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-lg"></i> Αποθήκευση Αυτοκινήτου
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Preview Panel -->
                    <div class="col-lg-4">
                        <div class="vehicle-preview" id="vehiclePreview">
                            <h5><i class="bi bi-eye"></i> Προεπισκόπηση</h5>
                            <hr class="border-light">
                            <div id="previewContent">
                                <p class="text-light mb-1">Συμπληρώστε τα στοιχεία για προεπισκόπηση</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showCustomerInfo() {
            const select = document.getElementById('customer_id');
            const customerInfo = document.getElementById('customerInfo');
            
            if (select.value) {
                const option = select.selectedOptions[0];
                const name = option.dataset.name;
                const vat = option.dataset.vat;
                
                customerInfo.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Ονοματεπώνυμο:</strong><br>
                            <span class="text-muted">${name}</span>
                        </div>
                        <div class="col-md-6">
                            <strong>ΑΦΜ:</strong><br>
                            <span class="text-muted">${vat}</span>
                        </div>
                    </div>
                `;
                customerInfo.classList.remove('d-none');
            } else {
                customerInfo.classList.add('d-none');
            }
            updatePreview();
        }

        function updatePreview() {
            const brand = document.getElementById('brand').value;
            const model = document.getElementById('model').value;
            const vehicleType = document.getElementById('vehicle_type').value;
            const fuelType = document.getElementById('fuel_type').value;
            const doors = document.getElementById('doors_count').value;
            const wheels = document.getElementById('wheels_count').value;
            const manufacturingDate = document.getElementById('manufacturing_date').value;
            const acquisitionYear = document.getElementById('acquisition_year').value;
            const customerSelect = document.getElementById('customer_id');
            const customerName = customerSelect.selectedOptions[0]?.dataset.name || '';

            const vehicleTypeText = {
                'passenger': 'Επιβατικό',
                'truck': 'Φορτηγό',
                'bus': 'Λεωφορείο'
            };

            const fuelTypeText = {
                'petrol': 'Βενζίνη',
                'diesel': 'Πετρέλαιο',
                'electric': 'Ηλεκτροκίνητο',
                'hybrid': 'Υβριδικό',
                'gas': 'Υγραέριο'
            };

            let previewHTML = '';
            
            if (brand || model) {
                previewHTML += `<h6 class="text-white mb-2">${brand} ${model}</h6>`;
            }
            
            if (customerName) {
                previewHTML += `<p class="mb-1"><i class="bi bi-person"></i> ${customerName}</p>`;
            }
            
            if (vehicleType) {
                previewHTML += `<p class="mb-1"><i class="bi bi-car-front"></i> ${vehicleTypeText[vehicleType] || vehicleType}</p>`;
            }
            
            if (fuelType) {
                previewHTML += `<p class="mb-1"><i class="bi bi-fuel-pump"></i> ${fuelTypeText[fuelType] || fuelType}</p>`;
            }
            
            if (doors && wheels) {
                previewHTML += `<div class="row mt-2">
                    <div class="col-6"><small><i class="bi bi-door-open"></i> ${doors} θύρες</small></div>
                    <div class="col-6"><small><i class="bi bi-circle"></i> ${wheels} ρόδες</small></div>
                </div>`;
            }
            
            if (manufacturingDate && acquisitionYear) {
                previewHTML += `<div class="row mt-1">
                    <div class="col-6"><small>Παραγωγή: ${new Date(manufacturingDate).getFullYear()}</small></div>
                    <div class="col-6"><small>Κτήση: ${acquisitionYear}</small></div>
                </div>`;
            }

            document.getElementById('previewContent').innerHTML = previewHTML || '<p class="text-light mb-1">Συμπληρώστε τα στοιχεία για προεπισκόπηση</p>';
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showCustomerInfo();
            updatePreview();
        });
    </script>
</body>
</html>