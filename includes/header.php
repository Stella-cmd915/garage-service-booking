<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Μηχανουργείο AutoCare'); ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '/garage_management/css/'; ?>main.css">
    <link rel="stylesheet" href="<?php echo isset($css_path) ? $css_path : '/garage_management/css/'; ?>responsive.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="/garage_management/" class="brand-link">
                    <i class="fas fa-car"></i>
                    <span>AutoCare</span>
                </a>
            </div>
            
            <div class="nav-menu">
                <a href="/garage_management/" class="nav-link">
                    <i class="fas fa-home"></i>
                    Αρχική
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <?php
                    $role = $_SESSION['user_role'];
                    $username = $_SESSION['username'];
                    $fullName = $_SESSION['full_name'];
                    ?>
                    
                    <div class="nav-dropdown">
                        <a href="#" class="nav-link dropdown-toggle">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($fullName); ?>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="dropdown-menu">
                            <?php if ($role === 'secretary'): ?>
                                <a href="/garage_management/users/secretary_dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i>
                                    Dashboard
                                </a>
                                <a href="/garage_management/users/user_management.php">
                                    <i class="fas fa-users"></i>
                                    Χρήστες
                                </a>
                            <?php elseif ($role === 'customer'): ?>
                                <a href="/garage_management/users/customer_dashboard.php">
                                    <i class="fas fa-user"></i>
                                    Προφίλ
                                </a>
                                <a href="/garage_management/appointments/customer_appointments.php">
                                    <i class="fas fa-calendar"></i>
                                    Ραντεβού
                                </a>
                                <a href="/garage_management/vehicles/customer_vehicles.php">
                                    <i class="fas fa-car"></i>
                                    Αυτοκίνητα
                                </a>
                            <?php elseif ($role === 'mechanic'): ?>
                                <a href="/garage_management/users/mechanic_dashboard.php">
                                    <i class="fas fa-wrench"></i>
                                    Dashboard
                                </a>
                                <a href="/garage_management/appointments/mechanic_appointments.php">
                                    <i class="fas fa-calendar-check"></i>
                                    Ραντεβού
                                </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="/garage_management/auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                Αποσύνδεση
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/garage_management/auth/login.php" class="nav-link">
                        <i class="fas fa-sign-in-alt"></i>
                        Σύνδεση
                    </a>
                    <a href="/garage_management/auth/register.php" class="nav-link btn-outline">
                        <i class="fas fa-user-plus"></i>
                        Εγγραφή
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="nav-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>
    
    <main class="main-content">