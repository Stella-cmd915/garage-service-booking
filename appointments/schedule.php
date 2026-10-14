<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is mechanic
if (!isLoggedIn() || getUserRole() !== 'mechanic') {
    header('Location: ../auth/login.php');
    exit;
}

$mechanic_id = $_SESSION['user_id'];

// Get current week start (Monday)
$current_week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('monday this week'));
$week_start = new DateTime($current_week_start);
$week_end = clone $week_start;
$week_end->add(new DateInterval('P6D'));

// Navigation dates
$prev_week = clone $week_start;
$prev_week->sub(new DateInterval('P7D'));
$next_week = clone $week_start;
$next_week->add(new DateInterval('P7D'));

try {
    // Get appointments for the week
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.phone as customer_phone,
               CONCAT(v.brand, ' ', v.model) as vehicle_info,
               v.serial_number
        FROM appointments a
        JOIN users u ON a.customer_id = u.id
        JOIN vehicles v ON a.vehicle_id = v.id
        WHERE a.mechanic_id = ? 
        AND a.appointment_date BETWEEN ? AND ?
        ORDER BY a.appointment_date, a.appointment_time
    ");
    $stmt->execute([$mechanic_id, $week_start->format('Y-m-d'), $week_end->format('Y-m-d')]);
    $appointments = $stmt->fetchAll();

    // Organize appointments by date
    $schedule = [];
    foreach ($appointments as $appointment) {
        $date = $appointment['appointment_date'];
        if (!isset($schedule[$date])) {
            $schedule[$date] = [];
        }
        $schedule[$date][] = $appointment;
    }

} catch (Exception $e) {
    $error_message = "Σφάλμα φόρτωσης: " . $e->getMessage();
    $schedule = [];
}

// Working hours
$work_hours = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', 
               '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00'];

$days_greek = ['Δευτέρα', 'Τρίτη', 'Τετάρτη', 'Πέμπτη', 'Παρασκευή', 'Σάββατο', 'Κυριακή'];
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πρόγραμμα Εργασίας - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .schedule-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .time-cell {
            width: 80px;
            background: #f8f9fa;
            border-right: 2px solid #dee2e6;
            font-weight: 600;
            text-align: center;
        }
        .day-cell {
            width: 14.28%;
            min-height: 60px;
            border-right: 1px solid #dee2e6;
            position: relative;
            padding: 0.5rem;
        }
        .appointment-block {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 0.5rem;
            margin: 2px 0;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .appointment-block:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }
        .appointment-block.status-created { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        .appointment-block.status-in_progress { background: linear-gradient(135deg, #17a2b8, #007bff); }
        .appointment-block.status-completed { background: linear-gradient(135deg, #28a745, #20c997); }
        .day-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
        }
        .today { background-color: rgba(0,123,255,0.1) !important; }
        .weekend { background-color: rgba(255,193,7,0.1) !important; }
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
                <!-- Header with Navigation -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-calendar-week"></i> Πρόγραμμα Εργασίας</h2>
                    <div class="d-flex gap-2">
                        <a href="my_assignments.php" class="btn btn-outline-primary">
                            <i class="bi bi-list-check"></i> Οι Εργασίες μου
                        </a>
                        <a href="../mechanic_dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- Week Navigation -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <a href="?week=<?php echo $prev_week->format('Y-m-d'); ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-chevron-left"></i> Προηγούμενη Εβδομάδα
                                </a>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 class="mb-0">
                                    <?php echo $week_start->format('d/m/Y'); ?> - <?php echo $week_end->format('d/m/Y'); ?>
                                </h4>
                                <small class="text-muted">
                                    Εβδομάδα <?php echo $week_start->format('W'); ?>, <?php echo $week_start->format('Y'); ?>
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="?week=<?php echo $next_week->format('Y-m-d'); ?>" class="btn btn-outline-primary">
                                    Επόμενη Εβδομάδα <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Quick Jump to Today -->
                        <div class="text-center mt-3">
                            <a href="schedule.php" class="btn btn-sm btn-success">
                                <i class="bi bi-calendar-day"></i> Σημερινή Εβδομάδα
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Schedule Table -->
                <div class="card schedule-table">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <!-- Day Headers -->
                            <thead>
                                <tr>
                                    <th class="time-cell">Ώρα</th>
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <?php 
                                        $current_date = clone $week_start;
                                        $current_date->add(new DateInterval('P' . $i . 'D'));
                                        $is_today = $current_date->format('Y-m-d') === date('Y-m-d');
                                        $is_weekend = in_array($i, [5, 6]); // Saturday, Sunday
                                        ?>
                                        <th class="day-header <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>">
                                            <div><?php echo $days_greek[$i]; ?></div>
                                            <div style="font-size: 0.9rem; opacity: 0.8;">
                                                <?php echo $current_date->format('d/m'); ?>
                                            </div>
                                        </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($work_hours as $hour): ?>
                                    <tr>
                                        <td class="time-cell align-middle">
                                            <strong><?php echo $hour; ?></strong>
                                        </td>
                                        <?php for ($day = 0; $day < 7; $day++): ?>
                                            <?php 
                                            $current_date = clone $week_start;
                                            $current_date->add(new DateInterval('P' . $day . 'D'));
                                            $date_str = $current_date->format('Y-m-d');
                                            $is_today = $date_str === date('Y-m-d');
                                            $is_weekend = in_array($day, [5, 6]);
                                            ?>
                                            <td class="day-cell <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>">
                                                <?php
                                                // Find appointments for this time slot
                                                if (isset($schedule[$date_str])) {
                                                    foreach ($schedule[$date_str] as $appointment) {
                                                        $apt_time = date('H:i', strtotime($appointment['appointment_time']));
                                                        if ($apt_time === $hour) {
                                                            ?>
                                                            <div class="appointment-block status-<?php echo $appointment['status']; ?>"
                                                                 data-bs-toggle="modal" 
                                                                 data-bs-target="#appointmentModal<?php echo $appointment['id']; ?>">
                                                                <div style="font-weight: 600; font-size: 0.8rem;">
                                                                    <?php echo htmlspecialchars($appointment['customer_name']); ?>
                                                                </div>
                                                                <div style="font-size: 0.7rem; opacity: 0.9;">
                                                                    <?php echo htmlspecialchars($appointment['vehicle_info']); ?>
                                                                </div>
                                                                <div style="font-size: 0.7rem; opacity: 0.8;">
                                                                    <?php echo ucfirst($appointment['reason']); ?>
                                                                </div>
                                                            </div>
                                                            <?php
                                                        }
                                                    }
                                                }
                                                ?>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Legend -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6>Υπόμνημα:</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <span class="badge" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">Σε Αναμονή</span>
                            </div>
                            <div class="col-md-3">
                                <span class="badge" style="background: linear-gradient(135deg, #17a2b8, #007bff);">Σε Εξέλιξη</span>
                            </div>
                            <div class="col-md-3">
                                <span class="badge" style="background: linear-gradient(135deg, #28a745, #20c997);">Ολοκληρώθηκε</span>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted"><i class="bi bi-info-circle"></i> Κάντε κλικ για λεπτομέρειες</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment Detail Modals -->
    <?php foreach ($appointments as $appointment): ?>
        <div class="modal fade" id="appointmentModal<?php echo $appointment['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Λεπτομέρειες Εργασίας</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Πελάτης:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['customer_name']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Τηλέφωνο:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['customer_phone'] ?? 'Δεν υπάρχει'); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Αυτοκίνητο:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['vehicle_info']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Σειριακός:</strong></div>
                            <div class="col-sm-8"><?php echo htmlspecialchars($appointment['serial_number']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Ημερομηνία:</strong></div>
                            <div class="col-sm-8"><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Ώρα:</strong></div>
                            <div class="col-sm-8"><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Λόγος:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-secondary"><?php echo ucfirst($appointment['reason']); ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-4"><strong>Κατάσταση:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge bg-<?php 
                                    echo match($appointment['status']) {
                                        'created' => 'warning',
                                        'in_progress' => 'info',
                                        'completed' => 'success',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php 
                                    echo match($appointment['status']) {
                                        'created' => 'Σε Αναμονή',
                                        'in_progress' => 'Σε Εξέλιξη',
                                        'completed' => 'Ολοκληρώθηκε',
                                        default => 'Άγνωστο'
                                    };
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($appointment['problem_description']): ?>
                            <div class="alert alert-light">
                                <strong>Περιγραφή Προβλήματος:</strong><br>
                                <?php echo htmlspecialchars($appointment['problem_description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['work_notes']): ?>
                            <div class="alert alert-info">
                                <strong>Σημειώσεις Εργασίας:</strong><br>
                                <?php echo htmlspecialchars($appointment['work_notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
                        <a href="my_assignments.php#assignment<?php echo $appointment['id']; ?>" class="btn btn-primary">
                            Επεξεργασία
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>