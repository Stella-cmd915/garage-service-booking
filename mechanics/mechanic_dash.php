<?php
require_once 'config/session.php';
require_once 'config/database.php';

// FIX: Add missing user_id to session if logged in but missing user_id
if (isset($_SESSION['username']) && !isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
    }
}

$pageTitle = 'Αρχική - Μηχανουργείο AutoCare';
include 'includes/header.php';
?>
<div class="hero-section">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Καλώς ήρθατε στο Μηχανουργείο AutoCare</h1>
                <p class="hero-subtitle">
                    Επαγγελματικές υπηρεσίες συντήρησης και επισκευής αυτοκινήτων 
                    με πολυετή εμπειρία και σύγχρονο εξοπλισμό.
                </p>
                
                <?php if (isLoggedIn()): ?>
                    <div class="user-welcome">
                        <h2>
                            <i class="fas fa-user-circle"></i>
                            Καλώς ήρθατε, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
                        </h2>
                        <p>Ρόλος: <strong><?php echo ucfirst($_SESSION['user_role']); ?></strong></p>
                        
                        <div class="quick-actions">
                            <?php
                            $role = $_SESSION['user_role'];
                            switch ($role):
                                case 'secretary':
                            ?>
                                <a href="users/secretary_dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt"></i>
                                    Dashboard Γραμματέα
                                </a>
                                <a href="users/user_management.php" class="btn btn-secondary">
                                    <i class="fas fa-users"></i>
                                    Διαχείριση Χρηστών
                                </a>
                            <?php
                                break;
                                case 'customer':
                            ?>
                                <a href="customer_dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-user"></i>
                                    Ο Λογαριασμός μου
                                </a>
                                <a href="customer_appointments.php" class="btn btn-secondary">
                                    <i class="fas fa-calendar"></i>
                                    Τα Ραντεβού μου
                                </a>
                            <?php
                                break;
                                case 'mechanic':
                            ?>
                                <a href="mechanics/mechanic_dash.php" class="btn btn-primary">
                                    <i class="fas fa-wrench"></i>
                                    Dashboard Μηχανικού
                                </a>
                                <a href="appointments/my_assignments.php" class="btn btn-secondary">
                                    <i class="fas fa-calendar-check"></i>
                                    Τα Ραντεβού μου
                                </a>
                            <?php
                                break;
                            endswitch;
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hero-actions">
                        <a href="auth/login.php" class="btn btn-primary btn-large">
                            <i class="fas fa-sign-in-alt"></i>
                            Σύνδεση
                        </a>
                        <a href="auth/register.php" class="btn btn-secondary btn-large">
                            <i class="fas fa-user-plus"></i>
                            Εγγραφή
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="hero-image">
                <img src="images/garage-hero.jpg" alt="Μηχανουργείο AutoCare" onerror="this.style.display='none'">
                <div class="hero-placeholder">
                    <i class="fas fa-car"></i>
                    <span>AutoCare</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="services-section">
    <div class="container">
        <h2>Οι Υπηρεσίες μας</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3>Γενική Συντήρηση</h3>
                <p>Πλήρη service αυτοκινήτου με έλεγχο όλων των συστημάτων</p>
                <ul>
                    <li>Αλλαγή λαδιών</li>
                    <li>Έλεγχος φρένων</li>
                    <li>Συντήρηση κινητήρα</li>
                </ul>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-wrench"></i>
                </div>
                <h3>Επισκευές</h3>
                <p>Εξειδικευμένες επισκευές για όλα τα προβλήματα του αυτοκινήτου σας</p>
                <ul>
                    <li>Επισκευή κινητήρα</li>
                    <li>Επισκευή ηλεκτρικών</li>
                    <li>Επισκευή αναρτήσεων</li>
                </ul>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>ΚΤΕΟ</h3>
                <p>Προετοιμασία και έλεγχος για επιτυχή διεκπεραίωση του ΚΤΕΟ</p>
                <ul>
                    <li>Έλεγχος εκπομπών</li>
                    <li>Έλεγχος φρένων</li>
                    <li>Έλεγχος φώτων</li>
                </ul>
            </div>
            
            <div class="service-card">
                <div class="service-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Έκτακτη Βοήθεια</h3>
                <p>24ωρη κάλυψη για έκτακτα προβλήματα στο δρόμο</p>
                <ul>
                    <li>Οδική βοήθεια</li>
                    <li>Ρυμούλκηση</li>
                    <li>Άμεση επισκευή</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="info-section">
    <div class="container">
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-clock"></i> Ώρες Λειτουργίας</h3>
                <p><strong>Δευτέρα - Παρασκευή:</strong> 08:00 - 16:00</p>
                <p><strong>Σάββατο:</strong> 09:00 - 14:00</p>
                <p><strong>Κυριακή:</strong> Κλειστά</p>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-map-marker-alt"></i> Στοιχεία Επικοινωνίας</h3>
                <p><strong>Διεύθυνση:</strong> Λεωφ. Αθηνών 123, Θεσσαλονίκη</p>
                <p><strong>Τηλέφωνο:</strong> 2310 123456</p>
                <p><strong>Email:</strong> info@autocare.gr</p>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-award"></i> Γιατί να μας Επιλέξετε</h3>
                <p>✓ Πιστοποιημένοι τεχνικοί</p>
                <p>✓ Σύγχρονος εξοπλισμός</p>
                <p>✓ Ανταγωνιστικές τιμές</p>
                <p>✓ Εγγύηση εργασιών</p>
            </div>
        </div>
    </div>
</div>

<?php if (!isLoggedIn()): ?>
<div class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>Έτοιμοι να ξεκινήσετε;</h2>
            <p>Κλείστε το ραντεβού σας σήμερα και εμπιστευθείτε το αυτοκίνητό σας στους ειδικούς μας!</p>
            <div class="cta-actions">
                <a href="auth/register.php" class="btn btn-primary btn-large">
                    <i class="fas fa-calendar-plus"></i>
                    Κλείστε Ραντεβού
                </a>
                <a href="tel:2310123456" class="btn btn-secondary btn-large">
                    <i class="fas fa-phone"></i>
                    Καλέστε μας
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #2c5aa0 0%, #1a4480 100%);
    color: white;
    padding: 4rem 0;
    margin-top: -1px; /* Overlap header slightly */
}

.hero-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.hero-text h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    font-weight: 700;
}

.hero-subtitle {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    line-height: 1.6;
    opacity: 0.9;
}

.user-welcome {
    background: rgba(255,255,255,0.1);
    padding: 1.5rem;
    border-radius: 12px;
    margin-top: 1.5rem;
    backdrop-filter: blur(10px);
}

.user-welcome h2 {
    color: white;
    margin-bottom: 0.5rem;
    font-size: 1.4rem;
}

.user-welcome p {
    margin-bottom: 1rem;
    opacity: 0.9;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.quick-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.hero-image {
    position: relative;
    text-align: center;
}

.hero-image img {
    width: 100%;
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}

.hero-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 3rem;
    backdrop-filter: blur(10px);
}

.hero-placeholder i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.7;
}

.hero-placeholder span {
    font-size: 1.5rem;
    font-weight: 600;
}

/* Services Section */
.services-section {
    padding: 4rem 0;
    background: #f8f9fa;
}

.services-section h2 {
    text-align: center;
    color: #2c5aa0;
    font-size: 2.2rem;
    margin-bottom: 3rem;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
}

.service-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

.service-icon {
    font-size: 3rem;
    color: #2c5aa0;
    margin-bottom: 1rem;
}

.service-card h3 {
    color: #333;
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.service-card p {
    color: #666;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

.service-card ul {
    list-style: none;
    padding: 0;
    text-align: left;
}

.service-card li {
    padding: 0.3rem 0;
    color: #555;
    position: relative;
    padding-left: 1.5rem;
}

.service-card li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: bold;
}

/* Info Section */
.info-section {
    padding: 4rem 0;
    background: white;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.info-card {
    padding: 2rem;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    transition: border-color 0.3s;
}

.info-card:hover {
    border-color: #2c5aa0;
}

.info-card h3 {
    color: #2c5aa0;
    margin-bottom: 1rem;
    font-size: 1.2rem;
}

.info-card h3 i {
    margin-right: 0.5rem;
}

.info-card p {
    margin: 0.5rem 0;
    color: #555;
}

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 4rem 0;
    text-align: center;
}

.cta-content h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.cta-content p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.cta-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-large {
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-content {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .hero-text h1 {
        font-size: 2rem;
    }
    
    .hero-actions,
    .quick-actions,
    .cta-actions {
        flex-direction: column;
    }
    
    .services-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>