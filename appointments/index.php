<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth_check.php';

// Check if user is secretary
if (!isSecretary()) {
    header("Location: ../../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Ραντεβού - AutoService</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-calendar-alt"></i> Διαχείριση Ραντεβού</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAppointmentModal">
                            <i class="fas fa-plus"></i> Νέο Ραντεβού
                        </button>
                    </div>
                </div>

                <!-- Φίλτρα Αναζήτησης -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Από Ημερομηνία</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo $_GET['date_from'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Έως Ημερομηνία</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo $_GET['date_to'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="customer_search" class="form-label">Πελάτης</label>
                                <input type="text" class="form-control" id="customer_search" name="customer_search" 
                                       placeholder="Επίθετο ή ΑΦΜ" value="<?php echo $_GET['customer_search'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Κατάσταση</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Όλες</option>
                                    <option value="ΔΗΜΙΟΥΡΓΗΜΕΝΟ" <?php echo ($_GET['status'] ?? '') == 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ' ? 'selected' : ''; ?>>Δημιουργημένο</option>
                                    <option value="ΣΕ ΕΞΕΛΙΞΗ" <?php echo ($_GET['status'] ?? '') == 'ΣΕ ΕΞΕΛΙΞΗ' ? 'selected' : ''; ?>>Σε Εξέλιξη</option>
                                    <option value="ΠΕΡΑΙΩΜΕΝΟ" <?php echo ($_GET['status'] ?? '') == 'ΠΕΡΑΙΩΜΕΝΟ' ? 'selected' : ''; ?>>Περαιωμένο</option>
                                    <option value="ΑΚΥΡΩΜΕΝΟ" <?php echo ($_GET['status'] ?? '') == 'ΑΚΥΡΩΜΕΝΟ' ? 'selected' : ''; ?>>Ακυρωμένο</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Αναζήτηση
                                </button>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Καθαρισμός
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Πίνακας Ραντεβού -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ημερομηνία</th>
                                        <th>Ώρα</th>
                                        <th>Πελάτης</th>
                                        <th>Αυτοκίνητο</th>
                                        <th>Μηχανικός</th>
                                        <th>Λόγος</th>
                                        <th>Κατάσταση</th>
                                        <th>Ενέργειες</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <p class="mb-0">Δεν υπάρχουν ραντεβού προς εμφάνιση.</p>
                                            <small class="text-muted">Δημιουργήστε το πρώτο ραντεβού πατώντας το κουμπί "Νέο Ραντεβού"</small>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>