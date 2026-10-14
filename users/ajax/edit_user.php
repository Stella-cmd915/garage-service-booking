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
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Database error');
}
?>

<input type="hidden" name="action" value="update_user">
<input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
<input type="hidden" name="current_role" value="<?php echo $user['role']; ?>">

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary mb-3"><i class="bi bi-person-badge"></i> Βασικά Στοιχεία</h6>
        
        <div class="mb-3">
            <label for="username" class="form-label">Όνομα χρήστη <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="username" name="username" 
                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="first_name" class="form-label">Όνομα <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="last_name" class="form-label">Επίθετο <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="id_number" class="form-label">Αριθμός Ταυτότητας <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="id_number" name="id_number" 
                   value="<?php echo htmlspecialchars($user['id_number']); ?>" required>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Ρόλος</label>
            <div class="form-control-plaintext">
                <span class="badge bg-<?php echo $user['role'] === 'customer' ? 'info' : ($user['role'] === 'mechanic' ? 'warning' : 'success'); ?> fs-6">
                    <?php 
                    $role_labels = [
                        'customer' => 'Πελάτης',
                        'mechanic' => 'Μηχανικός', 
                        'secretary' => 'Γραμματέας'
                    ];
                    echo $role_labels[$user['role']]; 
                    ?>
                </span>
                <small class="text-muted d-block">Ο ρόλος δεν μπορεί να αλλάξει</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-primary mb-3"><i class="bi bi-info-circle"></i> Κατάσταση & Πληροφορίες</h6>
        
        <div class="mb-3">
            <label class="form-label">Κατάσταση Λογαριασμού</label>
            <div class="form-control-plaintext">
                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?> fs-6">
                    <i class="bi bi-circle-fill"></i>
                    <?php echo $user['is_active'] ? 'Ενεργός' : 'Ανενεργός'; ?>
                </span>
                <small class="text-muted d-block">
                    Χρησιμοποιήστε το κουμπί ενεργοποίησης/απενεργοποίησης στη λίστα
                </small>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Ημερομηνία εγγραφής</label>
            <div class="form-control-plaintext">
                <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Τελευταία ενημέρωση</label>
            <div class="form-control-plaintext">
                <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?>
            </div>
        </div>
        
        <?php if ($user['role'] === 'customer'): ?>
            <hr>
            <h6 class="text-primary mb-3"><i class="bi bi-person-check"></i> Στοιχεία Πελάτη</h6>
            
            <div class="mb-3">
                <label for="afm" class="form-label">ΑΦΜ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="afm" name="afm" 
                       value="<?php echo htmlspecialchars($user['afm']); ?>" 
                       required pattern="[0-9]{9}" title="Το ΑΦΜ πρέπει να περιέχει 9 ψηφία">
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Διεύθυνση κατοικίας <span class="text-danger">*</span></label>
                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>
            
        <?php elseif ($user['role'] === 'mechanic'): ?>
            <hr>
            <h6 class="text-primary mb-3"><i class="bi bi-tools"></i> Στοιχεία Μηχανικού</h6>
            
            <div class="mb-3">
                <label for="specialty" class="form-label">Ειδικότητα <span class="text-danger">*</span></label>
                <select class="form-control" id="specialty" name="specialty" required>
                    <option value="">Επιλέξτε ειδικότητα...</option>
                    <option value="Αρχιμηχανικός" <?php echo $user['specialty'] === 'Αρχιμηχανικός' ? 'selected' : ''; ?>>Αρχιμηχανικός</option>
                    <option value="Ηλεκτρονικός" <?php echo $user['specialty'] === 'Ηλεκτρονικός' ? 'selected' : ''; ?>>Ηλεκτρονικός</option>
                    <option value="Μηχανικός Κινητήρων" <?php echo $user['specialty'] === 'Μηχανικός Κινητήρων' ? 'selected' : ''; ?>>Μηχανικός Κινητήρων</option>
                    <option value="Μηχανικός Φρένων" <?php echo $user['specialty'] === 'Μηχανικός Φρένων' ? 'selected' : ''; ?>>Μηχανικός Φρένων</option>
                    <option value="Μηχανικός A/C" <?php echo $user['specialty'] === 'Μηχανικός A/C' ? 'selected' : ''; ?>>Μηχανικός A/C</option>
                    <option value="Γενικός Μηχανικός" <?php echo $user['specialty'] === 'Γενικός Μηχανικός' ? 'selected' : ''; ?>>Γενικός Μηχανικός</option>
                    <option value="Άλλη" <?php echo !in_array($user['specialty'], ['Αρχιμηχανικός', 'Ηλεκτρονικός', 'Μηχανικός Κινητήρων', 'Μηχανικός Φρένων', 'Μηχανικός A/C', 'Γενικός Μηχανικός']) ? 'selected' : ''; ?>>Άλλη</option>
                </select>
            </div>
            
            <div class="mb-3" id="custom_specialty_div" style="<?php echo !in_array($user['specialty'], ['Αρχιμηχανικός', 'Ηλεκτρονικός', 'Μηχανικός Κινητήρων', 'Μηχανικός Φρένων', 'Μηχανικός A/C', 'Γενικός Μηχανικός']) ? 'display: block;' : 'display: none;'; ?>">
                <label for="custom_specialty" class="form-label">Καθορίστε την ειδικότητα</label>
                <input type="text" class="form-control" id="custom_specialty" name="custom_specialty" 
                       value="<?php echo !in_array($user['specialty'], ['Αρχιμηχανικός', 'Ηλεκτρονικός', 'Μηχανικός Κινητήρων', 'Μηχανικός Φρένων', 'Μηχανικός A/C', 'Γενικός Μηχανικός']) ? htmlspecialchars($user['specialty']) : ''; ?>">
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($user['role'] === 'customer'): ?>
    <div class="row mt-4">
        <div class="col-12">
            <hr>
            <h6 class="text-primary mb-3"><i class="bi bi-car-front"></i> Συνοπτικά Στοιχεία</h6>
            
            <?php
            // Get customer's vehicles count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE customer_id = ?");
            $stmt->execute([$user_id]);
            $vehicles_count = $stmt->fetchColumn();
            
            // Get customer's appointments count  
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id = ?");
            $stmt->execute([$user_id]);
            $appointments_count = $stmt->fetchColumn();
            ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-car-front text-primary" style="font-size: 2rem;"></i>
                            <h4 class="mt-2"><?php echo $vehicles_count; ?></h4>
                            <p class="text-muted mb-0">Καταχωρημένα Αυτοκίνητα</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-event text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2"><?php echo $appointments_count; ?></h4>
                            <p class="text-muted mb-0">Συνολικά Ραντεβού</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($user['role'] === 'mechanic'): ?>
    <div class="row mt-4">
        <div class="col-12">
            <hr>
            <h6 class="text-primary mb-3"><i class="bi bi-wrench"></i> Συνοπτικά Στοιχεία</h6>
            
            <?php
            // Get mechanic's assignments count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE mechanic_id = ?");
            $stmt->execute([$user_id]);
            $assignments_count = $stmt->fetchColumn();
            
            // Get completed appointments count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE mechanic_id = ? AND status = 'completed'");
            $stmt->execute([$user_id]);
            $completed_count = $stmt->fetchColumn();
            ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-event text-warning" style="font-size: 2rem;"></i>
                            <h4 class="mt-2"><?php echo $assignments_count; ?></h4>
                            <p class="text-muted mb-0">Συνολικές Αναθέσεις</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2"><?php echo $completed_count; ?></h4>
                            <p class="text-muted mb-0">Ολοκληρωμένα Ραντεβού</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle specialty dropdown for mechanics
    const specialtySelect = document.getElementById('specialty');
    const customSpecialtyDiv = document.getElementById('custom_specialty_div');
    const customSpecialtyInput = document.getElementById('custom_specialty');
    
    if (specialtySelect) {
        specialtySelect.addEventListener('change', function() {
            if (this.value === 'Άλλη') {
                customSpecialtyDiv.style.display = 'block';
                customSpecialtyInput.required = true;
            } else {
                customSpecialtyDiv.style.display = 'none';
                customSpecialtyInput.required = false;
                customSpecialtyInput.value = '';
            }
        });
    }
    
    // Update specialty value before form submission
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        if (specialtySelect && specialtySelect.value === 'Άλλη' && customSpecialtyInput.value.trim()) {
            // Create a hidden input with the custom specialty value
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'specialty';
            hiddenInput.value = customSpecialtyInput.value.trim();
            this.appendChild(hiddenInput);
            
            // Remove the name attribute from the select to prevent conflict
            specialtySelect.name = '';
        }
    });
    
    // Form validation
    const form = document.getElementById('editUserForm');
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Validate AFM format for customers
        const afmField = document.getElementById('afm');
        if (afmField && afmField.value.trim()) {
            const afmPattern = /^[0-9]{9}$/;
            if (!afmPattern.test(afmField.value.trim())) {
                afmField.classList.add('is-invalid');
                isValid = false;
                alert('Το ΑΦΜ πρέπει να περιέχει ακριβώς 9 ψηφία.');
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία.');
        }
    });
});
</script>