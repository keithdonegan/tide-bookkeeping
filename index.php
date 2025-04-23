<?php
session_start(); // Start session to handle flash messages

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST THIS LINE AS NEEDED

// Initialize variables
$transactions = [];
$total_count = 0;
$invoice_count = 0;
$needs_invoice_count = 0;
$percentage_complete = 0;

// Flash messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
$warning_message = $_SESSION['warning_message'] ?? null;
$warning_details = $_SESSION['warning_details'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['warning_message'], $_SESSION['warning_details']);

// Fetch transactions and calculate stats only if PDO connection was successful
if (isset($pdo)) {
    try {
        // Fetch all relevant data
        $stmt = $pdo->query('SELECT id, transaction_id, transaction_date, description, paid_in, paid_out, invoice_path, comments
                             FROM transactions
                             ORDER BY transaction_date DESC, id DESC');
        $transactions = $stmt->fetchAll();

        // Calculate Statistics
        $total_count = count($transactions);
        $invoice_count = 0;
        if ($total_count > 0) {
            foreach ($transactions as $tx) {
                if (!empty($tx['invoice_path'])) {
                    $invoice_count++;
                }
            }
            $needs_invoice_count = $total_count - $invoice_count;
            $percentage_complete = round(($invoice_count / $total_count) * 100);
        }

    } catch (\PDOException $e) {
        $error_message = "Error fetching transactions: " . $e->getCode();
        error_log("[Index Page DB Error] PDOException fetching transactions: " . $e->getMessage());
    }
} else {
    $error_message = "Database connection is not available.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statement Manager</title>
    <style>
        /* --- Basic Styling --- */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 20px; font-size: 14px; line-height: 1.5; background-color: #f8f9fa; color: #212529;}
        h1, h2 { color: #343a40; }
        hr { border: 0; height: 1px; background-color: #dee2e6; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #dee2e6; padding: 10px 12px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #e9ecef; font-weight: 600; position: relative; } /* TH needs position relative */
        thead th { position: sticky; top: 0; z-index: 1; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .upload-form { margin-bottom: 20px; padding: 20px; border: 1px solid #dee2e6; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .message { padding: 12px 18px; margin-bottom: 15px; border: 1px solid; border-radius: 4px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        .debug-details pre { max-height: 150px; overflow-y: auto; border: 1px solid #ffeeba; padding: 8px; margin-top: 8px; background-color: #fff; font-size: 0.85em; line-height: 1.4; }
        form { margin-bottom: 0; }
        td form { display: block; margin-top: 5px; }
        td textarea.comment-textarea { width: 100%; max-width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.95em; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; resize: vertical; min-height: 50px; transition: background-color 0.3s ease; }
        td textarea.comment-changed { background-color: #fff3cd; border-color: #ffeeba; }
        td input[type="file"] { font-size: 0.9em; margin-bottom: 5px; display: block; max-width: 180px; }
        button, input[type="submit"], button.save-all-btn { padding: 8px 15px; font-size: 0.95em; border-radius: 3px; cursor: pointer; border: 1px solid transparent; }
        button[type="submit"], button.save-all-btn { background-color: #007bff; color: white; border-color: #007bff; }
        button[type="submit"]:hover, button.save-all-btn:hover { background-color: #0056b3; border-color: #0056b3; }
        button.save-all-btn:disabled { background-color: #6c757d; border-color: #6c757d; cursor: not-allowed; }
        .actions-cell form button { margin-top: 3px; font-size: 0.85em; padding: 4px 8px;}
        .stats-area { background-color: #e9ecef; padding: 10px 15px; margin-bottom: 20px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95em; color: #495057; }
        .stats-area span { margin-right: 20px; }
        .stats-area strong { color: #343a40; }
        /* Column Widths */
        .col-date { width: 10%; }
        .col-txid { width: 15%; }
        .col-desc { width: 25%; }
        .col-paid-in, .col-paid-out { width: 8%; text-align: right; }
        .col-invoice { width: 17%; }
        .col-comments { width: 17%; }
        /* Amount Colors */
        .paid-in { color: #28a745; font-weight: 500; }
        .paid-out { color: #dc3545; font-weight: 500; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .txid-style { font-family: monospace; font-size: 0.9em; color: #6c757d; }
        .save-button-container { margin-bottom: 15px; text-align: right; }
        .save-button-container span { margin-left: 10px; font-weight: bold; }
        .status-saving { color: #ffc107; }
        .status-success { color: #28a745; }
        .status-error { color: #dc3545; }
        /* --- Filter Styles --- */
        .filter-icon { font-size: 0.8em; margin-left: 5px; cursor: pointer; color: #6c757d; display: inline-block; transition: color 0.2s ease; }
        .filter-icon:hover { color: #007bff; }
        th.filter-active .filter-icon { color: #fd7e14; /* Orange when active */ } /* Style icon when filter is active */
        /* OR Style the TH background when active: */
        /* th.filter-active { background-color: #ffeeba !important; } */

        .filter-input-container { display: none; position: absolute; top: 100%; left: 0; width: 98%; min-width: 150px; /* Min width */ padding: 8px; background-color: #f8f9fa; border: 1px solid #ccc; box-shadow: 0 2px 5px rgba(0,0,0,0.15); z-index: 10; box-sizing: border-box; }
        .filter-input-container.active { display: block; }
        .filter-input { width: 100%; padding: 5px 8px; font-size: 0.95em; border: 1px solid #ced4da; border-radius: 3px; box-sizing: border-box; }
        .filter-input-container label { font-size: 0.85em; display: block; margin-bottom: 3px; font-weight: normal; color: #495057;}
        .filter-input-container input[type="date"] { margin-bottom: 5px; } /* Space between date inputs */
        #clear-filters-btn { margin: 5px 0 10px 0; padding: 4px 8px; font-size: 0.85em; cursor: pointer; background-color: #6c757d; color: white; border: 1px solid #6c757d; border-radius: 3px;}
        #clear-filters-btn:hover { background-color: #5a6268; border-color: #545b62; }
    </style>
</head>
<body>
    <h1>Bank Statement Manager</h1>

    <?php /* ... Flash message display ... */ ?>
    <?php if ($success_message): ?> <div class="message success"><?php echo htmlspecialchars($success_message); ?></div> <?php endif; ?>
    <?php if ($error_message): ?> <div class="message error"><?php echo htmlspecialchars($error_message); ?></div> <?php endif; ?>
    <?php if ($warning_message): ?> <div class="message warning"> <?php /* ... warning display ... */ ?> </div> <?php endif; ?>


    <div class="upload-form">
        <h2>Upload Bank Statement CSV</h2>
        <form action="upload_csv.php" method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
            <button type="submit">Upload CSV</button>
        </form>
        <p><small>Note: Uses Transaction ID to prevent duplicates.</small></p>
    </div>
    <hr>

    <h2>Transactions</h2>

    <div class="stats-area">
        <span>Total: <strong><?php echo number_format($total_count); ?></strong></span>
        <span>Invoices: <strong><?php echo number_format($invoice_count); ?> / <?php echo number_format($total_count); ?></strong> (<?php echo $percentage_complete; ?>%)</span>
        <span>Needs Invoice: <strong><?php echo number_format($needs_invoice_count); ?></strong></span>
    </div>

    <div class="save-button-container">
        <span id="save-status"></span>
        <button type="button" id="save-all-comments-btn" class="save-all-btn" disabled>Save Changed Comments</button>
    </div>

    <div>
        <button type="button" id="clear-filters-btn">Clear All Filters</button>
     </div>

    <table id="transactions-table">
        <thead>
            <tr>
                <th class="col-date">
                    Date <span class="filter-icon" title="Filter by Date Range">📅</span>
                    <div class="filter-input-container">
                        <label for="filter-date-start">From:</label>
                        <input type="date" id="filter-date-start" class="filter-input date-range-filter" data-column-index="0" data-range-type="start">
                        <label for="filter-date-end">To:</label>
                        <input type="date" id="filter-date-end" class="filter-input date-range-filter" data-column-index="0" data-range-type="end">
                    </div>
                </th>
                <th class="col-txid">
                    Transaction ID <span class="filter-icon" title="Filter by Transaction ID">🔍</span>
                    <div class="filter-input-container">
                         <input type="text" class="filter-input" data-column-index="1" placeholder="Filter ID...">
                    </div>
                </th>
                <th class="col-desc">
                    Description <span class="filter-icon" title="Filter by Description">🔍</span>
                     <div class="filter-input-container">
                         <input type="text" class="filter-input" data-column-index="2" placeholder="Filter Desc...">
                     </div>
                </th>
                <th class="col-paid-in">
                    Paid In <span class="filter-icon" title="Filter by Amount Paid In">🔍</span>
                     <div class="filter-input-container">
                         <input type="text" class="filter-input" data-column-index="3" placeholder="Filter Paid In...">
                     </div>
                </th>
                <th class="col-paid-out">
                    Paid Out <span class="filter-icon" title="Filter by Amount Paid Out">🔍</span>
                     <div class="filter-input-container">
                         <input type="text" class="filter-input" data-column-index="4" placeholder="Filter Paid Out...">
                     </div>
                </th>
                <th class="col-invoice">Invoice </th>
                <th class="col-comments">
                    Comments <span class="filter-icon" title="Filter by Comments">🔍</span>
                    <div class="filter-input-container">
                         <input type="text" class="filter-input" data-column-index="6" placeholder="Filter Comments...">
                     </div>
                 </th>
            </tr>
            </thead>
        <tbody id="transaction-data"> <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td data-date="<?php echo htmlspecialchars($tx['transaction_date']); ?>">
                        <?php echo htmlspecialchars(date('d/m/Y', strtotime($tx['transaction_date']))); ?>
                    </td>
                    <td><span class="txid-style"><?php echo htmlspecialchars($tx['transaction_id']); ?></span></td>
                    <td><?php echo htmlspecialchars($tx['description']); ?></td>
                    <td class="paid-in">
                        <?php if (!empty($tx['paid_in']) && (float)$tx['paid_in'] != 0) { echo number_format($tx['paid_in'], 2); } ?>
                    </td>
                    <td class="paid-out">
                         <?php if (!empty($tx['paid_out']) && (float)$tx['paid_out'] != 0) { echo number_format($tx['paid_out'], 2); } ?>
                    </td>
                    <td class="actions-cell"> <?php /* ... Invoice display/attach form ... */ ?>
                        <?php if (!empty($tx['invoice_path'])): ?>
                            <a href="view_invoice.php?tx_id=<?php echo $tx['id']; ?>" target="_blank">View Invoice</a><br>
                            <form action="upload_invoice.php" method="post" enctype="multipart/form-data" style="margin-top: 5px;"><input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>"><label for="invoice_file_<?php echo $tx['id']; ?>" style="font-size:0.85em; display: block; margin-bottom: 3px;">Replace:</label><input type="file" id="invoice_file_<?php echo $tx['id']; ?>" name="invoice_file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.txt,.csv,.xls,.xlsx" required><button type="submit">Upload</button></form>
                        <?php else: ?>
                            <form action="upload_invoice.php" method="post" enctype="multipart/form-data"><input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>"><label for="invoice_file_<?php echo $tx['id']; ?>" style="font-size:0.85em; display: block; margin-bottom: 3px;">Attach Invoice:</label><input type="file" id="invoice_file_<?php echo $tx['id']; ?>" name="invoice_file" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.txt,.csv,.xls,.xlsx" required><button type="submit">Attach</button></form>
                        <?php endif; ?>
                    </td>
                    <td class="actions-cell"> <textarea class="comment-textarea" data-id="<?php echo $tx['id']; ?>" data-original-value="<?php echo htmlspecialchars($tx['comments'] ?? ''); ?>" rows="3" placeholder="Add comment..."><?php echo htmlspecialchars($tx['comments'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align: center; padding: 20px;">No transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="save-button-container" style="margin-top: 15px;"> </div>

    <script>
        // --- Debounce function ---
        function debounce(func, wait) { /* ... same as before ... */ let timeout; return function executedFunction(...args) { const later = () => { clearTimeout(timeout); func(...args); }; clearTimeout(timeout); timeout = setTimeout(later, wait); }; }

        // --- Filter Table Function (UPDATED for Date Range and Active Filter Style) ---
        function filterTable() {
            const dateFilterStartInput = document.getElementById('filter-date-start');
            const dateFilterEndInput = document.getElementById('filter-date-end');
            const otherFilterInputs = document.querySelectorAll('.filter-input:not(.date-range-filter)'); // Select non-date filters
            const tableBody = document.getElementById('transaction-data');
            const rows = tableBody.getElementsByTagName('tr');

            // Get filter values
            const dateFilterStart = dateFilterStartInput.value; // YYYY-MM-DD
            const dateFilterEnd = dateFilterEndInput.value;     // YYYY-MM-DD
            const textFilters = {}; // { colIndex: value }
            otherFilterInputs.forEach(input => {
                if (input.value.trim() !== '') {
                    textFilters[input.dataset.columnIndex] = input.value.trim().toLowerCase();
                }
            });
            const textFilterKeys = Object.keys(textFilters);

            // --- Update Active Filter Visual Indicators ---
            document.querySelectorAll('thead th').forEach(th => {
                const icon = th.querySelector('.filter-icon');
                if (!icon) return; // Skip headers without icons

                const filterInputContainer = th.querySelector('.filter-input-container');
                let isActive = false;
                if(th.classList.contains('col-date')) { // Special check for date range
                     isActive = dateFilterStart || dateFilterEnd; // Active if either date is set
                } else {
                    const input = filterInputContainer.querySelector('.filter-input');
                    if (input) {
                         isActive = input.value.trim() !== '';
                    }
                }

                if (isActive) {
                    th.classList.add('filter-active'); // Add class to TH (can style TH or icon)
                } else {
                    th.classList.remove('filter-active');
                }
            });
            // --- End Update Visual Indicators ---


            // Loop through rows and apply filters
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let display = true;

                // 1. Apply Date Range Filter
                const rowDate = cells[0] ? cells[0].dataset.date : null; // Get YYYY-MM-DD from data-date
                if (rowDate) {
                    if (dateFilterStart && rowDate < dateFilterStart) {
                        display = false;
                    }
                    if (display && dateFilterEnd && rowDate > dateFilterEnd) { // Only check end if start passed
                        display = false;
                    }
                } else if (dateFilterStart || dateFilterEnd) {
                     display = false; // Hide if date filter active but row has no date
                }

                // 2. Apply Text Filters (only if row is still visible)
                if (display) {
                    for (const colIndex of textFilterKeys) {
                        const cell = cells[colIndex];
                        if (cell) {
                            let cellValue = '';
                            if (colIndex == 6) { // Comments column
                                const textarea = cell.querySelector('.comment-textarea');
                                if (textarea) { cellValue = textarea.value.toLowerCase(); }
                            } else { // Other text columns
                                cellValue = (cell.textContent || cell.innerText || "").toLowerCase();
                            }
                            if (!cellValue.includes(textFilters[colIndex])) {
                                display = false; break; // Hide if any text filter doesn't match
                            }
                        } else { display = false; break; } // Hide if cell missing
                    }
                }

                row.style.display = display ? '' : 'none'; // Show/hide row
            } // end row loop
        } // end filterTable

        document.addEventListener('DOMContentLoaded', function() {
            // --- Comment Saving Logic (Unchanged) ---
            /* ... Keep the exact same comment saving JS from the previous version ... */
            const textareas = document.querySelectorAll('.comment-textarea'); const saveButtons = document.querySelectorAll('.save-all-btn'); const statusSpans = document.querySelectorAll('#save-status, #save-status-bottom'); let changedComments = {}; const updateSaveState = (message = '', statusClass = '') => { const hasChanges = Object.keys(changedComments).length > 0; saveButtons.forEach(button => { button.disabled = !hasChanges; }); statusSpans.forEach(span => { span.textContent = message; span.className = statusClass; }); }; textareas.forEach(textarea => { textarea.addEventListener('input', function() { const transactionId = this.dataset.id; const originalValue = this.dataset.originalValue; const currentValue = this.value; if (currentValue !== originalValue) { changedComments[transactionId] = currentValue; this.classList.add('comment-changed'); } else { delete changedComments[transactionId]; this.classList.remove('comment-changed'); } updateSaveState(); }); }); saveButtons.forEach(button => { button.addEventListener('click', function() { if (Object.keys(changedComments).length === 0) { updateSaveState('No changes to save.', ''); return; } saveButtons.forEach(btn => btn.disabled = true); updateSaveState('Saving...', 'status-saving'); fetch('save_all_comments.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(changedComments) }).then(response => { if (!response.ok) { return response.json().catch(() => null).then(errorData => { throw new Error(errorData?.message || `HTTP error! Status: ${response.status}`); }); } return response.json(); }).then(data => { if (data.status === 'success') { let message = `Saved ${data.updated_count || 0} comments.`; updateSaveState(message, 'status-success'); Object.keys(changedComments).forEach(id => { const savedTextarea = document.querySelector(`.comment-textarea[data-id='${id}']`); if (savedTextarea) { savedTextarea.dataset.originalValue = changedComments[id]; savedTextarea.classList.remove('comment-changed'); } }); changedComments = {}; updateSaveState(message, 'status-success'); setTimeout(() => updateSaveState(), 3500); } else { throw new Error(data.message || 'Unknown error during save.'); } }).catch(error => { console.error('Save error:', error); updateSaveState(`Error: ${error.message}`, 'status-error'); saveButtons.forEach(btn => btn.disabled = (Object.keys(changedComments).length === 0)); }); }); });


             // --- Filter Setup (Updated) ---
             const filterIcons = document.querySelectorAll('.filter-icon');
             const allFilterInputs = document.querySelectorAll('.filter-input'); // Includes date inputs now
             const debouncedFilter = debounce(filterTable, 350); // Slightly longer debounce

             // Toggle filter input visibility on icon click
             filterIcons.forEach(icon => {
                 icon.addEventListener('click', function(event) {
                     event.stopPropagation();
                     const container = this.nextElementSibling;
                     if (container && container.classList.contains('filter-input-container')) {
                         // Hide others first
                         document.querySelectorAll('.filter-input-container.active').forEach(openContainer => {
                             if (openContainer !== container) { openContainer.classList.remove('active'); }
                         });
                         container.classList.toggle('active');
                         if (container.classList.contains('active')) {
                             container.querySelector('.filter-input').focus(); // Focus first input
                         }
                     }
                 });
             });

             // Apply filter when typing/changing any filter input
             allFilterInputs.forEach(input => {
                 input.addEventListener('input', debouncedFilter);
                 // Prevent clicks inside container from closing it immediately
                 input.parentElement.addEventListener('click', (event) => event.stopPropagation() );
             });

             // Close filter popups if clicking outside
             document.addEventListener('click', function(event) {
                let clickedInsideFilter = event.target.closest('.filter-input-container') || event.target.closest('.filter-icon');
                 if (!clickedInsideFilter) {
                     document.querySelectorAll('.filter-input-container.active').forEach(container => {
                         container.classList.remove('active');
                     });
                 }
             });

             // Clear button functionality
             const clearButton = document.getElementById('clear-filters-btn');
             if(clearButton) {
                 clearButton.addEventListener('click', () => {
                     allFilterInputs.forEach(input => input.value = ''); // Clear inputs
                     document.querySelectorAll('.filter-input-container.active').forEach(c => c.classList.remove('active')); // Hide inputs
                     filterTable(); // Re-apply (shows all rows and clears active styles)
                 });
             }
             // --- End Filter Setup ---

        }); // End DOMContentLoaded
    </script>
    </body>
</html>