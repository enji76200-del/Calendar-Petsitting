/**
 * Frontend JavaScript for Calendar Pet Sitting plugin
 */

(function($) {
    'use strict';
    
    let calendar;
    let selectedService = null;
    let occurrences = [];
    
    /**
     * Initialize the calendar
     */
    window.initPetsittingCalendar = function(calendarId) {
        const calendarEl = document.getElementById(calendarId);
        if (!calendarEl) return;
        
        const view = calendarEl.dataset.view || 'dayGridMonth';
        const height = calendarEl.dataset.height || 600;
        const serviceId = calendarEl.dataset.serviceId || null;
        
        // Initialize FullCalendar
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: view,
            height: parseInt(height),
            locale: 'fr',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Aujourd\'hui',
                month: 'Mois',
                week: 'Semaine',
                day: 'Jour'
            },
            loading: function(isLoading) {
                if (isLoading) {
                    showLoading();
                } else {
                    hideLoading();
                }
            },
            eventSources: [
                {
                    url: calendarPetsitting.restUrl + 'availability',
                    method: 'GET',
                    extraParams: function() {
                        const params = {};
                        if (serviceId) {
                            params.service_id = serviceId;
                        }
                        return params;
                    },
                    success: function(data) {
                        hideLoading();
                    },
                    failure: function() {
                        hideLoading();
                        showError(calendarPetsitting.strings.error);
                    }
                }
            ],
            dateClick: function(info) {
                handleDateClick(info);
            },
            select: function(info) {
                handleSelect(info);
            },
            selectable: true,
            selectMirror: true,
            unselectAuto: false,
            eventDisplay: 'background',
            dayCellDidMount: function(info) {
                // Make available dates clickable
                if (isDateAvailable(info.date)) {
                    info.el.classList.add('petsitting-available-slot');
                    info.el.title = 'Cliquez pour réserver ce créneau';
                }
            }
        });
        
        calendar.render();
        
        // Initialize modal handlers
        initModalHandlers();
    };
    
    /**
     * Show loading indicator
     */
    function showLoading() {
        $('.petsitting-loading').show();
    }
    
    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('.petsitting-loading').hide();
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        // Create or update error message
        let errorEl = $('.petsitting-error-message');
        if (errorEl.length === 0) {
            errorEl = $('<div class="petsitting-error-message"></div>');
            $('.petsitting-calendar-container').prepend(errorEl);
        }
        
        errorEl.html('<p><strong>Erreur:</strong> ' + message + '</p>').show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            errorEl.fadeOut();
        }, 5000);
    }
    
    /**
     * Handle date click
     */
    function handleDateClick(info) {
        if (!isDateAvailable(info.date)) {
            return;
        }
        
        // Open booking modal with selected date
        openBookingModal(info.date);
    }
    
    /**
     * Handle date selection
     */
    function handleSelect(info) {
        if (!isDateAvailable(info.start)) {
            calendar.unselect();
            return;
        }
        
        // Open booking modal with selected date range
        openBookingModal(info.start, info.end);
    }
    
    /**
     * Check if a date is available
     */
    function isDateAvailable(date) {
        // This would be enhanced to check against actual availability data
        return true;
    }
    
    /**
     * Open booking modal
     */
    function openBookingModal(startDate, endDate = null) {
        resetForm();
        
        // Add initial occurrence if dates provided
        if (startDate) {
            const occurrence = {
                start_datetime: formatDateTimeForInput(startDate),
                end_datetime: endDate ? formatDateTimeForInput(endDate) : formatDateTimeForInput(new Date(startDate.getTime() + 60 * 60 * 1000))
            };
            addOccurrence(occurrence);
        }
        
        $('#petsitting-booking-modal').show();
    }
    
    /**
     * Initialize modal event handlers
     */
    function initModalHandlers() {
        // Close modal handlers
        $('.petsitting-modal-close, #cancel-booking').on('click', function() {
            closeBookingModal();
        });
        
        $('#close-success-modal').on('click', function() {
            $('#petsitting-success-modal').hide();
            calendar.refetchEvents();
        });
        
        // Click outside modal to close
        $('.petsitting-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Service selection change
        $('#service_id').on('change', function() {
            selectedService = getSelectedServiceData();
            updateOccurrencesForService();
            calculateTotal();
        });
        
        // Add occurrence button
        $('#add-occurrence').on('click', function() {
            addOccurrence();
        });
        
        // Confirm booking
        $('#confirm-booking').on('click', function() {
            submitBooking();
        });
        
        // Form field changes
        $('#petsitting-booking-form').on('change', 'input, select, textarea', function() {
            calculateTotal();
        });
    }
    
    /**
     * Reset form
     */
    function resetForm() {
        $('#petsitting-booking-form')[0].reset();
        occurrences = [];
        selectedService = null;
        updateOccurrencesDisplay();
        calculateTotal();
    }
    
    /**
     * Close booking modal
     */
    function closeBookingModal() {
        $('#petsitting-booking-modal').hide();
        calendar.unselect();
    }
    
    /**
     * Get selected service data
     */
    function getSelectedServiceData() {
        const $option = $('#service_id option:selected');
        if (!$option.val()) return null;
        
        return {
            id: parseInt($option.val()),
            type: $option.data('type'),
            price: parseInt($option.data('price')),
            minDuration: parseInt($option.data('min-duration')),
            stepMinutes: parseInt($option.data('step-minutes')) || 30
        };
    }
    
    /**
     * Add occurrence
     */
    function addOccurrence(data = null) {
        const occurrence = data || {
            start_datetime: '',
            end_datetime: ''
        };
        
        occurrences.push(occurrence);
        updateOccurrencesDisplay();
    }
    
    /**
     * Remove occurrence
     */
    function removeOccurrence(index) {
        occurrences.splice(index, 1);
        updateOccurrencesDisplay();
        calculateTotal();
    }
    
    /**
     * Update occurrences display
     */
    function updateOccurrencesDisplay() {
        const $container = $('#selected-occurrences');
        $container.empty();
        
        if (occurrences.length === 0) {
            $container.append('<p class="petsitting-no-occurrences">' + calendarPetsitting.strings.validation.minOccurrences + '</p>');
            return;
        }
        
        occurrences.forEach(function(occurrence, index) {
            const $occurrence = createOccurrenceElement(occurrence, index);
            $container.append($occurrence);
        });
    }
    
    /**
     * Create occurrence element
     */
    function createOccurrenceElement(occurrence, index) {
        const $element = $(`
            <div class="petsitting-occurrence" data-index="${index}">
                <div class="petsitting-occurrence-header">
                    <span class="petsitting-occurrence-title">${calendarPetsitting.strings.occurrences} ${index + 1}</span>
                    <button type="button" class="petsitting-occurrence-remove" onclick="removeOccurrence(${index})">
                        ${calendarPetsitting.strings.removeOccurrence}
                    </button>
                </div>
                <div class="petsitting-occurrence-fields">
                    <div class="petsitting-form-group">
                        <label>Début *</label>
                        <input type="datetime-local" name="occurrences[${index}][start_datetime]" 
                               value="${occurrence.start_datetime}" required 
                               onchange="updateOccurrence(${index}, 'start_datetime', this.value)">
                    </div>
                    <div class="petsitting-form-group">
                        <label>Fin *</label>
                        <input type="datetime-local" name="occurrences[${index}][end_datetime]" 
                               value="${occurrence.end_datetime}" required 
                               onchange="updateOccurrence(${index}, 'end_datetime', this.value)">
                    </div>
                </div>
            </div>
        `);
        
        return $element;
    }
    
    /**
     * Update occurrence data
     */
    window.updateOccurrence = function(index, field, value) {
        if (occurrences[index]) {
            occurrences[index][field] = value;
            calculateTotal();
        }
    };
    
    /**
     * Remove occurrence (global function)
     */
    window.removeOccurrence = function(index) {
        occurrences.splice(index, 1);
        updateOccurrencesDisplay();
        calculateTotal();
    };
    
    /**
     * Update occurrences based on selected service
     */
    function updateOccurrencesForService() {
        if (!selectedService) return;
        
        // Adjust occurrence times based on service type
        occurrences.forEach(function(occurrence) {
            if (selectedService.type === 'daily') {
                // For daily services, set to full day
                const startDate = new Date(occurrence.start_datetime);
                const endDate = new Date(occurrence.start_datetime);
                
                startDate.setHours(0, 0, 0, 0);
                endDate.setHours(23, 59, 59, 999);
                
                occurrence.start_datetime = formatDateTimeForInput(startDate);
                occurrence.end_datetime = formatDateTimeForInput(endDate);
            }
        });
        
        updateOccurrencesDisplay();
    }
    
    /**
     * Calculate total price
     */
    function calculateTotal() {
        if (!selectedService || occurrences.length === 0) {
            $('#booking-total').text('0,00 €');
            return;
        }
        
        let total = 0;
        
        occurrences.forEach(function(occurrence) {
            if (occurrence.start_datetime && occurrence.end_datetime) {
                const start = new Date(occurrence.start_datetime);
                const end = new Date(occurrence.end_datetime);
                const duration = (end - start) / (1000 * 60); // Duration in minutes
                
                let price = 0;
                
                switch (selectedService.type) {
                    case 'daily':
                        const days = Math.ceil(duration / (24 * 60));
                        price = selectedService.price * Math.max(1, days);
                        break;
                        
                    case 'hourly':
                        const hours = Math.ceil(duration / 60);
                        price = selectedService.price * Math.max(1, hours);
                        break;
                        
                    case 'minute':
                        const units = Math.ceil(duration / selectedService.stepMinutes);
                        price = selectedService.price * Math.max(1, units);
                        break;
                }
                
                total += price;
            }
        });
        
        const totalEuros = total / 100;
        $('#booking-total').text(totalEuros.toFixed(2).replace('.', ',') + ' €');
    }
    
    /**
     * Submit booking
     */
    function submitBooking() {
        if (!validateForm()) {
            return;
        }
        
        const formData = {
            customer: {
                first_name: $('#customer_first_name').val(),
                last_name: $('#customer_last_name').val(),
                email: $('#customer_email').val(),
                phone: $('#customer_phone').val()
            },
            service_id: parseInt($('#service_id').val()),
            occurrences: occurrences.map(function(occ) {
                return {
                    start_datetime: new Date(occ.start_datetime).toISOString(),
                    end_datetime: new Date(occ.end_datetime).toISOString()
                };
            }),
            notes: $('#booking_notes').val()
        };
        
        // Disable form
        $('#confirm-booking').prop('disabled', true).text('Traitement...');
        
        // Submit via REST API
        $.ajax({
            url: calendarPetsitting.restUrl + 'book',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    $('#petsitting-booking-modal').hide();
                    $('#petsitting-success-modal').show();
                } else {
                    alert(response.message || calendarPetsitting.strings.error);
                }
            },
            error: function(xhr) {
                let message = calendarPetsitting.strings.error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
                $('#confirm-booking').prop('disabled', false).text(calendarPetsitting.strings.confirmBooking);
            }
        });
    }
    
    /**
     * Validate form
     */
    function validateForm() {
        let isValid = true;
        
        // Clear previous errors
        $('.petsitting-form-group').removeClass('error');
        
        // Required fields
        const requiredFields = ['#customer_first_name', '#customer_last_name', '#customer_email', '#customer_phone', '#service_id'];
        
        requiredFields.forEach(function(field) {
            if (!$(field).val().trim()) {
                $(field).closest('.petsitting-form-group').addClass('error');
                isValid = false;
            }
        });
        
        // Email validation
        const email = $('#customer_email').val();
        if (email && !isValidEmail(email)) {
            $('#customer_email').closest('.petsitting-form-group').addClass('error');
            alert(calendarPetsitting.strings.validation.email);
            isValid = false;
        }
        
        // Phone validation
        const phone = $('#customer_phone').val();
        if (phone && !isValidPhone(phone)) {
            $('#customer_phone').closest('.petsitting-form-group').addClass('error');
            alert(calendarPetsitting.strings.validation.phone);
            isValid = false;
        }
        
        // Occurrences validation
        if (occurrences.length === 0) {
            alert(calendarPetsitting.strings.validation.minOccurrences);
            isValid = false;
        }
        
        // Validate each occurrence
        occurrences.forEach(function(occurrence, index) {
            if (!occurrence.start_datetime || !occurrence.end_datetime) {
                alert(`Créneau ${index + 1}: dates de début et fin requises`);
                isValid = false;
                return;
            }
            
            const start = new Date(occurrence.start_datetime);
            const end = new Date(occurrence.end_datetime);
            
            if (start >= end) {
                alert(`Créneau ${index + 1}: la date de fin doit être postérieure à la date de début`);
                isValid = false;
                return;
            }
            
            if (start < new Date()) {
                alert(`Créneau ${index + 1}: les réservations dans le passé ne sont pas autorisées`);
                isValid = false;
                return;
            }
        });
        
        return isValid;
    }
    
    /**
     * Validate email
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Validate phone
     */
    function isValidPhone(phone) {
        const re = /^[\+]?[0-9\s\-\(\)]{8,}$/;
        return re.test(phone.replace(/\s/g, ''));
    }
    
    /**
     * Format date for datetime-local input
     */
    function formatDateTimeForInput(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    
})(jQuery);