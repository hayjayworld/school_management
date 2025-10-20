// Main JavaScript file for School Management System

// Utility Functions
const utils = {
    // Show loading spinner
    showLoading: function(button) {
        const originalText = button.innerHTML;
        button.innerHTML = '<span class="loading-spinner"></span> Loading...';
        button.disabled = true;
        return originalText;
    },

    // Hide loading spinner
    hideLoading: function(button, originalText) {
        button.innerHTML = originalText;
        button.disabled = false;
    },

    // Format date
    formatDate: function(dateString) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    },

    // Format time
    formatTime: function(dateString) {
        const options = { hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleTimeString(undefined, options);
    },

    // Show notification
    showNotification: function(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Add to page
        const container = document.querySelector('.container') || document.body;
        container.insertBefore(alert, container.firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    },

    // Confirm action
    confirmAction: function(message) {
        return confirm(message);
    },

    // Validate email
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    // Validate phone
    validatePhone: function(phone) {
        const re = /^[0-9+]{10,15}$/;
        return re.test(phone);
    }
};

// Form Handling
const formHandler = {
    // Initialize form validation
    init: function() {
        this.setupValidation();
        this.setupAutoSave();
    },

    // Setup form validation
    setupValidation: function() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!formHandler.validateForm(this)) {
                    e.preventDefault();
                    utils.showNotification('Please fix the errors in the form.', 'danger');
                }
            });
        });
    },

    // Validate form
    validateForm: function(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.markInvalid(input, 'This field is required.');
                isValid = false;
            } else {
                this.markValid(input);
                
                // Additional validation based on input type
                if (input.type === 'email' && !utils.validateEmail(input.value)) {
                    this.markInvalid(input, 'Please enter a valid email address.');
                    isValid = false;
                }
                
                if (input.type === 'tel' && !utils.validatePhone(input.value)) {
                    this.markInvalid(input, 'Please enter a valid phone number.');
                    isValid = false;
                }
            }
        });
        
        return isValid;
    },

    // Mark field as invalid
    markInvalid: function(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        // Remove existing feedback
        const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Add feedback message
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    },

    // Mark field as valid
    markValid: function(field) {
        field.classList.add('is-valid');
        field.classList.remove('is-invalid');
        
        // Remove existing feedback
        const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
    },

    // Setup auto-save for forms
    setupAutoSave: function() {
        const autoSaveForms = document.querySelectorAll('form[data-auto-save]');
        
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('input, select, textarea');
            let timeout;
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        formHandler.autoSaveForm(form);
                    }, 1000);
                });
            });
        });
    },

    // Auto-save form data
    autoSaveForm: function(form) {
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Form auto-saved successfully');
            }
        })
        .catch(error => {
            console.error('Auto-save failed:', error);
        });
    }
};

// Data Table Handler
const dataTable = {
    // Initialize data tables
    init: function() {
        this.setupSearch();
        this.setupPagination();
        this.setupSorting();
    },

    // Setup search functionality
    setupSearch: function() {
        const searchInputs = document.querySelectorAll('[data-search]');
        
        searchInputs.forEach(input => {
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const tableId = this.getAttribute('data-search');
                const table = document.getElementById(tableId);
                
                if (table) {
                    const rows = table.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                }
            });
        });
    },

    // Setup pagination
    setupPagination: function() {
        // This would be implemented based on specific needs
        console.log('Pagination setup');
    },

    // Setup sorting
    setupSorting: function() {
        const sortableHeaders = document.querySelectorAll('th[data-sort]');
        
        sortableHeaders.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const table = this.closest('table');
                const columnIndex = this.cellIndex;
                const isNumeric = this.getAttribute('data-sort') === 'numeric';
                
                dataTable.sortTable(table, columnIndex, isNumeric);
            });
        });
    },

    // Sort table
    sortTable: function(table, columnIndex, isNumeric) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            let aValue = a.cells[columnIndex].textContent.trim();
            let bValue = b.cells[columnIndex].textContent.trim();
            
            if (isNumeric) {
                aValue = parseFloat(aValue) || 0;
                bValue = parseFloat(bValue) || 0;
                return aValue - bValue;
            } else {
                return aValue.localeCompare(bValue);
            }
        });
        
        // Remove existing rows
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }
        
        // Add sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }
};

// Exam Timer
const examTimer = {
    // Initialize exam timer
    init: function(durationInSeconds, onTimeUp) {
        this.duration = durationInSeconds;
        this.onTimeUp = onTimeUp;
        this.startTime = Date.now();
        this.interval = null;
        
        this.start();
    },

    // Start timer
    start: function() {
        this.interval = setInterval(() => {
            this.update();
        }, 1000);
    },

    // Update timer display
    update: function() {
        const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
        const remaining = this.duration - elapsed;
        
        if (remaining <= 0) {
            this.stop();
            if (this.onTimeUp) {
                this.onTimeUp();
            }
            return;
        }
        
        this.display(remaining);
        
        // Change color when time is running out
        const timerElement = document.getElementById('examTimer');
        if (timerElement) {
            if (remaining <= 300) { // 5 minutes
                timerElement.classList.add('text-danger');
                timerElement.classList.add('fw-bold');
            } else if (remaining <= 600) { // 10 minutes
                timerElement.classList.add('text-warning');
            }
        }
    },

    // Display time
    display: function(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        
        const timerElement = document.getElementById('examTimer');
        if (timerElement) {
            timerElement.textContent = timeString;
        }
    },

    // Stop timer
    stop: function() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    // Get remaining time
    getRemainingTime: function() {
        const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
        return this.duration - elapsed;
    }
};

// Chart Utilities (for future dashboard enhancements)
const chartUtils = {
    // Create simple bar chart
    createBarChart: function(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        };
        
        // Merge options
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'bar',
            data: data,
            options: chartOptions
        });
    },

    // Create pie chart
    createPieChart: function(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const defaultOptions = {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        };
        
        const chartOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: 'pie',
            data: data,
            options: chartOptions
        });
    }
};

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    formHandler.init();
    dataTable.init();
    
    // Initialize exam timer if on exam page
    const examDuration = document.getElementById('examDuration');
    if (examDuration) {
        const duration = parseInt(examDuration.value);
        examTimer.init(duration, function() {
            utils.showNotification('Time is up! Submitting your exam...', 'warning');
            document.getElementById('examForm').submit();
        });
    }
    
    // Add confirmation to delete buttons
    const deleteButtons = document.querySelectorAll('.btn-delete, .btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!utils.confirmAction('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        utils,
        formHandler,
        dataTable,
        examTimer,
        chartUtils
    };
}