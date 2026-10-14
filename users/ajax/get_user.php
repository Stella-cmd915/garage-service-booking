<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in and is secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    http_response_code(403);
    exit('Unauthorized');
}

$user_id = $_GET['id'] ?? '';

if (empty($user_id)) {
    http_response_code(400);
    exit('Missing user ID');
}

try {
    $query = "
        SELECT u.*, 
               c.afm, c.address,
               m.specialty
        FROM users u
        LEFT JOIN customers c ON u.id = c.user_id
        LEFT JOIN mechanics m ON u.id = m.user_id
        WHERE u.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        exit('User not found');
    }
    
    $role_labels = [
        'customer' => 'Πελάτης',
        'mechanic' => 'Μηχανικός',
        'secretary' => 'Γραμματέας'
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Database error');
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary"><i class="bi bi-person-badge"></i> Βασικά Στοιχεία</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>ID:</strong></td>
                <td><?php echo $user['id']; ?></td>
            </tr>
            <tr>
                <td><strong>Όνομα χρήστη:</strong></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
            </tr>
            <tr>
                <td><strong>Ονοματεπώνυμο:</strong></td>
                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
            </tr>
            <tr>
                <td><strong>Αριθμός Ταυτότητας:</strong></td>
                <td><?php echo htmlspecialchars($user['id_number']); ?></td>
            </tr>
            <tr>
                <td><strong>Ρόλος:</strong></td>
                <td>
                    <span class="badge bg-<?php echo $user['role'] === 'customer' ? 'info' : ($user['role'] === 'mechanic' ? 'warning' : 'success'); ?>">
                        <?php echo $role_labels[$user['role']]; ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="text-primary"><i class="bi bi-gear"></i> Κατάσταση Λογαριασμού</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Κατάσταση:</strong></td>
                <td>
                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                        <i class="bi bi-circle-fill"></i>
                        <?php echo $user['is_active'] ? 'Ενεργός' : 'Ανενεργός'; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Ημερομηνία εγγραφής:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
            </tr>
            <tr>
                <td><strong>Τελευταία ενημέρωση:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if ($user['role'] === 'customer'): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-primary"><i class="bi bi-person-check"></i> Στοιχεία Πελάτη</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>ΑΦΜ:</strong></td>
                    <td><?php echo htmlspecialchars($user['afm']); ?></td>
                </tr>
                <tr>
                    <td><strong>Διεύθυνση:</strong></td>
                    <td><?php echo htmlspecialchars($user['address']); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Customer's vehicles -->
    <?php
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $vehicles = $stmt->fetchAll();
    ?>
    
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-primary"><i class="bi bi-car-front"></i> Αυτοκίνητα (<?php echo count($vehicles); ?>)</h6>
            <?php if (empty($vehicles)): ?>
                <p class="text-muted">Δεν έχει καταχωρημένα αυτοκίνητα.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Ημερομηνία</th>
                                <th>Ώρα</th>
                                <th>Αυτοκίνητο</th>
                                <th>Λόγος</th>
                                <th>Κατάσταση</th>
                                <th>Μηχανικός</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['brand'] . ' ' . $appointment['model']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $appointment['reason'] === 'repair' ? 'warning' : 'info'; ?>">
                                            <?php echo $appointment['reason'] === 'repair' ? 'Επιδιόρθωση' : 'Σέρβις'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_labels = [
                                            'created' => '<span class="badge bg-secondary">Δημιουργημένο</span>',
                                            'in_progress' => '<span class="badge bg-primary">Σε εξέλιξη</span>',
                                            'completed' => '<span class="badge bg-success">Περαιωμένο</span>',
                                            'cancelled' => '<span class="badge bg-danger">Ακυρωμένο</span>'
                                        ];
                                        echo $status_labels[$appointment['status']];
                                        ?>
                                    </td>
                                    <td><?php echo $appointment['mechanic_name'] ? htmlspecialchars($appointment['mechanic_name']) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
<?php elseif ($user['role'] === 'mechanic'): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-primary"><i class="bi bi-tools"></i> Στοιχεία Μηχανικού</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Ειδικότητα:</strong></td>
                    <td><?php echo htmlspecialchars($user['specialty']); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- Mechanic's appointments -->
    <?php
    $stmt = $pdo->prepare("
        SELECT a.*, v.brand, v.model, v.serial_number,
               CONCAT(cu.first_name, ' ', cu.last_name) as customer_name
        FROM appointments a
        LEFT JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users cu ON a.customer_id = cu.id
        WHERE a.mechanic_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $mechanic_appointments = $stmt->fetchAll();
    ?>
    
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-primary"><i class="bi bi-calendar-event"></i> Πρόσφατα Ραντεβού (<?php echo count($mechanic_appointments); ?>)</h6>
            <?php if (empty($mechanic_appointments)): ?>
                <p class="text-muted">Δεν έχει ανατεθεί σε ραντεβού.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Ημερομηνία</th>
                                <th>Ώρα</th>
                                <th>Πελάτης</th>
                                <th>Αυτοκίνητο</th>
                                <th>Λόγος</th>
                                <th>Κατάσταση</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mechanic_appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['brand'] . ' ' . $appointment['model']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $appointment['reason'] === 'repair' ? 'warning' : 'info'; ?>">
                                            <?php echo $appointment['reason'] === 'repair' ? 'Επιδιόρθωση' : 'Σέρβις'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_labels = [
                                            'created' => '<span class="badge bg-secondary">Δημιουργημένο</span>',
                                            'in_progress' => '<span class="badge bg-primary">Σε εξέλιξη</span>',
                                            'completed' => '<span class="badge bg-success">Περαιωμένο</span>',
                                            'cancelled' => '<span class="badge bg-danger">Ακυρωμένο</span>'
                                        ];
                                        echo $status_labels[$appointment['status']];
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
                        <thead>
                            <tr>
                                <th>Σειριακός Αριθμός</th>
                                <th>Μάρκα</th>
                                <th>Μοντέλο</th>
                                <th>Τύπος</th>
                                <th>Είδος Κίνησης</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vehicle['serial_number']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['brand']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['engine_type']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Customer's appointments -->
    <?php
    $stmt = $pdo->prepare("
        SELECT a.*, v.brand, v.model, v.serial_number,
               CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name
        FROM appointments a
        LEFT JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users mu ON a.mechanic_id = mu.id
        WHERE a.customer_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $appointments = $stmt->fetchAll();
    ?>
    
    <div class="row mt-3">
        <div class="col-12">
            <h6 class="text-primary"><i class="bi bi-calendar-event"></i> Πρόσφατα Ραντεβού (<?php echo count($appointments); ?>)</h6>
            <?php if (empty($appointments)): ?>
                <p class="text-muted">Δεν έχει καταχωρημένα ραντεβού.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">