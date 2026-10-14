<?php
// Include auth functions
require_once __DIR__ . '/auth.php';

// Get user data safely
$user_role = getUserRole();
$user_full_name = getUserFullName();
$user_id = $_SESSION['user_id'] ?? null;

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Base URL for absolute paths
$base_url = '/garage_management';
?>

<div class="sidebar bg-dark" style="min-height: 100vh;">
    <div class="p-3">
        <!-- User Info -->
        <div class="text-center mb-4">
            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="bi bi-person-fill text-white"></i>
            </div>
            <div class="mt-2">
                <h6 class="text-white mb-0"><?php echo htmlspecialchars($user_full_name); ?></h6>
                <small class="text-muted"><?php 
                    $role_labels = [
                        'customer' => 'Πελάτης',
                        'secretary' => 'Γραμματέας', 
                        'mechanic' => 'Μηχανικός',
                        'admin' => 'Διαχειριστής',
                        'guest' => 'Επισκέπτης'
                    ];
                    echo $role_labels[$user_role] ?? 'Άγνωστος';
                ?></small>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="nav flex-column">
            <?php if ($user_role === 'customer'): ?>
                <!-- Customer Menu -->
                <a class="nav-link text-white <?php echo $current_page === 'customer_dashboard.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/customer_dashboard.php">
                    <i class="bi bi-house-door me-2"></i>Dashboard
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'customer_vehicles.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/vehicles/customer_vehicles.php">
                    <i class="bi bi-car-front me-2"></i>Τα Αυτοκίνητά μου
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'my_appointments.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/appointments/my_appointments.php">
                    <i class="bi bi-calendar-check me-2"></i>Τα Ραντεβού μου
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'book_appointment.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/appointments/book_appointment.php">
                    <i class="bi bi-calendar-plus me-2"></i>Κλείσιμο Ραντεβού
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'add_vehicle.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/vehicles/add_vehicle.php">
                    <i class="bi bi-car-front-fill me-2"></i>Προσθήκη Αυτοκινήτου
                </a>
                
                <hr class="text-white">
                
                <a class="nav-link text-white <?php echo $current_page === 'edit_profile.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/users/edit_profile.php">
                    <i class="bi bi-person-gear me-2"></i>Επεξεργασία Προφίλ
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'my_history.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/reports/my_history.php">
                    <i class="bi bi-clock-history me-2"></i>Ιστορικό Εργασιών
                </a>
                
            <?php elseif ($user_role === 'secretary'): ?>
                <!-- Secretary/Admin Menu -->
                <a class="nav-link text-white <?php echo $current_page === 'secretary_dashboard.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/secretary_dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'manage_users.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/users/manage_users.php">
                    <i class="bi bi-people me-2"></i>Διαχείριση Χρηστών
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'manage_vehicles.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/vehicles/manage_vehicles.php">
                    <i class="bi bi-car-front me-2"></i>Διαχείριση Οχημάτων
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'manage_appointments.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/appointments/manage_appointments.php">
                    <i class="bi bi-calendar-event me-2"></i>Διαχείριση Ραντεβού
                </a>
                
                <hr class="text-white">
                
                <a class="nav-link text-white <?php echo $current_page === 'add_user.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/users/add_user.php">
                    <i class="bi bi-person-plus me-2"></i>Νέος Χρήστης
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'add_vehicle.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/vehicles/add_vehicle.php">
                    <i class="bi bi-car-front-fill me-2"></i>Νέο Όχημα
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'book_appointment.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/appointments/book_appointment.php">
                    <i class="bi bi-calendar-plus me-2"></i>Νέο Ραντεβού
                </a>
                
                <hr class="text-white">
                
                <a class="nav-link text-white <?php echo $current_dir === 'reports' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/reports/index.php">
                    <i class="bi bi-bar-chart me-2"></i>Αναφορές
                </a>
                
            <?php elseif ($user_role === 'mechanic'): ?>
                <!-- Mechanic Menu -->
                <a class="nav-link text-white <?php echo $current_page === 'mechanic_dashboard.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/mechanic_dashboard.php">
                    <i class="bi bi-tools me-2"></i>Dashboard Μηχανικού
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'my_assignments.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/appointments/my_assignments.php">
                    <i class="bi bi-calendar-check me-2"></i>Οι Εργασίες μου
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'schedule.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/appointments/schedule.php">
                    <i class="bi bi-calendar-week me-2"></i>Πρόγραμμα
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'work_progress.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/mechanics/work_progress.php">
                    <i class="bi bi-gear me-2"></i>Πρόοδος Εργασιών
                </a>
                
                <hr class="text-white">
                
                <a class="nav-link text-white <?php echo $current_page === 'edit_profile.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/users/edit_profile.php">
                    <i class="bi bi-person-gear me-2"></i>Επεξεργασία Προφίλ
                </a>
                
            <?php elseif ($user_role === 'admin'): ?>
                <!-- Admin Menu -->
                <a class="nav-link text-white <?php echo $current_page === 'admin_dashboard.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/admin_dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>Admin Dashboard
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'system_settings.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/admin/system_settings.php">
                    <i class="bi bi-gear me-2"></i>Ρυθμίσεις Συστήματος
                </a>
                
                <a class="nav-link text-white <?php echo $current_page === 'system_logs.php' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/admin/system_logs.php">
                    <i class="bi bi-file-text me-2"></i>Αρχεία Καταγραφής
                </a>
                
                <hr class="text-white">
                
                <a class="nav-link text-white <?php echo $current_dir === 'reports' ? 'active bg-primary rounded' : ''; ?>" 
                   href="<?php echo $base_url; ?>/reports/index.php">
                    <i class="bi bi-bar-chart me-2"></i>Αναφορές
                </a>
                
            <?php else: ?>
                <!-- Guest/Unknown User Menu -->
                <a class="nav-link text-white" href="<?php echo $base_url; ?>/auth/login.php">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Σύνδεση
                </a>
                <a class="nav-link text-white" href="<?php echo $base_url; ?>/auth/register.php">
                    <i class="bi bi-person-plus me-2"></i>Εγγραφή
                </a>
            <?php endif; ?>
            
            <!-- Logout for logged in users -->
            <?php if ($user_id): ?>
                <hr class="text-white">
                <a class="nav-link text-white" href="<?php echo $base_url; ?>/auth/logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>Αποσύνδεση
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<style>
.sidebar .nav-link {
    padding: 0.75rem 1rem;
    margin-bottom: 0.25rem;
    border-radius: 0.375rem;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: white !important;
    transform: translateX(3px);
}

.sidebar .nav-link.active {
    background-color: #007bff !important;
    color: white !important;
    font-weight: 500;
}

.sidebar .nav-link.active:hover {
    background-color: #0056b3 !important;
    transform: translateX(0);
}

.sidebar hr {
    border-color: rgba(255, 255, 255, 0.2) !important;
    margin: 1rem 0;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100% !important;
        min-height: auto !important;
    }
}
</style>