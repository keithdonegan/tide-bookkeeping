// tide-books.js - Main JavaScript for Tide Books Dashboard

document.addEventListener('DOMContentLoaded', function() {
    // --- EARLY INITIALIZATION: Load saved column widths first ---
    loadSavedColumnWidths();
    
    // --- UTILITY FUNCTIONS ---
    
    // Debounce function to limit how often a function can be called
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const context = this;
            const later = () => {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // --- GLOBAL STATE ---
    let currentDateFilter = { start: null, end: null };
    let currentTotalCount = parseInt(document.getElementById('stats-total')?.textContent.replace(/,/g, '') || 0);
    let currentCompleteCount = parseInt(document.getElementById('stats-complete-count')?.textContent.replace(/,/g, '') || 0);
    
    // --- STATUS UPDATE FUNCTIONS ---
    
    // Helper function to update stats display
    function updateStatsDisplay() {
        const totalEl = document.getElementById('stats-total');
        const completeCountEl = document.getElementById('stats-complete-count');
        const totalDisplayEl = document.getElementById('stats-total-display');
        const percentageEl = document.getElementById('stats-percentage');
        const needsActionEl = document.getElementById('stats-needs-action');

        if (totalEl && completeCountEl && totalDisplayEl && percentageEl && needsActionEl) {
            // Ensure counts are numbers
            currentTotalCount = Number(currentTotalCount) || 0;
            currentCompleteCount = Number(currentCompleteCount) || 0;

            let needsCompletion = currentTotalCount - currentCompleteCount;
            let percentage = (currentTotalCount > 0) ? Math.round((currentCompleteCount / currentTotalCount) * 100) : 0;

            totalEl.textContent = currentTotalCount.toLocaleString();
            completeCountEl.textContent = currentCompleteCount.toLocaleString();
            totalDisplayEl.textContent = currentTotalCount.toLocaleString();
            percentageEl.textContent = percentage;
            needsActionEl.textContent = needsCompletion.toLocaleString();
        } else {
            console.error("One or more stats elements not found.");
        }
    }
    
    // Helper function for toast notifications
    function showToast(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toast-container');
        if (!container) { 
            console.error("Toast container not found!"); 
            return; 
        }
        
        const toast = document.createElement('div');
        toast.className = `toast-message toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        
        // Fade out animation
        setTimeout(() => { toast.classList.add('fade-out'); }, duration - 500);
        
        // Remove element after fade out
        setTimeout(() => { 
            if (toast.parentNode === container) { 
                container.removeChild(toast); 
            } 
        }, duration);
    }
    
    // Check and update row completion status
    function checkAndApplyRowCompletion(transactionId) {
        const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
        if (!row) return false;

        const categorySelect = row.querySelector('.category-select');
        const hasCategory = categorySelect && categorySelect.value !== "" && categorySelect.value !== "add_new";

        // Check if invoice is present OR "no invoice required" is checked
        const invoiceCell = row.querySelector('.invoice-actions');
        const hasInvoice = invoiceCell && invoiceCell.querySelector('.link-view') !== null;
        const noInvoiceRequired = invoiceCell && invoiceCell.dataset.invoiceRequired === '0';

        let isNowComplete = false;
        if (hasCategory && (hasInvoice || noInvoiceRequired)) {
            row.classList.add('transaction-complete');
            isNowComplete = true;
        } else {
            row.classList.remove('transaction-complete');
        }
        
        console.log(`Row ${transactionId} completion check: Category=${hasCategory}, Invoice=${hasInvoice}, NoInvoiceRequired=${noInvoiceRequired}, Complete=${isNowComplete}`);
        return isNowComplete;
    }
    
    // Update invoice status span
    function updateInvoiceStatusSpan(transactionId, message, statusClass = '') {
        const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
        const statusSpan = row?.querySelector('.invoice-status');
        if (statusSpan) {
            statusSpan.textContent = message;
            statusSpan.className = `invoice-status ${statusClass}`; // Reset and add
        }
    }
    
    // Update category status span
    function updateCategoryStatus(transactionId, message, statusClass = '') {
        const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
        const statusSpan = row?.querySelector('.category-status');
        if (statusSpan) {
            statusSpan.textContent = message;
            statusSpan.className = `category-status ${statusClass}`; // Reset and add
        }
    }
    
    // Update comment status span
    function updateCommentStatusSpan(transactionId, message, statusClass = '') {
        const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
        const statusSpan = row?.querySelector('.comment-save-status');
        if (statusSpan) {
            statusSpan.textContent = message;
            statusSpan.className = `comment-save-status ${statusClass}`;
        } else if (transactionId) {
            console.warn(`Status span not found for comment row ${transactionId}`);
        }
    }
    
    // --- TABLE FUNCTIONALITY ---
    
    // Add tooltips to cells with truncated text
    function addTooltipsToTruncatedCells() {
        const cells = document.querySelectorAll('#transactions-table tbody td:not(.tooltip-added)');
        
        cells.forEach(cell => {
            // Skip cells with form controls
            if (cell.querySelector('select, textarea, input, button')) {
                return;
            }
            
            const hasOverflow = cell.scrollWidth > cell.clientWidth;
            
            if (hasOverflow) {
                cell.title = cell.textContent.trim();
                cell.classList.add('tooltip-added');
                cell.style.cursor = 'help';
            }
        });
    }
    
    // Filter the table based on current filters
    function filterTable() {
        const tableBody = document.getElementById('transaction-data');
        if (!tableBody) return;
        
        const rows = tableBody.querySelectorAll('tr');
        const textFilters = document.querySelectorAll('.filter-input:not(.date-filter)');
        let activeFilterCount = 0;

        rows.forEach(row => {
            let display = true;
            const dateCellValue = row.querySelector('td[data-date]')?.dataset.date;

            // 1. Date Range Filter
            if (currentDateFilter.start && currentDateFilter.end && dateCellValue) {
                if (dateCellValue < currentDateFilter.start || dateCellValue > currentDateFilter.end) {
                    display = false;
                }
            } else if (currentDateFilter.start && !currentDateFilter.end && dateCellValue) {
                if (dateCellValue !== currentDateFilter.start) {
                    display = false;
                }
            }

            // 2. Text Filters (if row is still visible)
            if (display) {
                textFilters.forEach(input => {
                    const columnIndex = parseInt(input.dataset.columnIndex, 10);
                    const filterValue = input.value.toLowerCase().trim();
                    const cell = row.cells[columnIndex];
                    let cellValue = '';

                    if (filterValue !== '') {
                        activeFilterCount++; // Count active text filters
                        
                        // Special handling for category dropdown
                        if (columnIndex === 3) {
                            const select = cell.querySelector('.category-select');
                            cellValue = select ? select.options[select.selectedIndex].text.toLowerCase() : '';
                        } else if (columnIndex === 7) { // Comments column
                            const textarea = cell.querySelector('.comment-textarea');
                            cellValue = textarea ? textarea.value.toLowerCase() : '';
                        } else {
                            cellValue = cell ? cell.textContent.toLowerCase() : '';
                        }

                        if (!cellValue.includes(filterValue)) {
                            display = false;
                        }
                    }
                });
            }

            row.style.display = display ? '' : 'none';
        });

        // Style header and show/hide clear button based on active filters
        document.querySelectorAll('th .filter-icon').forEach(icon => {
            const th = icon.closest('th');
            const inputContainer = th.querySelector('.filter-input-container');
            const input = inputContainer?.querySelector('.filter-input');
            if (input?.value || (input?.id === 'filter-date-range' && window.flatpickrInstanceDateRange?.selectedDates.length > 0)) {
                th.classList.add('filter-active');
            } else {
                th.classList.remove('filter-active');
            }
        });

        const clearButton = document.getElementById('clear-filters-btn');
        if (clearButton) {
            const dateFilterActive = window.flatpickrInstanceDateRange?.selectedDates.length > 0;
            clearButton.style.display = (activeFilterCount > 0 || dateFilterActive) ? 'inline-block' : 'none';
        }
        
        updateTotals(); // Update totals based on visible rows
        setTimeout(addTooltipsToTruncatedCells, 100); // Re-check tooltips after filtering
    }
    
    // Update the totals based on visible rows
    function updateTotals() {
        const tableBody = document.getElementById('transaction-data');
        if (!tableBody) return;
        
        const rows = tableBody.querySelectorAll('tr');
        
        let totalPaidIn = 0;
        let totalPaidOut = 0;
        
        rows.forEach(row => {
            // Only include visible rows in the totals
            if (row.style.display !== 'none') {
                const paidInCell = row.querySelector('.paid-in');
                const paidOutCell = row.querySelector('.paid-out');
                
                if (paidInCell) {
                    const paidInText = paidInCell.textContent.trim();
                    if (paidInText) {
                        // Extract numeric value from "£1,234.56" format
                        const paidInValue = parseFloat(paidInText.replace(/[£,]/g, ''));
                        if (!isNaN(paidInValue)) {
                            totalPaidIn += paidInValue;
                        }
                    }
                }
                
                if (paidOutCell) {
                    const paidOutText = paidOutCell.textContent.trim();
                    if (paidOutText) {
                        // Extract numeric value from "£1,234.56" format
                        const paidOutValue = parseFloat(paidOutText.replace(/[£,]/g, ''));
                        if (!isNaN(paidOutValue)) {
                            totalPaidOut += paidOutValue;
                        }
                    }
                }
            }
        });
        
        // Update the total cells
        const paidInTotalCell = document.querySelector('.paid-in-total');
        const paidOutTotalCell = document.querySelector('.paid-out-total');
        const balanceTotalCell = document.querySelector('.balance-total');
        
        if (paidInTotalCell) {
            paidInTotalCell.textContent = '£' + totalPaidIn.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        if (paidOutTotalCell) {
            paidOutTotalCell.textContent = '£' + totalPaidOut.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        if (balanceTotalCell) {
            const balance = totalPaidIn - totalPaidOut;
            balanceTotalCell.textContent = '£' + balance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            balanceTotalCell.style.color = balance >= 0 ? '#28a745' : '#dc3545';
        }
    }
    
    // --- DATA MANAGEMENT FUNCTIONS ---
    
    // Save category selection
    function saveCategorySelection(transactionId, categoryId, selectElement) {
        const row = selectElement.closest('tr');
        const wasComplete = row.classList.contains('transaction-complete');
        const originalSelection = selectElement.dataset.currentSelection || '';

        updateCategoryStatus(transactionId, 'Saving...', 'status-saving');

        // Create JSON data
        const jsonData = {
            transaction_id: transactionId,
            category_id: categoryId 
        };

        // Add a small delay before fetch to allow UI update
        setTimeout(() => {
            fetch('save_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(jsonData)
            })
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok.'); }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    updateCategoryStatus(transactionId, 'Saved!', 'status-success');
                    selectElement.dataset.currentSelection = categoryId; // Update stored selection

                    // Update completion status and stats
                    const isNowComplete = checkAndApplyRowCompletion(transactionId);
                    console.log(`After Category Save - Row ${transactionId} complete: ${isNowComplete}, Was complete: ${wasComplete}`);
                    if (isNowComplete && !wasComplete) {
                        currentCompleteCount++;
                        updateStatsDisplay();
                    } else if (!isNowComplete && wasComplete) {
                        currentCompleteCount--;
                        updateStatsDisplay();
                    } else {
                        updateStatsDisplay();
                    }

                    showToast('Category Saved!', 'success');
                    setTimeout(() => updateCategoryStatus(transactionId, ''), 2000); // Clear after 2s
                } else {
                    throw new Error(data.message || 'Save failed.');
                }
            })
            .catch(error => {
                console.error('Error saving category:', error);
                updateCategoryStatus(transactionId, 'Error!', 'status-error');
                showToast(`Error saving category: ${error.message}`, 'error', 5000);
                selectElement.value = originalSelection; // Revert dropdown on error
                setTimeout(() => updateCategoryStatus(transactionId, ''), 3000); // Clear after 3s
            });
        }, 50); // 50ms delay
    }

    // --- EVENT LISTENERS SETUP ---
    
    // Setup auto-save for comments
    function setupCommentAutoSave() {
        const commentTextareas = document.querySelectorAll('.comment-textarea');
        commentTextareas.forEach(textarea => {
            textarea.dataset.originalValue = textarea.value.trim(); // Store initial value

            textarea.addEventListener('blur', debounce(function() {
                const transactionId = this.dataset.id;

                if (typeof transactionId === 'undefined' || transactionId === null || transactionId === '') {
                    console.error("Comment blur event: Transaction ID is missing or empty on element.", this);
                    const row = this.closest('tr');
                    const statusSpan = row?.querySelector('.comment-save-status');
                    if(statusSpan) {
                        statusSpan.textContent = 'Error: No ID!';
                        statusSpan.className = 'comment-save-status status-error';
                    }
                    return; // Stop execution
                }

                const originalValue = this.dataset.originalValue;
                const currentValue = this.value.trim();

                if (currentValue !== originalValue) {
                    updateCommentStatusSpan(transactionId, 'Saving...', 'status-saving');

                    const formData = new FormData();
                    formData.append('transaction_id', transactionId);
                    formData.append('comments', currentValue);

                    fetch('save_single_comment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().catch(() => ({})).then(errorData => {
                                throw new Error(errorData.message || `Network error: ${response.statusText}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            updateCommentStatusSpan(transactionId, 'Saved!', 'status-success');
                            this.dataset.originalValue = currentValue; // Update original value on success
                            showToast('Comment Saved!', 'success');
                            setTimeout(() => updateCommentStatusSpan(transactionId, ''), 2000); // Clear after 2s
                        } else {
                            throw new Error(data.message || 'Save operation failed.');
                        }
                    })
                    .catch(error => {
                        console.error('Error saving comment:', error);
                        updateCommentStatusSpan(transactionId, 'Error!', 'status-error');
                        showToast(`Error saving comment: ${error.message}`, 'error', 5000);
                        setTimeout(() => updateCommentStatusSpan(transactionId, ''), 3000); // Clear after 3s
                    });
                } else {
                     updateCommentStatusSpan(transactionId, ''); // Clear status if no change
                }
            }, 500)); // Debounce with 500ms delay after blur
        });
    }
    
    // Replace the setupFilters() function with this version:

// Replace both of these functions:

// 1. First function to update - original handler for all filters
function setupFilters() {
    const filterIcons = document.querySelectorAll('.filter-icon');
    const textFilterInputs = document.querySelectorAll('.filter-input:not(.date-filter)');
    const clearButton = document.getElementById('clear-filters-btn');
    const debouncedFilter = debounce(filterTable, 350); // Debounce text input filtering

    filterIcons.forEach(icon => {
        icon.addEventListener('click', (event) => {
            event.stopPropagation(); // Prevent outside click handler closing it immediately
            const container = icon.nextElementSibling;
            
            // Close other open filters first
            document.querySelectorAll('.filter-input-container.active').forEach(openContainer => {
                if (openContainer !== container) {
                    openContainer.classList.remove('active');
                }
            });
            
            container.classList.toggle('active');
            
            // Special handling for date filter
            if (icon.closest('th.col-date') && container.classList.contains('active') && window.flatpickrInstanceDateRange) {
                // Focus and open the date picker
                setTimeout(() => {
                    const dateInput = container.querySelector('#filter-date-range');
                    if (dateInput) {
                        dateInput.focus();
                        window.flatpickrInstanceDateRange.open();
                    }
                }, 50);
            } else if (container.classList.contains('active')) {
                container.querySelector('.filter-input')?.focus(); // Auto-focus regular input
            }
        });
    });

    textFilterInputs.forEach(input => {
        input.addEventListener('input', debouncedFilter);
        input.addEventListener('keydown', (event) => { 
            if (event.key === 'Enter') {
                filterTable(); // Filter immediately on Enter
            }
        });
    });

    // Close filter popups when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.filter-input-container') && 
            !event.target.matches('.filter-icon') &&
            !event.target.closest('.flatpickr-calendar')) {
            document.querySelectorAll('.filter-input-container.active').forEach(container => {
                container.classList.remove('active');
            });
            
            // Also close flatpickr if it's open
            if (window.flatpickrInstanceDateRange && window.flatpickrInstanceDateRange.isOpen) {
                window.flatpickrInstanceDateRange.close();
            }
        }
    });

    if(clearButton) {
        clearButton.addEventListener('click', () => {
            textFilterInputs.forEach(input => input.value = '');
            if (window.flatpickrInstanceDateRange) {
                window.flatpickrInstanceDateRange.clear(); // Clears selection and input
            }
            currentDateFilter.start = null; // Clear date state
            currentDateFilter.end = null;
            filterTable(); // Re-apply filters (which will show all rows)
            document.querySelectorAll('th.filter-active').forEach(th => th.classList.remove('filter-active')); // Remove active styling
            clearButton.style.display = 'none'; // Hide button
            document.querySelectorAll('.filter-input-container.active').forEach(c => c.classList.remove('active')); // Close popups
        });
    }
}
    
    // Setup date picker and financial year filter
    function setupDateFilters() {
        // Initialize Flatpickr for date range filtering if the element exists
        const dateRangeInput = document.getElementById('filter-date-range');
        if (dateRangeInput) {
            console.log("Initializing date picker...");
            try {
                window.flatpickrInstanceDateRange = flatpickr("#filter-date-range", {
                    mode: "range",
                    dateFormat: "Y-m-d", // ISO 8601 for easier comparison
                    altInput: true, // Human-readable format
                    altFormat: "j M Y",
                    onClose: function(selectedDates, dateStr, instance) {
                        console.log("Date picker closed with dates:", selectedDates);
                        // Update global state only when the picker is closed
                        if (selectedDates.length === 2) {
                            currentDateFilter.start = instance.formatDate(selectedDates[0], "Y-m-d");
                            currentDateFilter.end = instance.formatDate(selectedDates[1], "Y-m-d");
                        } else if (selectedDates.length === 1) { // Allow single date selection
                            currentDateFilter.start = instance.formatDate(selectedDates[0], "Y-m-d");
                            currentDateFilter.end = null; // Or set to start date if range required
                        } else {
                            currentDateFilter.start = null;
                            currentDateFilter.end = null;
                        }
                        console.log("Updated date filter state:", currentDateFilter);
                        
                        // Force filterTable to run immediately
                        filterTable();
                    }
                });
                console.log("Date picker initialized successfully");
            } catch (error) {
                console.error("Error initializing date picker:", error);
            }
        } else {
            console.error("Date range input element not found");
        }

        // Financial Year Filter Dropdown Logic
        const fySelect = document.getElementById('fy-filter-select');
        if (fySelect && window.flatpickrInstanceDateRange) {
            fySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const startDate = selectedOption.dataset.startDate;
                const endDate = selectedOption.dataset.endDate;
                const selectedValue = this.value;

                console.log("FY Handler - Selected Value:", selectedValue, "Start Date:", startDate, "End Date:", endDate);

                // Clear any active column filters when changing FY
                document.querySelectorAll('.filter-input').forEach(input => {
                    if (input.id !== 'filter-date-range') input.value = ''; // Clear text filters
                });
                document.querySelectorAll('.filter-input-container.active').forEach(container => {
                    container.classList.remove('active'); // Close filter popups
                });

                if (selectedValue === "all" || selectedValue === "") {
                    // Clear the Flatpickr instance and the date filter state
                    window.flatpickrInstanceDateRange.clear();
                    currentDateFilter.start = null;
                    currentDateFilter.end = null;
                } else if (startDate && endDate) {
                    // Set Flatpickr to the selected range
                    window.flatpickrInstanceDateRange.setDate([startDate, endDate], false);
                    // Update the date filter state
                    currentDateFilter.start = startDate;
                    currentDateFilter.end = endDate;
                } else {
                    // Fallback: Clear if dates are invalid/missing
                    window.flatpickrInstanceDateRange.clear();
                    currentDateFilter.start = null;
                    currentDateFilter.end = null;
                }

                // Apply the filter based on the new date range (and cleared text filters)
                filterTable();
            });
        }
    }
    
    // Setup category dropdown handling
    function setupCategoryDropdowns() {
        const allCategorySelects = document.querySelectorAll('.category-select');
        allCategorySelects.forEach(select => {
            // Store the initial selection when the page loads
            select.dataset.currentSelection = select.value;

            select.addEventListener('change', function() {
                const transactionId = this.dataset.id;
                const selectedValue = this.value;
                const originalSelection = this.dataset.currentSelection || '';

                if (selectedValue === "add_new") {
                    const newCategoryName = prompt("Enter new category name:");
                    if (newCategoryName && newCategoryName.trim() !== "") {
                        updateCategoryStatus(transactionId, 'Adding...', 'status-saving');
                        // AJAX call to add the category
                        const formData = new FormData();
                        formData.append('category_name', newCategoryName.trim());
                        fetch('add_category.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success' && data.id && data.name) {
                                    updateCategoryStatus(transactionId, 'Added!', 'status-success');
                                    // Add the new option to *all* category dropdowns dynamically
                                    const newOptionHTML = `<option value="${data.id}">${data.name}</option>`;
                                    allCategorySelects.forEach(s => {
                                        const addNewOpt = s.querySelector('option[value="add_new"]');
                                        if (addNewOpt) {
                                            addNewOpt.insertAdjacentHTML('beforebegin', newOptionHTML);
                                        }
                                    });
                                    // Select the newly added category in the current dropdown
                                    this.value = data.id;
                                    // Now save this selection for the transaction
                                    saveCategorySelection(transactionId, data.id, this);
                                } else {
                                    throw new Error(data.message || 'Failed to add category.');
                                }
                            })
                            .catch(error => {
                                console.error('Error adding category:', error);
                                updateCategoryStatus(transactionId, 'Error!', 'status-error');
                                showToast(`Error: ${error.message}`, 'error');
                                this.value = originalSelection; // Revert selection
                                setTimeout(() => updateCategoryStatus(transactionId, ''), 3000);
                            });
                    } else {
                        // User cancelled or entered empty name, revert selection
                        this.value = originalSelection;
                    }
                } else if (selectedValue !== originalSelection) {
                    // If a different existing category is chosen
                    saveCategorySelection(transactionId, selectedValue, this);
                }
            });
        });
    }
    
    // Setup invoice actions (view, upload, delete)
    function setupInvoiceActions() {
        const tableBody = document.getElementById('transaction-data');
        if (!tableBody) return;

        // Listener for clicks on action links within the table body
        tableBody.addEventListener('click', function(event) {
            const targetLink = event.target.closest('a'); // Find the nearest parent anchor tag
            if (!targetLink) return; // Exit if the click wasn't on or within an anchor

            const actionsCell = targetLink.closest('.invoice-actions');
            if (!actionsCell) return; // Exit if not within the invoice actions cell

            const transactionId = actionsCell.dataset.transactionId;
            const fileInput = actionsCell.querySelector('.hidden-invoice-input');

            // --- Attach Link ---
            if (targetLink.classList.contains('link-attach')) {
                event.preventDefault(); // Prevent default anchor behavior
                if (fileInput) {
                    fileInput.click(); // Trigger the hidden file input
                } else {
                    console.error("Hidden file input not found for attach action.");
                    showToast("Error: Could not find file input.", "error");
                }
            }

            // --- Delete Link ---
            else if (targetLink.classList.contains('link-delete')) {
                event.preventDefault(); // Prevent default anchor behavior
                if (confirm('Are you sure you want to delete this invoice?')) {
                    // Make sure we store the actionsCell reference correctly
                    const actionsCell = targetLink.closest('.invoice-actions');
                    const transactionId = actionsCell.dataset.transactionId;
                    const currentInvoiceRequired = actionsCell.dataset.invoiceRequired || '1';
                    const row = actionsCell.closest('tr');
                    const wasComplete = row.classList.contains('transaction-complete');
                    
                    updateInvoiceStatusSpan(transactionId, 'Deleting...', 'status-saving');
            
                    fetch('delete_invoice.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `transaction_id=${transactionId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            updateInvoiceStatusSpan(transactionId, ''); // Clear status
                            
                            // Replace View/Delete links with Attach link and checkbox
                            actionsCell.innerHTML = `
                                <div class="invoice-action-wrapper">
                                    <a href="#" class="link-attach" title="Attach Invoice">Attach</a>
                                    <div class="no-invoice-required">
                                        <label>
                                            <input type="checkbox" class="no-invoice-required-checkbox" data-id="${transactionId}" 
                                                ${currentInvoiceRequired === '0' ? 'checked' : ''}>
                                            <span>No invoice required</span>
                                        </label>
                                    </div>
                                </div>
                                <input type="file" name="invoice_file_${transactionId}" class="hidden-invoice-input" data-id="${transactionId}" accept=".webp,.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <span class="invoice-status"></span>
                            `;
                            
                            showToast('Invoice Deleted', 'success');
            
                            // Update completion status and stats
                            const isNowComplete = checkAndApplyRowCompletion(transactionId);
                            console.log(`After Delete - Row ${transactionId} complete: ${isNowComplete}, Was complete: ${wasComplete}`);
                            if (wasComplete && !isNowComplete) {
                                currentCompleteCount--;
                                updateStatsDisplay();
                            }
                        } else {
                            throw new Error(data.message || 'Deletion failed.');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting invoice:', error);
                        updateInvoiceStatusSpan(transactionId, 'Error!', 'status-error');
                        showToast(`Error deleting invoice: ${error.message}`, 'error', 5000);
                        setTimeout(() => updateInvoiceStatusSpan(transactionId, ''), 3000);
                    });
                }
            }
        });

        // Listener for file selection changes (Attach/Replace)
        tableBody.addEventListener('change', function(event) {
            if (event.target.classList.contains('hidden-invoice-input')) {
                const fileInput = event.target;
                const transactionId = fileInput.dataset.id;
                const file = fileInput.files[0];
                const actionsCell = fileInput.closest('.invoice-actions');
                const row = fileInput.closest('tr');
                const wasComplete = row.classList.contains('transaction-complete');

                if (file && transactionId && actionsCell) {
                    updateInvoiceStatusSpan(transactionId, 'Uploading...', 'status-saving');

                    const formData = new FormData();
                    formData.append('invoice_file', file);
                    formData.append('transaction_id', transactionId);

                    fetch('upload_invoice.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success' && data.filepath) {
                            updateInvoiceStatusSpan(transactionId, ''); // Clear status
                            // Update the links to View | Delete
                            actionsCell.innerHTML = `
                                <a href="view_invoice.php?tx_id=${transactionId}" target="_blank" class="link-view" title="View Invoice">View</a>
                                <span class="link-separator">|</span>
                                <a href="#" class="link-delete" title="Delete Invoice">Delete</a>
                                <input type="file" name="invoice_file_${transactionId}" class="hidden-invoice-input" data-id="${transactionId}" accept=".webp,.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <span class="invoice-status"></span>
                            `;
                            
                            showToast('Invoice Uploaded!', 'success');

                            // Update completion status and stats
                            const isNowComplete = checkAndApplyRowCompletion(transactionId);
                            console.log(`After Upload - Row ${transactionId} complete: ${isNowComplete}, Was complete: ${wasComplete}`);
                            if (isNowComplete && !wasComplete) {
                                currentCompleteCount++;
                                updateStatsDisplay();
                            } else if (!isNowComplete && wasComplete) {
                                currentCompleteCount--;
                                updateStatsDisplay();
                            } else {
                                // If status didn't change (e.g., replacing an existing invoice)
                                updateStatsDisplay();
                            }
                        } else {
                            throw new Error(data.message || 'Upload failed.');
                        }
                    })
                    .catch(error => {
                        console.error('Error uploading invoice:', error);
                        updateInvoiceStatusSpan(transactionId, 'Upload Error!', 'status-error');
                        showToast(`Upload Error: ${error.message}`, 'error', 5000);
                        setTimeout(() => updateInvoiceStatusSpan(transactionId, ''), 3000);
                    })
                    .finally(() => {
                        // Reset the file input value so the user can select the same file again if needed
                        fileInput.value = '';
                    });
                } else if (!file) {
                    updateInvoiceStatusSpan(transactionId, ''); // Clear status if no file selected
                }
            }
        });
    }
    
    // Setup "No invoice required" checkbox functionality
    function setupInvoiceRequiredCheckbox() {
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('no-invoice-required-checkbox')) {
                const checkbox = event.target;
                const transactionId = checkbox.dataset.id;
    
                if (!transactionId) {
                    console.error("No transaction ID found on checkbox:", checkbox);
                    return;
                }
    
                console.log("'No invoice required' checkbox changed for transaction:", transactionId, "New value:", checkbox.checked);
    
                const row = checkbox.closest('tr');
                const actionsCell = checkbox.closest('.invoice-actions');
                const wasComplete = row.classList.contains('transaction-complete');
                const invoiceRequired = !checkbox.checked;
    
                updateInvoiceStatusSpan(transactionId, 'Saving...', 'status-saving');
    
                const saveUrl = (window.location.href.indexOf('/index.php') !== -1)
                    ? 'save_invoice_required.php'
                    : window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1) + 'save_invoice_required.php';
    
                fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transaction_id: transactionId, invoice_required: invoiceRequired })
                })
                .then(response => {
                    if (!response.ok) throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        updateInvoiceStatusSpan(transactionId, 'Saved!', 'status-success');
                        setTimeout(() => updateInvoiceStatusSpan(transactionId, ''), 2000);
    
                        // Update the data attribute on the cell to reflect the new state
                        if (actionsCell) {
                            actionsCell.dataset.invoiceRequired = invoiceRequired ? '1' : '0';
                        }
    
                        // Update completion status and stats
                        const isNowComplete = checkAndApplyRowCompletion(transactionId);
                        if (isNowComplete && !wasComplete) {
                            currentCompleteCount++;
                            updateStatsDisplay();
                        } else if (!isNowComplete && wasComplete) {
                            currentCompleteCount--;
                            updateStatsDisplay();
                        }
    
                        showToast('Invoice requirement saved!', 'success');
                    } else {
                        throw new Error(data.message || 'Save failed');
                    }
                })
                .catch(error => {
                    console.error('Error saving invoice requirement:', error);
                    updateInvoiceStatusSpan(transactionId, 'Error!', 'status-error');
                    checkbox.checked = !checkbox.checked;
                    showToast(`Error: ${error.message}`, 'error', 5000);
                    setTimeout(() => updateInvoiceStatusSpan(transactionId, ''), 3000);
                });
            }
        });
    }

    // --- RESIZABLE COLUMNS ---
    
    function initResizableColumns() {
        const table = document.getElementById('transactions-table');
        if (!table) return;
        
        // Make sure the table has the right class
        table.classList.add('resizable-table');
        
        // Ensure we have the CSS for resizable columns
        if (!document.getElementById('resizable-columns-css')) {
            const style = document.createElement('style');
            style.id = 'resizable-columns-css';
            style.textContent = `
                /* Main table positioning */
                .resizable-table {
                    table-layout: fixed !important;
                    width: 100% !important;
                    border-collapse: collapse !important;
                }
                
                /* Header cell positioning */
                .resizable-table th {
                    position: relative !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                    white-space: nowrap !important;
                    padding-right: 15px !important; /* Space for resizer */
                }
                
                /* Resizer styling and positioning */
                .resizer {
                    position: absolute !important;
                    top: 0 !important;
                    right: 0 !important;
                    width: 8px !important;
                    height: 100% !important;
                    background-color: rgba(0, 0, 0, 0.05) !important;
                    cursor: col-resize !important;
                    z-index: 10 !important;
                    transition: background-color 0.15s ease !important;
                }
                
                /* Better hover and active states */
                .resizer:hover, .dragging .resizer {
                    background-color: rgba(0, 123, 255, 0.5) !important;
                }
                
                /* Active dragging states */
                .dragging {
                    cursor: col-resize !important;
                    user-select: none !important;
                }
                
                /* Apply dragging state to entire table */
                .resizable-table.dragging th,
                .resizable-table.dragging td {
                    border-right: 1px dashed rgba(0, 123, 255, 0.5) !important;
                }
                
                /* Allow very narrow columns */
                .resizable-table th.narrow, 
                .resizable-table td.narrow {
                    min-width: 20px !important;
                    max-width: 50px !important;
                }
                
                /* Disable text selection during resize */
                .user-select-none {
                    user-select: none !important;
                    -webkit-user-select: none !important;
                    -moz-user-select: none !important;
                    -ms-user-select: none !important;
                }
            `;
            document.head.appendChild(style);
        }
        
        // Get table headers
        const headers = table.querySelectorAll('thead th');
        
        // Get any saved column widths
        const savedWidths = (typeof window.savedColumnWidths !== 'undefined' && window.savedColumnWidths) 
            ? window.savedColumnWidths 
            : {};
        
        // Default column widths as percentages (fallback if no saved widths)
        const defaultColumnWidths = {
            'col-date': '9%',
            'col-txid': '13%',
            'col-desc': '23%',
            'col-category': '12%',
            'col-paid-in': '7%',
            'col-paid-out': '7%',
            'col-invoice': '12%',
            'col-comments': '11%'
        };
        
        // Apply the column widths first
        headers.forEach((header) => {
            const columnClass = header.classList[0];
            
            if (columnClass && savedWidths[columnClass]) {
                // Apply saved width
                header.style.width = savedWidths[columnClass];
            } else if (columnClass && defaultColumnWidths[columnClass]) {
                // Apply default width
                header.style.width = defaultColumnWidths[columnClass];
            }
        });
        
        // Remove any existing resizers to avoid duplicates
        document.querySelectorAll('.resizer').forEach(r => r.remove());
        
        // Add resizers to all headers except last one
        headers.forEach((header, index) => {
            if (index < headers.length - 1) {
                // Create resizer element
                const resizer = document.createElement('div');
                resizer.classList.add('resizer');
                header.appendChild(resizer);
                
                // State variables
                let startX, startWidth, tableWidth;
                
                // Mouse down handler - start resize
                const mouseDownHandler = function(e) {
                    // Prevent default behavior
                    e.preventDefault();
                    
                    // Add dragging class to table and header
                    table.classList.add('dragging');
                    header.classList.add('dragging');
                    
                    // Get the current mouse position and element width
                    startX = e.clientX;
                    startWidth = header.getBoundingClientRect().width;
                    tableWidth = table.getBoundingClientRect().width;
                    
                    // Add the mousemove and mouseup event listeners
                    document.addEventListener('mousemove', mouseMoveHandler);
                    document.addEventListener('mouseup', mouseUpHandler);
                    
                    // Add class to prevent text selection during resizing
                    document.body.classList.add('user-select-none');
                };
                
                // Mouse move handler - perform resize
                const mouseMoveHandler = function(e) {
                    // Calculate the new width
                    const width = startWidth + (e.clientX - startX);
                    
                    // Enforce minimum width of 20px
                    if (width >= 20) {
                        // Calculate and set percentage width for better responsiveness
                        const widthPercentage = (width / tableWidth) * 100;
                        header.style.width = `${widthPercentage}%`;
                        
                        // Add or remove narrow class based on width
                        if (width < 50) {
                            header.classList.add('narrow');
                        } else {
                            header.classList.remove('narrow');
                        }
                    }
                };
                
                // Mouse up handler - stop resize
                const mouseUpHandler = function() {
                    // Remove the dragging classes
                    table.classList.remove('dragging');
                    header.classList.remove('dragging');
                    
                    // Remove the event listeners
                    document.removeEventListener('mousemove', mouseMoveHandler);
                    document.removeEventListener('mouseup', mouseUpHandler);
                    
                    // Remove text selection prevention
                    document.body.classList.remove('user-select-none');
                    
                    // Save the column widths
                    saveColumnWidths();
                };
                
                // Add the mousedown event listener to the resizer
                resizer.addEventListener('mousedown', mouseDownHandler);
            }
        });
        
        console.log('Improved resizable columns initialized');
    }

    // Function to load saved column widths
    function loadSavedColumnWidths() {
        console.log("Loading saved column widths...");
        
        // Flag to track if we've loaded from server
        window.hasColumnWidthEndpoint = true; // Set this to true to enable server-side saving
        
        // First try to load from localStorage as a fallback
        try {
            const localWidths = localStorage.getItem('tideBooks_columnWidths');
            if (localWidths) {
                window.savedColumnWidths = JSON.parse(localWidths);
                console.log("Column widths loaded from localStorage:", window.savedColumnWidths);
            }
        } catch (e) {
            console.error("Error loading column widths from localStorage:", e);
        }
        
        // Then try to load from server (this will override localStorage if successful)
        fetch('get_column_widths.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.column_widths) {
                    window.savedColumnWidths = data.column_widths;
                    console.log("Column widths loaded from server:", window.savedColumnWidths);
                    
                    // Also update localStorage
                    try {
                        localStorage.setItem('tideBooks_columnWidths', JSON.stringify(data.column_widths));
                    } catch (e) {
                        console.error("Error saving to localStorage:", e);
                    }
                    
                    // Apply the widths immediately if the table exists
                    applyColumnWidths();
                }
            })
            .catch(error => {
                console.warn("Could not load column widths from server:", error);
                // Continue with localStorage values if server fails
            });
    }
    
    // Function to apply column widths to the table
    function applyColumnWidths() {
        if (!window.savedColumnWidths) return;
        
        const headers = document.querySelectorAll('#transactions-table thead th');
        headers.forEach(header => {
            const columnClass = header.classList[0];
            if (columnClass && window.savedColumnWidths[columnClass]) {
                header.style.width = window.savedColumnWidths[columnClass];
            }
        });
    }
    
    // Function to save column widths
    function saveColumnWidths() {
        const headers = document.querySelectorAll('#transactions-table thead th');
        const widths = {};
        
        // Collect current widths
        headers.forEach(header => {
            const columnClass = header.classList[0];
            if (columnClass) {
                widths[columnClass] = header.style.width;
            }
        });
        
        // Store widths in memory
        window.savedColumnWidths = widths;
        
        // Save to localStorage as a backup
        try {
            localStorage.setItem('tideBooks_columnWidths', JSON.stringify(widths));
        } catch (e) {
            console.error("Error saving to localStorage:", e);
        }
        
        // Save to server if endpoint exists
        if (typeof window.hasColumnWidthEndpoint !== 'undefined' && window.hasColumnWidthEndpoint) {
            fetch('save_column_widths.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ column_widths: widths })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Column widths saved to server');
                }
            })
            .catch(error => {
                console.error('Error saving column widths:', error);
            });
        }
    }
    
    // Fix for date filter icon
    function setupDateFilterFix() {
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
    }
    
    // --- INITIALIZATION ---
    
    // Main initialization function - all setup happens here
    function initializeDashboard() {
        
        // Setup core functionality
        setupCommentAutoSave();
        setupFilters();  // This now handles all filters including the date filter
        setupDateFilters();
        setupCategoryDropdowns();
        setupInvoiceActions();
        setupInvoiceRequiredCheckbox();
        initResizableColumns();
        
        // Initial UI state
        setTimeout(addTooltipsToTruncatedCells, 500);
        filterTable();
        updateTotals();
        updateStatsDisplay();
        
        // Re-check tooltips after window resize
        window.addEventListener('resize', debounce(function() {
            addTooltipsToTruncatedCells();
            
            // Re-apply column widths if they were changed
            applyColumnWidths();
        }, 200));
        
        // Log the current state for debugging
        console.log("Dashboard initialized with:", {
            "Date picker available": !!window.flatpickrInstanceDateRange,
            "Transaction count": currentTotalCount,
            "Complete count": currentCompleteCount
        });
        
    }
    
    // Initialize the dashboard
    initializeDashboard();
});