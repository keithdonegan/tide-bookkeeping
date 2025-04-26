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
        // old - $stmt_tx = $pdo->query('SELECT id, transaction_id, transaction_date, description, paid_in, paid_out, category_id, invoice_path, comments FROM transactions ORDER BY transaction_date DESC, id DESC');
        $stmt_tx = $pdo->query('SELECT id, transaction_id, transaction_date, description, paid_in, paid_out, category_id, invoice_path, comments, invoice_required FROM transactions ORDER BY transaction_date DESC, id DESC');
        $transactions = $stmt_tx->fetchAll();

        // 5. Calculate Completion Statistics
        // $total_count = count($transactions); $complete_count = 0;
        // if ($total_count > 0) { foreach ($transactions as $tx) { if (!empty($tx['category_id']) && !empty($tx['invoice_path'])) { $complete_count++; } } $needs_completion_count = $total_count - $complete_count; $percentage_complete = ($total_count > 0) ? round(($complete_count / $total_count) * 100) : 0; }
        
        // 5. Calculate Completion Statistics
        $total_count = count($transactions); 
        $complete_count = 0;
        if ($total_count > 0) { 
            foreach ($transactions as $tx) { 
                // Transaction is complete if it has a category AND (has an invoice OR doesn't require one)
                if (!empty($tx['category_id']) && 
                    (!empty($tx['invoice_path']) || (isset($tx['invoice_required']) && $tx['invoice_required'] == 0))) { 
                    $complete_count++; 
                } 
            } 
            $needs_completion_count = $total_count - $complete_count; 
            $percentage_complete = ($total_count > 0) ? round(($complete_count / $total_count) * 100) : 0; 
        }
        
        

    } catch (\PDOException $e) { $error_message = "Error fetching data: " . $e->getCode(); error_log("[Index Page DB Error] PDOException fetching data: " . $e->getMessage()); }
} else { $error_message = "Database connection is not available."; }

// Helper function for date formatting
function format_nice_date($date_string) { if (empty($date_string)) return 'N/A'; $timestamp = strtotime($date_string); return ($timestamp === false) ? 'Invalid Date' : date('jS F Y', $timestamp); }
// --- End PHP Setup ---


// Fetch user's column width preferences if available
$column_widths = [];
if (isset($pdo) && isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $preference_type = 'column_widths';
        
        $sql = "SELECT preference_data FROM user_preferences WHERE user_id = :user_id AND preference_type = :preference_type";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':preference_type', $preference_type, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch();
        if ($result && !empty($result['preference_data'])) {
            $column_widths = json_decode($result['preference_data'], true);
        }
    } catch (\PDOException $e) {
        error_log("[Index Page DB Error] Failed to fetch column widths: " . $e->getMessage());
        // Continue without preferences if error occurs
    }
}
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
        
        /* 
 * 1. Add these CSS styles to your existing style section 
 */

/* Tooltip styles */
.tooltip-cell {
    position: relative;
    cursor: pointer;
}

.tooltip-cell:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 0;
    top: 100%;
    z-index: 100;
    background-color: #333;
    color: #fff;
    padding: 8px 12px;
    border-radius: 4px;
    min-width: 200px;
    max-width: 400px;
    white-space: normal;
    word-wrap: break-word;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    line-height: 1.5;
    font-size: 0.9em;
    animation: fadeIn 0.3s;
}

/* Add a small arrow at the top of the tooltip */
.tooltip-cell:hover::before {
    content: "";
    position: absolute;
    left: 15px;
    top: 100%;
    border-width: 6px;
    border-style: solid;
    border-color: transparent transparent #333 transparent;
    transform: translateY(-6px);
    z-index: 101;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
/* Ensure overflow is hidden in normal state but tooltip shows full text */
.tooltip-cell {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

        

        .no-invoice-required { 
            margin-top: 5px; 
            font-size: 0.9em; 
        }
        .no-invoice-required label { 
            display: flex; 
            align-items: center; 
            cursor: pointer; 
            color: #495057; 
        }
        .no-invoice-required input[type="checkbox"] { 
            margin-right: 5px; 
            cursor: pointer; 
        }
        .invoice-action-wrapper { 
            display: flex; 
            flex-direction: column; 
        }
        
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
        
        /* 
 * 5. CSS for Resizable Columns
 * Add to your style section in index.php
 */
/* --- Resizable Columns Styles --- */
.resizable-table {
    table-layout: fixed;
    width: 100%;
}

.resizer {
    position: absolute;
    top: 0;
    right: -3px; /* Position it slightly to the right of the edge */
    width: 6px; /* Make it wider for easier targeting */
    cursor: col-resize;
    height: 100%;
    background-color: #bdc3c7;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 10; /* Ensure it's above other elements */
}

th:hover .resizer {
    opacity: 0.5;
}

th.resizing {
    cursor: col-resize !important;
    user-select: none;
}

.resizing .resizer {
    opacity: 1 !important;
    background-color: #3498db;
}

/* Fixed width calculation for the table */
.resizable-table {
    table-layout: fixed !important;
    width: 100% !important;
    border-collapse: separate !important; /* This helps with width calculations */
    border-spacing: 0 !important;
}

/* Enhanced table cell control */
.resizable-table {
    table-layout: fixed !important;
    width: 100% !important;
    border-collapse: collapse !important;
}

.resizable-table th, .resizable-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 10px 12px; /* Make sure padding is consistent */
    box-sizing: border-box !important;
}

/* Cells can be made very narrow if needed */
.resizable-table th.very-narrow {
    min-width: 30px !important;
    width: 30px !important;
}

/* Better resizer positioning */
.resizer {
    position: absolute;
    top: 0;
    right: -3px;
    width: 6px;
    cursor: col-resize;
    height: 100%;
    background-color: #bdc3c7;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 10;
}

th:hover .resizer {
    opacity: 0.5;
}

.resizing .resizer {
    opacity: 1 !important;
    background-color: #3498db;
}
        
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
                    
                    <?php 
                        $row_class = (!empty($tx['category_id']) && 
                                     (!empty($tx['invoice_path']) || (isset($tx['invoice_required']) && $tx['invoice_required'] == 0))) 
                                    ? 'transaction-complete' : ''; 
                    ?>
                    
                    <tr data-transaction-row-id="<?php echo $tx['id']; ?>" class="<?php echo $row_class; ?>">
                        <td data-date="<?php echo htmlspecialchars($tx['transaction_date']); ?>"><?php echo htmlspecialchars(date('d/m/Y', strtotime($tx['transaction_date']))); ?></td>
                        <td><span class="txid-style"><?php echo htmlspecialchars($tx['transaction_id']); ?></span></td>
                        <td class="tooltip-cell" data-tooltip="<?php echo htmlspecialchars($tx['description']); ?>">
                            <?php echo htmlspecialchars($tx['description']); ?>
                        </td>
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


                        <td class="actions-cell invoice-actions" data-transaction-id="<?php echo $tx['id']; ?>" data-invoice-required="<?php echo (isset($tx['invoice_required']) && $tx['invoice_required'] == 0) ? '0' : '1'; ?>">
                        <?php if (!empty($tx['invoice_path'])): ?>
                            <a href="view_invoice.php?tx_id=<?php echo (int)$tx['id']; ?>" target="_blank" class="link-view" title="View Invoice">View</a>
                            <span class="link-separator">|</span>
                            <a href="#" class="link-delete" title="Delete Invoice">Delete</a>
                        <?php else: ?>
                        
                        
                            <div class="invoice-action-wrapper">
                                <a href="#" class="link-attach" title="Attach Invoice">Attach</a>
                                <div class="no-invoice-required">
                                    <label>
                                        <input type="checkbox" class="no-invoice-required-checkbox" data-id="<?php echo $tx['id']; ?>" 
                                            <?php echo (isset($tx['invoice_required']) && $tx['invoice_required'] == 0) ? 'checked' : ''; ?>>
                                        <span>No invoice required</span>
                                    </label>
                                </div>
                            </div>
                            
                            
                        <?php endif; ?>
                        <input type="file" name="invoice_file_<?php echo $tx['id']; ?>" class="hidden-invoice-input" data-id="<?php echo $tx['id']; ?>" accept=".webp,.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                        <span class="invoice-status"></span>
                    </td>
                    
                    
                    <!-- Comments cell (make sure this is still present) -->
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


     <div id="toast-container"></div> 
     
     <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
     <script src="tide_books.js"></script>
     
    </body>
</html>