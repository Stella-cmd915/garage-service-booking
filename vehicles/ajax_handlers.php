<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['secretary', 'customer'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_vehicle':
        get_vehicle_details();
        break;
    case 'check_serial':
        check_serial_number();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function get_vehicle_details() {
    global $pdo;
    
    $vehicle_id = $_GET['id'] ?? 0;
    
    if (!$vehicle_id) {
        echo json_encode(['success' => false, 'message' => 'Vehicle ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                v.*,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.vat_number,
                c.phone,
                c.address,
                (SELECT COUNT(*) FROM appointments WHERE vehicle_id = v.id) as appointment_count,
                (SELECT COUNT(*) FROM appointments WHERE vehicle_id = v.id AND status = 'completed') as completed_appointments
            FROM vehicles v
            JOIN users c ON v.customer_id = c.id
            WHERE v.id = ?
        ");
        
        $stmt->execute([$vehicle_id]);
        $vehicle = $stmt->fetch();
        
        if (!$vehicle) {
            echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
            return;
        }
        
        // Format vehicle type
        $vehicle_type_display = match($vehicle['vehicle_type']) {
            'passenger' => 'Επιβατικό',
            'truck' => 'Φορτηγό',
            'bus' => 'Λεωφορείο',
            default => ucfirst($vehicle['vehicle_type'])
        };
        
        // Format fuel type
        $fuel_type_display = match($vehicle['fuel_type']) {
            'electric' => 'Ηλεκτροκίνητο',
            'diesel' => 'Πετρέλαιο',
            'gas' => 'Υγραέριο',
            'hybrid' => 'Υβριδικό',
            default => 'Βενζίνη'
        };
        
        // Get recent appointments
        $stmt = $pdo->prepare("
            SELECT 
                appointment_date,
                appointment_time,
                status,
                reason
            FROM appointments 
            WHERE vehicle_id = ? 
            ORDER BY appointment_date DESC, appointment_time DESC 
            LIMIT 5
        ");
        $stmt->execute([$vehicle_id]);
        $recent_appointments = $stmt->fetchAll();
        
        // Generate HTML
        $html = '
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary mb-3"><i class="bi bi-car-front"></i> Στοιχεία Αυτοκινήτου</h6>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Μάρκα:</strong></td>
                        <td>' . htmlspecialchars($vehicle['brand']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Μοντέλο:</strong></td>
                        <td>' . htmlspecialchars($vehicle['model']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Σειριακός Αριθμός:</strong></td>
                        <td><code>' . htmlspecialchars($vehicle['serial_number']) . '</code></td>
                    </tr>
                    <tr>
                        <td><strong>Τύπος:</strong></td>
                        <td><span class="badge bg-primary">' . $vehicle_type_display . '</span></td>
                    </tr>
                    <tr>
                        <td><strong>Καύσιμο:</strong></td>
                        <td>' . $fuel_type_display . '</td>
                    </tr>
                    <tr>
                        <td><strong>Θύρες:</strong></td>
                        <td>' . $vehicle['doors'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Ρόδες:</strong></td>
                        <td>' . $vehicle['wheels'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Έτος Παραγωγής:</strong></td>
                        <td>' . $vehicle['manufacturing_year'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Έτος Κτήσης:</strong></td>
                        <td>' . $vehicle['year_acquired'] . '</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-success mb-3"><i class="bi bi-person"></i> Στοιχεία Ιδιοκτήτη</h6>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Ονοματεπώνυμο:</strong></td>
                        <td>' . htmlspecialchars($vehicle['customer_name']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>ΑΦΜ:</strong></td>
                        <td>' . htmlspecialchars($vehicle['vat_number']) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Τηλέφωνο:</strong></td>
                        <td>' . htmlspecialchars($vehicle['phone'] ?? 'Δεν έχει καταχωρηθεί') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Διεύθυνση:</strong></td>
                        <td>' . htmlspecialchars($vehicle['address'] ?? 'Δεν έχει καταχωρηθεί') . '</td>
                    </tr>
                </table>
                
                <h6 class="text-info mb-3 mt-4"><i class="bi bi-graph-up"></i> Στατιστικά</h6>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <h5 class="mb-0">' . $vehicle['appointment_count'] . '</h5>
                            <small>Συνολικά Ραντεβού</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-light rounded">
                            <h5 class="mb-0">' . $vehicle['completed_appointments'] . '</h5>
                            <small>Ολοκληρωμένα</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
        // Recent appointments section
        if (!empty($recent_appointments)) {
            $html .= '
            <hr class="my-4">
            <h6 class="text-warning mb-3"><i class="bi bi-calendar-check"></i> Πρόσφατα Ραντεβού</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Ημερομηνία</th>
                            <th>Ώρα</th>
                            <th>Λόγος</th>
                            <th>Κατάσταση</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($recent_appointments as $appointment) {
                $status_class = match($appointment['status']) {
                    'created' => 'bg-secondary',
                    'in_progress' => 'bg-primary',
                    'completed' => 'bg-success',
                    'cancelled' => 'bg-danger',
                    default => 'bg-secondary'
                };
                
                $status_text = match($appointment['status']) {
                    'created' => 'Δημιουργημένο',
                    'in_progress' => 'Σε Εξέλιξη',
                    'completed' => 'Περαιωμένο',
                    'cancelled' => 'Ακυρωμένο',
                    default => 'Άγνωστο'
                };
                
                $reason_text = $appointment['reason'] == 'service' ? 'Σέρβις' : 'Επιδιόρθωση';
                
                $html .= '
                        <tr>
                            <td>' . date('d/m/Y', strtotime($appointment['appointment_date'])) . '</td>
                            <td>' . date('H:i', strtotime($appointment['appointment_time'])) . '</td>
                            <td>' . $reason_text . '</td>
                            <td><span class="badge ' . $status_class . '">' . $status_text . '</span></td>
                        </tr>';
            }
            
            $html .= '
                    </tbody>
                </table>
            </div>';
        } else {
            $html .= '
            <hr class="my-4">
            <div class="text-center py-3">
                <i class="bi bi-calendar-x text-muted"></i>
                <p class="text-muted mb-0">Δεν υπάρχουν ραντεβού για αυτό το αυτοκίνητο</p>
            </div>';
        }
        
        echo json_encode(['success' => true, 'html' => $html]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function check_serial_number() {
    global $pdo;
    
    $serial_number = $_GET['serial'] ?? '';
    $vehicle_id = $_GET['vehicle_id'] ?? 0; // For edit mode
    
    if (!$serial_number) {
        echo json_encode(['available' => false, 'message' => 'Serial number is required']);
        return;
    }
    
    try {
        $sql = "SELECT COUNT(*) as count FROM vehicles WHERE serial_number = ?";
        $params = [$serial_number];
        
        // If editing existing vehicle, exclude it from check
        if ($vehicle_id) {
            $sql .= " AND id != ?";
            $params[] = $vehicle_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->fetch()['count'];
        
        $available = $count == 0;
        $message = $available ? 'Διαθέσιμος σειριακός αριθμός' : 'Ο σειριακός αριθμός υπάρχει ήδη';
        
        echo json_encode(['available' => $available, 'message' => $message]);
        
    } catch (Exception $e) {
        echo json_encode(['available' => false, 'message' => 'Database error']);
    }
}
?>