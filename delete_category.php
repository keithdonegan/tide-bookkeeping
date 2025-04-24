<?php
define('API_AUTH_CHECK', true);

// !!! IMPORTANT: Ensure this path is correct !!!
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

// Default response
$response = ['status' => 'error', 'message' => 'Invalid request.'];
$http_status_code = 400;

// Check request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from the request body
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    // Validate input
    $category_id = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);

    if ($category_id === false || $category_id <= 0) { // Check if ID is a positive integer
        $response['message'] = 'Invalid or missing Category ID.';
        error_log("[Delete Category Error] Invalid or missing category_id in POST data: " . $json_data);
    } elseif (!isset($pdo)) {
         $http_status_code = 500;
         $response['message'] = 'Database connection failed.';
         error_log("[Delete Category Error] PDO object not available.");
    } else {
        // Proceed with deletion
        try {
            $sql = "DELETE FROM categories WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Check if any row was actually deleted
                if ($stmt->rowCount() > 0) {
                    $http_status_code = 200; // OK
                    $response = ['status' => 'success', 'message' => 'Category deleted successfully.'];
                } else {
                     $http_status_code = 404; // Not Found
                     $response['message'] = 'Category not found or already deleted.';
                     error_log("[Delete Category Error] Category ID not found: {$category_id}");
                }
            } else {
                // Execute failed for unknown reason
                $http_status_code = 500;
                $response['message'] = 'Failed to execute database deletion.';
                error_log("[Delete Category Error] Delete execute failed for ID: {$category_id}");
            }
        } catch (\PDOException $e) {
             $http_status_code = 500;
             $response['message'] = 'Database error occurred during deletion.';
             // Detailed error log
             error_log("[Delete Category DB Error] PDOException for ID {$category_id}: " . $e->getMessage());
        }
    }
} else { // Not POST
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
}

// Send JSON response
http_response_code($http_status_code);
echo json_encode($response);
exit;
?>