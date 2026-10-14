-- Garage Management System Database Setup
-- Σχήμα βάσης για το PHP project

CREATE DATABASE IF NOT EXISTS garage_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE garage_management;

-- Drop existing tables (in correct order due to foreign keys)
DROP TABLE IF EXISTS service_parts;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS mechanics;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;

-- Users table (βασική οντότητα για authentication)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    id_number VARCHAR(20) UNIQUE NOT NULL COMMENT 'Αριθμός Ταυτότητας (ΑΤ)',
    role ENUM('customer', 'secretary', 'mechanic') NOT NULL,
    phone VARCHAR(15),
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Λογαριασμός ανενεργός μέχρι ενεργοποίησης από γραμματέα',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_id_number (id_number)
);

-- Customers table (επέκταση του users για πελάτες)
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    tax_number VARCHAR(20) UNIQUE NOT NULL COMMENT 'ΑΦΜ',
    address TEXT NOT NULL COMMENT 'Διεύθυνση κατοικίας',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_tax_number (tax_number)
);

-- Mechanics table (επέκταση του users για μηχανικούς)  
CREATE TABLE mechanics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    specialty VARCHAR(100) NOT NULL COMMENT 'Ειδικότητα (αρχιμηχανικός, ηλεκτρονικός, κλπ)',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Vehicles table (αυτοκίνητα πελατών)
CREATE TABLE vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL COMMENT 'Σύνδεση με customers.user_id',
    serial_number VARCHAR(30) UNIQUE NOT NULL COMMENT 'Σειριακός αριθμός (VIN)',
    brand VARCHAR(50) NOT NULL COMMENT 'Μάρκα',
    model VARCHAR(50) NOT NULL COMMENT 'Μοντέλο', 
    vehicle_type ENUM('passenger', 'truck', 'bus') NOT NULL COMMENT 'Τύπος: επιβατικό, φορτηγό, λεωφορείο',
    fuel_type ENUM('petrol', 'diesel', 'electric', 'hybrid', 'gas') NOT NULL COMMENT 'Είδος κίνησης',
    doors_count INT NOT NULL COMMENT 'Αριθμός θυρών',
    wheels_count INT NOT NULL COMMENT 'Αριθμός ροδών', 
    manufacturing_date DATE NOT NULL COMMENT 'Ημερομηνία παραγωγής',
    acquisition_year YEAR NOT NULL COMMENT 'Έτος κτήσης',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id),
    INDEX idx_serial_number (serial_number),
    INDEX idx_brand_model (brand, model),
    INDEX idx_vehicle_type (vehicle_type)
);

-- Appointments table (ραντεβού)
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    mechanic_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason ENUM('service', 'repair') NOT NULL COMMENT 'Λόγος: σέρβις ή επιδιόρθωση',
    problem_description TEXT COMMENT 'Περιγραφή προβλήματος (υποχρεωτικό για επιδιόρθωση)',
    status ENUM('created', 'in_progress', 'completed', 'cancelled') DEFAULT 'created' COMMENT 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ, ΣΕ ΕΞΕΛΙΞΗ, ΠΕΡΑΙΩΜΕΝΟ, ΑΚΥΡΩΜΕΝΟ',
    total_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Συνολικό κόστος',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,  
    FOREIGN KEY (mechanic_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appointment_date (appointment_date),
    INDEX idx_appointment_time (appointment_time),
    INDEX idx_customer_id (customer_id),
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_mechanic_id (mechanic_id),
    INDEX idx_status (status)
);

-- Services/Work table (εργασίες που έγιναν)
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT,
    description TEXT NOT NULL COMMENT 'Περιγραφή εργασίας',
    materials_used TEXT COMMENT 'Σχετιζόμενα υλικά',
    completion_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Ώρα περαίωσης εργασίας',
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Κόστος εργασίας',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_appointment_id (appointment_id)
);

-- Sample data for demonstration

-- Insert sample users (admin/secretary created manually)
INSERT INTO users (username, email, password, first_name, last_name, id_number, role, phone, is_active) VALUES
-- Γραμματέας (ενεργή)
('admin', 'admin@garage.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Μαρία', 'Παπαδοπούλου', 'ΑΤ123456', 'secretary', '2310-555-001', TRUE),

-- Μηχανικοί
('mechanic1', 'petros@garage.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Πέτρος', 'Μιχαηλίδης', 'ΑΤ234567', 'mechanic', '6931-111-111', TRUE),
('mechanic2', 'antonis@garage.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Αντώνης', 'Βασιλείου', 'ΑΤ345678', 'mechanic', '6932-222-222', TRUE),
('mechanic3', 'spyros@garage.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Σπύρος', 'Κατσικάς', 'ΑΤ456789', 'mechanic', '6933-333-333', TRUE),

-- Πελάτες (αρχικά ανενεργοί - θα ενεργοποιηθούν από γραμματέα)
('customer1', 'giannis@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Γιάννης', 'Παπαδόπουλος', 'ΑΤ567890', 'customer', '6941-234-567', TRUE),
('customer2', 'maria@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Μαρία', 'Γεωργίου', 'ΑΤ678901', 'customer', '6952-345-678', TRUE),
('customer3', 'kostas@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Κώστας', 'Αντωνίου', 'ΑΤ789012', 'customer', '6963-456-789', TRUE),
('customer4', 'eleni@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ελένη', 'Δημητρίου', 'ΑΤ890123', 'customer', '6974-567-890', TRUE),
('customer5', 'nikos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Νίκος', 'Κωνσταντίνου', 'ΑΤ901234', 'customer', '6985-678-901', TRUE);

-- Insert customers (extended info for customer users)
INSERT INTO customers (user_id, tax_number, address) VALUES
(5, '123456789', 'Αθήνας 15, Θεσσαλονίκη 54248'),
(6, '234567890', 'Μητροπόλεως 23, Θεσσαλονίκη 54623'), 
(7, '345678901', 'Εγνατίας 45, Θεσσαλονίκη 54630'),
(8, '456789012', 'Τσιμισκή 67, Θεσσαλονίκη 54622'),
(9, '567890123', 'Βασ. Όλγας 89, Θεσσαλονίκη 54645');

-- Insert mechanics (extended info for mechanic users)
INSERT INTO mechanics (user_id, specialty) VALUES
(2, 'Αρχιμηχανικός'),
(3, 'Ηλεκτρονικός'),
(4, 'Μηχανικός κινητήρων');

-- Insert sample vehicles
INSERT INTO vehicles (customer_id, serial_number, brand, model, vehicle_type, fuel_type, doors_count, wheels_count, manufacturing_date, acquisition_year) VALUES
(5, 'VIN1HGBH41JXMN109186', 'Toyota', 'Corolla', 'passenger', 'petrol', 4, 4, '2020-03-15', 2020),
(5, 'VIN2HGBH41JXMN109187', 'BMW', '320d', 'passenger', 'diesel', 4, 4, '2019-07-22', 2019),
(6, 'VIN3HGBH41JXMN109188', 'Volkswagen', 'Golf', 'passenger', 'petrol', 4, 4, '2021-01-10', 2021),
(7, 'VIN4HGBH41JXMN109189', 'Ford', 'Focus', 'passenger', 'petrol', 4, 4, '2018-05-30', 2018),
(8, 'VIN5HGBH41JXMN109190', 'Audi', 'A4', 'passenger', 'diesel', 4, 4, '2022-02-14', 2022),
(9, 'VIN6HGBH41JXMN109191', 'Mercedes', 'C200', 'passenger', 'petrol', 4, 4, '2020-09-08', 2020),
(6, 'VIN7HGBH41JXMN109192', 'Hyundai', 'i30', 'passenger', 'hybrid', 4, 4, '2023-01-20', 2023),
(7, 'VIN8HGBH41JXMN109193', 'Renault', 'Megane', 'passenger', 'electric', 4, 4, '2023-05-15', 2023);

-- Insert sample appointments
INSERT INTO appointments (customer_id, vehicle_id, mechanic_id, appointment_date, appointment_time, reason, problem_description, status, total_cost) VALUES
-- Σημερινά ραντεβού
(5, 1, 2, CURDATE(), '09:00:00', 'service', NULL, 'created', 0.00),
(6, 3, 3, CURDATE(), '11:00:00', 'repair', 'Πρόβλημα με φώτα - δεν ανάβουν τα εμπρός φανάρια', 'in_progress', 120.00),
(7, 4, 4, CURDATE(), '14:00:00', 'repair', 'Αντικατάσταση τακάκια φρένων', 'created', 0.00),

-- Μελλοντικά ραντεβού
(8, 5, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 'service', NULL, 'created', 0.00),
(9, 6, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '08:30:00', 'repair', 'Check engine light αναμμένο', 'created', 0.00),
(5, 2, 4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '13:00:00', 'service', NULL, 'created', 0.00),

-- Παλιά ολοκληρωμένα ραντεβού
(6, 7, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:30:00', 'service', NULL, 'completed', 95.50),
(7, 8, 3, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '11:30:00', 'repair', 'Πρόβλημα με κλιματισμό', 'completed', 180.00),

-- Ακυρωμένα ραντεβού
(8, 5, NULL, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '15:00:00', 'service', NULL, 'cancelled', 0.00);

-- Insert sample services/work done
INSERT INTO services (appointment_id, description, materials_used, cost) VALUES
(2, 'Αντικατάσταση λάμπας φαναριού', 'Λάμπα H7 12V', 25.00),
(2, 'Έλεγχος ηλεκτρικής εγκατάστασης', 'Διάφορα καλώδια', 95.00),
(7, 'Αλλαγή λαδιού κινητήρα', 'Λάδι 5W30 5L, Φίλτρο λαδιού', 75.50),
(7, 'Έλεγχος φρένων', NULL, 20.00),
(8, 'Συμπλήρωση φρέον κλιματισμού', 'Φρέον R134a 1kg', 150.00),
(8, 'Καθαρισμός φίλτρου καμπίνας', 'Φίλτρο καμπίνας', 30.00);

-- Create useful views for the application

-- View για πελάτες με τα οχήματά τους
CREATE VIEW customer_vehicles_view AS
SELECT 
    u.id as user_id,
    u.username,
    u.first_name,
    u.last_name,
    u.email,
    u.phone,
    u.is_active,
    c.tax_number,
    c.address,
    v.id as vehicle_id,
    v.serial_number,
    v.brand,
    v.model,
    v.vehicle_type,
    v.fuel_type,
    v.doors_count,
    v.wheels_count,
    v.manufacturing_date,
    v.acquisition_year
FROM users u
JOIN customers c ON u.id = c.user_id
LEFT JOIN vehicles v ON u.id = v.customer_id
WHERE u.role = 'customer';

-- View για επερχόμενα ραντεβού
CREATE VIEW upcoming_appointments_view AS
SELECT 
    a.id,
    a.appointment_date,
    a.appointment_time,
    a.reason,
    a.problem_description,
    a.status,
    a.total_cost,
    CONCAT(cu.first_name, ' ', cu.last_name) as customer_name,
    cu.phone as customer_phone,
    c.tax_number as customer_tax,
    CONCAT(v.brand, ' ', v.model, ' (', v.serial_number, ')') as vehicle_info,
    CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name,
    m.specialty as mechanic_specialty
FROM appointments a
JOIN users cu ON a.customer_id = cu.id
JOIN customers c ON cu.id = c.user_id  
JOIN vehicles v ON a.vehicle_id = v.id
LEFT JOIN users mu ON a.mechanic_id = mu.id
LEFT JOIN mechanics m ON mu.id = m.user_id
WHERE a.appointment_date >= CURDATE()
  AND a.status NOT IN ('cancelled', 'completed')
ORDER BY a.appointment_date, a.appointment_time;

-- View για ιστορικό εργασιών
CREATE VIEW service_history_view AS
SELECT 
    s.id,
    s.description,
    s.materials_used,
    s.completion_time,
    s.cost,
    a.appointment_date,
    a.reason as appointment_reason,
    CONCAT(cu.first_name, ' ', cu.last_name) as customer_name,
    CONCAT(v.brand, ' ', v.model) as vehicle,
    v.serial_number,
    CONCAT(mu.first_name, ' ', mu.last_name) as mechanic_name
FROM services s
JOIN appointments a ON s.appointment_id = a.id
JOIN users cu ON a.customer_id = cu.id
JOIN vehicles v ON a.vehicle_id = v.id
JOIN users mu ON a.mechanic_id = mu.id
ORDER BY s.completion_time DESC;

-- Create indexes for better performance
CREATE INDEX idx_users_role_active ON users(role, is_active);
CREATE INDEX idx_appointments_date_status ON appointments(appointment_date, status);
CREATE INDEX idx_vehicles_customer_brand ON vehicles(customer_id, brand);

-- Display setup completion message
SELECT 'Database setup completed successfully!' as Status;

-- Display basic statistics
SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'customer' AND is_active = TRUE) as Active_Customers,
    (SELECT COUNT(*) FROM users WHERE role = 'secretary' AND is_active = TRUE) as Active_Secretaries,
    (SELECT COUNT(*) FROM users WHERE role = 'mechanic' AND is_active = TRUE) as Active_Mechanics,
    (SELECT COUNT(*) FROM vehicles) as Total_Vehicles,
    (SELECT COUNT(*) FROM appointments WHERE status != 'cancelled') as Active_Appointments,
    (SELECT COUNT(*) FROM services) as Completed_Services;

-- Show sample login credentials
SELECT '=== SAMPLE LOGIN CREDENTIALS ===' as Info;
SELECT 
    username,
    'password' as password_hint,
    role,
    CONCAT(first_name, ' ', last_name) as full_name,
    is_active
FROM users 
ORDER BY role, username;