<?php
session_start(); // Optional: For potential future authentication

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST THIS LINE AS NEEDED

// Default response
$response = ['status' => 'error', 'message' => 'Invalid request.'];
$http_status_code = 400; // Bad Request

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from the request body
    $json_data = file_get_contents('php://input');
    $changed_comments = json_decode($json_data, true); // Decode as associative array

    // Validate decoded data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Invalid JSON data received.';
        error_log("[Save All Comments Error] Invalid JSON: " . json_last_error_msg());
    } elseif (!is_array($changed_comments)) {
        $response['message'] = 'Expected an array of comments.';
        error_log("[Save All Comments Error] Data is not an array.");
    } elseif (empty($changed_comments)) {
        $http_status_code = 200; // Not an error, just nothing to save
        $response = ['status' => 'success', 'message' => 'No changes submitted.', 'updated_count' => 0];
    } elseif (!isset($pdo)) {
        // Check if DB connection exists
        $http_status_code = 500; // Internal Server Error
        $response['message'] = 'Database connection failed.';
        error_log("[Save All Comments Error] PDO object not available.");
    } else {
        // Proceed with database update
        $updated_count = 0;
        $error_occurred = false;

        try {
            $pdo->beginTransaction();
            // Prepare statement outside the loop for efficiency
            $sql = "UPDATE transactions SET comments = :comment WHERE id = :id";
            $stmt = $pdo->prepare($sql);

            foreach ($changed_comments as $id => $comment) {
                // Basic validation for each item
                if (!filter_var($id, FILTER_VALIDATE_INT)) {
                    error_log("[Save All Comments Warning] Invalid Transaction ID skipped: " . $id);
                    continue; // Skip invalid IDs
                }
                $transaction_id = (int)$id;
                $comment_text = trim((string)$comment); // Trim and cast to string

                // Bind parameters
                 // Bind NULL if comment is empty, otherwise bind the string
                $stmt->bindValue(':comment', $comment_text === '' ? null : $comment_text, $comment_text === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

                // Execute update
                if ($stmt->execute()) {
                    // Check if any row was actually affected (optional, UPDATE might succeed with 0 rows affected if data is the same)
                    // For simplicity, we count successful executions
                    $updated_count++;
                } else {
                    // This case might be rare if execute throws PDOException on major errors
                    error_log("[Save All Comments Warning] stmt->execute() returned false for TX ID: {$transaction_id}");
                }
            } // end foreach

            // If loop finished without exceptions, commit
            $pdo->commit();
            $http_status_code = 200; // OK
            $response = ['status' => 'success', 'message' => "Successfully saved {$updated_count} comments.", 'updated_count' => $updated_count];

        } catch (\PDOException $e) {
            // Rollback on any database error during the loop or commit
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $http_status_code = 500; // Internal Server Error
            $response['message'] = 'Database error occurred during save.';
            error_log("[Save All Comments DB Error] PDOException: " . $e->getMessage());
            // Potentially add more context about which ID failed if needed
        } catch (\Exception $e) {
             // Catch other potential errors
             if ($pdo->inTransaction()) { $pdo->rollBack(); } // Attempt rollback
             $http_status_code = 500;
             $response['message'] = 'An unexpected error occurred.';
             error_log("[Save All Comments General Error] Exception: " . $e->getMessage());
        }
    } // end else $pdo exists
} else { // Not a POST request
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
}

// Send the JSON response back to the JavaScript
http_response_code($http_status_code);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>