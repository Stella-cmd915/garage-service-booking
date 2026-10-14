<?php
session_start();

// Έλεγχος ρόλου
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'mechanic') {
    header("Location: login.php");
    exit();
}

require_once("config.php");

$mechanic_id = $_SESSION['user_id'];

// Σήμερα
$today = date("Y-m-d");

// Συνολικός αριθμός ραντεβού μηχανικού
$sql1 = "SELECT COUNT(*) as total FROM appointments WHERE mechanic_id = ?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("i", $mechanic_id);
$stmt1->execute();
$total = $stmt1->get_result()->fetch_assoc()['total'];

// Σημερινά ραντεβού
$sql2 = "SELECT COUNT(*) as today FROM appointments WHERE mechanic_id = ? AND appointment_date = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("is", $mechanic_id, $today);
$stmt2->execute();
$today_count = $stmt2->get_result()->fetch_assoc()['today'];

// Ολοκληρωμένα
$sql3 = "SELECT COUNT(*) as done FROM appointments WHERE mechanic_id = ? AND status = 'completed'";
$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("i", $mechanic_id);
$stmt3->execute();
$done = $stmt3->get_result()->fetch_assoc()['done'];
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Μηχανικού</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>🔧 Dashboard Μηχανικού</h2>
    <p>Καλώς ήρθες, <strong><?= htmlspecialchars($_SESSION['first_name']) ?></strong>!</p>

    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card text-center p-3">
                <h4>Συνολικά Ραντεβού</h4>
                <p class="display-6"><?= $total ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3">
                <h4>Σημερινά Ραντεβού</h4>
                <p class="display-6"><?= $today_count ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3">
                <h4>Ολοκληρωμένα</h4>
                <p class="display-6"><?= $done ?></p>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="appointments/mechanic_appointments.php" class="btn btn-primary">📅 Δες τα ραντεβού μου</a>
        <a href="logout.php" class="btn btn-danger">Αποσύνδεση</a>
    </div>
</div>
</body>
</html>
