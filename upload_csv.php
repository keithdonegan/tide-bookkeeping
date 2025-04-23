<?php
session_start();

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST THIS LINE AS NEEDED

// --- Basic File Upload Checks ---
if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_OK         => "No errors.",
        UPLOAD_ERR_INI_SIZE   => "File exceeds server's max upload size.", UPLOAD_ERR_FORM_SIZE  => "File exceeds form's max size.",
        UPLOAD_ERR_PARTIAL    => "File only partially uploaded.", UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Server missing temporary folder.", UPLOAD_ERR_CANT_WRITE => "Server failed to write file.",
        UPLOAD_ERR_EXTENSION  => "Upload stopped by PHP extension.",
    ];
    $error_code = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $_SESSION['error_message'] = "Error uploading CSV file: " . ($upload_errors[$error_code] ?? 'Unknown upload error');
    error_log("[CSV Upload Error] Upload Error Code: " . $error_code);
    header('Location: index.php');
    exit;
}

$file = $_FILES['csv_file'];
$filename = $file['name'];
$tmp_path = $file['tmp_name'];
$file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if ($file_ext !== 'csv') {
    $_SESSION['error_message'] = "Invalid file type. Only CSV files are allowed.";
    header('Location: index.php');
    exit;
}

// --- CSV Parsing Logic ---
$transactions_to_insert = [];
$row_count = 0;
$skipped_rows = 0;
$error_details = [];

$csv_delimiter = ',';

if (($handle = fopen($tmp_path, "rt")) !== FALSE) {

    $header_row = fgetcsv($handle, 2000, $csv_delimiter);
    $row_count++;

    if ($header_row === FALSE || $header_row === null) {
        $_SESSION['error_message'] = "Could not read header row from CSV.";
        fclose($handle);
        error_log("[CSV Upload Error] Failed to read header row. File: " . basename($filename));
        header('Location: index.php');
        exit;
    }

    // Column Indices based on user's CSV data
    $date_col_index = 0;
    $transaction_id_col_index = 1;
    $desc_col_index = 2;
    $money_in_col_index = 6; // "Paid in"
    $money_out_col_index = 7; // "Paid out"

    while (($data = fgetcsv($handle, 2000, $csv_delimiter)) !== FALSE) {
        $row_count++;
        $current_row_for_error = "Row " . $row_count;

        if (empty(array_filter($data, function($value) { return $value !== null && $value !== ''; }))) {
            continue; // Skip blank lines silently
        }

        if (!isset($data[$date_col_index]) || !isset($data[$transaction_id_col_index]) || !isset($data[$desc_col_index]) || !isset($data[$money_in_col_index]) || !isset($data[$money_out_col_index])) {
             $skipped_rows++;
             $error_details[] = $current_row_for_error . ": Skipped due to missing expected columns.";
             continue;
        }

        $raw_date = trim($data[$date_col_index]);
        $raw_transaction_id = trim($data[$transaction_id_col_index]);
        $description = trim($data[$desc_col_index]);

        // Clean Transaction ID
        $cleaned_transaction_id = ltrim($raw_transaction_id, "'");
        if (empty($cleaned_transaction_id)) {
            $skipped_rows++;
            $error_details[] = $current_row_for_error . ": Skipped due to missing or empty Transaction ID.";
            continue;
        }

        // Date Parsing
        $date_format_string = 'Y-m-d H:i:s';
        $transaction_date_obj = date_create_from_format($date_format_string, $raw_date);
        if (!$transaction_date_obj) {
            if (!empty($raw_date)) {
                $skipped_rows++;
                $error_details[] = $current_row_for_error . ": Skipped due to invalid date format ('" . htmlspecialchars($raw_date) . "', expected '" . $date_format_string . "').";
            }
            continue;
        }
        $formatted_date = $transaction_date_obj->format('Y-m-d');

        // Amount Parsing
        $money_in = 0.0;
        $money_out = 0.0;
        try {
            $raw_money_in = trim($data[$money_in_col_index]);
            $raw_money_out = trim($data[$money_out_col_index]);
            $cleaned_money_in = '';
            $cleaned_money_out = '';
            $is_numeric_in = false;
            $is_numeric_out = false;

            if (!empty($raw_money_in)) {
                $comma_removed_in = str_replace(',', '', $raw_money_in);
                if (stripos($comma_removed_in, 'Charge of ') === 0 && preg_match('/Charge of\s*(-?[\d.]+)/', $comma_removed_in, $matches)) {
                     $cleaned_money_in = $matches[1];
                } elseif (preg_match('/[\d.]+/', $comma_removed_in)) {
                    $cleaned_money_in = preg_replace('/[^\d.-]/', '', $comma_removed_in);
                    if (strlen($cleaned_money_in) > 12 && strpos($cleaned_money_in, '.') === false) { $cleaned_money_in = ''; }
                    if ($cleaned_money_in === '-' || $cleaned_money_in === '.' || $cleaned_money_in === '-.') { $cleaned_money_in = ''; }
                }
                 $is_numeric_in = !empty($cleaned_money_in) && is_numeric($cleaned_money_in);
                 $money_in = $is_numeric_in ? (float)$cleaned_money_in : 0.0;
            }

            if (!empty($raw_money_out)) {
                 $comma_removed_out = str_replace(',', '', $raw_money_out);
                 if (stripos($comma_removed_out, 'Charge of ') === 0 && preg_match('/Charge of\s*(-?[\d.]+)/', $comma_removed_out, $matches)) {
                     $cleaned_money_out = $matches[1];
                 } elseif (preg_match('/ - (\d+(\.\d+)?)/', $comma_removed_out, $matches)) {
                     $cleaned_money_out = $matches[1];
                 } elseif (preg_match('/[\d.]+/', $comma_removed_out)) {
                    $cleaned_money_out = preg_replace('/[^\d.-]/', '', $comma_removed_out);
                     if (strlen($cleaned_money_out) > 12 && strpos($cleaned_money_out, '.') === false) { $cleaned_money_out = ''; }
                     if ($cleaned_money_out === '-' || $cleaned_money_out === '.' || $cleaned_money_out === '-.') { $cleaned_money_out = ''; }
                 }
                  $is_numeric_out = !empty($cleaned_money_out) && is_numeric($cleaned_money_out);
                  $money_out = $is_numeric_out ? (float)$cleaned_money_out : 0.0;
            }

        } catch (Exception $e) {
             $skipped_rows++;
             $error_details[] = $current_row_for_error . ": Skipped due to amount processing error - " . $e->getMessage();
             error_log("[CSV Amount Error Row $row_count] Exception: " . $e->getMessage());
             continue;
        }

        if (empty($formatted_date) || empty($description) || empty($cleaned_transaction_id)) {
             if (empty($cleaned_transaction_id)) continue;
             $skipped_rows++;
             $error_details[] = $current_row_for_error . ": Skipped due to missing essential data after processing (Date/Desc/ID).";
             continue;
         }

        // Add to batch for insertion
        $transactions_to_insert[] = [
            'transaction_id' => $cleaned_transaction_id,
            'date' => $formatted_date,
            'description' => $description,
            'paid_in' => $money_in,
            'paid_out' => $money_out
        ];
    } // End while
    fclose($handle);

    // Database Insertion
     if (!empty($transactions_to_insert)) {
        if (!isset($pdo)) {
             $_SESSION['error_message'] = "Database connection is not available.";
             error_log("[CSV Upload Error] PDO object not available.");
        } else {
            $sql = "INSERT IGNORE INTO transactions (transaction_id, transaction_date, description, paid_in, paid_out) VALUES (:transaction_id, :date, :description, :paid_in, :paid_out)";
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare($sql);
                $inserted_count = 0;

                foreach ($transactions_to_insert as $tx) {
                    $stmt->bindParam(':transaction_id', $tx['transaction_id'], PDO::PARAM_STR);
                    $stmt->bindParam(':date', $tx['date'], PDO::PARAM_STR);
                    $stmt->bindParam(':description', $tx['description'], PDO::PARAM_STR);
                    $stmt->bindValue(':paid_in', (string)$tx['paid_in'], PDO::PARAM_STR);
                    $stmt->bindValue(':paid_out', (string)$tx['paid_out'], PDO::PARAM_STR);
                    $stmt->execute();
                    if ($stmt->rowCount() > 0) { $inserted_count++; }
                }
                $pdo->commit();

                $attempted_count = count($transactions_to_insert);
                $ignored_count = $attempted_count - $inserted_count;
                $_SESSION['success_message'] = "Processed $attempted_count transactions. Added $inserted_count new transactions.";
                if ($ignored_count > 0) { $_SESSION['success_message'] .= " Ignored $ignored_count duplicates."; }
                if ($skipped_rows > 0) {
                     $_SESSION['warning_message'] = "Skipped $skipped_rows rows during parsing.";
                     $_SESSION['warning_details'] = $error_details;
                     error_log("[CSV Upload Skipped Rows Summary] File: " . basename($filename) . ", Count: $skipped_rows");
                }

            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $_SESSION['error_message'] = "Database error during insert: " . $e->getCode();
                 error_log("[CSV Upload DB Error] File: " . basename($filename) . ", PDOException: " . $e->getMessage());
                if (!empty($transactions_to_insert)) { $_SESSION['error_message'] .= " Last attempt ID: " . htmlspecialchars($transactions_to_insert[count($transactions_to_insert)-1]['transaction_id'] ?? 'N/A'); }
                 if ($skipped_rows > 0) { $_SESSION['warning_details'] = $error_details; }
            } // End catch
        } // End else pdo exists
    } else { // End if !empty insert array
        $_SESSION['error_message'] = "No valid transactions found in the CSV file after processing.";
         if ($skipped_rows > 0) {
             $_SESSION['error_message'] .= " Skipped $skipped_rows rows.";
             $_SESSION['warning_details'] = $error_details;
             error_log("[CSV Upload No Valid Transactions] File: " . basename($filename) . ", Skipped: $skipped_rows");
         }
    } // End else !empty insert array

} else { // End if fopen
    $_SESSION['error_message'] = "Could not open the uploaded CSV file.";
    error_log("[CSV Upload File Open Error] File: " . basename($filename) . ", Temp Path: " . $tmp_path);
}

header('Location: index.php');
exit;
?>