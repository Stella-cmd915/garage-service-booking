/**
 * AJAX Functions for Garage Management System
 * Course: Web Programming (321-88103)
 * 
 * This file contains AJAX functionality for dynamic content loading
 * Features: Live search, real-time updates, async form submissions
 */

// AJAX Manager
const AjaxManager = {
    // Configuration
    config: {
        timeout: 30000, // 30 seconds
        retryAttempts: 3,
        retryDelay: 1000 // 1 second
    },
    
    // Active requests tracking
    activeRequests: new Map(),
    
    // Initialize AJAX functionality
    init() {
        this.setupSearchFunctions();
        this.setupFormSubmissions();
        this.setupRealTimeUpdates();
        this.setupAutoComplete();
        
        console.log('AJAX Manager initialized');
    },
    
    // Setup live search functionality
    setupSearchFunctions() {
        // User search
        const userSearchInput = document.getElementById('user-search');
        if (userSearchInput) {
            userSearchInput.addEventListener('input', 
                GarageApp.debounce(this.searchUsers.bind(this), 300)
            );
        }
        
        // Vehicle search
        const vehicleSearchInput = document.getElementById('vehicle-search');
        if (vehicleSearchInput) {
            vehicleSearchInput.addEventListener('input', 
                GarageApp.debounce(this.searchVehicles.bind(this), 300)
            );
        }
        
        // Appointment search
        const appointmentSearchInput = document.getElementById('appointment-search');
        if (appointmentSearchInput) {
            appointmentSearchInput.addEventListener('input', 
                GarageApp.debounce(this.searchAppointments.bind(this), 300)
            );
        }
        
        // Global search
        const globalSearchInput = document.getElementById('global-search');
        if (globalSearchInput) {
            globalSearchInput.addEventListener('input', 
                GarageApp.debounce(this.globalSearch.bind(this), 500)
            );
        }
    },
    
    // User search
    async searchUsers(event) {
        const query = event.target.value.trim();
        const resultsContainer = document.getElementById('user-search-results');
        
        if (!resultsContainer) return;
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            return;
        }
        
        try {
            this.showSearchLoading(resultsContainer);
            
            const response = await this.apiRequest('/api/users.php', {
                action: 'search',
                query: query,
                limit: 10
            });
            
            if (response.success) {
                this.renderUserResults(response.data, resultsContainer);
            } else {
                resultsContainer.innerHTML = `<div class="alert alert-warning">${response.message}</div>`;
            }
        } catch (error) {
            console.error('User search failed:', error);
            resultsContainer.innerHTML = '<div class="alert alert-error">Σφάλμα αναζήτησης</div>';
        }
    },
    
    // Vehicle search
    async searchVehicles(event) {
        const query = event.target.value.trim();
        const resultsContainer = document.getElementById('vehicle-search-results');
        
        if (!resultsContainer) return;
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            return;
        }
        
        try {
            this.showSearchLoading(resultsContainer);
            
            const response = await this.apiRequest('/api/vehicles.php', {
                action: 'search',
                query: query,
                limit: 10
            });
            
            if (response.success) {
                this.renderVehicleResults(response.data, resultsContainer);
            } else {
                resultsContainer.innerHTML = `<div class="alert alert-warning">${response.message}</div>`;
            }
        } catch (error) {
            console.error('Vehicle search failed:', error);
            resultsContainer.innerHTML = '<div class="alert alert-error">Σφάλμα αναζήτησης</div>';
        }
    },
    
    // Appointment search
    async searchAppointments(event) {
        const query = event.target.value.trim();
        const resultsContainer = document.getElementById('appointment-search-results');
        
        if (!resultsContainer) return;
        
        if (query.length < 2) {
            this.loadTodayAppointments(resultsContainer);
            return;
        }
        
        try {
            this.showSearchLoading(resultsContainer);
            
            const response = await this.apiRequest('/api/appointments.php', {
                action: 'search',
                query: query,
                limit: 20
            });
            
            if (response.success) {
                this.renderAppointmentResults(response.data, resultsContainer);
            } else {
                resultsContainer.innerHTML = `<div class="alert alert-warning">${response.message}</div>`;
            }
        } catch (error) {
            console.error('Appointment search failed:', error);
            resultsContainer.innerHTML = '<div class="alert alert-error">Σφάλμα αναζήτησης</div>';
        }
    },
    
    // Load today's appointments
    async loadTodayAppointments(container) {
        try {
            this.showSearchLoading(container);
            
            const response = await this.apiRequest('/api/appointments.php', {
                action: 'today'
            });
            
            if (response.success) {
                this.renderAppointmentResults(response.data, container);
            }
        } catch (error) {
            console.error('Failed to load today appointments:', error);
        }
    },
    
    // Global search
    async globalSearch(event) {
        const query = event.target.value.trim();
        const resultsContainer = document.getElementById('global-search-results');
        
        if (!resultsContainer) return;
        
        if (query.length < 3) {
            resultsContainer.innerHTML = '';
            return;
        }
        
        try {
            this.showSearchLoading(resultsContainer);
            
            const response = await this.apiRequest('/api/search.php', {
                query: query,
                types: ['users', 'vehicles', 'appointments']
            });
            
            if (response.success) {
                this.renderGlobalResults(response.data, resultsContainer);
            } else {
                resultsContainer.innerHTML = `<div class="alert alert-warning">${response.message}</div>`;
            }
        } catch (error) {
            console.error('Global search failed:', error);
            resultsContainer.innerHTML = '<div class="alert alert-error">Σφάλμα αναζήτησης</div>';
        }
    },
    
    // Show search loading state
    showSearchLoading(container) {
        container.innerHTML = '<div class="text-center p-3"><div class="spinner"></div></div>';
    },
    
    // Render user search results
    renderUserResults(users, container) {
        if (users.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Δεν βρέθηκαν χρήστες</div>';
            return;
        }
        
        const html = users.map(user => `
            <div class="search-result-item" data-user-id="${user.id}">
                <div class="search-result-header">
                    <strong>${user.first_name} ${user.last_name}</strong>
                    <span class="badge badge-${this.getRoleClass(user.role)}">${this.getRoleLabel(user.role)}</span>
                </div>
                <div class="search-result-details">
                    <small>
                        ${user.email} | ${user.username}
                        ${user.phone ? ` | ${user.phone}` : ''}
                    </small>
                </div>
                <div class="search-result-actions">
                    <a href="/users/profile.php?id=${user.id}" class="btn btn-sm btn-primary">Προβολή</a>
                    ${this.canEditUser(user) ? `<a href="/users/edit.php?id=${user.id}" class="btn btn-sm btn-secondary">Επεξεργασία</a>` : ''}
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    // Render vehicle search results
    renderVehicleResults(vehicles, container) {
        if (vehicles.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Δεν βρέθηκαν αυτοκίνητα</div>';
            return;
        }
        
        const html = vehicles.map(vehicle => `
            <div class="search-result-item" data-vehicle-id="${vehicle.id}">
                <div class="search-result-header">
                    <strong>${vehicle.brand} ${vehicle.model}</strong>
                    <span class="text-muted">${vehicle.serial_number}</span>
                </div>
                <div class="search-result-details">
                    <small>
                        Ιδιοκτήτης: ${vehicle.customer_name}
                        | ${vehicle.vehicle_type}
                        | ${vehicle.fuel_type}
                    </small>
                </div>
                <div class="search-result-actions">
                    <a href="/vehicles/details.php?id=${vehicle.id}" class="btn btn-sm btn-primary">Προβολή</a>
                    ${this.canEditVehicle(vehicle) ? `<a href="/vehicles/edit.php?id=${vehicle.id}" class="btn btn-sm btn-secondary">Επεξεργασία</a>` : ''}
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    // Render appointment search results
    renderAppointmentResults(appointments, container) {
        if (appointments.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Δεν βρέθηκαν ραντεβού</div>';
            return;
        }
        
        const html = appointments.map(appointment => `
            <div class="search-result-item" data-appointment-id="${appointment.id}">
                <div class="search-result-header">
                    <strong>${GarageApp.formatDate(appointment.appointment_date)} ${appointment.appointment_time}</strong>
                    <span class="badge badge-${this.getStatusClass(appointment.status)}">${this.getStatusLabel(appointment.status)}</span>
                </div>
                <div class="search-result-details">
                    <small>
                        Πελάτης: ${appointment.customer_name}
                        | ${appointment.brand} ${appointment.model}
                        | ${appointment.reason === 'repair' ? 'Επιδιόρθωση' : 'Σέρβις'}
                        ${appointment.mechanic_name ? ` | Μηχανικός: ${appointment.mechanic_name}` : ''}
                    </small>
                </div>
                <div class="search-result-actions">
                    <a href="/appointments/details.php?id=${appointment.id}" class="btn btn-sm btn-primary">Προβολή</a>
                    ${this.canEditAppointment(appointment) ? `<a href="/appointments/edit.php?id=${appointment.id}" class="btn btn-sm btn-secondary">Επεξεργασία</a>` : ''}
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    // Setup async form submissions
    setupFormSubmissions() {
        document.addEventListener('submit', async (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                await this.submitForm(e.target);
            }
        });
    },
    
    // Submit form via AJAX
    async submitForm(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        
        try {
            // Validate form
            if (!GarageApp.validateForm(form)) {
                return;
            }
            
            // Set loading state
            if (submitBtn) {
                GarageApp.setLoadingState(submitBtn, true);
            }
            
            // Clear previous errors
            form.querySelectorAll('.form-error').forEach(error => error.remove());
            form.querySelectorAll('.form-control.error').forEach(field => 
                field.classList.remove('error')
            );
            
            // Submit form
            const formData = new FormData(form);
            const response = await this.apiRequest(form.action, formData, 'POST');
            
            if (response.success) {
                // Show success message
                GarageApp.showNotification(response.message || 'Επιτυχής αποθήκευση', 'success');
                
                // Handle redirect
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1000);
                }
                
                // Reset form if specified
                if (form.hasAttribute('data-reset-on-success')) {
                    form.reset();
                }
            } else {
                // Handle validation errors
                if (response.errors) {
                    this.showFormErrors(form, response.errors);
                }
                
                GarageApp.showNotification(response.message || 'Σφάλμα αποθήκευσης', 'error');
            }
        } catch (error) {
            console.error('Form submission failed:', error);
            GarageApp.showNotification('Σφάλμα σύνδεσης με τον εξυπηρετητή', 'error');
        } finally {
            // Reset button state
            if (submitBtn) {
                GarageApp.setLoadingState(submitBtn, false);
            }
        }
    },
    
    // Show form validation errors
    showFormErrors(form, errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                GarageApp.setFieldValidation(field, false, errors[fieldName]);
            }
        });
    },
    
    // Setup real-time updates
    setupRealTimeUpdates() {
        // Check for appointment status updates
        if (document.querySelector('.appointment-status')) {
            this.startAppointmentStatusPolling();
        }
        
        // Check for mechanic availability updates
        if (document.querySelector('.mechanic-availability')) {
            this.startMechanicAvailabilityPolling();
        }
    },
    
    // Poll appointment status updates
    startAppointmentStatusPolling() {
        setInterval(async () => {
            try {
                const response = await this.apiRequest('/api/appointments.php', {
                    action: 'status_updates'
                });
                
                if (response.success && response.data.length > 0) {
                    this.updateAppointmentStatuses(response.data);
                }
            } catch (error) {
                console.error('Failed to poll appointment status:', error);
            }
        }, 30000); // Every 30 seconds
    },
    
    // Update appointment statuses in the UI
    updateAppointmentStatuses(updates) {
        updates.forEach(update => {
            const statusElement = document.querySelector(`[data-appointment-id="${update.id}"] .appointment-status`);
            if (statusElement) {
                statusElement.className = `appointment-status badge badge-${this.getStatusClass(update.status)}`;
                statusElement.textContent = this.getStatusLabel(update.status);
                
                // Show notification for status change
                if (update.changed) {
                    GarageApp.showNotification(
                        `Το ραντεβού ${update.id} άλλαξε κατάσταση σε: ${this.getStatusLabel(update.status)}`,
                        'info',
                        3000
                    );
                }
            }
        });
    },
    
    // Setup autocomplete
    setupAutoComplete() {
        const autocompleteInputs = document.querySelectorAll('[data-autocomplete]');
        
        autocompleteInputs.forEach(input => {
            const source = input.getAttribute('data-autocomplete');
            const minLength = parseInt(input.getAttribute('data-min-length') || '2');
            
            let dropdown = null;
            
            input.addEventListener('input', GarageApp.debounce(async (e) => {
                const query = e.target.value.trim();
                
                if (query.length < minLength) {
                    this.hideAutocomplete(dropdown);
                    return;
                }
                
                try {
                    const response = await this.apiRequest('/api/autocomplete.php', {
                        source: source,
                        query: query,
                        limit: 10
                    });
                    
                    if (response.success) {
                        dropdown = this.showAutocomplete(input, response.data);
                    }
                } catch (error) {
                    console.error('Autocomplete failed:', error);
                }
            }, 300));
            
            // Hide autocomplete on blur (with delay to allow clicking)
            input.addEventListener('blur', () => {
                setTimeout(() => {
                    this.hideAutocomplete(dropdown);
                }, 200);
            });
        });
    },
    
    // Show autocomplete dropdown
    showAutocomplete(input, items) {
        // Remove existing dropdown
        const existing = input.parentNode.querySelector('.autocomplete-dropdown');
        if (existing) {
            existing.remove();
        }
        
        if (items.length === 0) return null;
        
        const dropdown = document.createElement('div');
        dropdown.className = 'autocomplete-dropdown';
        dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        `;
        
        const html = items.map(item => `
            <div class="autocomplete-item" data-value="${item.value}" style="
                padding: 0.75rem;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            " onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='white'">
                ${item.label}
            </div>
        `).join('');
        
        dropdown.innerHTML = html;
        
        // Add click handlers
        dropdown.addEventListener('click', (e) => {
            if (e.target.classList.contains('autocomplete-item')) {
                input.value = e.target.getAttribute('data-value');
                this.hideAutocomplete(dropdown);
                
                // Trigger change event
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        
        // Position dropdown
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(dropdown);
        
        return dropdown;
    },
    
    // Hide autocomplete dropdown
    hideAutocomplete(dropdown) {
        if (dropdown && dropdown.parentNode) {
            dropdown.remove();
        }
    },
    
    // Generic API request method
    async apiRequest(url, data = {}, method = 'GET') {
        const options = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken.content;
        }
        
        if (method === 'GET') {
            const params = new URLSearchParams(data);
            url += '?' + params.toString();
        } else {
            if (data instanceof FormData) {
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }
        
        const response = await fetch(GarageApp.config.baseUrl + url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        } else {
            return await response.text();
        }
    },
    
    // Helper methods for rendering
    getRoleClass(role) {
        const classes = {
            'customer': 'primary',
            'mechanic': 'success',
            'secretary': 'warning'
        };
        return classes[role] || 'secondary';
    },
    
    getRoleLabel(role) {
        const labels = {
            'customer': 'Πελάτης',
            'mechanic': 'Μηχανικός',
            'secretary': 'Γραμματέας'
        };
        return labels[role] || role;
    },
    
    getStatusClass(status) {
        const classes = {
            'created': 'warning',
            'in_progress': 'info',
            'completed': 'success',
            'cancelled': 'danger'
        };
        return classes[status] || 'secondary';
    },
    
    getStatusLabel(status) {
        const labels = {
            'created': 'Δημιουργημένο',
            'in_progress': 'Σε εξέλιξη',
            'completed': 'Ολοκληρωμένο',
            'cancelled': 'Ακυρωμένο'
        };
        return labels[status] || status;
    },
    
    // Permission checks
    canEditUser(user) {
        const currentUser = GarageApp.state.currentUser;
        if (!currentUser) return false;
        
        return currentUser.role === 'secretary' || currentUser.id === user.id;
    },
    
    canEditVehicle(vehicle) {
        const currentUser = GarageApp.state.currentUser;
        if (!currentUser) return false;
        
        return currentUser.role === 'secretary' || 
               (currentUser.role === 'customer' && currentUser.id === vehicle.customer_id);
    },
    
    canEditAppointment(appointment) {
        const currentUser = GarageApp.state.currentUser;
        if (!currentUser) return false;
        
        return currentUser.role === 'secretary' || 
               (currentUser.role === 'customer' && currentUser.id === appointment.customer_id) ||
               (currentUser.role === 'mechanic' && currentUser.id === appointment.mechanic_id);
    }
};

// Initialize AJAX Manager when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AjaxManager.init());
} else {
    AjaxManager.init();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AjaxManager;
}