<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

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
        $incorporation_year = 2020; $incorporation_month_day = '06-25'; // MM-DD
        $current_year = date('Y'); $current_month_day = date('m-d');
        $current_fy_end_year = ($current_month_day < $incorporation_month_day) ? $current_year : $current_year + 1;
        for ($year = $incorporation_year; $year < $current_fy_end_year; $year++) {
             $start_date = $year . '-' . $incorporation_month_day; $end_year = $year + 1;
             $end_date_obj = date_create($end_year . '-' . $incorporation_month_day); date_sub($end_date_obj, date_interval_create_from_date_string("1 day")); $end_date = date_format($end_date_obj, 'Y-m-d');
             $financial_years[] = ['label' => "FY ".substr($year,-2)."/".substr($end_year,-2), 'start' => $start_date, 'end' => $end_date];
        }

        // 4. Fetch Transactions (Select invoice_path as well)
        $stmt_tx = $pdo->query('SELECT id, transaction_id, transaction_date, description, paid_in, paid_out, category_id, invoice_path, comments FROM transactions ORDER BY transaction_date DESC, id DESC');
        $transactions = $stmt_tx->fetchAll();

        // 5. Calculate Completion Statistics
        $total_count = count($transactions); $complete_count = 0;
        if ($total_count > 0) { foreach ($transactions as $tx) { if (!empty($tx['category_id']) && !empty($tx['invoice_path'])) { $complete_count++; } } $needs_completion_count = $total_count - $complete_count; $percentage_complete = ($total_count > 0) ? round(($complete_count / $total_count) * 100) : 0; }

    } catch (\PDOException $e) { $error_message = "Error fetching data: " . $e->getCode(); error_log("[Index Page DB Error] PDOException fetching data: " . $e->getMessage()); }
} else { $error_message = "Database connection is not available."; }

// Helper function for date formatting
function format_nice_date($date_string) { if (empty($date_string)) return 'N/A'; $timestamp = strtotime($date_string); return ($timestamp === false) ? 'Invalid Date' : date('jS F Y', $timestamp); }
// --- End PHP Setup ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Amigos Ltd: Tide Books Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* --- Basic Styles (Keep as is) --- */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 20px; font-size: 14px; line-height: 1.5; background-color: #f8f9fa; color: #212529;}
        h1, h2 { color: #343a40; margin-bottom: 0.8rem;}
        hr { border: 0; height: 1px; background-color: #dee2e6; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; table-layout: fixed; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #dee2e6; padding: 10px 12px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #e9ecef; font-weight: 600; position: relative; }
        thead th { position: sticky; top: 0; z-index: 1; background-color: #e9ecef; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        
        .upload-form { 
            margin-bottom: 20px; 
            padding: 20px; 
            border: 1px solid #dee2e6; 
            background-color: #fff; 
            border-radius: 5px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            display: flex;
            justify-content: space-between
        }
        .stats-right {
            background: #f8f9fa;
            border-radius: 4px; 
            border: 1px solid #dee2e6; 
            padding: 10px 15px; 
            font-size: 0.95em; 
            color: #495057; 
            width: 50%;
        }
        .stats-area { 
            display: flex;
            flex-direction: column;
        }
        .stats-area span { 
            margin-bottom: 12px;
        }
        .stats-right h3 { 
            margin-top: 0;   
        }
        
        .message { padding: 12px 18px; margin-bottom: 15px; border: 1px solid; border-radius: 4px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; }
        .debug-details pre { max-height: 150px; overflow-y: auto; border: 1px solid #ffeeba; padding: 8px; margin-top: 8px; background-color: #fff; font-size: 0.85em; line-height: 1.4; }
        form { margin-bottom: 0; }
        td textarea.comment-textarea { width: 100%; max-width: 100%; box-sizing: border-box; font-family: inherit; font-size: 0.95em; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; resize: vertical; min-height: 50px; transition: background-color 0.3s ease; }
        button, input[type="submit"], button.save-all-btn { padding: 8px 15px; font-size: 0.95em; border-radius: 3px; cursor: pointer; border: 1px solid transparent; }
        button[type="submit"], button.save-all-btn { background-color: #007bff; color: white; border-color: #007bff; }
        button[type="submit"]:hover, button.save-all-btn:hover { background-color: #0056b3; border-color: #0056b3; }
        button.save-all-btn:disabled { background-color: #6c757d; border-color: #6c757d; cursor: not-allowed; }
        
        
        .stats-area strong { color: #343a40; }
        .data-summary { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 10px 15px; background-color: #fdfdfe; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.9em;}
        .date-range-display { color: #495057; }
        .fy-filter label { margin-right: 5px; color: #495057; }
        .fy-filter select { padding: 4px 8px; font-size: 0.9em; border-radius: 3px; border: 1px solid #ced4da; margin-left: 5px; cursor: pointer;}
        .col-date { width: 9%; } .col-txid { width: 13%; } .col-desc { width: 23%; } .col-category { width: 12%; } 
        .col-paid-in, .col-paid-out { width: 7%; text-align: right; } .col-invoice { width: 12%; } /* Increased width slightly for text */ .col-comments { width: 11%; }
        .paid-in { color: #28a745; font-weight: 500; text-align: right; } .paid-out { color: #dc3545; font-weight: 500; text-align: right; }
        a { color: #007bff; text-decoration: none; } a:hover { text-decoration: underline; }
        .txid-style { font-family: monospace; font-size: 0.9em; color: #6c757d; }
        .status-saving { color: #ffc107; } .status-success { color: #28a745; } .status-error { color: #dc3545; }
        .filter-icon { font-size: 0.8em; margin-left: 5px; cursor: pointer; color: #6c757d; display: inline-block; transition: color 0.2s ease; } .filter-icon:hover { color: #007bff; } th.filter-active { background-color: #ffeeba !important; } .filter-input-container { display: none; position: absolute; top: 100%; left: 0; width: 98%; min-width: 180px; padding: 8px; background-color: #f8f9fa; border: 1px solid #ccc; box-shadow: 0 2px 5px rgba(0,0,0,0.15); z-index: 10; box-sizing: border-box; } .filter-input-container.active { display: block; } .filter-input { width: 100%; padding: 5px 8px; font-size: 0.95em; border: 1px solid #ced4da; border-radius: 3px; box-sizing: border-box; } #clear-filters-btn { margin: 5px 0 10px 0; padding: 4px 8px; font-size: 0.85em; cursor: pointer; background-color: #6c757d; color: white; border: 1px solid #6c757d; border-radius: 3px; display: none; } #clear-filters-btn:hover { background-color: #5a6268; border-color: #545b62; } .flatpickr-input { background-color: #fff !important; cursor: pointer;}

        /* --- MODIFIED: Invoice Action Link Styles --- */
        .invoice-actions a {
            display: inline-block; /* Or inline, adjust as needed */
            cursor: pointer;
            text-decoration: none;
            color: #007bff; /* Default link color */
            transition: color 0.2s ease, text-decoration 0.2s ease;
            font-size: 0.9em; /* Smaller font size for table cell */
        }
        .invoice-actions a:hover {
            color: #0056b3; /* Darker on hover */
            text-decoration: underline;
        }
        /* Optional: Specific colors for actions */
        .invoice-actions .link-delete { color: #dc3545; }
        .invoice-actions .link-delete:hover { color: #bd2130; }
        /* .invoice-actions .link-replace { color: #ffc107; }
           .invoice-actions .link-replace:hover { color: #d39e00; } */
        /* .invoice-actions .link-attach { color: #28a745; }
           .invoice-actions .link-attach:hover { color: #1e7e34; } */
        /* Keep pipe separator style if desired */
        .invoice-actions .link-separator {
            color: #adb5bd; /* Light gray */
            margin: 0 3px; /* Spacing around separator */
            font-size: 0.9em;
            cursor: default;
        }

        /* --- REMOVED/COMMENTED OUT: Icon Styles (No longer needed) --- */
        /*
        .invoice-actions span, .invoice-actions a { display: inline-block; margin-right: 8px; cursor: pointer; font-size: 1.1em; text-decoration: none; color: #6c757d; transition: color 0.2s ease; }
        .invoice-actions span:hover, .invoice-actions a:hover { color: #007bff; }
        .icon-view { color: #17a2b8; } .icon-view:hover { color: #117a8b; }
        .icon-attach { color: #28a745; } .icon-attach:hover { color: #1e7e34; }
        .icon-replace { color: #ffc107; } .icon-replace:hover { color: #d39e00; }
        .icon-delete { color: #dc3545; } .icon-delete:hover { color: #bd2130; }
        */

        /* --- CSS Fix for Hidden File Input (Keep) --- */
        .hidden-invoice-input { display: none !important; }
        .invoice-status { font-size: 0.8em; margin-left: 5px; display: block; margin-top: 3px; height: 1em; color: #6c757d; } /* Added default color */

        /* --- Category/Comment Styles (Keep as is) --- */
        td select.category-select { width: 100%; padding: 5px; font-size: 0.95em; border: 1px solid #ced4da; border-radius: 3px; background-color: #fff; box-sizing: border-box; cursor: pointer; max-width: 150px; } td select.category-select option[value="add_new"] { font-style: italic; color: #007bff; background-color: #e9ecef; } .category-status { font-size: 0.8em; display: block; margin-top: 3px; height: 1em; }
        tr.transaction-complete { background-color: #d4edda !important; } tr.transaction-complete:nth-child(even) { background-color: #c3e6cb !important; }
        .comment-save-status { font-weight: bold; font-size: 0.85em; display: block; margin-top: 3px; height: 1em; }
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 1050; display: flex; flex-direction: column; align-items: flex-end; } .toast-message { background-color: #333; color: #fff; padding: 12px 20px; border-radius: 5px; margin-top: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); opacity: 1; transition: opacity 0.5s ease-out; font-size: 0.9em; } .toast-message.toast-success { background-color: #28a745; color: #fff; } .toast-message.toast-error { background-color: #dc3545; color: #fff; } .toast-message.fade-out { opacity: 0; }
    </style>
</head>
<body>
    <h1>Web Amigos Ltd: Tide Books Dashboard</h1>
    <a href="logout.php" style="position: absolute; top: 25px; right: 20px; display: inline-block; margin-bottom: 15px; padding: 5px 10px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 3px;">Logout</a>

    <?php if ($success_message): ?> <div class="message success"><?php echo htmlspecialchars($success_message); ?></div> <?php endif; ?>
    <?php if ($error_message): ?> <div class="message error"><?php echo htmlspecialchars($error_message); ?></div> <?php endif; ?>
    <?php if ($warning_message): ?>
    <div class="message warning">
        <?php echo htmlspecialchars($warning_message); ?>
        <?php if ($warning_details && is_array($warning_details) && count($warning_details) > 0): ?>
            <div class="debug-details">
                <pre><?php echo htmlspecialchars(print_r($warning_details, true)); ?></pre>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="upload-form">
        <div class="upload-form-left">
            <h2>Upload Bank Statement CSV</h2>
            <form action="upload_csv.php" method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv,text/csv" required>
                <button type="submit">Upload CSV</button>
            </form>
            <p><small>Note: Uses Transaction ID to prevent duplicates.</small></p>
        </div>
        <div class="stats-right">
            <h3>Transaction Stats</h3>
             <div class="stats-area">
                <span>Total: <strong id="stats-total"><?php echo number_format($total_count); ?></strong></span>
                <span>Completed (Cat & Inv): <strong id="stats-complete-count"><?php echo number_format($complete_count); ?></strong> / <strong id="stats-total-display"><?php echo number_format($total_count); ?></strong> (<strong id="stats-percentage"><?php echo $percentage_complete; ?></strong>%)</span>
                <span>Needs Action: <strong id="stats-needs-action"><?php echo number_format($needs_completion_count); ?></strong></span>
            </div>
            
        </div>
    </div>

    <h2>Transactions</h2>

    <div class="data-summary">
        <span class="date-range-display">
             Data Range:
             <?php echo $min_date ? htmlspecialchars(format_nice_date($min_date)) : 'N/A'; ?>
             to
             <?php echo $max_date ? htmlspecialchars(format_nice_date($max_date)) : 'N/A'; ?>
        </span>
        <div class="fy-filter">
             <label for="fy-filter-select">Filter by Financial Year:</label>
             <select id="fy-filter-select">
                 <option value="">-- Select FY --</option>
                 <?php foreach ($financial_years as $fy): ?>
                     <option value="<?php echo $fy['start'] . '_' . $fy['end']; ?>"
                             data-start-date="<?php echo $fy['start']; ?>"
                             data-end-date="<?php echo $fy['end']; ?>">
                         <?php echo htmlspecialchars($fy['label']); ?>
                     </option>
                 <?php endforeach; ?>
                 <option value="all">-- Show All Dates --</option>
             </select>
        </div>
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
        </tr></thead>
        <tbody id="transaction-data">
            <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $tx): ?>
                    <?php $row_class = (!empty($tx['category_id']) && !empty($tx['invoice_path'])) ? 'transaction-complete' : ''; ?>
                    <tr data-transaction-row-id="<?php echo $tx['id']; ?>" class="<?php echo $row_class; ?>">
                        <td data-date="<?php echo htmlspecialchars($tx['transaction_date']); ?>"><?php echo htmlspecialchars(date('d/m/Y', strtotime($tx['transaction_date']))); ?></td>
                        <td><span class="txid-style"><?php echo htmlspecialchars($tx['transaction_id']); ?></span></td>
                        <td><?php echo htmlspecialchars($tx['description']); ?></td>
                        <td class="actions-cell category-cell">
                            <select class="category-select" data-id="<?php echo $tx['id']; ?>" data-current-selection="<?php echo htmlspecialchars((string)$tx['category_id']); ?>">
                                <option value="">-- Select --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $tx['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="add_new">+ Add New Category</option>
                            </select>
                            <span class="category-status"></span>
                        </td>
                        <!-- Replace with this improved code: -->
                        <td class="paid-in"><?php echo (!empty($tx['paid_in']) && floatval($tx['paid_in']) > 0) ? '£' . number_format($tx['paid_in'], 2) : ''; ?></td>
                        <td class="paid-out"><?php echo (!empty($tx['paid_out']) && floatval($tx['paid_out']) > 0) ? '£' . number_format($tx['paid_out'], 2) : ''; ?></td>

                        <td class="actions-cell invoice-actions" data-transaction-id="<?php echo $tx['id']; ?>">
                            <?php if (!empty($tx['invoice_path'])): ?>
                                <a href="view_invoice.php?tx_id=<?php echo (int)$tx['id']; ?>" target="_blank" class="link-view" title="View Invoice">View</a>
                                <span class="link-separator">|</span>
                                <a href="#" class="link-delete" title="Delete Invoice">Delete</a>
                            <?php else: ?>
                                <a href="#" class="link-attach" title="Attach Invoice">Attach</a>
                            <?php endif; ?>
                             <input type="file" name="invoice_file_<?php echo $tx['id']; ?>" class="hidden-invoice-input" data-id="<?php echo $tx['id']; ?>" accept=".webp,.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                             <span class="invoice-status"></span>
                        </td>
                        <td class="actions-cell comment-cell">
                            <textarea class="comment-textarea" data-id="<?php echo $tx['id']; ?>" placeholder="Add comments..."><?php echo htmlspecialchars($tx['comments'] ?? ''); ?></textarea>
                            <span class="comment-save-status"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8">No transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
        <!-- In index.php, add this code after the closing </tbody> tag of your table -->

        <tfoot>
            <tr class="totals-row">
                <td colspan="4" style="text-align: right; font-weight: bold;">Totals:</td>
                <td class="paid-in-total" style="font-weight: bold;"><?php 
                    // Calculate total paid in
                    $total_paid_in = 0;
                    foreach ($transactions as $tx) {
                        if (!empty($tx['paid_in']) && floatval($tx['paid_in']) > 0) {
                            $total_paid_in += floatval($tx['paid_in']);
                        }
                    }
                    echo '£' . number_format($total_paid_in, 2);
                ?></td>
                <td class="paid-out-total" style="font-weight: bold;"><?php 
                    // Calculate total paid out
                    $total_paid_out = 0;
                    foreach ($transactions as $tx) {
                        if (!empty($tx['paid_out']) && floatval($tx['paid_out']) > 0) {
                            $total_paid_out += floatval($tx['paid_out']);
                        }
                    }
                    echo '£' . number_format($total_paid_out, 2);
                ?></td>
                <td colspan="2"></td>
            </tr>
            
        </tfoot>
    </table>

     <?php /* <form id="invoice-upload-form" style="display: none;"></form> */ ?>

     <div id="toast-container"></div> 

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // --- Debounce function ---
        // --- Debounce function (Corrected Version) ---
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        // Capture the correct 'this' context from when executedFunction is called
        const context = this;
        const later = () => {
            timeout = null; // Clear the timeout ID after execution
            // Call the original function ('func') with the correct context ('this')
            // and arguments ('args') using .apply()
            func.apply(context, args);
        };
        clearTimeout(timeout); // Clear the previous timeout
        timeout = setTimeout(later, wait); // Set the new timeout
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
                // Ensure counts are numbers
                currentTotalCount = Number(currentTotalCount) || 0;
                currentCompleteCount = Number(currentCompleteCount) || 0;

                let needsCompletion = currentTotalCount - currentCompleteCount;
                let percentage = (currentTotalCount > 0) ? Math.round((currentCompleteCount / currentTotalCount) * 100) : 0;

                totalEl.textContent = currentTotalCount.toLocaleString();
                completeCountEl.textContent = currentCompleteCount.toLocaleString();
                totalDisplayEl.textContent = currentTotalCount.toLocaleString(); // Update this as well
                percentageEl.textContent = percentage;
                needsActionEl.textContent = needsCompletion.toLocaleString();
            } else {
                console.error("One or more stats elements not found.");
            }
        }


        // --- Helper Function to Check/Apply Row Completion Class ---
        function checkAndApplyRowCompletion(transactionId) {
            const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
            if (!row) return false;

            const categorySelect = row.querySelector('.category-select');
            const hasCategory = categorySelect && categorySelect.value !== "" && categorySelect.value !== "add_new";

            // Check if a "View" link exists, indicating an invoice is present
            const invoiceCell = row.querySelector('.invoice-actions');
            const hasInvoice = invoiceCell && invoiceCell.querySelector('.link-view') !== null;

            let isNowComplete = false;
            if (hasCategory && hasInvoice) {
                row.classList.add('transaction-complete');
                isNowComplete = true;
            } else {
                row.classList.remove('transaction-complete');
            }
            console.log(`Row ${transactionId} completion check: Category=${hasCategory}, Invoice=${hasInvoice}, Complete=${isNowComplete}`);
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
             // Fade out animation
             setTimeout(() => { toast.classList.add('fade-out'); }, duration - 500); // Start fade out 500ms before removing
             // Remove element after fade out
             setTimeout(() => { if (toast.parentNode === container) { container.removeChild(toast); } }, duration);
        }
        
        // Function to update the totals based on visible rows
        function updateTotals() {
            const tableBody = document.getElementById('transaction-data');
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

        // --- Filter Table Function ---
        function filterTable() {
             const tableBody = document.getElementById('transaction-data');
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
                 } else if (currentDateFilter.start && !currentDateFilter.end && dateCellValue) { // Handle single date selection
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
        }

        document.addEventListener('DOMContentLoaded', function() {

           // *** Auto-Save Comment Logic ***
            const commentTextareas = document.querySelectorAll('.comment-textarea');

            function updateCommentStatusSpan(transactionId, message, statusClass = '') {
                const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
                const statusSpan = row?.querySelector('.comment-save-status');
                if (statusSpan) {
                    statusSpan.textContent = message;
                    statusSpan.className = `comment-save-status ${statusClass}`; // Reset classes then add new one
                } else if (transactionId) { // Avoid logging if ID itself was the problem initially
                    console.warn(`Status span not found for comment row ${transactionId}`);
                }
            }

            commentTextareas.forEach(textarea => {
                textarea.dataset.originalValue = textarea.value.trim(); // Store initial value

                // Ensure you are using the CORRECTED debounce function defined above
                textarea.addEventListener('blur', debounce(function() {
                    const transactionId = this.dataset.id; // 'this' should now be correct

                    // Keep this essential check
                    if (typeof transactionId === 'undefined' || transactionId === null || transactionId === '') {
                        console.error("Comment blur event: Transaction ID is missing or empty on element.", this);
                        // You might want to show an error in the status span for this specific row
                        // but finding the span without the ID is tricky. Maybe find parent row first.
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


             // --- Filter Setup ---
             const filterIcons = document.querySelectorAll('.filter-icon');
             const allFilterInputs = document.querySelectorAll('.filter-input');
             const textFilterInputs = document.querySelectorAll('.filter-input:not(.date-filter)');
             const dateRangeInput = document.getElementById('filter-date-range');
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
                    if (container.classList.contains('active')) {
                        container.querySelector('.filter-input')?.focus(); // Auto-focus input
                    }
                });
            });

            textFilterInputs.forEach(input => {
                input.addEventListener('input', debouncedFilter);
                 input.addEventListener('keydown', (event) => { // Allow Enter key to filter immediately
                    if (event.key === 'Enter') {
                        filterTable(); // Filter immediately on Enter
                    }
                 });
            });

            // Close filter popups when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.filter-input-container') && !event.target.matches('.filter-icon')) {
                    document.querySelectorAll('.filter-input-container.active').forEach(container => {
                        container.classList.remove('active');
                    });
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

            // Initialize Flatpickr for date range filtering
            window.flatpickrInstanceDateRange = flatpickr("#filter-date-range", {
                 mode: "range",
                 dateFormat: "Y-m-d", // ISO 8601 for easier comparison
                 altInput: true, // Human-readable format
                 altFormat: "j M Y",
                 onClose: function(selectedDates, dateStr, instance) {
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
                     filterTable(); // Apply filter
                 },
                 "locale": {
                    "rangeSeparator": ' to ' // Custom separator
                 }
            });

            // *** Financial Year Filter Dropdown Logic ***
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
                        // Set Flatpickr to the selected range (don't trigger onClose immediately)
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
            } else {
                console.error("Financial Year select or Flatpickr instance not found.");
            }
            // *** End FY Filter Logic ***


            // === MODIFIED: Invoice Action Link Logic (using event delegation) ===
            const tableBody = document.getElementById('transaction-data');

            function updateInvoiceStatusSpan(transactionId, message, statusClass = '') {
                const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
                const statusSpan = row?.querySelector('.invoice-status');
                if (statusSpan) {
                    statusSpan.textContent = message;
                    statusSpan.className = `invoice-status ${statusClass}`; // Reset and add
                }
            }

            // Listener for clicks on action links within the table body
            tableBody.addEventListener('click', function(event) {
                const targetLink = event.target.closest('a'); // Find the nearest parent anchor tag
                if (!targetLink) return; // Exit if the click wasn't on or within an anchor

                const actionsCell = targetLink.closest('.invoice-actions');
                if (!actionsCell) return; // Exit if not within the invoice actions cell

                const transactionId = actionsCell.dataset.transactionId;
                const row = actionsCell.closest('tr');
                const wasComplete = row.classList.contains('transaction-complete');
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
                                // Replace View/Replace/Delete links with Attach link
                                actionsCell.innerHTML = `
                                    <a href="#" class="link-attach" title="Attach Invoice">Attach</a>
                                    <input type="file" name="invoice_file_${transactionId}" class="hidden-invoice-input" data-id="${transactionId}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                    <span class="invoice-status"></span>
                                `;
                                showToast('Invoice Deleted', 'success');

                                // Update completion status and stats
                                const isNowComplete = checkAndApplyRowCompletion(transactionId); // Will return false
                                console.log(`After Delete - Row ${transactionId} complete: ${isNowComplete}, Was complete: ${wasComplete}`);
                                if (wasComplete) { // Only decrease count if it *was* complete
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
                            showToast(`Error: ${error.message}`, 'error', 5000);
                            setTimeout(() => updateInvoiceStatusSpan(transactionId, ''), 3000);
                        });
                    }
                }

                // Note: The "View" link (<a class="link-view" href="..." target="_blank">)
                // does not need explicit JS handling here, as its default behavior is correct.
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

                        fetch('upload_invoice.php', { // Your endpoint for uploading
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success' && data.filepath) {
                                updateInvoiceStatusSpan(transactionId, ''); // Clear status
                                // Update the links to View | Replace | Delete
                                // Use data.filepath for the View link href
                                const safeFilepath = encodeURIComponent(data.filepath); // Ensure filename is URL-safe if using relative paths
                                const viewUrl = 'uploads/' + safeFilepath; // Adjust base path as needed
                                actionsCell.innerHTML = `
                                    <a href="view_invoice.php?tx_id=${transactionId}" target="_blank" class="link-view" title="View Invoice">View</a>
                                    <span class="link-separator">|</span>
                                    <a href="#" class="link-delete" title="Delete Invoice">Delete</a>
                                    <input type="file" name="invoice_file_${transactionId}" class="hidden-invoice-input" data-id="${transactionId}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
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
                                    // This shouldn't happen if upload succeeds, but good to check
                                    currentCompleteCount--;
                                    updateStatsDisplay();
                                } else {
                                    // If status didn't change (e.g., replacing an existing invoice),
                                    // update stats anyway to ensure consistency, though counts might be same.
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
            // === END Invoice Action Link Logic ===


            // *** Category Dropdown Logic ***
            const allCategorySelects = document.querySelectorAll('.category-select');

            function updateCategoryStatus(transactionId, message, statusClass = '') {
                const row = document.querySelector(`tr[data-transaction-row-id='${transactionId}']`);
                const statusSpan = row?.querySelector('.category-status');
                if (statusSpan) {
                    statusSpan.textContent = message;
                    statusSpan.className = `category-status ${statusClass}`; // Reset and add
                }
            }

            // Replace the saveCategorySelection function in index.php with this version:

function saveCategorySelection(transactionId, categoryId, selectElement) {
    const row = selectElement.closest('tr');
    const wasComplete = row.classList.contains('transaction-complete');
    const originalSelection = selectElement.dataset.currentSelection || '';

    updateCategoryStatus(transactionId, 'Saving...', 'status-saving');

    // Create JSON data instead of FormData
    const jsonData = {
        transaction_id: transactionId,
        category_id: categoryId // Can be empty string if "-- Select --" is chosen
    };

    // Add a small delay before fetch to allow UI update
    setTimeout(() => {
        fetch('save_category.php', { // Your endpoint for saving category
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
                    // Status didn't change, update stats anyway for consistency
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
                        // If a different existing category (or '-- Select --') is chosen
                        saveCategorySelection(transactionId, selectedValue, this);
                    }
                });
            });
            // *** End Category Dropdown Logic ***

            // --- Initial setup calls ---
            filterTable(); // Apply any default/persisted filters on load
            updateTotals(); // Initialize totals
            updateStatsDisplay(); // Display initial stats

        }); // End DOMContentLoaded
    </script>
    </body>
</html>