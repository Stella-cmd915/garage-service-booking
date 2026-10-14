</main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>
                        <i class="fas fa-car"></i>
                        AutoCare
                    </h3>
                    <p>Επαγγελματικές υπηρεσίες συντήρησης και επισκευής αυτοκινήτων με πολυετή εμπειρία.</p>
                </div>
                
                <div class="footer-section">
                    <h4>Επικοινωνία</h4>
                    <p><i class="fas fa-map-marker-alt"></i> Λεωφ. Αθηνών 123, Θεσσαλονίκη</p>
                    <p><i class="fas fa-phone"></i> 2310 123456</p>
                    <p><i class="fas fa-envelope"></i> info@autocare.gr</p>
                </div>
                
                <div class="footer-section">
                    <h4>Ώρες Λειτουργίας</h4>
                    <p>Δευτέρα - Παρασκευή: 08:00 - 16:00</p>
                    <p>Σάββατο: 09:00 - 14:00</p>
                    <p>Κυριακή: Κλειστά</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> AutoCare Μηχανουργείο. Όλα τα δικαιώματα διατηρούνται.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript Files -->
    <script src="<?php echo isset($js_path) ? $js_path : '/garage_management/js/'; ?>main.js"></script>
    <script src="<?php echo isset($js_path) ? $js_path : '/garage_management/js/'; ?>ajax.js"></script>
    
    <!-- Custom JavaScript for this page -->
    <?php if (isset($custom_js)): ?>
        <script><?php echo $custom_js; ?></script>
    <?php endif; ?>
</body>
</html>