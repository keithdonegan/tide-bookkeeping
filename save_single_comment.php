<?php
// Mark this as an API endpoint with JSON responses
define('API_AUTH_CHECK', true);

require_once __DIR__ . '/includes/auth.php';

// --- Request Validation & Input Reading ---
$response = ['status' => 'error', 'message' => 'Invalid request.']; // Default error response
$http_status_code = 400; // Bad Request

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
    error_log('[Save Comment Request Error] Method not POST.');
} else {
    // --- Read data consistently from $_POST ---
    error_log('[Save Comment] Received POST data: ' . print_r($_POST, true)); // Log what PHP received

    // Validate Transaction ID from $_POST
    $transaction_id_raw = $_POST['transaction_id'] ?? null; // Use null coalescing operator
    // Validate as a positive integer
    $transaction_id = filter_var($transaction_id_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    // Validate Comment from $_POST (allow empty string, check if key exists)
    $comment_key_exists = array_key_exists('comments', $_POST); // Check key name from JS FormData
    $comment_text = $comment_key_exists ? trim((string)$_POST['comments']) : null; // Use 'comments' key from JS

    if ($transaction_id === false || $transaction_id === null) {
        $response['message'] = 'Invalid or missing Transaction ID.';
        error_log('[Save Comment Validation Error] Invalid transaction_id in POST: ' . $transaction_id_raw);
    } elseif (!$comment_key_exists) {
        $response['message'] = 'Comment data missing in request.';
        error_log('[Save Comment Validation Error] comments key missing in POST.');
        $transaction_id = false; // Invalidate to skip DB logic
    } else {
        // If basic validation passed, proceed to database logic

        // --- Database Logic ---
        require_once __DIR__ . '/includes/db_config.php'; // Ensure path is correct

        if (!isset($pdo)) {
            $http_status_code = 500; // Internal Server Error
            $response['message'] = 'Database connection failed.';
            error_log("[Save Comment DB Error] PDO object not available.");
        } else {
            // Data seems valid, attempt database update
            error_log("[Save Comment] Attempting update for TX ID: {$transaction_id}");
            try {
                $sql = "UPDATE transactions SET comments = :comment WHERE id = :id";
                $stmt = $pdo->prepare($sql);

                // Bind parameters (handle empty comment string as NULL in DB)
                $stmt->bindValue(':comment', $comment_text === '' ? null : $comment_text, $comment_text === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // Success
                    $http_status_code = 200; // OK
                    $response = ['status' => 'success', 'message' => 'Comment saved.'];
                    error_log("[Save Comment] Success for TX ID: {$transaction_id}");
                } else {
                    // Update failed for unknown database reason
                    $http_status_code = 500;
                    $response['message'] = 'Failed to save comment in database.';
                    error_log("[Save Comment DB Error] Update execute returned false for TX ID: {$transaction_id}. Info: " . print_r($stmt->errorInfo(), true));
                }
            } catch (\PDOException $e) {
                $http_status_code = 500; // Internal Server Error
                $response['message'] = 'Database error occurred saving comment.';
                error_log("[Save Comment DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
            }
        } // End DB connection check
    } // End validation check
} // End POST check

// --- Send Final JSON Response ---
http_response_code($http_status_code);
echo json_encode($response);
exit;
?>