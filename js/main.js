/**
 * Main JavaScript for Garage Management System
 * Course: Web Programming (321-88103)
 * 
 * This file contains core JavaScript functionality for the application
 * Features: DOM manipulation, event handling, utility functions
 */

// Global application object
const GarageApp = {
    // Configuration
    config: {
        baseUrl: '/garage_management',
        apiUrl: '/garage_management/api',
        sessionTimeout: 3600000, // 1 hour in milliseconds
        language: 'el', // Default language
        itemsPerPage: 10
    },
    
    // State management
    state: {
        currentUser: null,
        isLoggedIn: false,
        currentPage: 1,
        loading: false
    },
    
    // Initialize application
    init() {
        this.setupEventListeners();
        this.setupCSRFProtection();
        this.setupSessionTimeout();
        this.setupFormValidation();
        this.setupModals();
        this.setupTooltips();
        this.checkUserSession();
        
        console.log('Garage Management System initialized');
    },
    
    // Setup event listeners
    setupEventListeners() {
        // Mobile menu toggle
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (mobileMenuToggle && navMenu) {
            mobileMenuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }
        
        // User dropdown
        const userInfo = document.querySelector('.user-info');
        const userDropdown = document.querySelector('.user-dropdown');
        
        if (userInfo && userDropdown) {
            userInfo.addEventListener('click', (e) => {
                e.preventDefault();
                userDropdown.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userInfo.contains(e.target)) {
                    userDropdown.classList.remove('active');
                }
            });
        }
        
        // Alert close buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('alert-close')) {
                e.target.closest('.alert').remove();
            }
        });
        
        // Confirm delete buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-delete') || 
                e.target.closest('.btn-delete')) {
                if (!confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το στοιχείο;')) {
                    e.preventDefault();
                }
            }
        });
        
        // Auto-hide flash messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-permanent')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
        
        // Form submit loading states
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            
            if (submitBtn && !submitBtn.disabled) {
                this.setLoadingState(submitBtn, true);
                
                // Reset loading state after a delay if form is still visible
                setTimeout(() => {
                    if (document.contains(submitBtn)) {
                        this.setLoadingState(submitBtn, false);
                    }
                }, 10000);
            }
        });
    },
    
    // Setup CSRF protection
    setupCSRFProtection() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            // Add CSRF token to all AJAX requests
            const originalFetch = window.fetch;
            window.fetch = function(url, options = {}) {
                options.headers = options.headers || {};
                options.headers['X-CSRF-Token'] = csrfToken.content;
                return originalFetch(url, options);
            };
        }
    },
    
    // Setup session timeout warning
    setupSessionTimeout() {
        const warningTime = this.config.sessionTimeout - 300000; // 5 minutes before expiry
        
        setTimeout(() => {
            this.showSessionWarning();
        }, warningTime);
    },
    
    // Show session timeout warning
    showSessionWarning() {
        const modal = this.createModal({
            title: 'Λήξη Συνεδρίας',
            body: 'Η συνεδρία σας θα λήξει σε 5 λεπτά. Θέλετε να συνεχίσετε;',
            buttons: [
                {
                    text: 'Συνέχεια',
                    class: 'btn-primary',
                    click: () => {
                        this.extendSession();
                        modal.hide();
                    }
                },
                {
                    text: 'Αποσύνδεση',
                    class: 'btn-secondary',
                    click: () => {
                        window.location.href = this.config.baseUrl + '/auth/logout.php';
                    }
                }
            ]
        });
        modal.show();
    },
    
    // Extend user session
    async extendSession() {
        try {
            const response = await fetch(this.config.apiUrl + '/extend-session.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                this.setupSessionTimeout(); // Reset timeout
            }
        } catch (error) {
            console.error('Failed to extend session:', error);
        }
    },
    
    // Check user session status
    async checkUserSession() {
        try {
            const response = await fetch(this.config.apiUrl + '/session-status.php', {
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.state.currentUser = data.user;
                this.state.isLoggedIn = data.loggedIn;
            }
        } catch (error) {
            console.error('Failed to check session:', error);
        }
    },
    
    // Setup form validation
    setupFormValidation() {
        document.addEventListener('blur', (e) => {
            if (e.target.matches('.form-control[required]')) {
                this.validateField(e.target);
            }
        }, true);
        
        document.addEventListener('input', (e) => {
            if (e.target.matches('.form-control.error')) {
                this.validateField(e.target);
            }
        });
    },
    
    // Validate form field
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        let isValid = true;
        let errorMessage = '';
        
        // Required validation
        if (required && !value) {
            isValid = false;
            errorMessage = 'Αυτό το πεδίο είναι υποχρεωτικό';
        }
        
        // Email validation
        else if (type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Παρακαλούμε εισάγετε έγκυρη διεύθυνση email';
            }
        }
        
        // Password validation
        else if (field.name === 'password' && value) {
            if (value.length < 6) {
                isValid = false;
                errorMessage = 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες';
            }
        }
        
        // Password confirmation
        else if (field.name === 'password_confirm' && value) {
            const passwordField = document.querySelector('input[name="password"]');
            if (passwordField && value !== passwordField.value) {
                isValid = false;
                errorMessage = 'Οι κωδικοί δεν ταιριάζουν';
            }
        }
        
        // Phone validation
        else if (field.name === 'phone' && value) {
            const phoneRegex = /^[\d\s\-\+\(\)]+$/;
            if (!phoneRegex.test(value) || value.length < 10) {
                isValid = false;
                errorMessage = 'Παρακαλούμε εισάγετε έγκυρο αριθμό τηλεφώνου';
            }
        }
        
        // Tax number validation (Greek ΑΦΜ)
        else if (field.name === 'tax_number' && value) {
            if (value.length !== 9 || !/^\d+$/.test(value)) {
                isValid = false;
                errorMessage = 'Το ΑΦΜ πρέπει να έχει 9 ψηφία';
            }
        }
        
        // ID number validation (Greek ΑΤ)
        else if (field.name === 'id_number' && value) {
            if (value.length < 6 || value.length > 8) {
                isValid = false;
                errorMessage = 'Ο αριθμός ταυτότητας πρέπει να έχει 6-8 χαρακτήρες';
            }
        }
        
        this.setFieldValidation(field, isValid, errorMessage);
        return isValid;
    },
    
    // Set field validation state
    setFieldValidation(field, isValid, message = '') {
        const formGroup = field.closest('.form-group');
        let errorElement = formGroup.querySelector('.form-error');
        
        // Remove existing error message
        if (errorElement) {
            errorElement.remove();
        }
        
        // Update field classes
        field.classList.remove('error', 'success');
        field.classList.add(isValid ? 'success' : 'error');
        
        // Add error message if needed
        if (!isValid && message) {
            errorElement = document.createElement('div');
            errorElement.className = 'form-error';
            errorElement.textContent = message;
            formGroup.appendChild(errorElement);
        }
    },
    
    // Validate entire form
    validateForm(form) {
        const fields = form.querySelectorAll('.form-control[required]');
        let isValid = true;
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    },
    
    // Setup modal functionality
    setupModals() {
        document.addEventListener('click', (e) => {
            // Open modal
            if (e.target.hasAttribute('data-modal')) {
                e.preventDefault();
                const modalId = e.target.getAttribute('data-modal');
                const modal = document.getElementById(modalId);
                if (modal) {
                    this.showModal(modal);
                }
            }
            
            // Close modal
            if (e.target.classList.contains('modal-close') || 
                e.target.classList.contains('modal')) {
                const modal = e.target.closest('.modal');
                if (modal) {
                    this.hideModal(modal);
                }
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    this.hideModal(activeModal);
                }
            }
        });
    },
    
    // Show modal
    showModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Focus first form element if exists
        const firstInput = modal.querySelector('input, textarea, select');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    },
    
    // Hide modal
    hideModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    },
    
    // Create modal programmatically
    createModal(options) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">${options.title || ''}</h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    ${options.body || ''}
                </div>
                <div class="modal-footer">
                    ${options.buttons ? options.buttons.map(btn => 
                        `<button type="button" class="btn ${btn.class || 'btn-secondary'}" data-action="${btn.action || ''}">${btn.text}</button>`
                    ).join('') : '<button type="button" class="btn btn-secondary modal-close">Κλείσιμο</button>'}
                </div>
            </div>
        `;
        
        // Add event listeners for buttons
        if (options.buttons) {
            options.buttons.forEach((btn, index) => {
                const btnElement = modal.querySelectorAll('.modal-footer .btn')[index];
                if (btn.click) {
                    btnElement.addEventListener('click', btn.click);
                }
            });
        }
        
        document.body.appendChild(modal);
        
        return {
            element: modal,
            show: () => this.showModal(modal),
            hide: () => {
                this.hideModal(modal);
                setTimeout(() => modal.remove(), 300);
            }
        };
    },
    
    // Setup tooltips
    setupTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip);
            element.addEventListener('mouseleave', this.hideTooltip);
        });
    },
    
    // Show tooltip
    showTooltip(e) {
        const element = e.target;
        const text = element.getAttribute('data-tooltip');
        
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: #333;
            color: white;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
        `;
        
        document.body.appendChild(tooltip);
        
        // Position tooltip
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
        
        // Show tooltip
        setTimeout(() => tooltip.style.opacity = '1', 10);
        
        // Store reference for cleanup
        element._tooltip = tooltip;
    },
    
    // Hide tooltip
    hideTooltip(e) {
        const element = e.target;
        if (element._tooltip) {
            element._tooltip.remove();
            delete element._tooltip;
        }
    },
    
    // Set loading state for buttons
    setLoadingState(button, loading) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = '<span class="spinner"></span> Φόρτωση...';
        } else {
            button.disabled = false;
            button.textContent = button.dataset.originalText || 'Submit';
        }
    },
    
    // Show notification
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="alert-close">&times;</button>
        `;
        
        // Add to page
        const container = document.querySelector('.main-content') || document.body;
        container.insertBefore(notification, container.firstChild);
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.transition = 'opacity 0.5s';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 500);
                }
            }, duration);
        }
        
        return notification;
    },
    
    // Format date for display
    formatDate(date, format = 'dd/mm/yyyy') {
        if (!date) return '';
        
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        
        return format
            .replace('dd', day)
            .replace('mm', month)
            .replace('yyyy', year)
            .replace('hh', hours)
            .replace('ii', minutes);
    },
    
    // Format currency
    formatCurrency(amount, currency = '€') {
        const formatted = parseFloat(amount).toFixed(2);
        return formatted.replace('.', ',') + ' ' + currency;
    },
    
    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Language helpers
    language: {
        // Get current language
        get() {
            return GarageApp.config.language;
        },
        
        // Set language
        set(lang) {
            GarageApp.config.language = lang;
            localStorage.setItem('language', lang);
            
            // Update page elements
            document.documentElement.lang = lang;
            
            // Reload page to apply language changes
            window.location.reload();
        }
    }
};

// Utility functions available globally
window.utils = {
    formatDate: GarageApp.formatDate.bind(GarageApp),
    formatCurrency: GarageApp.formatCurrency.bind(GarageApp),
    debounce: GarageApp.debounce.bind(GarageApp),
    showNotification: GarageApp.showNotification.bind(GarageApp)
};

// Initialize application when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => GarageApp.init());
} else {
    GarageApp.init();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GarageApp;
}

function showFieldError(fieldName, message) {
    const errorElement = document.getElementById(fieldName + '-error');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.color = 'red';
    }
}

function hideFieldError(fieldName) {
    const errorElement = document.getElementById(fieldName + '-error');
    if (errorElement) {
        errorElement.textContent = '';
    }
}