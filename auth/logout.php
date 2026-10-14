<?php
require_once '../config/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // If not logged in, redirect to login page
    header('Location: login.php');
    exit();
}

// Get user info for goodbye message
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// Destroy session
session_unset();
session_destroy();

// Start new session for flash message
session_start();
$_SESSION['logout_message'] = "Αντίο " . htmlspecialchars($username) . "! Αποσυνδεθήκατε επιτυχώς.";

$pageTitle = 'Αποσύνδεση - Μηχανουργείο AutoCare';
include '../includes/header.php';
?>

<div class="container">
    <div class="logout-container">
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1>Αποσύνδεση Επιτυχής</h1>
            
            <div class="logout-message">
                <p>
                    <strong>Αντίο <?php echo htmlspecialchars($username); ?>!</strong><br>
                    Αποσυνδεθήκατε επιτυχώς από το σύστημα.
                </p>
                
                <div class="security-info">
                    <i class="fas fa-shield-alt"></i>
                    <span>Η συνεδρία σας έχει τερματιστεί με ασφάλεια</span>
                </div>
            </div>
            
            <div class="logout-actions">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Σύνδεση Ξανά
                </a>
                
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Αρχική Σελίδα
                </a>
            </div>
            
            <div class="logout-tips">
                <h3><i class="fas fa-lightbulb"></i> Συμβουλές Ασφαλείας</h3>
                <ul>
                    <li>Κλείστε όλα τα παράθυρα του browser για επιπλέον ασφάλεια</li>
                    <li>Μη χρησιμοποιείτε κοινόχρηστους υπολογιστές για ευαίσθητες εργασίες</li>
                    <li>Αποσυνδέεστε πάντα όταν τελειώνετε τη χρήση</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.logout-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
    padding: 2rem 1rem;
}

.logout-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 3rem 2rem;
    text-align: center;
    max-width: 500px;
    width: 100%;
}

.logout-icon {
    font-size: 4rem;
    color: #28a745;
    margin-bottom: 1.5rem;
}

.logout-card h1 {
    color: #2c5aa0;
    margin-bottom: 1.5rem;
    font-size: 1.8rem;
}

.logout-message {
    margin-bottom: 2rem;
}

.logout-message p {
    font-size: 1.1rem;
    color: #333;
    margin-bottom: 1rem;
}

.security-info {
    display: inline-flex;
    align-items: center;
    background: #d4edda;
    color: #155724;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    border: 1px solid #c3e6cb;
}

.security-info i {
    margin-right: 0.5rem;
}

.logout-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 2rem;
}

.logout-actions .btn {
    flex: 1;
    max-width: 180px;
}

.logout-tips {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: left;
    border-left: 4px solid #2c5aa0;
}

.logout-tips h3 {
    color: #2c5aa0;
    margin-bottom: 1rem;
    font-size: 1rem;
    text-align: center;
}

.logout-tips ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.logout-tips li {
    padding: 0.5rem 0;
    position: relative;
    padding-left: 1.5rem;
    color: #666;
    font-size: 0.9rem;
}

.logout-tips li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: bold;
}

/* Auto-redirect countdown */
.redirect-countdown {
    margin-top: 1.5rem;
    padding: 1rem;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    color: #856404;
    font-size: 0.9rem;
}

.countdown-timer {
    font-weight: bold;
    color: #d63384;
}

@media (max-width: 768px) {
    .logout-container {
        padding: 1rem 0.5rem;
    }
    
    .logout-card {
        padding: 2rem 1.5rem;
    }
    
    .logout-actions {
        flex-direction: column;
    }
    
    .logout-actions .btn {
        max-width: none;
    }
    
    .logout-icon {
        font-size: 3rem;
    }
    
    .logout-card h1 {
        font-size: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Optional: Auto-redirect after 30 seconds
    let countdown = 30;
    
    // Uncomment the following code if you want auto-redirect functionality
    /*
    const redirectDiv = document.createElement('div');
    redirectDiv.className = 'redirect-countdown';
    redirectDiv.innerHTML = `
        <i class="fas fa-clock"></i>
        Θα μεταφερθείτε αυτόματα στην αρχική σελίδα σε <span class="countdown-timer">${countdown}</span> δευτερόλεπτα.
        <br><small>Κάντε κλικ οπουδήποτε για να ακυρώσετε την αυτόματη μεταφορά.</small>
    `;
    
    document.querySelector('.logout-card').appendChild(redirectDiv);
    
    const timer = setInterval(function() {
        countdown--;
        document.querySelector('.countdown-timer').textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(timer);
            window.location.href = '../index.php';
        }
    }, 1000);
    
    // Cancel auto-redirect on any user interaction
    document.addEventListener('click', function() {
        clearInterval(timer);
        redirectDiv.style.display = 'none';
    });
    
    document.addEventListener('keypress', function() {
        clearInterval(timer);
        redirectDiv.style.display = 'none';
    });
    */
    
    // Clear any remaining session storage (if used)
    if (typeof(Storage) !== "undefined") {
        sessionStorage.clear();
    }
    
    // Optional: Prevent back button access to protected pages
    history.replaceState(null, null, window.location.href);
    window.addEventListener('popstate', function() {
        history.replaceState(null, null, window.location.href);
    });
});
</script>

<?php include '../includes/footer.php'; ?>