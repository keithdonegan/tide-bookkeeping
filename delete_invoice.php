<?php
// Mark this as an API endpoint with JSON responses
define('API_AUTH_CHECK', true);

// !!! IMPORTANT: Ensure this path is correct !!!
require_once __DIR__ . '/includes/auth.php';

// --- Request Validation & Input Reading ---
$response = ['status' => 'error', 'message' => 'Invalid request.']; // Default error response
$http_status_code = 400; // Bad Request

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
    error_log('[Delete Invoice Request Error] Method not POST.');
} else {
    // --- Read data consistently from $_POST ---
    error_log('[Delete Invoice] Received POST data: ' . print_r($_POST, true)); // Log what PHP received

    // Validate Transaction ID from $_POST
    $transaction_id_raw = $_POST['transaction_id'] ?? null; // Use null coalescing operator
    // Validate as a positive integer
    $transaction_id = filter_var($transaction_id_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($transaction_id === false || $transaction_id === null) {
        // This is the ONLY place this specific error message should originate now
        $response['message'] = 'Invalid or missing transaction ID.';
        error_log('[Delete Invoice Validation Error] Invalid transaction_id in POST: ' . $transaction_id_raw);
        // $http_status_code remains 400 (set by default)
    } else {
        // If basic validation passed, proceed to database logic

        // --- Database & File Deletion Logic ---
        require_once __DIR__ . '/includes/db_config.php'; // Ensure path is correct

        if (!isset($pdo)) {
            $http_status_code = 500; // Internal Server Error
            $response['message'] = 'Database connection failed.';
            error_log("[Delete Invoice DB Error] PDO object not available for TX ID: {$transaction_id}.");
        } else {
            // --- Proceed with Deletion ---
            $file_deleted_from_server = false;
            $db_updated = false;
            $original_path = null;

            error_log("[Delete Invoice] Attempting actions for TX ID: {$transaction_id}");
            try {
                // 1. Get the current invoice path to delete the file later
                $sql_select = "SELECT invoice_path FROM transactions WHERE id = :id";
                $stmt_select = $pdo->prepare($sql_select);
                $stmt_select->bindParam(':id', $transaction_id, PDO::PARAM_INT);
                $stmt_select->execute();
                $result = $stmt_select->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC

                if ($result && !empty($result['invoice_path'])) {
                    $original_path = $result['invoice_path'];
                    error_log("[Delete Invoice] Found original path: {$original_path} for TX ID: {$transaction_id}");
                } else {
                    error_log("[Delete Invoice] No original invoice path found in DB for TX ID: {$transaction_id}. Only updating DB record.");
                }

                // 2. Update the database to remove the link (SET invoice_path = NULL)
                $sql_update = "UPDATE transactions SET invoice_path = NULL WHERE id = :id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':id', $transaction_id, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    $db_updated = true;
                    error_log("[Delete Invoice] DB record updated successfully for TX ID: {$transaction_id}");

                    // 3. Delete the actual file from the server IF path was found and DB updated
                    if ($original_path) {
                        // *** IMPORTANT: Adjust this path logic based on how paths are stored ***
                        // This example assumes $original_path is relative path like 'uploads/invoices/inv_123.pdf'
                        // And script is running one level above 'uploads' maybe? Adjust base path carefully.
                        $base_path = __DIR__; // Or dirname(__DIR__) if script is in includes/
                        // Construct path relative to the base path where uploads folder resides
                        $potential_filepath = $base_path . '/' . $original_path;

                        // Use realpath cautiously, ensure base path is solid first
                        $absolute_filepath = realpath($potential_filepath);

                        // Log paths for debugging
                        error_log("[Delete Invoice] Base path: {$base_path}");
                        error_log("[Delete Invoice] Original path from DB: {$original_path}");
                        error_log("[Delete Invoice] Constructed path: {$potential_filepath}");
                        error_log("[Delete Invoice] Real path: {$absolute_filepath}");

                        // Security Check: Ensure the file exists and is within an expected uploads directory structure
                        // This example assumes uploads is a direct subdirectory of base_path
                        $expected_base_dir = realpath($base_path . '/uploads'); // More flexible base dir check

                        if (!$expected_base_dir) {
                             error_log("[Delete Invoice Warning] Could not resolve expected base directory: {$base_path}/uploads");
                        } elseif ($absolute_filepath && strpos($absolute_filepath, $expected_base_dir) === 0 && file_exists($absolute_filepath) && is_file($absolute_filepath)) {
                            if (unlink($absolute_filepath)) {
                                $file_deleted_from_server = true;
                                error_log("[Delete Invoice] Successfully deleted file: {$absolute_filepath} for TX ID: {$transaction_id}");
                            } else {
                                 error_log("[Delete Invoice Warning] Failed to delete file (check permissions?): {$absolute_filepath} for TX ID: {$transaction_id}");
                            }
                        } else {
                             error_log("[Delete Invoice Warning] File not found, path mismatch, or not a file. Cannot delete. Path checked: {$absolute_filepath} (derived from {$potential_filepath}) for TX ID: {$transaction_id}");
                        }
                    } // end if original_path

                    // Prepare success response
                    $http_status_code = 200;
                    $response = ['status' => 'success', 'message' => 'Invoice link removed.' . ($file_deleted_from_server ? ' File deleted.' : ($original_path ? ' File NOT deleted (see logs).' : ''))];

                } else { // DB update failed
                    $http_status_code = 500;
                    $response['message'] = 'Failed to update database.';
                    error_log("[Delete Invoice Error] DB update execute() failed for TX ID: {$transaction_id}. Info: " . print_r($stmt_update->errorInfo(), true));
                }

            } catch (\PDOException $e) {
                $http_status_code = 500;
                $response['message'] = 'Database error occurred during deletion.';
                error_log("[Delete Invoice DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
            } catch (\Exception $e) { // Catch potential general errors (e.g., file system)
                $http_status_code = 500;
                $response['message'] = 'An unexpected error occurred during deletion.';
                error_log("[Delete Invoice General Error] Exception for TX ID {$transaction_id}: " . $e->getMessage());
            }
        } // End DB connection check
    } // End validation check
} // End POST check

// --- Send Final JSON Response ---
http_response_code($http_status_code);
// No need to set Content-Type again if already set earlier
echo json_encode($response);
exit;
?>