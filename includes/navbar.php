<?php
// Make sure auth functions are available
require_once __DIR__ . '/auth.php';

$current_user = getCurrentUser();
$user_role = $current_user['role'] ?? '';
$user_name = getUserFullName();

// Base URL
$base_url = '/garage_management';

// Role-specific dashboard links
$dashboard_links = [
    'secretary' => $base_url . '/secretary_dashboard.php',
    'customer' => $base_url . '/customer_dashboard.php', 
    'mechanic' => $base_url . '/mechanic_dashboard.php',
    'admin' => $base_url . '/admin_dashboard.php'
];

$dashboard_link = $dashboard_links[$user_role] ?? $base_url . '/auth/login.php';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="<?php echo $dashboard_link; ?>">
            <i class="bi bi-wrench-adjustable-circle me-2"></i>
            AutoService
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Left side navigation -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $dashboard_link; ?>">
                        <i class="bi bi-house me-1"></i>Αρχική
                    </a>
                </li>
                
                <?php if ($user_role === 'secretary'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-people me-1"></i>Χρήστες
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/users/manage_users.php">
                                <i class="bi bi-person-gear me-2"></i>Διαχείριση Χρηστών
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/users/add_user.php">
                                <i class="bi bi-person-plus me-2"></i>Νέος Χρήστης
                            </a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-car-front me-1"></i>Αυτοκίνητα
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vehicles/manage_vehicles.php">
                                <i class="bi bi-list me-2"></i>Λίστα Αυτοκινήτων
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/vehicles/add_vehicle.php">
                                <i class="bi bi-plus-square me-2"></i>Νέο Αυτοκίνητο
                            </a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-calendar-check me-1"></i>Ραντεβού
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/appointments/manage_appointments.php">
                                <i class="bi bi-calendar-event me-2"></i>Διαχείριση Ραντεβού
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/appointments/book_appointment.php">
                                <i class="bi bi-calendar-plus me-2"></i>Νέο Ραντεβού
                            </a></li>
                        </ul>
                    </li>
                <?php endif; ?>
                
                <?php if ($user_role === 'customer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/appointments/my_appointments.php">
                            <i class="bi bi-calendar-check me-1"></i>Τα Ραντεβού μου
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/vehicles/customer_vehicles.php">
                            <i class="bi bi-car-front me-1"></i>Τα Αυτοκίνητά μου
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($user_role === 'mechanic'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/appointments/my_assignments.php">
                            <i class="bi bi-tools me-1"></i>Οι Εργασίες μου
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_url; ?>/appointments/schedule.php">
                            <i class="bi bi-calendar-week me-1"></i>Πρόγραμμα
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right side - User menu -->
            <ul class="navbar-nav">
                <!-- Language switcher (placeholder) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-globe me-1"></i>EL
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item active" href="#"><i class="bi bi-check me-2"></i>Ελληνικά</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-translate me-2"></i>English</a></li>
                    </ul>
                </li>

                <!-- User dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" 
                       data-bs-auto-close="outside">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($user_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <!-- User info header -->
                        <li class="dropdown-header">
                            <div class="d-flex flex-column">
                                <strong><?php echo htmlspecialchars($user_name); ?></strong>
                                <small class="text-muted">
                                    <span class="badge bg-<?php 
                                        echo match($user_role) {
                                            'secretary' => 'primary',
                                            'customer' => 'success', 
                                            'mechanic' => 'warning',
                                            'admin' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php 
                                        echo match($user_role) {
                                            'secretary' => 'Γραμματέας',
                                            'customer' => 'Πελάτης',
                                            'mechanic' => 'Μηχανικός',
                                            'admin' => 'Διαχειριστής',
                                            default => ucfirst($user_role)
                                        };
                                        ?>
                                    </span>
                                </small>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Profile and settings -->
                        <li><a class="dropdown-item" href="<?php echo $base_url; ?>/users/edit_profile.php">
                            <i class="bi bi-person me-2"></i>Προφίλ
                        </a></li>
                        
                        <?php if ($user_role === 'secretary' || $user_role === 'admin'): ?>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/admin/settings.php">
                                <i class="bi bi-gear me-2"></i>Ρυθμίσεις Συστήματος
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/reports/index.php">
                                <i class="bi bi-bar-chart me-2"></i>Αναφορές
                            </a></li>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'customer'): ?>
                            <li><a class="dropdown-item" href="<?php echo $base_url; ?>/reports/my_history.php">
                                <i class="bi bi-clock-history me-2"></i>Ιστορικό Εργασιών
                            </a></li>
                        <?php endif; ?>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Session info -->
                        <li class="dropdown-header">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Σύνδεση: <?php echo date('H:i', $_SESSION['login_time'] ?? time()); ?>
                            </small>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Logout -->
                        <li>
                            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout()">
                                <i class="bi bi-box-arrow-right me-2"></i>Αποσύνδεση
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Logout confirmation modal -->
<div class="modal fade" id="logoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-box-arrow-right me-2"></i>Επιβεβαίωση Αποσύνδεσης
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Είστε σίγουροι ότι θέλετε να αποσυνδεθείτε;</p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Θα χρειαστεί να συνδεθείτε ξανά για να προσπελάσετε το σύστημα.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Ακύρωση
                </button>
                <a href="<?php echo $base_url; ?>/auth/logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right me-1"></i>Αποσύνδεση
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    const modal = new bootstrap.Modal(document.getElementById('logoutModal'));
    modal.show();
}

// Session timeout warning (optional)
let sessionTimeout;
let warningTimeout;

function resetSessionTimer() {
    clearTimeout(sessionTimeout);
    clearTimeout(warningTimeout);
    
    // Warning 5 minutes before timeout
    warningTimeout = setTimeout(() => {
        if (confirm('Η συνεδρία σας θα λήξει σε 5 λεπτά. Θέλετε να παραμείνετε συνδεδεμένοι;')) {
            // Ping server to extend session
            fetch('<?php echo $base_url; ?>/ping.php', {method: 'POST'});
        }
    }, 115 * 60 * 1000); // 115 minutes
    
    // Actual timeout
    sessionTimeout = setTimeout(() => {
        alert('Η συνεδρία σας έληξε. Θα ανακατευθυνθείτε στη σελίδα σύνδεσης.');
        window.location.href = '<?php echo $base_url; ?>/auth/logout.php';
    }, 120 * 60 * 1000); // 120 minutes
}

// Reset timer on any user activity
document.addEventListener('click', resetSessionTimer);
document.addEventListener('keypress', resetSessionTimer);

// Initialize timer
resetSessionTimer();
</script>