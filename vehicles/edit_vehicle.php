<?php
session_start();

// ΠΡΟΣΩΡΙΝΟ - Προσομοίωση σύνδεσης ως γραμματέας
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'secretary';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Secretary';

require_once '../config/database.php';

// Get vehicle ID
$vehicle_id = $_GET['id'] ?? 0;

if (!$vehicle_id) {
    $_SESSION['error'] = "Μη έγκυρο ID αυτοκινήτου";
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customer_id = $_POST['customer_id'];
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model']);
        $serial_number = trim($_POST['serial_number']);
        $vehicle_type = $_POST['vehicle_type'];
        $fuel_type = $_POST['fuel_type'];
        $doors = (int)$_POST['doors'];
        $wheels = (int)$_POST['wheels'];
        $manufacturing_year = (int)$_POST['manufacturing_year'];
        $year_acquired = (int)$_POST['year_acquired'];
        
        // Validation
        $errors = [];
        
        if (empty($customer_id)) $errors[] = "Επιλέξτε πελάτη";
        if (empty($brand)) $errors[] = "Η μάρκα είναι υποχρεωτική";
        if (empty($model)) $errors[] = "Το μοντέλο είναι υποχρεωτικό";
        if (empty($serial_number)) $errors[] = "Ο σειριακός αριθμός είναι υποχρεωτικός";
        if (empty($vehicle_type)) $errors[] = "Επιλέξτε τύπο αυτοκινήτου";
        if (empty($fuel_type)) $errors[] = "Επιλέξτε τύπο καυσίμου";
        
        if ($doors < 2 || $doors > 6) $errors[] = "Οι θύρες πρέπει να είναι μεταξύ 2 και 6";
        if ($wheels < 4 || $wheels > 18) $errors[] = "Οι ρόδες πρέπει να είναι μεταξύ 4 και 18";
        if ($manufacturing_year < 1900 || $manufacturing_year > date('Y') + 1) 
            $errors[] = "Μη έγκυρο έτος παραγωγής";
        if ($year_acquired < $manufacturing_year || $year_acquired > date('Y')) 
            $errors[] = "Μη έγκυρο έτος κτήσης";
        
        // Check if serial number already exists (excluding current vehicle)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vehicles WHERE serial_number = ? AND id != ?");
        $stmt->execute([$serial_number, $vehicle_id]);
        if ($stmt->fetch()['count'] > 0) {
            $errors[] = "Ο σειριακός αριθμός υπάρχει ήδη";
        }
        
        // Check if customer exists and is active
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE id = ? AND role = 'customer' AND is_active = 1");
        $stmt->execute([$customer_id]);
        if ($stmt->fetch()['count'] == 0) {
            $errors[] = "Μη έγκυρος πελάτης";
        }
        
        if (empty($errors)) {
            // Update vehicle
            $stmt = $pdo->prepare("
                UPDATE vehicles SET
                    customer_id = ?, brand = ?, model = ?, serial_number = ?, vehicle_type = ?, 
                    fuel_type = ?, doors = ?, wheels = ?, manufacturing_year = ?, year_acquired = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $customer_id, $brand, $model, $serial_number, $vehicle_type, $fuel_type,
                $doors, $wheels, $manufacturing_year, $year_acquired, $vehicle_id
            ]);
            
            $_SESSION['success'] = "Το αυτοκίνητο ενημερώθηκε επιτυχώς!";
            header('Location: index.php');
            exit();
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Σφάλμα κατά την ενημέρωση: " . $e->getMessage();
    }
}

// Get vehicle data
try {
    $stmt = $pdo->prepare("
        SELECT v.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
        FROM vehicles v 
        JOIN users c ON v.customer_id = c.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        $_SESSION['error'] = "Το αυτοκίνητο δεν βρέθηκε";
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Σφάλμα φόρτωσης αυτοκινήτου";
    header('Location: index.php');
    exit();
}

// Get customers for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, vat_number FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = [];
    error_log("Error loading customers: " . $e->getMessage());
}

// Get appointment count for this vehicle
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE vehicle_id = ?");
    $stmt->execute([$vehicle_id]);
    $appointment_count = $stmt->fetch()['count'];
} catch (Exception $e) {
    $appointment_count = 0;
}

// Use form data if validation failed, otherwise use vehicle data
$form_data = [
    'customer_id' => $_POST['customer_id'] ?? $vehicle['customer_id'],
    'brand' => $_POST['brand'] ?? $vehicle['brand'],
    'model' => $_POST['model'] ?? $vehicle['model'],
    'serial_number' => $_POST['serial_number'] ?? $vehicle['serial_number'],
    'vehicle_type' => $_POST['vehicle_type'] ?? $vehicle['vehicle_type'],
    'fuel_type' => $_POST['fuel_type'] ?? $vehicle['fuel_type'],
    'doors' => $_POST['doors'] ?? $vehicle['doors'],
    'wheels' => $_POST['wheels'] ?? $vehicle['wheels'],
    'manufacturing_year' => $_POST['manufacturing_year'] ?? $vehicle['manufacturing_year'],
    'year_acquired' => $_POST['year_acquired'] ?? $vehicle['year_acquired']
];
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επεξεργασία Αυτοκινήτου - AutoService</title>
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
        .form-floating {
            margin-bottom: 1rem;
        }
        .serial-check {
            margin-top: 0.25rem;
            font-size: 0.875rem;
        }
        .serial-check.available {
            color: #198754;
        }
        .serial-check.unavailable {
            color: #dc3545;
        }
        .btn-save {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
        }
        .customer-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        .vehicle-preview {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .current-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .danger-zone {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
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
                    <h2><i class="bi bi-pencil-square"></i> Επεξεργασία Αυτοκινήτου</h2>
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Πίσω στη Λίστα
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="viewVehicle(<?php echo $vehicle_id; ?>)">
                            <i class="bi bi-eye"></i> Προβολή
                        </button>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Current Vehicle Info -->
                <div class="current-info mb-4">
                    <h6><i class="bi bi-info-circle"></i> Τρέχοντα Στοιχεία</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($vehicle['serial_number']); ?></small>
                        </div>
                        <div class="col-md-6">
                            <small>Ιδιοκτήτης: <strong><?php echo htmlspecialchars($vehicle['customer_name']); ?></strong></small>
                            <small class="d-block text-muted">Ραντεβού: <?php echo $appointment_count; ?></small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <form method="POST" id="vehicleForm">
                                <!-- Customer Selection Section -->
                                <div class="mb-4">
                                    <h5 class="section-header"><i class="bi bi-person"></i> Στοιχεία Πελάτη</h5>
                                    
                                    <div class="form-floating">
                                        <select class="form-select" id="customer_id" name="customer_id" required onchange="showCustomerInfo()">
                                            <option value="">Επιλέξτε πελάτη...</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>" 
                                                        data-name="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>"
                                                        data-vat="<?php echo htmlspecialchars($customer['vat_number']); ?>"
                                                        <?php echo $form_data['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' (' . $customer['vat_number'] . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="customer_id">Πελάτης <span class="text-danger">*</span></label>
                                    </div>
                                    
                                    <div id="customerInfo" class="customer-info d-none">
                                        <!-- Customer info will be populated via JavaScript -->
                                    </div>
                                </div>

                                <!-- Vehicle Basic Info Section -->
                                <div class="mb-4">
                                    <h5 class="section-header"><i class="bi bi-car-front"></i> Βασικά Στοιχεία Αυτοκινήτου</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="brand" name="brand" 
                                                       value="<?php echo htmlspecialchars($form_data['brand']); ?>" 
                                                       required maxlength="50" onchange="updatePreview()">
                                                <label for="brand">Μάρκα <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="model" name="model" 
                                                       value="<?php echo htmlspecialchars($form_data['model']); ?>" 
                                                       required maxlength="50" onchange="updatePreview()">
                                                <label for="model">Μοντέλο <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                               value="<?php echo htmlspecialchars($form_data['serial_number']); ?>" 
                                               required maxlength="30" onchange="checkSerialNumber()">
                                        <label for="serial_number">Σειριακός Αριθμός <span class="text-danger">*</span></label>
                                        <div id="serialCheck" class="serial-check"></div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
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
                                            <div class="form-floating">
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
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="doors" name="doors" 
                                                       value="<?php echo $form_data['doors']; ?>" 
                                                       min="2" max="6" required onchange="updatePreview()">
                                                <label for="doors">Αριθμός Θυρών <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="wheels" name="wheels" 
                                                       value="<?php echo $form_data['wheels']; ?>" 
                                                       min="4" max="18" required onchange="updatePreview()">
                                                <label for="wheels">Αριθμός Ροδών <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="manufacturing_year" name="manufacturing_year" 
                                                       value="<?php echo $form_data['manufacturing_year']; ?>" 
                                                       min="1900" max="<?php echo date('Y') + 1; ?>" required onchange="updatePreview()">
                                                <label for="manufacturing_year">Έτος Παραγωγής <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-floating">
                                                <input type="number" class="form-control" id="year_acquired" name="year_acquired" 
                                                       value="<?php echo $form_data['year_acquired']; ?>" 
                                                       min="1900" max="<?php echo date('Y'); ?>" required onchange="updatePreview()">
                                                <label for="year_acquired">Έτος Κτήσης <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="text-center">
                                    <button type="submit" class="btn btn-save btn-lg">
                                        <i class="bi bi-check-lg"></i> Ενημέρωση Αυτοκινήτου
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Danger Zone -->
                        <?php if ($appointment_count > 0): ?>
                            <div class="danger-zone">
                                <h6 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Προσοχή</h6>
                                <p class="mb-0">
                                    Αυτό το αυτοκίνητο έχει <strong><?php echo $appointment_count; ?> ραντεβού</strong>. 
                                    Η αλλαγή του ιδιοκτήτη θα επηρεάσει τα υπάρχοντα ραντεβού.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Preview Panel -->
                    <div class="col-lg-4">
                        <div class="vehicle-preview" id="vehiclePreview">
                            <h5><i class="bi bi-eye"></i> Προεπισκόπηση Αλλαγών</h5>
                            <hr class="border-light">
                            <div id="previewContent">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>

                        <!-- Vehicle History Card -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-clock-history"></i> Ιστορικό</h6>
                            </div>
                            <div class="card-body">
                                <small>
                                    <strong>Δημιουργήθηκε:</strong><br>
                                    <?php echo date('d/m/Y H:i', strtotime($vehicle['created_at'])); ?><br><br>
                                    
                                    <?php if ($vehicle['updated_at']): ?>
                                        <strong>Τελευταία ενημέρωση:</strong><br>
                                        <?php echo date('d/m/Y H:i', strtotime($vehicle['updated_at'])); ?><br><br>
                                    <?php endif; ?>
                                    
                                    <strong>Συνολικά ραντεβού:</strong> <?php echo $appointment_count; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let serialCheckTimeout;
        const vehicleId = <?php echo $vehicle_id; ?>;

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

        function checkSerialNumber() {
            const serialInput = document.getElementById('serial_number');
            const serialCheck = document.getElementById('serialCheck');
            const serial = serialInput.value.trim();

            clearTimeout(serialCheckTimeout);
            
            if (!serial) {
                serialCheck.textContent = '';
                return;
            }

            serialCheck.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Έλεγχος...';
            
            serialCheckTimeout = setTimeout(() => {
                fetch(`ajax_handlers.php?action=check_serial&serial=${encodeURIComponent(serial)}&vehicle_id=${vehicleId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            serialCheck.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
                            serialCheck.className = 'serial-check available';
                        } else {
                            serialCheck.innerHTML = '<i class="bi bi-x-circle"></i> ' + data.message;
                            serialCheck.className = 'serial-check unavailable';
                        }
                    })
                    .catch(error => {
                        serialCheck.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Σφάλμα ελέγχου';
                        serialCheck.className = 'serial-check unavailable';
                    });
            }, 500);
        }

        function updatePreview() {
            const brand = document.getElementById('brand').value;
            const model = document.getElementById('model').value;
            const vehicleType = document.getElementById('vehicle_type').value;
            const fuelType = document.getElementById('fuel_type').value;
            const doors = document.getElementById('doors').value;
            const wheels = document.getElementById('wheels').value;
            const manufacturingYear = document.getElementById('manufacturing_year').value;
            const yearAcquired = document.getElementById('year_acquired').value;
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
            
            if (manufacturingYear && yearAcquired) {
                previewHTML += `<div class="row mt-1">
                    <div class="col-6"><small>Παραγωγή: ${manufacturingYear}</small></div>
                    <div class="col-6"><small>Κτήση: ${yearAcquired}</small></div>
                </div>`;
            }

            document.getElementById('previewContent').innerHTML = previewHTML || '<p class="text-light mb-1">Συμπληρώστε τα στοιχεία για προεπισκόπηση</p>';
        }

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

        // Form validation
        document.getElementById('vehicleForm').addEventListener('submit', function(e) {
            const manufacturingYear = parseInt(document.getElementById('manufacturing_year').value);
            const yearAcquired = parseInt(document.getElementById('year_acquired').value);
            
            if (yearAcquired < manufacturingYear) {
                e.preventDefault();
                alert('Το έτος κτήσης δεν μπορεί να είναι μικρότερο από το έτος παραγωγής.');
                return;
            }
            
            const serialCheck = document.getElementById('serialCheck');
            if (serialCheck.classList.contains('unavailable')) {
                e.preventDefault();
                alert('Ο σειριακός αριθμός υπάρχει ήδη. Παρακαλώ εισάγετε έναν διαφορετικό.');
                return;
            }
        });

        // Initialize
        showCustomerInfo();
        updatePreview();
    </script>
</body>
</html>