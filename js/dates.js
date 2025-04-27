// Add this code to your tide_books.js file or include it in a separate script file

document.addEventListener('DOMContentLoaded', function() {
    // Fix for date filter icon
    const dateFilterIcon = document.querySelector('th.col-date .filter-icon');
    if (dateFilterIcon) {
        dateFilterIcon.addEventListener('click', function(event) {
            event.stopPropagation();
            
            // Get the container and input
            const container = this.nextElementSibling;
            const dateInput = container.querySelector('#filter-date-range');
            
            // Toggle the active class on the container
            container.classList.toggle('active');
            
            // If the container is now active and flatpickr is initialized
            if (container.classList.contains('active') && window.flatpickrInstanceDateRange) {
                // Focus and open the date picker
                dateInput.focus();
                window.flatpickrInstanceDateRange.open();
            } else if (window.flatpickrInstanceDateRange) {
                // Close the date picker if container is closed
                window.flatpickrInstanceDateRange.close();
            }
        });
    }
    
    // Close flatpickr when clicking outside
    document.addEventListener('click', function(event) {
        // If click is outside flatpickr calendar and outside filter icon
        if (!event.target.closest('.flatpickr-calendar') && 
            !event.target.matches('.filter-icon') &&
            !event.target.closest('.filter-input-container')) {
            
            // Close all filter containers
            document.querySelectorAll('.filter-input-container.active').forEach(container => {
                container.classList.remove('active');
            });
            
            // Close flatpickr if it's open
            if (window.flatpickrInstanceDateRange && window.flatpickrInstanceDateRange.isOpen) {
                window.flatpickrInstanceDateRange.close();
            }
        }
    });
    
    // Make sure flatpickr initializes properly even if called late
    function ensureDatePickerInitialized() {
        const dateRangeInput = document.getElementById('filter-date-range');
        if (dateRangeInput && !window.flatpickrInstanceDateRange) {
            try {
                window.flatpickrInstanceDateRange = flatpickr("#filter-date-range", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "j M Y",
                    onClose: function(selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            currentDateFilter.start = instance.formatDate(selectedDates[0], "Y-m-d");
                            currentDateFilter.end = instance.formatDate(selectedDates[1], "Y-m-d");
                        } else if (selectedDates.length === 1) {
                            currentDateFilter.start = instance.formatDate(selectedDates[0], "Y-m-d");
                            currentDateFilter.end = null;
                        } else {
                            currentDateFilter.start = null;
                            currentDateFilter.end = null;
                        }
                        
                        // Apply filter immediately
                        filterTable();
                    }
                });
                console.log("Date picker initialized by fix script");
            } catch (error) {
                console.error("Error initializing date picker:", error);
            }
        }
    }
    
    // Try to initialize after a short delay in case the original init failed
    setTimeout(ensureDatePickerInitialized, 500);
});