<?php
// Mark this as an API endpoint with JSON responses
define('API_AUTH_CHECK', true);

require_once __DIR__ . '/includes/auth.php';

// --- Request Validation & Input Reading ---
$response = ['status' => 'error', 'message' => 'An unknown error occurred processing the upload.']; // Default error
$http_status_code = 500; // Default to server error unless specific validation fails

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
    error_log("[Upload Invoice Request Error] Method not POST.");
} else {
    // --- Database Connection ---
    require_once __DIR__ . '/includes/db_config.php'; // Needs to happen before DB usage
    if (!isset($pdo)) {
        $http_status_code = 500; // Internal Server Error
        $response['message'] = 'Server configuration error: Database connection failed.';
        error_log("[Upload Invoice DB Error] PDO object not available before validation.");
        // Exit early if DB isn't working
        http_response_code($http_status_code); echo json_encode($response); exit;
    }

    // --- Input Validation (Using $_POST and $_FILES) ---
    $transaction_id_raw = $_POST['transaction_id'] ?? null;
    $transaction_id = filter_var($transaction_id_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    // Check if file is properly uploaded
    $is_file_present = isset($_FILES['invoice_file']) && 
                       isset($_FILES['invoice_file']['tmp_name']) && 
                       is_uploaded_file($_FILES['invoice_file']['tmp_name']);
    
    $upload_error = isset($_FILES['invoice_file']) ? $_FILES['invoice_file']['error'] : UPLOAD_ERR_NO_FILE;

    if ($transaction_id === false || $transaction_id === null) {
        $http_status_code = 400; // Bad Request
        $response['message'] = "Invalid or missing Transaction ID.";
        error_log("[Upload Invoice Validation Error] Invalid transaction_id in POST: " . $transaction_id_raw);
    } elseif (!$is_file_present || $upload_error !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.',
        ];
        $response['message'] = "Invoice upload error: " . ($upload_errors[$upload_error] ?? 'Unknown upload error');
        $http_status_code = 400; // Bad Request
        error_log("[Upload Invoice Validation Error] Upload Error Code: {$upload_error} for potential TX ID {$transaction_id_raw}");
    } else {
        // --- Passed Initial Validation - Proceed with File Checks & Processing ---
        $file = $_FILES['invoice_file'];
        $original_filename = basename($file['name']); // Use basename for security
        $tmp_path = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

        // Configuration Constants
        define('INVOICE_UPLOAD_REL_DIR', 'uploads/invoices/'); // Reinstating invoices subfolder
        define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB limit
        define('ALLOWED_EXTENSIONS', ['webp', 'pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx']);

        if ($file_size > MAX_FILE_SIZE) {
            $http_status_code = 400; $response['message'] = "Invoice file too large (Max: " . (MAX_FILE_SIZE / 1024 / 1024) . " MB).";
            error_log("[Upload Invoice Validation Error] File too large ({$file_size} bytes) for TX ID {$transaction_id}");
        } elseif (!in_array($file_ext, ALLOWED_EXTENSIONS)) {
            $http_status_code = 400; $response['message'] = "Invalid invoice file type ('{$file_ext}'). Allowed: " . implode(', ', ALLOWED_EXTENSIONS);
            error_log("[Upload Invoice Validation Error] Invalid extension '{$file_ext}' for TX ID {$transaction_id}");
        } else {
            // --- File Storage Logic with Year/Month Structure ---
            $year = date('Y');
            $month = date('m');
            
            // Base path for all uploads
            $upload_base_absolute = __DIR__ . '/' . rtrim(INVOICE_UPLOAD_REL_DIR, '/');
            
            // Create the year/month nested directories
            $target_subdir_absolute = $upload_base_absolute . '/' . $year . '/' . $month;
            $target_subdir_relative = rtrim(INVOICE_UPLOAD_REL_DIR, '/') . '/' . $year . '/' . $month; // For DB storage
            
            error_log("[Upload Invoice] Attempting to use directory: {$target_subdir_absolute}");

            // Create directories if they don't exist (recursive)
            if (!file_exists($target_subdir_absolute)) {
                if (!mkdir($target_subdir_absolute, 0755, true)) {
                    $response['message'] = "Server error: Failed to create upload directory structure.";
                    error_log("[Upload Invoice File System Error] Failed to create directory: {$target_subdir_absolute}");
                    $http_status_code = 500;
                    http_response_code($http_status_code);
                    echo json_encode($response);
                    exit;
                }
                error_log("[Upload Invoice] Created directory structure: {$target_subdir_absolute}");
            }
            
            if (!is_writable($target_subdir_absolute)) {
                $response['message'] = "Server error: Upload directory not writable. Please check permissions.";
                error_log("[Upload Invoice File System Error] Directory not writable: {$target_subdir_absolute}");
                $http_status_code = 500;
                http_response_code($http_status_code);
                echo json_encode($response);
                exit;
            }

            // Directory OK - Create unique filename
            $safe_original_filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $original_filename);
            $new_filename = "tx_" . $transaction_id . "_" . time() . "_" . $safe_original_filename;
            $destination_path_absolute = $target_subdir_absolute . '/' . $new_filename;
            $relative_path_for_db = $target_subdir_relative . '/' . $new_filename; // Store this path

            // --- Move the Uploaded File ---
            if (move_uploaded_file($tmp_path, $destination_path_absolute)) {
                error_log("[Upload Invoice] File moved successfully to: {$destination_path_absolute}");
                
                // Make sure the file is readable by the web server
                chmod($destination_path_absolute, 0644);
                
                // --- Update Database ---
                try {
                    $sql = "UPDATE transactions SET invoice_path = :path WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':path', $relative_path_for_db, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $http_status_code = 200; // OK
                        $response['status'] = 'success';
                        $response['message'] = 'Invoice uploaded and linked successfully.';
                        $response['filepath'] = $relative_path_for_db; // Send full relative path for DB
                        error_log("[Upload Invoice] DB updated successfully for TX ID: {$transaction_id}, Path: {$relative_path_for_db}");
                    } else {
                        $http_status_code = 500;
                        $response['message'] = "Server error: Failed to link invoice in database.";
                        error_log("[Upload Invoice DB Error] stmt->execute() failed for TX ID: {$transaction_id}. Info: " . print_r($stmt->errorInfo(), true));
                        unlink($destination_path_absolute); // Delete orphan file
                    }
                } catch (\PDOException $e) {
                    unlink($destination_path_absolute); // Delete orphan file if DB fails
                    $http_status_code = 500;
                    $response['message'] = "Server error: Database error linking invoice.";
                    error_log("[Upload Invoice DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
                } // End DB catch
            } else { // move_uploaded_file failed
                $http_status_code = 500;
                $response['message'] = "Server error: Failed to save uploaded file.";
                error_log("[Upload Invoice File System Error] move_uploaded_file() failed. From: {$tmp_path}, To: {$destination_path_absolute}, TX ID: {$transaction_id}");
            } // End move_uploaded_file
        } // End size/extension validation checks
    } // End initial ID/File validation checks
} // End POST check

// --- Send Final JSON Response ---
http_response_code($http_status_code);
echo json_encode($response);
exit;
?>