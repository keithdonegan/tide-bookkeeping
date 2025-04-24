<?php
// Mark this as an API endpoint with JSON responses
define('API_AUTH_CHECK', true);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

// Default response
$response = ['status' => 'error', 'message' => 'Invalid request.'];
$http_status_code = 400; // Bad Request

// Check request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine content type and read data accordingly
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // Get JSON data from the request body sent by fetch
        $json_data = file_get_contents('php://input');
        error_log("[Save Category] Raw Input: " . $json_data); // Log raw input for debugging
        $data = json_decode($json_data, true); // Decode as associative array
    } else {
        // Handle form data
        $data = $_POST;
        error_log("[Save Category] Form data: " . print_r($_POST, true));
    }

    // Validate input
    $transaction_id = filter_var($data['transaction_id'] ?? null, FILTER_VALIDATE_INT);
    // Use array_key_exists for robust check if key was sent, even if value is null
    $category_id_key_exists = isset($data['category_id']);
    $category_id_received = $data['category_id'] ?? null; // Get value if key exists
    $category_id = null; // Default to NULL

    // Check if transaction_id is valid first
    if ($transaction_id === false) {
        $response['message'] = 'Invalid or missing Transaction ID.';
        error_log("[Save Category Error] Invalid transaction_id.");
    }
    // Now check category_id *only if* transaction_id was okay
    elseif (!$category_id_key_exists) {
        // The key itself was missing from the JSON sent by JS
        $response['message'] = 'Category ID key missing in request.';
        error_log("[Save Category Error] category_id key was missing.");
        $transaction_id = false; // Invalidate request
    } elseif ($category_id_received === null || $category_id_received === '') {
        // Key exists, value is null or empty string -> Save NULL to DB
        $category_id = null;
    } elseif (filter_var($category_id_received, FILTER_VALIDATE_INT) !== false) {
        // Key exists, value is a valid integer
        $category_id = (int)$category_id_received;
    } else {
        // Key exists, but value is invalid (not null, not empty, not int)
        $response['message'] = 'Invalid Category ID provided.';
        error_log("[Save Category Error] Invalid category_id value type: " . print_r($category_id_received, true));
        $transaction_id = false; // Invalidate request
    }

    // Proceed only if validation passed (transaction_id is still integer)
    if ($transaction_id !== false) {
        if (!isset($pdo)) {
             $http_status_code = 500;
             $response['message'] = 'Database connection failed.';
             error_log("[Save Category Error] PDO object not available.");
        } else {
            // Data seems valid, attempt database update
            try {
                $sql = "UPDATE transactions SET category_id = :category_id WHERE id = :id";
                $stmt = $pdo->prepare($sql);

                // Bind parameters (handle NULL correctly for category_id)
                $stmt->bindValue(':category_id', $category_id, $category_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // Success
                    $http_status_code = 200; // OK
                    $response = ['status' => 'success', 'message' => 'Category saved.'];
                } else {
                    // Update failed for unknown reason
                    $http_status_code = 500;
                    $response['message'] = 'Failed to update category in database.';
                    error_log("[Save Category Error] Update execute failed for TX ID: {$transaction_id}");
                }
            } catch (\PDOException $e) {
                $http_status_code = 500; // Internal Server Error
                 if (strpos($e->getMessage(), 'FOREIGN KEY constraint') !== false) {
                      $response['message'] = 'Invalid category selected (does not exist).';
                      $http_status_code = 400; // Bad request
                 } else {
                      $response['message'] = 'Database error occurred saving category.';
                 }
                error_log("[Save Category DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
            }
        } // end if $pdo check
    } // end if validation passed ($transaction_id !== false)

} else { // Not POST
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
}

// --- Send Final JSON Response ---
http_response_code($http_status_code);
echo json_encode($response);
exit;
?>