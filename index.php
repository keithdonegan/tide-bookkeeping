<?php
session_start(); // Start the session first

// Check if the user ID session variable is NOT set
if (!isset($_SESSION['user_id'])) {
    // If not set, redirect to the login page
    header('Location: login.php');
    exit; // Stop script execution immediately
}

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST THIS LINE AS NEEDED

// --- PHP: Initialize variables ---
$transactions = [];
$categories = [];
$total_count = 0;
$complete_count = 0;
$needs_completion_count = 0;
$percentage_complete = 0;
$min_date = null; // For overall date range
$max_date = null; // For overall date range
$financial_years = []; // For preset filters

// --- PHP: Flash messages ---
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
$warning_message = $_SESSION['warning_message'] ?? null;
$warning_details = $_SESSION['warning_details'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['warning_message'], $_SESSION['warning_details']);

// --- PHP: Fetch data & Calculate Stats ---
if (isset($pdo)) {
    try {
        // 1. Fetch Categories
        $stmt_cat = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
        $categories = $stmt_cat->fetchAll();

        // 2. Fetch Min/Max Dates
        $stmt_dates = $pdo->query('SELECT MIN(transaction_date) AS min_date, MAX(transaction_date) AS max_date FROM transactions');
        $date_result = $stmt_dates->fetch();
        if ($date_result) {
            $min_date = $date_result['min_date'];
            $max_date = $date_result['max_date'];
        }

        // 3. Calculate Financial Year Presets
        $incorporation_year = 2020;
        $incorporation_month_day = '06-25'; // MM-DD
        $current_year = date('Y');
        $current_month_day = date('m-d');
        $current_fy_end_year = ($current_month_day < $incorporation_month_day) ? $current_year : $current_year + 1;
        for ($year = $incorporation_year; $year < $current_fy_end_year; $year++) {
             $start_date = $year . '-' . $incorporation_month_day;
             $end_year = $year + 1;
             $end_date_obj = date_create($end_year . '-' . $incorporation_month_day);
             date_sub($end_date_obj, date_interval_create_from_date_string("1 day"));
             $end_date = date_format($end_date_obj, 'Y-m-d');
             $financial_years[] = ['label' => "FY ".substr($year,-2)."/".substr($end_year,-2), 'start' => $start_date, 'end' => $end_date];
        }

        // 4. Fetch Transactions
        $stmt_tx = $pdo->query('SELECT id, transaction_id, transaction_date, description, paid_in, paid_out, category_id, invoice_path, comments
                             FROM transactions
                             ORDER BY transaction_date DESC, id DESC');
        $transactions = $stmt_tx->fetchAll();

        // 5. Calculate Completion Statistics
        $total_count = count($transactions);
        $complete_count = 0;
        if ($total_count > 0) {
            foreach ($transactions as $tx) {
                if (!empty($tx['category_id']) && !empty($tx['invoice_path'])) { $complete_count++; }
            }
            $needs_completion_count = $total_count - $complete_count;
            $percentage_complete = ($total_count > 0) ? round(($complete_count / $total_count) * 100) : 0;
        }

    } catch (\PDOException $e) {
        $error_message = "Error fetching data: " . $e->getCode();
        error_log("[Index Page DB Error] PDOException fetching data: " . $e->getMessage());
    }
} else {
    $error_message = "Database connection is not available.";
}

// Helper function for date formatting
function format_nice_date($date_string) {
    if (empty($date_string)) return 'N/A';
    $timestamp = strtotime($date_string);
    return ($timestamp === false) ? 'Invalid Date' : date('jS F Y', $timestamp);
}
// --- End PHP Setup ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statement Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* --- CSS Styles (Includes ALL styles from previous versions + FY Filter/Date Range Display) --- */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 20px; font-size: 14px; line-height: 1.5; background-color: #f8f9fa; color: #212529;}
        h1, h2 { color: #343a40; margin-bottom: 0.8rem;}
        hr { border: 0; height: 1px; background-color: #dee2e6; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #dee2e6; padding: 10px 12px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #e9ecef; font-weight: 600; position: relative; }
        thead th { position: sticky; top: 0; z-index: 1; background-color: #e9ecef; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .upload-form { margin-bottom: 20px; padding: 20px; border: 1px solid #dee2e6; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .message { padding: 12px 18px; margin-bottom: 15px; border: 1px solid; border-radius: 4px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; } .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; } .warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        .debug-details pre { max-height: 150px; overflow-y: auto; border: 1px solid #ffeeba; padding: 8px; margin-top: 8px; background-color: #fff; font-size: 0.85em; line-height: 1.4; }
        form { margin-bottom: 0; }
        td textarea.comment-textarea { width: 100%; max-width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.95em; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; resize: vertical; min-height: 50px; transition: background-color 0.3s ease; }
        button, input[type="submit"] { padding: 8px 15px; font-size: 0.95em; border-radius: 3px; cursor: pointer; border: 1px solid transparent; }
        button[type="submit"] { background-color: #007bff; color: white; border-color: #007bff; } button[type="submit"]:hover { background-color: #0056b3; border-color: #0056b3; }
        .stats-area { background-color: #e9ecef; padding: 10px 15px; margin-bottom: 10px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.95em; color: #495057; } .stats-area span { margin-right: 20px; } .stats-area strong { color: #343a40; }
        .data-summary { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px 15px; background-color: #fdfdfe; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.9em;}
        .date-range-display { color: #495057; }
        .fy-filter label { margin-right: 5px; color: #495057; }
        .fy-filter select { padding: 4px 8px; font-size: 0.9em; border-radius: 3px; border: 1px solid #ced4da; margin-left: 5px; cursor: pointer;}
        /* Column Widths */
        .col-date { width: 9%; } .col-txid { width: 13%; } .col-desc { width: 23%; } .col-category { width: 12%; }
        .col-paid-in, .col-paid-out { width: 7%; text-align: right; }
        .col-invoice { width: 10%; } .col-comments { width: 11%; }
        /* Amount Colors */
        .paid-in { color: #28a745; font-weight: 500; } .paid-out { color: #dc3545; font-weight: 500; }
        a { color: #007bff; text-decoration: none; } a:hover { text-decoration: underline; }
        .txid-style { font-family: monospace; font-size: 0.9em; color: #6c757d; }
        /* Removed save button styles */
        .status-saving { color: #ffc107; } .status-success { color: #28a745; } .status-error { color: #dc3545; }
        /* Filter Styles */
        .filter-icon { font-size: 0.8em; margin-left: 5px; cursor: pointer; color: #6c757d; display: inline-block; transition: color 0.2s ease; } .filter-icon:hover { color: #007bff; }
        th.filter-active { background-color: #ffeeba !important; }
        .filter-input-container { display: none; position: absolute; top: 100%; left: 0; width: 98%; min-width: 180px; padding: 8px; background-color: #f8f9fa; border: 1px solid #ccc; box-shadow: 0 2px 5px rgba(0,0,0,0.15); z-index: 10; box-sizing: border-box; } .filter-input-container.active { display: block; }
        .filter-input { width: 100%; padding: 5px 8px; font-size: 0.95em; border: 1px solid #ced4da; border-radius: 3px; box-sizing: border-box; }
        #clear-filters-btn { margin: 5px 0 10px 0; padding: 4px 8px; font-size: 0.85em; cursor: pointer; background-color: #6c757d; color: white; border: 1px solid #6c757d; border-radius: 3px; display: none; } #clear-filters-btn:hover { background-color: #5a6268; border-color: #545b62; }
        .flatpickr-input { background-color: #fff !important; cursor: pointer;}
        /* Invoice Icon Styles */
        .invoice-actions span, .invoice-actions a { display: inline-block; margin-right: 8px; cursor: pointer; font-size: 1.1em; text-decoration: none; color: #6c757d; transition: color 0.2s ease; } .invoice-actions span:hover, .invoice-actions a:hover { color: #007bff; } .icon-view { color: #17a2b8; } .icon-view:hover { color: #117a8b; } .icon-attach { color: #28a745; } .icon-attach:hover { color: #1e7e34; } .icon-replace { color: #ffc107; } .icon-replace:hover { color: #d39e00; } .icon-delete { color: #dc3545; } .icon-delete:hover { color: #bd2130; } .hidden-invoice-input { display: none; } .invoice-status { font-size: 0.8em; margin-left: 5px; display: block; margin-top: 3px; height: 1em; }
         /* Category Select Styles */
         td select.category-select { width: 100%; padding: 5px; font-size: 0.95em; border: 1px solid #ced4da; border-radius: 3px; background-color: #fff; box-sizing: border-box; cursor: pointer; max-width: 150px; }
         td select.category-select option[value="add_new"] { font-style: italic; color: #007bff; background-color: #e9ecef; }
         .category-status { font-size: 0.8em; display: block; margin-top: 3px; height: 1em; }
         /* Style for Completed Rows */
        tr.transaction-complete { background-color: #d4edda !important; }
        tr.transaction-complete:nth-child(even) { background-color: #c3e6cb !important; }
        /* Comment Save Status Style */
        .comment-save-status { font-weight: bold; font-size: 0.85em; display: block; margin-top: 3px; height: 1em; }
        /* *** ADDED: Toast Notification Styles *** */
        #toast-container {
            position: fixed; /* Keep it in view */
            bottom: 20px;    /* Distance from bottom */
            right: 20px;     /* Distance from right */
            z-index: 1050;   /* Appear above other elements */
            display: flex;
            flex-direction: column; /* Stack toasts vertically */
            align-items: flex-end; /* Align toasts to the right */
        }
        .toast-message {
            background-color: #333; /* Default dark background */
            color: #fff;
            padding: 12px 20px;
            border-radius: 5px;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            opacity: 1;
            transition: opacity 0.5s ease-out; /* Fade out transition */
            font-size: 0.9em;
        }
        /* Style for success messages */
        .toast-message.toast-success {
            background-color: #28a745; /* Green */
            color: #fff;
        }
        /* Style for error messages */
        .toast-message.toast-error {
            background-color: #dc3545; /* Red */
            color: #fff;
        }
        /* Class added by JS to trigger fade out */
        .toast-message.fade-out {
            opacity: 0;
        }
        /* --- End Toast Styles --- */
        .hidden-invoice-input,
        .actions-cell input { display: none !important; } 

    </style>
</head>
<body>
    <h1>Bank Statement Manager</h1>
    <a href="logout.php" style="display: inline-block; margin-bottom: 15px; padding: 5px 10px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 3px;">Logout</a>

    <?php if ($success_message): ?> <div class="message success"><?php echo htmlspecialchars($success_message); ?></div> <?php endif; ?>
    <?php if ($error_message): ?> <div class="message error"><?php echo htmlspecialchars($error_message); ?></div> <?php endif; ?>
    <?php if ($warning_message): ?>
        <div class="message warning">
            <?php echo htmlspecialchars($warning_message); ?>
            <?php if ($warning_details): ?> <div class="debug-details"><pre><?php echo htmlspecialchars(implode("\n", array_slice($warning_details, 0, 20))) . (count($warning_details)>20 ? "\n..." : ""); ?></pre></div> <?php endif; ?>
        </div>
    <?php endif; ?>

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

    <div class="data-summary">
        <span class="date-range-display">
            Data Range:
            <?php if ($min_date && $max_date): ?>
                <strong><?php echo format_nice_date($min_date); ?></strong> - <strong><?php echo format_nice_date($max_date); ?></strong>
            <?php elseif ($min_date): ?>
                From <strong><?php echo format_nice_date($min_date); ?></strong>
            <?php else: ?>
                <strong>N/A</strong>
            <?php endif; ?>
        </span>
        <div class="fy-filter">
            <label for="fy-filter-select">Filter by Financial Year:</label>
            <select id="fy-filter-select">
                <option value="">-- Select FY --</option>
                <?php foreach ($financial_years as $fy): ?>
                    <option value="<?php echo $fy['start'] . '_' . $fy['end']; ?>" data-start-date="<?php echo $fy['start']; ?>" data-end-date="<?php echo $fy['end']; ?>"> <?php echo htmlspecialchars($fy['label']); ?> </option>
                <?php endforeach; ?>
                 <option value="all">-- Show All Dates --</option>
            </select>
        </div>
    </div>

    <div class="stats-area">
        <span>Total: <strong id="stats-total"><?php echo number_format($total_count); ?></strong></span>
        <span>Completed (Cat & Inv): <strong id="stats-complete-count"><?php echo number_format($complete_count); ?></strong> / <strong id="stats-total-display"><?php echo number_format($total_count); ?></strong></span>
        <span>(<strong id="stats-percentage"><?php echo $percentage_complete; ?></strong>%)</span>
        <span>Needs Action: <strong id="stats-needs-action"><?php echo number_format($needs_completion_count); ?></strong></span>
    </div>

    <div><button type="button" id="clear-filters-btn">Clear All Filters</button></div>

    <table id="transactions-table">
        <thead><tr>
                <th class="col-date"> Date <span class="filter-icon" title="Filter by Date Range">📅</span> <div class="filter-input-container"> <input type="text" id="filter-date-range" class="filter-input date-filter" data-column-index="0" placeholder="Select Date or Range..."> </div> </th>
                <th class="col-txid"> Transaction ID <span class="filter-icon" title="Filter by Transaction ID">🔍</span> <div class="filter-input-container"> <input type="text" class="filter-input" data-column-index="1" placeholder="Filter ID..."> </div> </th>
                <th class="col-desc"> Description <span class="filter-icon" title="Filter by Description">🔍</span> <div class="filter-input-container"> <input type="text" class="filter-input" data-column-index="2" placeholder="Filter Desc..."> </div> </th>
                <th class="col-category"> Category <span class="filter-icon" title="Filter by Category">🔍</span> <div class="filter-input-container"> <input type="text" class="filter-input" data-column-index="3" placeholder="Filter Category..."> </div> </th>
                <th class="col-paid-in"> Paid In <span class="filter-icon" title="Filter by Amount Paid In">🔍</span> <div class="filter-input-container"> <input type="text" class="filter-input" data-column-index="4" placeholder="Filter Paid In..."> </div> </th>
                <th class="col-paid-out"> Paid Out <span class="filter-icon" title="Filter by Amount Paid Out">🔍</span> <div class="filter-input-container"> <input type="text" class="filter-input" data-column-index="5" placeholder="Filter Paid Out..."> </div> </th>
                <th class="col-invoice">Invoice</th>
                <th class="col-comments"> Comments <span class="filter-icon" title="Filter by Comments">🔍</span> <div class="filter-input-container"> <input type="text" class="filter-input" data-column-index="7" placeholder="Filter Comments..."> </div> </th>
            </tr>
        </thead>
        <tbody id="transaction-data">
             <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $tx): ?>
                    <?php $row_class = (!empty($tx['category_id']) && !empty($tx['invoice_path'])) ? 'transaction-complete' : ''; ?>
                <tr data-transaction-row-id="<?php echo $tx['id']; ?>" class="<?php echo $row_class; ?>">
                    <td data-date="<?php echo htmlspecialchars($tx['transaction_date']); ?>"><?php echo htmlspecialchars(date('d/m/Y', strtotime($tx['transaction_date']))); ?></td>
                    <td><span class="txid-style"><?php echo htmlspecialchars($tx['transaction_id']); ?></span></td>
                    <td><?php echo htmlspecialchars($tx['description']); ?></td>
                    <td class="actions-cell category-cell"><?php /* Category Select */ ?> <select class="category-select" data-id="<?php echo $tx['id']; ?>" title="Assign Category"> <option value="" <?php if (empty($tx['category_id'])) echo 'selected'; ?>>-- Select --</option> <?php foreach ($categories as $category): ?> <option value="<?php echo $category['id']; ?>" <?php if ($tx['category_id'] == $category['id']) echo 'selected'; ?>> <?php echo htmlspecialchars($category['name']); ?> </option> <?php endforeach; ?> <option value="add_new" style="font-style: italic; color: #007bff; background-color: #e9ecef;">[ Add New... ]</option> </select> <span class="category-status"></span> </td>
                    <td class="paid-in"><?php if (!empty($tx['paid_in']) && (float)$tx['paid_in'] != 0) { echo number_format($tx['paid_in'], 2); } ?></td>
                    <td class="paid-out"><?php if (!empty($tx['paid_out']) && (float)$tx['paid_out'] != 0) { echo number_format($tx['paid_out'], 2); } ?></td>
                    <td class="actions-cell invoice-actions"><?php /* Invoice Icons */ ?> <?php if (!empty($tx['invoice_path'])): ?><a href="view_invoice.php?tx_id=<?php echo $tx['id']; ?>" target="_blank" class="icon-view" title="View Invoice">👁️</a> <span class="icon-replace" data-id="<?php echo $tx['id']; ?>" title="Replace Invoice">🔄</span> <span class="icon-delete" data-id="<?php echo $tx['id']; ?>" title="Delete Invoice Link">🗑️</span> <?php else: ?> <span class="icon-attach" data-id="<?php echo $tx['id']; ?>" title="Attach Invoice">📎</span> <?php endif; ?> <input type="file" class="hidden-invoice-input" id="invoice-input-<?php echo $tx['id']; ?>" data-tx-id="<?php echo $tx['id']; ?>" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.txt,.csv,.xls,.xlsx"> <span class="invoice-status"></span> </td>
                    <td class="actions-cell"><?php /* Comments Textarea + Status */ ?> <textarea class="comment-textarea" data-id="<?php echo $tx['id']; ?>" data-original-value="<?php echo htmlspecialchars($tx['comments'] ?? ''); ?>" rows="3" placeholder="Add comment..."><?php echo htmlspecialchars($tx['comments'] ?? ''); ?></textarea><span class="comment-save-status"></span> </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8">No transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

     <form id="invoice-upload-form" style="display: none;"></form>

    <div id="toast-container"></div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
    // ****** START OF COMPLETE JAVASCRIPT BLOCK ******

    // --- Debounce function ---
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // --- Global state for date filter ---
    let currentDateFilter = { start: null, end: null };

    // --- Global state for stats ---
    let currentTotalCount = <?php echo $total_count; ?>;
    let currentCompleteCount = <?php echo $complete_count; ?>;

    // --- Helper Function to Update Stats Display ---
    function updateStatsDisplay() {
        const totalEl = document.getElementById('stats-total');
        const completeCountEl = document.getElementById('stats-complete-count');
        const totalDisplayEl = document.getElementById('stats-total-display');
        const percentageEl = document.getElementById('stats-percentage');
        const needsActionEl = document.getElementById('stats-needs-action');

        if (totalEl && completeCountEl && totalDisplayEl && percentageEl && needsActionEl) {
             let needsCompletion = currentTotalCount - currentCompleteCount;
             let percentage = (currentTotalCount > 0) ? Math.round((currentCompleteCount / currentTotalCount) * 100) : 0;
             totalEl.textContent = currentTotalCount.toLocaleString();
             completeCountEl.textContent = currentCompleteCount.toLocaleString();
             totalDisplayEl.textContent = currentTotalCount.toLocaleString();
             percentageEl.textContent = percentage;
             needsActionEl.textContent = needsCompletion.toLocaleString();
        }
    }

    // --- Helper Function to Check/Apply Row Completion Class ---
    function checkAndApplyRowCompletion(transactionId) {
        const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
        if (!row) return false;
        const categorySelect = row.querySelector('.category-select');
        const hasCategory = categorySelect && categorySelect.value !== "" && categorySelect.value !== "add_new";
        const hasInvoice = row.querySelector('.invoice-actions .icon-view') !== null;
        let isNowComplete = false;
        if (hasCategory && hasInvoice) { row.classList.add('transaction-complete'); isNowComplete = true; }
        else { row.classList.remove('transaction-complete'); }
        return isNowComplete;
    }

    // --- Helper Function for Toast Notifications ---
    function showToast(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toast-container');
        if (!container) { console.error("Toast container not found!"); return; };
        const toast = document.createElement('div');
        toast.className = `toast-message toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; }, duration - 500);
        setTimeout(() => { toast.remove(); }, duration);
    }


    // --- Filter Table Function ---
    function filterTable() {
         const dateFilterInput = document.getElementById('filter-date-range');
         const otherFilterInputs = document.querySelectorAll('.filter-input:not(.date-filter)');
         const tableBody = document.getElementById('transaction-data');
         if (!tableBody) return;
         const rows = tableBody.getElementsByTagName('tr');
         const dateFilterStart = currentDateFilter.start; const dateFilterEnd = currentDateFilter.end;
         const textFilters = {};
         otherFilterInputs.forEach(input => { if (input.value.trim() !== '') { textFilters[input.dataset.columnIndex] = input.value.trim().toLowerCase(); } });
         const textFilterKeys = Object.keys(textFilters);
         let isAnyFilterActive = false;
         document.querySelectorAll('thead th').forEach(th => { const icon = th.querySelector('.filter-icon'); if (!icon) return; let isActive = false; if (th.classList.contains('col-date')) { isActive = dateFilterStart || dateFilterEnd; } else { const input = th.querySelector('.filter-input'); if (input && textFilters.hasOwnProperty(input.dataset.columnIndex)) { isActive = true; } } if (isActive) { th.classList.add('filter-active'); isAnyFilterActive = true; } else { th.classList.remove('filter-active'); } });
         const clearButton = document.getElementById('clear-filters-btn'); if(clearButton) { clearButton.style.display = isAnyFilterActive ? 'inline-block' : 'none'; }
         for (let i = 0; i < rows.length; i++) { const row = rows[i]; const cells = row.getElementsByTagName('td'); let display = true; if (cells.length < 8) { display = false; } else { const rowDate = cells[0] ? cells[0].dataset.date : null; if (rowDate) { if (dateFilterStart && rowDate < dateFilterStart) { display = false; } if (display && dateFilterEnd && rowDate > dateFilterEnd) { display = false; } } else if (dateFilterStart || dateFilterEnd) { display = false; } if (display) { for (const colIndex of textFilterKeys) { const cell = cells[colIndex]; if (!cell) { display = false; break; } let cellValue = ''; if (colIndex == 7) { const textarea = cell.querySelector('.comment-textarea'); if (textarea) { cellValue = textarea.value.toLowerCase(); } } else if (colIndex == 3) { const select = cell.querySelector('.category-select'); if(select && select.value && select.value !== 'add_new') { cellValue = select.options[select.selectedIndex].text.toLowerCase(); } else if (select && !select.value){ cellValue = ""; } } else { cellValue = (cell.textContent || cell.innerText || "").trim().toLowerCase(); } if (!cellValue.includes(textFilters[colIndex])) { display = false; break; } } } } row.style.display = display ? '' : 'none'; }
     } // end filterTable


    document.addEventListener('DOMContentLoaded', function() {

        // *** Auto-Save Comment Logic ***
        const commentTextareas = document.querySelectorAll('.comment-textarea');
        function updateCommentStatusSpan(transactionId, message, statusClass = '') { const textarea = document.querySelector(`.comment-textarea[data-id='${transactionId}']`); const statusEl = textarea ? textarea.closest('td').querySelector('.comment-save-status') : null; if (statusEl) { statusEl.textContent = message; statusEl.className = 'comment-save-status ' + statusClass; if (statusClass === 'status-success' || statusClass === 'status-error') { setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 2500); } } else { console.warn("Could not find comment status element for TX ID:", transactionId); } }
        commentTextareas.forEach(textarea => {
            textarea.dataset.originalValue = textarea.value; // Ensure baseline
            textarea.addEventListener('blur', function() {
                const transactionId = this.dataset.id; const originalValue = this.dataset.originalValue; const currentValue = this.value.trim();
                if (currentValue !== originalValue) {
                    /* updateCommentStatusSpan(transactionId, 'Saving...', 'status-saving'); */
                    fetch('save_single_comment.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ transaction_id: transactionId, comment: currentValue }) })
                    // --- UPDATED Fetch Error Handling ---
                    .then(response => {
                        if (!response.ok) { return response.json().catch(() => { throw new Error(`HTTP error ${response.status}: ${response.statusText}`); }).then(err => { throw new Error(err?.message || `HTTP error ${response.status}: ${response.statusText}`); }); }
                        return response.json();
                    })
                    // --- End Update ---
                    .then(data => { if (data.status === 'success') { /* updateCommentStatusSpan(transactionId, 'Saved!', 'status-success'); */ showToast('Comment Saved!', 'success'); this.dataset.originalValue = currentValue; } else { throw new Error(data.message || 'Save failed.'); } })
                    .catch(error => { console.error('Comment Save error:', error); updateCommentStatusSpan(transactionId, `Error!`, 'status-error'); showToast(`Error saving comment: ${error.message}`, 'error'); });
                } else { updateCommentStatusSpan(transactionId, '', ''); }
            });
        });
        // *** End Auto-Save Comment Logic ***


         // --- Filter Setup ---
         const filterIcons = document.querySelectorAll('.filter-icon'); const allFilterInputs = document.querySelectorAll('.filter-input'); const textFilterInputs = document.querySelectorAll('.filter-input:not(.date-filter)'); const dateRangeInput = document.getElementById('filter-date-range'); const clearButton = document.getElementById('clear-filters-btn'); const debouncedFilter = debounce(filterTable, 350); filterIcons.forEach(icon => { icon.addEventListener('click', function(event) { event.stopPropagation(); const container = this.nextElementSibling; if (container && container.classList.contains('filter-input-container')) { document.querySelectorAll('.filter-input-container.active').forEach(openContainer => { if (openContainer !== container) { openContainer.classList.remove('active'); } }); container.classList.toggle('active'); if (container.classList.contains('active')) { container.querySelector('.filter-input').focus(); } } }); }); textFilterInputs.forEach(input => { input.addEventListener('input', debouncedFilter); input.closest('.filter-input-container').addEventListener('click', (event) => event.stopPropagation() ); }); document.addEventListener('click', function(event) { let clickedInsideFilter = event.target.closest('.filter-input-container') || event.target.closest('.filter-icon') || event.target.closest('.flatpickr-calendar'); if (!clickedInsideFilter) { document.querySelectorAll('.filter-input-container.active').forEach(container => { container.classList.remove('active'); }); } }); if(clearButton) { clearButton.addEventListener('click', () => { textFilterInputs.forEach(input => input.value = ''); if (window.flatpickrInstanceDateRange) { window.flatpickrInstanceDateRange.clear(); } currentDateFilter.start = null; currentDateFilter.end = null; document.querySelectorAll('.filter-input-container.active').forEach(c => c.classList.remove('active')); filterTable(); }); } window.flatpickrInstanceDateRange = flatpickr("#filter-date-range", { mode: "range", dateFormat: "Y-m-d", altInput: true, altFormat: "d/m/Y", allowInput: true, onClose: function(selectedDates, dateStr, instance) { if (selectedDates.length === 1) { currentDateFilter.start = instance.formatDate(selectedDates[0], "Y-m-d"); currentDateFilter.end = instance.formatDate(selectedDates[0], "Y-m-d"); } else if (selectedDates.length === 2) { currentDateFilter.start = instance.formatDate(selectedDates[0], "Y-m-d"); currentDateFilter.end = instance.formatDate(selectedDates[1], "Y-m-d"); } else { currentDateFilter.start = null; currentDateFilter.end = null; } filterTable(); } });
         // --- End Filter Setup ---
         
         // *** ADD THIS BLOCK: Financial Year Filter Dropdown Logic ***
            const fySelect = document.getElementById('fy-filter-select');
            // Ensure flatpickr instance exists (it's set globally as window.flatpickrInstanceDateRange)

            if (fySelect && window.flatpickrInstanceDateRange) {
                fySelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const startDate = selectedOption.dataset.startDate;
                    const endDate = selectedOption.dataset.endDate;
                    const selectedValue = this.value; // Value like "2020-06-25_2021-06-24" or "all" or ""

                    console.log("FY Handler - Selected Value:", selectedValue, "Start Date:", startDate, "End Date:", endDate); // Debugging line

                    // Update Flatpickr and global state based on selection
                    if (selectedValue === "all" || selectedValue === "") {
                        // Clear the date range filter
                        window.flatpickrInstanceDateRange.clear();
                        // ** Explicitly clear the global filter state **
                        currentDateFilter.start = null;
                        currentDateFilter.end = null;
                    } else if (startDate && endDate) {
                        // Set the Flatpickr visual input AND update global state
                        // Set date silently first (false), then manually update state
                        window.flatpickrInstanceDateRange.setDate([startDate, endDate], false);
                        currentDateFilter.start = startDate;
                        currentDateFilter.end = endDate;
                    } else {
                        // Fallback for unexpected value - clear filter
                        window.flatpickrInstanceDateRange.clear();
                        currentDateFilter.start = null;
                        currentDateFilter.end = null;
                    }

                    // ** Explicitly trigger the table filtering function **
                    filterTable();

                    // Optional: Close filter popups if open
                     document.querySelectorAll('.filter-input-container.active').forEach(container => {
                         container.classList.remove('active');
                     });

                }); // End event listener
            } else {
                if (!fySelect) console.error("FY Select dropdown not found");
                if (!window.flatpickrInstanceDateRange) console.error("Flatpickr instance not found");
            }
            // *** End FY Filter Logic ***


        // --- Invoice Icon Logic ---
        const tableBody = document.getElementById('transaction-data');
        function updateInvoiceStatusSpan(transactionId, message, statusClass = '') { /* ... same helper function ... */ }
        tableBody.addEventListener('click', function(event) {
            const target = event.target; const transactionId = target.dataset.id;
            if ((target.classList.contains('icon-attach') || target.classList.contains('icon-replace')) && transactionId) { event.preventDefault(); const fileInput = document.getElementById(`invoice-input-${transactionId}`); if (fileInput) { fileInput.click(); } }
            if (target.classList.contains('icon-delete') && transactionId) {
                event.preventDefault(); if (confirm(`Are you sure...?`)) { updateInvoiceStatusSpan(transactionId, 'Deleting...', 'status-saving');
                    fetch('delete_invoice.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ transaction_id: transactionId }) })
                    // --- UPDATED Fetch Error Handling ---
                    .then(response => {
                        if (!response.ok) { return response.json().catch(() => { throw new Error(`HTTP error ${response.status}: ${response.statusText}`); }).then(err => { throw new Error(err?.message || `HTTP error ${response.status}: ${response.statusText}`); }); }
                        return response.json();
                    })
                    // --- End Update ---
                    .then(data => { if (data.status === 'success') { const actionCell = target.closest('.invoice-actions'); const row = target.closest('tr'); const wasComplete = row ? row.classList.contains('transaction-complete') : false; if (actionCell) { actionCell.innerHTML = `<span class="icon-attach" ...>📎</span> <input type="file" ...><span class="invoice-status"></span>`; } showToast('Invoice Deleted', 'success'); if(wasComplete) { currentCompleteCount--; } updateStatsDisplay(); checkAndApplyRowCompletion(transactionId); } else { throw new Error(data.message || 'Delete failed.'); } })
                    .catch(error => { console.error('Delete error:', error); updateInvoiceStatusSpan(transactionId, `Error!`, 'status-error'); showToast(`Error deleting invoice: ${error.message}`, 'error'); });
                }
            }
        });
        tableBody.addEventListener('change', function(event) { if (event.target.classList.contains('hidden-invoice-input')) { /* ... File selection logic ... */
            const fileInput = event.target; const transactionId = fileInput.dataset.txId; const file = fileInput.files[0]; if (file && transactionId) { updateInvoiceStatusSpan(transactionId, 'Uploading...', 'status-saving'); const formData = new FormData(); formData.append('transaction_id', transactionId); formData.append('invoice_file', file);
                fetch('upload_invoice.php', { method: 'POST', body: formData })
                // --- UPDATED Fetch Error Handling ---
                .then(response => {
                    if (!response.ok) { return response.json().catch(() => { throw new Error(`HTTP error ${response.status}: ${response.statusText}`); }).then(err => { throw new Error(err?.message || `HTTP error ${response.status}: ${response.statusText}`); }); }
                    return response.json();
                })
                // --- End Update ---
                .then(data => { if (data.status === 'success') { updateInvoiceStatusSpan(transactionId, 'Saved!', 'status-success'); const actionCell = fileInput.closest('.invoice-actions'); const newFileName = data.filename || file.name; const row = fileInput.closest('tr'); const wasComplete = row ? row.classList.contains('transaction-complete') : false; if (actionCell) { actionCell.innerHTML = `<a href="${data.view_url || '#'}" target="_blank" ...>👁️</a> <span ...>🔄</span> <span ...>🗑️</span> <input type="file" ...><span class="invoice-status"></span>`; } const isNowComplete = checkAndApplyRowCompletion(transactionId); if (isNowComplete && !wasComplete) { currentCompleteCount++; } updateStatsDisplay(); showToast('Invoice Uploaded!', 'success'); } else { throw new Error(data.message || 'Upload failed.'); } })
                .catch(error => { console.error('Upload error:', error); updateInvoiceStatusSpan(transactionId, `Error!`, 'status-error'); showToast(`Error uploading invoice: ${error.message}`, 'error'); })
                .finally(() => { fileInput.value = ''; });
            } }
        });
        // --- End Invoice Icon Logic ---


        // --- Category Dropdown Logic ---
        const allCategorySelects = document.querySelectorAll('.category-select'); function updateCategoryStatus(transactionId, message, statusClass = '') { /* ... */ } allCategorySelects.forEach(select => { select.addEventListener('change', function() { /* ... Add new category logic ... */ const transactionId = this.dataset.id; const selectedValue = this.value; const selectElement = this; const previousSelection = selectElement.dataset.currentSelection || ""; updateCategoryStatus(transactionId, '', ''); if (selectedValue === "add_new") { const newCategoryName = prompt("Enter new category name:"); if (newCategoryName && newCategoryName.trim() !== "") { updateCategoryStatus(transactionId, 'Adding...', 'status-saving');
            fetch('add_new_category.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ name: newCategoryName.trim() }) })
            // --- UPDATED Fetch Error Handling ---
            .then(response => {
                if (!response.ok) { return response.json().catch(()=>null).then(err => { throw new Error(err?.message || response.statusText);}); }
                return response.json();
            })
            // --- End Update ---
            .then(data => { if (data.status === 'success' && data.id && data.name) { /* ... add option, set value ... */ showToast(`Category '${data.name}' added`, 'success'); saveCategorySelection(transactionId, data.id, selectElement); } else { throw new Error(data.message || 'Failed to add category.'); } })
            .catch(error => { console.error('Add Category error:', error); updateCategoryStatus(transactionId, `Error: ${error.message}`, 'status-error'); showToast(`Error adding category: ${error.message}`, 'error'); selectElement.value = previousSelection; });
             } else { selectElement.value = previousSelection; } } else { saveCategorySelection(transactionId, selectedValue, selectElement); } }); select.dataset.currentSelection = select.value; });
        function saveCategorySelection(transactionId, categoryId, selectElement) { updateCategoryStatus(transactionId, 'Saving...', 'status-saving'); const categoryIdToSend = categoryId === "" ? null : categoryId;
            fetch('save_transaction_category.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ transaction_id: transactionId, category_id: categoryIdToSend }) })
            // --- UPDATED Fetch Error Handling ---
            .then(response => {
                if (!response.ok) { return response.json().catch(()=>null).then(err => { throw new Error(err?.message || response.statusText);}); }
                return response.json();
            })
            // --- End Update ---
            .then(data => { if (data.status === 'success') { updateCategoryStatus(transactionId, 'Saved!', 'status-success'); const row = selectElement.closest('tr'); const wasComplete = row ? row.classList.contains('transaction-complete') : false; const isNowComplete = checkAndApplyRowCompletion(transactionId); if (isNowComplete && !wasComplete) { currentCompleteCount++; } else if (!isNowComplete && wasComplete) { currentCompleteCount--; } updateStatsDisplay(); selectElement.dataset.currentSelection = categoryId; showToast('Category Saved!', 'success'); } else { throw new Error(data.message || 'Failed to save category.'); } })
            .catch(error => { console.error('Save Category error:', error); updateCategoryStatus(transactionId, `Error: ${error.message}`, 'status-error'); showToast(`Error saving category: ${error.message}`, 'error'); });
        }
        // --- End Category Dropdown Logic ---


         // Initial setup calls
         filterTable();
         updateStatsDisplay();

    }); // End DOMContentLoaded
    // ****** END OF COMPLETE JAVASCRIPT BLOCK ******
    </script>

</body>
</html>
