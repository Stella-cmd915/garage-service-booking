<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth.php';

// Ensure user data is loaded
loadUserDataToSession();

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'book_appointment') {
            $customer_id = $_POST['customer_id'];
            $vehicle_id = $_POST['vehicle_id'];
            $appointment_date = $_POST['appointment_date'];
            $appointment_time = $_POST['appointment_time'];
            $reason = $_POST['reason'];
            $problem_description = $_POST['problem_description'] ?? '';
            
            // Validate appointment time (08:00 - 16:00)
            $time = strtotime($appointment_time);
            $start_time = strtotime('08:00');
            $end_time = strtotime('16:00');
            
            if ($time < $start_time || $time > $end_time) {
                throw new Exception("Η ώρα πρέπει να είναι μεταξύ 08:00 και 16:00.");
            }
            
            // Check for available mechanic
            $available_mechanic = findAvailableMechanic($pdo, $appointment_date, $appointment_time);
            
            if (!$available_mechanic) {
                throw new Exception("Δεν υπάρχει διαθέσιμος μηχανικός για την επιλεγμένη ώρα. Παρακαλώ επιλέξτε άλλη ώρα.");
            }
            
            // Create appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (
                    customer_id, vehicle_id, mechanic_id, appointment_date, appointment_time,
                    reason, problem_description, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'created', NOW())
            ");
            
            $stmt->execute([
                $customer_id, $vehicle_id, $available_mechanic['id'], 
                $appointment_date, $appointment_time, $reason, $problem_description
            ]);
            
            setSuccessMessage("Το ραντεβού κλείστηκε επιτυχώς! Ανατέθηκε στον μηχανικό: " . 
                              htmlspecialchars($available_mechanic['name']));
            
            header('Location: manage_appointments.php');
            exit();
        }
    } catch (Exception $e) {
        setErrorMessage($e->getMessage());
    }
}

// Function to find available mechanic
function findAvailableMechanic($pdo, $date, $time) {
    // Get all mechanics
    try {
        $stmt = $pdo->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name FROM users u WHERE u.role = 'mechanic' AND u.is_active = 1");
        $stmt->execute();
        $mechanics = $stmt->fetchAll();
        
        // Shuffle to get random mechanic if multiple available
        shuffle($mechanics);
        
        foreach ($mechanics as $mechanic) {
            if (isMechanicAvailable($pdo, $mechanic['id'], $date, $time)) {
                return $mechanic;
            }
        }
    } catch (Exception $e) {
        error_log("Error finding mechanics: " . $e->getMessage());
    }
    
    return null;
}

// Function to check if mechanic is available
function isMechanicAvailable($pdo, $mechanic_id, $date, $time) {
    try {
        // A mechanic is busy for 2 hours from their appointment time
        $appointment_time = date('H:i:s', strtotime($time));
        
        // Check for conflicts: any appointment that would overlap with our 2-hour window
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as conflicts 
            FROM appointments 
            WHERE mechanic_id = ? 
            AND appointment_date = ? 
            AND status IN ('created', 'in_progress')
            AND (
                (appointment_time <= ? AND DATE_ADD(CONCAT(appointment_date, ' ', appointment_time), INTERVAL 2 HOUR) > CONCAT(?, ' ', ?))
                OR (appointment_time >= ? AND appointment_time < DATE_ADD(CONCAT(?, ' ', ?), INTERVAL 2 HOUR))
            )
        ");
        
        $stmt->execute([
            $mechanic_id, $date, $appointment_time, $date, $appointment_time,
            $appointment_time, $date, $appointment_time
        ]);
        $conflicts = $stmt->fetch()['conflicts'];
        
        return $conflicts == 0;
    } catch (Exception $e) {
        error_log("Error checking mechanic availability: " . $e->getMessage());
        return false;
    }
}

// Get customers for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = [];
    error_log("Error loading customers: " . $e->getMessage());
}

// Get vehicles for selected customer (via AJAX)
$selected_customer_id = $_GET['customer_id'] ?? '';
$customer_vehicles = [];
if ($selected_customer_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, brand, model, serial_number FROM vehicles WHERE customer_id = ? ORDER BY brand, model");
        $stmt->execute([$selected_customer_id]);
        $customer_vehicles = $stmt->fetchAll();
    } catch (Exception $e) {
        $customer_vehicles = [];
        error_log("Error loading vehicles: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Κλείσιμο Ραντεβού - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .booking-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .step {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin: 0 0.5rem;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .form-section h5 {
            color: #007bff;
            margin-bottom: 1rem;
        }
        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .time-slot {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            font-weight: 500;
        }
        .time-slot:hover {
            border-color: #007bff;
            background: #f0f8ff;
            transform: translateY(-2px);
        }
        .time-slot.selected {
            border-color: #007bff;
            background: #007bff;
            color: white;
            transform: translateY(-2px);
        }
        .time-slot.unavailable {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            cursor: not-allowed;
        }
        .availability-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .summary-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
            border-bottom: 1px dotted #dee2e6;
        }
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .btn-navigation {
            min-width: 100px;
        }
        .step-content {
            min-height: 300px;
        }
        @media (max-width: 768px) {
            .step {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            .time-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            }
            .booking-form {
                padding: 1rem;
            }
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
                    <h2><i class="bi bi-calendar-plus"></i> Κλείσιμο Νέου Ραντεβού</h2>
                    <a href="<?php echo getDashboardUrl(); ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Πίσω στη Διαχείριση
                    </a>
                </div>

                <?php $success = getSuccessMessage(); if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php $error = getErrorMessage(); if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="booking-form">
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step active" id="step1">
                            <i class="bi bi-person me-2"></i>1. Πελάτης
                        </div>
                        <div class="step" id="step2">
                            <i class="bi bi-car-front me-2"></i>2. Αυτοκίνητο
                        </div>
                        <div class="step" id="step3">
                            <i class="bi bi-calendar me-2"></i>3. Ημερομηνία
                        </div>
                        <div class="step" id="step4">
                            <i class="bi bi-clock me-2"></i>4. Ώρα
                        </div>
                        <div class="step" id="step5">
                            <i class="bi bi-info-circle me-2"></i>5. Στοιχεία
                        </div>
                    </div>

                    <form method="POST" id="appointmentForm">
                        <input type="hidden" name="action" value="book_appointment">
                        
                        <!-- Step 1: Customer Selection -->
                        <div class="form-section step-content" id="section1">
                            <h5><i class="bi bi-person"></i> Επιλογή Πελάτη</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="customer_id" class="form-label">
                                        Πελάτης <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerVehicles()">
                                        <option value="">Επιλέξτε πελάτη...</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>" 
                                                    <?php echo $selected_customer_id == $customer['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i>
                                        Επιλέξτε τον πελάτη για τον οποίο θέλετε να κλείσετε ραντεβού
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary btn-navigation" onclick="nextStep(2)" disabled id="step1Next">
                                        Επόμενο <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Vehicle Selection -->
                        <div class="form-section step-content d-none" id="section2">
                            <h5><i class="bi bi-car-front"></i> Επιλογή Αυτοκινήτου</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="vehicle_id" class="form-label">
                                        Αυτοκίνητο <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Επιλέξτε αυτοκίνητο...</option>
                                        <!-- Populated via JavaScript -->
                                    </select>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i>
                                        Εμφανίζονται μόνο τα αυτοκίνητα του επιλεγμένου πελάτη
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-secondary btn-navigation me-2" onclick="prevStep(1)">
                                        <i class="bi bi-arrow-left"></i> Πίσω
                                    </button>
                                    <button type="button" class="btn btn-primary btn-navigation" onclick="nextStep(3)" disabled id="step2Next">
                                        Επόμενο <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Date Selection -->
                        <div class="form-section step-content d-none" id="section3">
                            <h5><i class="bi bi-calendar"></i> Επιλογή Ημερομηνίας</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="appointment_date" class="form-label">
                                        Ημερομηνία Ραντεβού <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required onchange="checkDateAvailability()">
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i>
                                        Επιλέξτε ημερομηνία από σήμερα και μετά
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-secondary btn-navigation me-2" onclick="prevStep(2)">
                                        <i class="bi bi-arrow-left"></i> Πίσω
                                    </button>
                                    <button type="button" class="btn btn-primary btn-navigation" onclick="nextStep(4)" disabled id="step3Next">
                                        Επόμενο <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Time Selection -->
                        <div class="form-section step-content d-none" id="section4">
                            <h5><i class="bi bi-clock"></i> Επιλογή Ώρας</h5>
                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">
                                        Διαθέσιμες Ώρες <span class="text-danger">*</span>
                                    </label>
                                    <div class="availability-info">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small>
                                                    <i class="bi bi-info-circle"></i>
                                                    <strong>Ωράριο Λειτουργίας:</strong> 08:00 - 16:00
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <small>
                                                    <i class="bi bi-clock"></i>
                                                    <strong>Διάρκεια:</strong> Κάθε ραντεβού διαρκεί 2 ώρες
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="appointment_time" name="appointment_time" required>
                                    <div class="time-grid" id="timeGrid">
                                        <!-- Time slots populated via JavaScript -->
                                    </div>
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="button" class="btn btn-secondary btn-navigation me-2" onclick="prevStep(3)">
                                        <i class="bi bi-arrow-left"></i> Πίσω
                                    </button>
                                    <button type="button" class="btn btn-primary btn-navigation" onclick="nextStep(5)" disabled id="step4Next">
                                        Επόμενο <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 5: Appointment Details -->
                        <div class="form-section step-content d-none" id="section5">
                            <h5><i class="bi bi-info-circle"></i> Στοιχεία Ραντεβού</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="reason" class="form-label">
                                            Λόγος Ραντεβού <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="reason" name="reason" required onchange="toggleProblemDescription()">
                                            <option value="">Επιλέξτε λόγο...</option>
                                            <option value="service">Σέρβις</option>
                                            <option value="repair">Επιδιόρθωση</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3" id="problemDescriptionDiv" style="display: none;">
                                        <label for="problem_description" class="form-label">
                                            Περιγραφή Προβλήματος <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control" id="problem_description" name="problem_description" 
                                                  rows="3" placeholder="Περιγράψτε το πρόβλημα που αντιμετωπίζετε..."></textarea>
                                        <div class="form-text">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Υποχρεωτικό για επιδιορθώσεις
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Summary -->
                            <div class="summary-card">
                                <h6><i class="bi bi-check-circle text-success"></i> Σύνοψη Ραντεβού</h6>
                                <div id="appointmentSummary">
                                    <!-- Populated via JavaScript -->
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary btn-navigation me-2" onclick="prevStep(4)">
                                    <i class="bi bi-arrow-left"></i> Πίσω
                                </button>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-lg"></i> Κλείσιμο Ραντεβού
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        let selectedTimeSlot = null;

        // Step navigation
        function nextStep(step) {
            document.getElementById(`section${currentStep}`).classList.add('d-none');
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}`).classList.add('completed');
            
            currentStep = step;
            document.getElementById(`section${currentStep}`).classList.remove('d-none');
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            if (step === 4) {
                generateTimeSlots();
            } else if (step === 5) {
                updateAppointmentSummary();
            }
        }

        function prevStep(step) {
            document.getElementById(`section${currentStep}`).classList.add('d-none');
            document.getElementById(`step${currentStep}`).classList.remove('active');
            
            currentStep = step;
            document.getElementById(`section${currentStep}`).classList.remove('d-none');
            document.getElementById(`step${step}`).classList.remove('completed');
            document.getElementById(`step${step}`).classList.add('active');
        }

        // Load customer vehicles
        function loadCustomerVehicles() {
            const customerId = document.getElementById('customer_id').value;
            const vehicleSelect = document.getElementById('vehicle_id');
            const step1Next = document.getElementById('step1Next');
            
            if (customerId) {
                step1Next.disabled = false;
                
                // Show loading
                vehicleSelect.innerHTML = '<option value="">Φόρτωση αυτοκινήτων...</option>';
                
                // Load vehicles via AJAX
                fetch(`get_customer_vehicles.php?customer_id=${customerId}`)
                    .then(response => response.json())
                    .then(data => {
                        vehicleSelect.innerHTML = '<option value="">Επιλέξτε αυτοκίνητο...</option>';
                        if (data.vehicles && data.vehicles.length > 0) {
                            data.vehicles.forEach(vehicle => {
                                vehicleSelect.innerHTML += 
                                    `<option value="${vehicle.id}">${vehicle.brand} ${vehicle.model} (${vehicle.serial_number})</option>`;
                            });
                        } else {
                            vehicleSelect.innerHTML += '<option value="" disabled>Δεν βρέθηκαν αυτοκίνητα για αυτόν τον πελάτη</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading vehicles:', error);
                        vehicleSelect.innerHTML = '<option value="">Σφάλμα φόρτωσης αυτοκινήτων</option>';
                    });
            } else {
                step1Next.disabled = true;
                vehicleSelect.innerHTML = '<option value="">Επιλέξτε αυτοκίνητο...</option>';
            }
        }

        // Vehicle selection change
        document.getElementById('vehicle_id').addEventListener('change', function() {
            document.getElementById('step2Next').disabled = !this.value;
        });

        // Date selection change
        function checkDateAvailability() {
            const date = document.getElementById('appointment_date').value;
            document.getElementById('step3Next').disabled = !date;
        }

        // Generate time slots
        function generateTimeSlots() {
            const timeGrid = document.getElementById('timeGrid');
            timeGrid.innerHTML = '';
            
            // Generate slots from 08:00 to 14:00 (last slot that allows 2 hours until 16:00)
            for (let hour = 8; hour <= 14; hour++) {
                for (let minutes of [0, 30]) {
                    const timeString = `${hour.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                    const slot = document.createElement('div');
                    slot.className = 'time-slot';
                    slot.textContent = timeString;
                    slot.onclick = () => selectTimeSlot(timeString, slot);
                    
                    // Add some visual feedback
                    slot.setAttribute('title', `Κλείσιμο ραντεβού για ${timeString} (Διάρκεια: 2 ώρες)`);
                    
                    timeGrid.appendChild(slot);
                }
            }
        }

        // Select time slot
        function selectTimeSlot(time, element) {
            if (element.classList.contains('unavailable')) {
                return;
            }
            
            // Remove previous selection
            document.querySelectorAll('.time-slot.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Select current
            element.classList.add('selected');
            document.getElementById('appointment_time').value = time + ':00';
            document.getElementById('step4Next').disabled = false;
            selectedTimeSlot = time;
        }

        // Toggle problem description
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

        // Update appointment summary
        function updateAppointmentSummary() {
            const customer = document.getElementById('customer_id').selectedOptions[0]?.text || '';
            const vehicle = document.getElementById('vehicle_id').selectedOptions[0]?.text || '';
            const date = document.getElementById('appointment_date').value;
            const time = selectedTimeSlot;
            const reason = document.getElementById('reason').selectedOptions[0]?.text || '';
            const problemDescription = document.getElementById('problem_description').value;
            
            const formattedDate = date ? new Date(date).toLocaleDateString('el-GR') : '';
            const endTime = time ? `${parseInt(time.split(':')[0]) + 2}:${time.split(':')[1]}` : '';
            
            let summaryHTML = `
                <div class="summary-item">
                    <span><strong>Πελάτης:</strong></span>
                    <span>${customer}</span>
                </div>
                <div class="summary-item">
                    <span><strong>Αυτοκίνητο:</strong></span>
                    <span>${vehicle}</span>
                </div>
                <div class="summary-item">
                    <span><strong>Ημερομηνία:</strong></span>
                    <span>${formattedDate}</span>
                </div>
                <div class="summary-item">
                    <span><strong>Ώρα:</strong></span>
                    <span>${time} - ${endTime}</span>
                </div>
                <div class="summary-item">
                    <span><strong>Λόγος:</strong></span>
                    <span>${reason}</span>
                </div>
            `;
            
            if (problemDescription.trim()) {
                summaryHTML += `
                <div class="summary-item">
                    <span><strong>Περιγραφή Προβλήματος:</strong></span>
                    <span style="font-style: italic;">${problemDescription}</span>
                </div>
                `;
            }
            
            document.getElementById('appointmentSummary').innerHTML = summaryHTML;
        }

        // Form submission validation
        document.getElementById('appointmentForm').addEventListener('submit', function(e) {
            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Κλείσιμο...';
            submitBtn.disabled = true;
            
            if (!selectedTimeSlot) {
                e.preventDefault();
                alert('Παρακαλώ επιλέξτε ώρα ραντεβού.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
            
            const reason = document.getElementById('reason').value;
            const problemDescription = document.getElementById('problem_description').value;
            
            if (reason === 'repair' && !problemDescription.trim()) {
                e.preventDefault();
                alert('Παρακαλώ περιγράψτε το πρόβλημα για την επιδιόρθωση.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                return;
            }
        });

        // Initialize tooltips if Bootstrap is available
        document.addEventListener('DOMContentLoaded', function() {
            // Enable Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    </script>
</body>
</html>