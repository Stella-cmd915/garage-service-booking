<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Require customer role
requireRole('customer');

$user_id = $_SESSION['user_id'];

// Get customer data
try {
    // Get customer details
    $stmt = $pdo->prepare("
        SELECT u.*, c.afm, c.address 
        FROM users u 
        LEFT JOIN customers c ON u.id = c.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $customer = $stmt->fetch();
    
    // Get customer's vehicles count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vehicles WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $vehiclesCount = $stmt->fetch()['count'];
    
    // Get customer's appointments count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE customer_id = ?");
    $stmt->execute([$user_id]);
    $appointmentsCount = $stmt->fetch()['count'];
    
    // Get upcoming appointments
    $stmt = $pdo->prepare("
        SELECT a.*, v.make, v.model, v.license_plate,
               CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name,
               m.specialty as mechanic_specialty
        FROM appointments a
        LEFT JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users mu ON a.mechanic_id = mu.id
        LEFT JOIN mechanics m ON mu.id = m.user_id
        WHERE a.customer_id = ? AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $upcomingAppointments = $stmt->fetchAll();
    
    // Get recent appointments
    $stmt = $pdo->prepare("
        SELECT a.*, v.make, v.model, v.license_plate,
               CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name
        FROM appointments a
        LEFT JOIN vehicles v ON a.vehicle_id = v.id
        LEFT JOIN users mu ON a.mechanic_id = mu.id
        WHERE a.customer_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recentAppointments = $stmt->fetchAll();
    
    // Get customer's vehicles
    $stmt = $pdo->prepare("
        SELECT * FROM vehicles 
        WHERE customer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $customerVehicles = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Customer dashboard query error: " . $e->getMessage());
    $customer = null;
    $vehiclesCount = $appointmentsCount = 0;
    $upcomingAppointments = $recentAppointments = $customerVehicles = [];
}

$pageTitle = 'Ο Λογαριασμός μου - Μηχανουργείο AutoCare';
include '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-user"></i>
            Ο Λογαριασμός μου
        </h1>
        <p>Καλώς ήρθατε, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Διαχειριστείτε τα στοιχεία και τις υπηρεσίες σας.</p>
    </div>

    <!-- Customer Stats -->
    <div class="stats-grid">
        <div class="stat-card vehicles">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($vehiclesCount); ?></h3>
                <p>Τα Αυτοκίνητά μου</p>
            </div>
        </div>

        <div class="stat-card appointments">
            <div class="stat-icon">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($appointmentsCount); ?></h3>
                <p>Συνολικά Ραντεβού</p>
            </div>
        </div>

        <div class="stat-card profile">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3>Ενεργός</h3>
                <p>Κατάσταση Λογαριασμού</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2><i class="fas fa-bolt"></i> Γρήγορες Ενέργειες</h2>
        <div class="actions-grid">
            <a href="../appointments/book_appointment.php" class="action-card primary">
                <i class="fas fa-calendar-plus"></i>
                <h3>Κλείσιμο Ραντεβού</h3>
                <p>Κλείστε νέο ραντεβού για service ή επισκευή</p>
            </a>

            <a href="../vehicles/add_vehicle.php" class="action-card">
                <i class="fas fa-car-side"></i>
                <h3>Προσθήκη Αυτοκινήτου</h3>
                <p>Καταχωρήστε νέο αυτοκίνητο στο σύστημα</p>
            </a>

            <a href="../appointments/my_appointments.php" class="action-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Τα Ραντεβού μου</h3>
                <p>Προβολή και διαχείριση των ραντεβού σας</p>
            </a>

            <a href="../vehicles/my_vehicles.php" class="action-card">
                <i class="fas fa-car"></i>
                <h3>Τα Αυτοκίνητά μου</h3>
                <p>Διαχείριση των αυτοκινήτων σας</p>
            </a>

            <a href="edit_profile.php" class="action-card">
                <i class="fas fa-user-edit"></i>
                <h3>Επεξεργασία Προφίλ</h3>
                <p>Ενημερώστε τα στοιχεία σας</p>
            </a>

            <a href="../reports/my_history.php" class="action-card">
                <i class="fas fa-history"></i>
                <h3>Ιστορικό Εργασιών</h3>
                <p>Προβάλετε το ιστορικό των εργασιών σας</p>
            </a>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- Profile Info -->
        <div class="content-section profile-info">
            <h2><i class="fas fa-user-circle"></i> Τα Στοιχεία μου</h2>
            
            <div class="profile-details">
                <div class="profile-item">
                    <strong>Ονοματεπώνυμο:</strong>
                    <span><?php echo htmlspecialchars(($_SESSION['full_name'] ?? 'Μη διαθέσιμο')); ?></span>
                </div>
                
                <div class="profile-item">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars(($customer['email'] ?? 'Μη διαθέσιμο')); ?></span>
                </div>
                
                <div class="profile-item">
                    <strong>Όνομα Χρήστη:</strong>
                    <span><?php echo htmlspecialchars(($customer['username'] ?? 'Μη διαθέσιμο')); ?></span>
                </div>
                
                <div class="profile-item">
                    <strong>ΑΦΜ:</strong>
                    <span><?php echo htmlspecialchars(($customer['afm'] ?? 'Μη διαθέσιμο')); ?></span>
                </div>
                
                <div class="profile-item">
                    <strong>Διεύθυνση:</strong>
                    <span><?php echo htmlspecialchars(($customer['address'] ?? 'Μη διαθέσιμη')); ?></span>
                </div>
                
                <div class="profile-item">
                    <strong>Αρ. Ταυτότητας:</strong>
                    <span><?php echo htmlspecialchars(($customer['id_number'] ?? 'Μη διαθέσιμος')); ?></span>
                </div>
            </div>
            
            <div class="section-footer">
                <a href="edit_profile.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Επεξεργασία
                </a>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="content-section">
            <h2><i class="fas fa-calendar-alt"></i> Επερχόμενα Ραντεβού</h2>
            
            <?php if (empty($upcomingAppointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar"></i>
                    <p>Δεν έχετε επερχόμενα ραντεβού</p>
                    <a href="../appointments/book_appointment.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i>
                        Κλείστε Ραντεβού
                    </a>
                </div>
            <?php else: ?>
                <div class="appointments-list">
                    <?php foreach ($upcomingAppointments as $appointment): ?>
                        <div class="appointment-item">
                            <div class="appointment-date">
                                <div class="date">
                                    <?php echo date('d', strtotime($appointment['appointment_date'])); ?>
                                </div>
                                <div class="month">
                                    <?php echo date('M', strtotime($appointment['appointment_date'])); ?>
                                </div>
                            </div>
                            <div class="appointment-info">
                                <h4><?php echo htmlspecialchars($appointment['service_type']); ?></h4>
                                <p>
                                    <i class="fas fa-car"></i>
                                    <?php echo htmlspecialchars($appointment['make'] . ' ' . $appointment['model']); ?>
                                    (<?php echo htmlspecialchars($appointment['license_plate']); ?>)
                                </p>
                                <p>
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                </p>
                                <?php if ($appointment['mechanic_name']): ?>
                                    <p>
                                        <i class="fas fa-wrench"></i>
                                        <?php echo htmlspecialchars($appointment['mechanic_name']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="appointment-status">
                                <span class="status <?php echo strtolower($appointment['status']); ?>">
                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-footer">
                    <a href="../appointments/my_appointments.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Προβολή Όλων
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Vehicles -->
    <div class="content-section full-width">
        <h2><i class="fas fa-car"></i> Τα Αυτοκίνητά μου</h2>
        
        <?php if (empty($customerVehicles)): ?>
            <div class="empty-state">
                <i class="fas fa-car"></i>
                <p>Δεν έχετε καταχωρημένα αυτοκίνητα</p>
                <a href="../vehicles/add_vehicle.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i>
                    Προσθήκη Αυτοκινήτου
                </a>
            </div>
        <?php else: ?>
            <div class="vehicles-grid">
                <?php foreach ($customerVehicles as $vehicle): ?>
                    <div class="vehicle-card">
                        <div class="vehicle-header">
                            <h4><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h4>
                            <span class="license-plate"><?php echo htmlspecialchars($vehicle['license_plate']); ?></span>
                        </div>
                        <div class="vehicle-details">
                            <div class="detail">
                                <span class="label">Έτος:</span>
                                <span><?php echo htmlspecialchars($vehicle['year']); ?></span>
                            </div>
                            <div class="detail">
                                <span class="label">Τύπος:</span>
                                <span><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></span>
                            </div>
                            <div class="detail">
                                <span class="label">Κίνηση:</span>
                                <span><?php echo htmlspecialchars($vehicle['engine_type']); ?></span>
                            </div>
                        </div>
                        <div class="vehicle-actions">
                            <a href="../vehicles/view_vehicle.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-eye"></i>
                                Προβολή
                            </a>
                            <a href="../appointments/book_appointment.php?vehicle_id=<?php echo $vehicle['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-calendar-plus"></i>
                                Ραντεβού
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="section-footer">
                <a href="../vehicles/my_vehicles.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i>
                    Προβολή Όλων
                </a>
                <a href="../vehicles/add_vehicle.php" class="btn btn-secondary">
                    <i class="fas fa-plus"></i>
                    Προσθήκη Νέου
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem 0;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 12px;
    margin-top: 1rem;
}

.dashboard-header h1 {
    font-size: 2.2rem;
    margin-bottom: 0.5rem;
}

.dashboard-header p {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2.5rem;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-card.vehicles .stat-icon { background: #28a745; }
.stat-card.appointments .stat-icon { background: #dc3545; }
.stat-card.profile .stat-icon { background: #2c5aa0; }

.stat-info h3 {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.25rem;
}

.stat-info p {
    color: #666;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 3rem;
}

.quick-actions h2 {
    color: #28a745;
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.action-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-decoration: none;
    color: inherit;
    transition: transform 0.3s, box-shadow 0.3s;
    border-left: 4px solid #28a745;
}

.action-card.primary {
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.action-card.primary h3,
.action-card.primary p {
    color: white;
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    text-decoration: none;
    color: inherit;
}

.action-card i {
    font-size: 2rem;
    color: #28a745;
    margin-bottom: 1rem;
}

.action-card.primary i {
    color: white;
}

.action-card h3 {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.action-card p {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Dashboard Content */
.dashboard-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.content-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 1.5rem;
}

.content-section.full-width {
    grid-column: 1 / -1;
}

.content-section h2 {
    color: #28a745;
    margin-bottom: 1.5rem;
    font-size: 1.3rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f0f0f0;
}

/* Profile Info */
.profile-details {
    display: grid;
    gap: 1rem;
}

.profile-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.profile-item strong {
    color: #333;
    font-weight: 600;
}

.profile-item span {
    color: #666;
}

/* Appointments */
.appointments-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.appointment-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

.appointment-date {
    text-align: center;
    background: #28a745;
    color: white;
    padding: 0.5rem;
    border-radius: 8px;
    min-width: 60px;
}

.appointment-date .date {
    font-size: 1.5rem;
    font-weight: bold;
}

.appointment-date .month {
    font-size: 0.8rem;
    text-transform: uppercase;
}

.appointment-info {
    flex: 1;
}

.appointment-info h4 {
    margin: 0 0 0.5rem 0;
    color: #333;
}

.appointment-info p {
    margin: 0.25rem 0;
    color: #666;
    font-size: 0.9rem;
}

.appointment-info i {
    margin-right: 0.5rem;
    color: #28a745;
    width: 16px;
}

.appointment-status .status {
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status.scheduled { background: #fff3cd; color: #856404; }
.status.confirmed { background: #d4edda; color: #155724; }
.status.in_progress { background: #cce5ff; color: #004085; }
.status.completed { background: #d1ecf1; color: #0c5460; }

/* Vehicles */
.vehicles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.vehicle-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    border-left: 4px solid #28a745;
    transition: transform 0.3s;
}

.vehicle-card:hover {
    transform: translateY(-2px);
}

.vehicle-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.vehicle-header h4 {
    margin: 0;
    color: #333;
}

.license-plate {
    background: #333;
    color: white;
    padding: 0.3rem 0.6rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
}

.vehicle-details {
    display: grid;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.detail {
    display: flex;
    justify-content: space-between;
}

.detail .label {
    font-weight: 600;
    color: #333;
}

.vehicle-actions {
    display: flex;
    gap: 0.5rem;
}

/* Section Footer */
.section-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #666;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Buttons */
.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.btn-primary {
    background: #28a745;
    color: white;
}

.btn-primary:hover {
    background: #218838;
    color: white;
    text-decoration: none;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
    text-decoration: none;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-content {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .vehicles-grid {
        grid-template-columns: 1fr;
    }
    
    .appointment-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .vehicle-actions {
        flex-direction: column;
    }
    
    .vehicle-actions .btn {
        width: 100%;
    }
}
</style>

<?php include '../includes/footer.php'; ?>