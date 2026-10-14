<?php
session_start();

// ΠΡΟΣΩΡΙΝΟ - Προσομοίωση σύνδεσης ως γραμματέας
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'secretary';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Secretary';

require_once '../config/database.php';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    try {
        $file = $_FILES['import_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Σφάλμα κατά την μεταφόρτωση του αρχείου.');
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            throw new Exception('Επιτρέπονται μόνο αρχεία CSV, XLSX ή XLS.');
        }
        
        // Move uploaded file
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $temp_file = $upload_dir . uniqid() . '.' . $file_extension;
        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            throw new Exception('Αποτυχία αποθήκευσης του αρχείου.');
        }
        
        // Parse file
        $vehicles_data = [];
        
        if ($file_extension === 'csv') {
            $vehicles_data = parseCSV($temp_file);
        } else {
            $vehicles_data = parseExcel($temp_file);
        }
        
        // Delete temp file
        unlink($temp_file);
        
        if (empty($vehicles_data)) {
            throw new Exception('Το αρχείο δεν περιέχει έγκυρα δεδομένα.');
        }
        
        // Get customers map (vat_number => id)
        $stmt = $pdo->prepare("SELECT id, vat_number FROM users WHERE role = 'customer' AND is_active = 1");
        $stmt->execute();
        $customers_map = [];
        while ($row = $stmt->fetch()) {
            $customers_map[$row['vat_number']] = $row['id'];
        }
        
        // Process vehicles
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($vehicles_data as $index => $vehicle_row) {
            $line_number = $index + 2; // +2 because array is 0-based and we skip header
            
            try {
                // Validate required fields
                if (empty($vehicle_row['vat_number']) || empty($vehicle_row['brand']) || 
                    empty($vehicle_row['model']) || empty($vehicle_row['serial_number'])) {
                    throw new Exception("Γραμμή $line_number: Λείπουν υποχρεωτικά πεδία");
                }
                
                // Find customer
                if (!isset($customers_map[$vehicle_row['vat_number']])) {
                    throw new Exception("Γραμμή $line_number: Δεν βρέθηκε πελάτης με ΑΦΜ " . $vehicle_row['vat_number']);
                }
                
                $customer_id = $customers_map[$vehicle_row['vat_number']];
                
                // Check if serial number exists
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vehicles WHERE serial_number = ?");
                $stmt->execute([$vehicle_row['serial_number']]);
                if ($stmt->fetch()['count'] > 0) {
                    throw new Exception("Γραμμή $line_number: Ο σειριακός αριθμός " . $vehicle_row['serial_number'] . " υπάρχει ήδη");
                }
                
                // Set defaults for optional fields
                $vehicle_type = $vehicle_row['vehicle_type'] ?? 'passenger';
                $fuel_type = $vehicle_row['fuel_type'] ?? 'petrol';
                $doors = (int)($vehicle_row['doors'] ?? 4);
                $wheels = (int)($vehicle_row['wheels'] ?? 4);
                $manufacturing_year = (int)($vehicle_row['manufacturing_year'] ?? date('Y'));
                $year_acquired = (int)($vehicle_row['year_acquired'] ?? date('Y'));
                
                // Validate values
                if (!in_array($vehicle_type, ['passenger', 'truck', 'bus'])) {
                    $vehicle_type = 'passenger';
                }
                
                if (!in_array($fuel_type, ['petrol', 'diesel', 'electric', 'hybrid', 'gas'])) {
                    $fuel_type = 'petrol';
                }
                
                if ($doors < 2 || $doors > 6) $doors = 4;
                if ($wheels < 4 || $wheels > 18) $wheels = 4;
                if ($manufacturing_year < 1900 || $manufacturing_year > date('Y') + 1) $manufacturing_year = date('Y');
                if ($year_acquired < $manufacturing_year || $year_acquired > date('Y')) $year_acquired = $manufacturing_year;
                
                // Insert vehicle
                $stmt = $pdo->prepare("
                    INSERT INTO vehicles (
                        customer_id, brand, model, serial_number, vehicle_type, fuel_type,
                        doors, wheels, manufacturing_year, year_acquired, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $customer_id,
                    trim($vehicle_row['brand']),
                    trim($vehicle_row['model']),
                    trim($vehicle_row['serial_number']),
                    $vehicle_type,
                    $fuel_type,
                    $doors,
                    $wheels,
                    $manufacturing_year,
                    $year_acquired
                ]);
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                $skipped++;
            }
        }
        
        // Set success message
        $message = "Μαζική εισαγωγή ολοκληρώθηκε! ";
        $message .= "Εισήχθησαν: $imported αυτοκίνητα, Παραλείφθηκαν: $skipped";
        
        if (!empty($errors)) {
            $message .= "\n\nΣφάλματα:\n" . implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $message .= "\n... και " . (count($errors) - 10) . " ακόμη σφάλματα.";
            }
        }
        
        $_SESSION['import_result'] = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
        
        header('Location: bulk_import.php?success=1');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

function parseCSV($file_path) {
    $data = [];
    $header = [];
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        // Read header
        if (($header_row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $header = array_map('trim', $header_row);
            $header = array_map('strtolower', $header);
        }
        
        // Read data rows
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($row) === count($header)) {
                $vehicle = [];
                foreach ($header as $i => $col) {
                    $vehicle[$col] = trim($row[$i]);
                }
                $data[] = $vehicle;
            }
        }
        fclose($handle);
    }
    
    return $data;
}

function parseExcel($file_path) {
    // For Excel files, we would need a library like PhpSpreadsheet
    // For this example, we'll return empty array and show error
    throw new Exception('Η υποστήριξη Excel αρχείων δεν είναι ακόμη διαθέσιμη. Παρακαλώ χρησιμοποιήστε CSV.');
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Μαζική Εισαγωγή Αυτοκινήτων - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .import-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .step-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .step-number {
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
        .file-drop-zone {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .file-drop-zone:hover {
            background: #f0f8ff;
            border-color: #0056b3;
        }
        .file-drop-zone.dragover {
            background: #e3f2fd;
            border-color: #1976d2;
        }
        .template-table {
            font-size: 0.875rem;
        }
        .result-card {
            border-left: 4px solid #28a745;
        }
        .error-card {
            border-left: 4px solid #dc3545;
        }
        .errors-list {
            max-height: 300px;
            overflow-y: auto;
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
                    <h2><i class="bi bi-upload"></i> Μαζική Εισαγωγή Αυτοκινήτων</h2>
                    <a href="index.php" class="btn btn-outline-secondary">
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

                <!-- Import Results -->
                <?php if (isset($_SESSION['import_result']) && isset($_GET['success'])): ?>
                    <?php 
                    $result = $_SESSION['import_result']; 
                    unset($_SESSION['import_result']);
                    ?>
                    <div class="card result-card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-check-circle"></i> Αποτελέσματα Εισαγωγής</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-success"><?php echo $result['imported']; ?></h3>
                                        <small>Επιτυχής Εισαγωγή</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-warning"><?php echo $result['skipped']; ?></h3>
                                        <small>Παραλείφθηκαν</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <h3 class="text-info"><?php echo $result['imported'] + $result['skipped']; ?></h3>
                                        <small>Σύνολο Γραμμών</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($result['errors'])): ?>
                                <div class="error-card card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-exclamation-circle"></i> Σφάλματα (<?php echo count($result['errors']); ?>)</h6>
                                    </div>
                                    <div class="card-body errors-list">
                                        <?php foreach ($result['errors'] as $error): ?>
                                            <small class="d-block text-danger mb-1">• <?php echo htmlspecialchars($error); ?></small>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Instructions -->
                        <div class="step-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="step-number">1</div>
                                <h5 class="mb-0">Προετοιμασία Αρχείου</h5>
                            </div>
                            <p class="mb-3">
                                Δημιουργήστε ένα CSV αρχείο με τα εξής στήλες (header):
                            </p>
                            <div class="table-responsive">
                                <table class="table template-table table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>vat_number</th>
                                            <th>brand</th>
                                            <th>model</th>
                                            <th>serial_number</th>
                                            <th>vehicle_type</th>
                                            <th>fuel_type</th>
                                            <th>doors</th>
                                            <th>wheels</th>
                                            <th>manufacturing_year</th>
                                            <th>year_acquired</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>123456789</td>
                                            <td>Toyota</td>
                                            <td>Corolla</td>
                                            <td>VIN123456</td>
                                            <td>passenger</td>
                                            <td>petrol</td>
                                            <td>4</td>
                                            <td>4</td>
                                            <td>2020</td>
                                            <td>2021</td>
                                        </tr>
                                        <tr class="table-light">
                                            <td><small>ΑΦΜ Πελάτη</small></td>
                                            <td><small>Μάρκα</small></td>
                                            <td><small>Μοντέλο</small></td>
                                            <td><small>Σειριακός</small></td>
                                            <td><small>Τύπος</small></td>
                                            <td><small>Καύσιμο</small></td>
                                            <td><small>Θύρες</small></td>
                                            <td><small>Ρόδες</small></td>
                                            <td><small>Έτος Παραγ.</small></td>
                                            <td><small>Έτος Κτήσης</small></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="step-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="step-number">2</div>
                                <h5 class="mb-0">Μεταφόρτωση Αρχείου</h5>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data" id="importForm">
                                <div class="file-drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                                    <i class="bi bi-cloud-upload display-4 text-primary mb-3"></i>
                                    <h5>Σύρετε το αρχείο εδώ ή κλικάρετε για επιλογή</h5>
                                    <p class="text-muted mb-0">Υποστηρίζονται: CSV, XLSX, XLS</p>
                                    <small class="text-muted">Μέγιστο μέγεθος: 10MB</small>
                                </div>
                                
                                <input type="file" id="fileInput" name="import_file" accept=".csv,.xlsx,.xls" 
                                       style="display: none;" onchange="handleFileSelect(this)">
                                
                                <div id="fileInfo" class="mt-3 d-none">
                                    <div class="alert alert-info">
                                        <i class="bi bi-file-earmark-text"></i>
                                        <span id="fileName"></span>
                                        <small id="fileSize" class="text-muted ms-2"></small>
                                    </div>
                                    
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="bi bi-upload"></i> Εισαγωγή Αυτοκινήτων
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearFile()">
                                            <i class="bi bi-x"></i> Ακύρωση
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Download Template -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-download"></i> Template Αρχείο</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">Κατεβάστε το template για να δείτε τη σωστή μορφή:</p>
                                <button class="btn btn-outline-primary w-100" onclick="downloadTemplate()">
                                    <i class="bi bi-file-earmark-arrow-down"></i> Κατέβασμα CSV Template
                                </button>
                            </div>
                        </div>

                        <!-- Help -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Οδηγίες</h6>
                            </div>
                            <div class="card-body">
                                <small>
                                    <strong>Υποχρεωτικά πεδία:</strong><br>
                                    • vat_number (ΑΦΜ υπάρχοντος πελάτη)<br>
                                    • brand (μάρκα)<br>
                                    • model (μοντέλο)<br>
                                    • serial_number (μοναδικός)<br><br>
                                    
                                    <strong>Έγκυρες τιμές:</strong><br>
                                    • vehicle_type: passenger, truck, bus<br>
                                    • fuel_type: petrol, diesel, electric, hybrid, gas<br>
                                    • doors: 2-6<br>
                                    • wheels: 4-18<br><br>
                                    
                                    <strong>Σημείωση:</strong><br>
                                    Γραμμές με σφάλματα θα παραλειφθούν και θα εμφανιστεί αναλυτική αναφορά.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop functionality
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            dropZone.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(fileInput);
            }
        });
        
        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                const fileName = document.getElementById('fileName');
                const fileSize = document.getElementById('fileSize');
                const fileInfo = document.getElementById('fileInfo');
                
                fileName.textContent = file.name;
                fileSize.textContent = `(${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                fileInfo.classList.remove('d-none');
                
                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    alert('Το αρχείο είναι πολύ μεγάλο. Μέγιστο επιτρεπτό μέγεθος: 10MB');
                    clearFile();
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['csv', 'xlsx', 'xls'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                if (!allowedTypes.includes(fileExtension)) {
                    alert('Μη έγκυρος τύπος αρχείου. Επιτρέπονται μόνο: CSV, XLSX, XLS');
                    clearFile();
                    return;
                }
            }
        }
        
        function clearFile() {
            const fileInput = document.getElementById('fileInput');
            const fileInfo = document.getElementById('fileInfo');
            
            fileInput.value = '';
            fileInfo.classList.add('d-none');
        }
        
        function downloadTemplate() {
            // Create CSV template
            const template = `vat_number,brand,model,serial_number,vehicle_type,fuel_type,doors,wheels,manufacturing_year,year_acquired
123456789,Toyota,Corolla,VIN123456,passenger,petrol,4,4,2020,2021
987654321,BMW,X5,VIN789012,passenger,diesel,5,4,2019,2020`;
            
            const blob = new Blob([template], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'vehicles_template.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
        
        // Form submission with loading state
        document.getElementById('importForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Επεξεργασία...';
            submitBtn.disabled = true;
            
            // Re-enable button after 30 seconds (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 30000);
        });
    </script>
</body>
</html>