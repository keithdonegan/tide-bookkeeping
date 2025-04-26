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
    // Get JSON data from the request body
    $json_data = file_get_contents('php://input');
    error_log("[Save Column Widths] Raw Input: " . $json_data);
    $data = json_decode($json_data, true);
    
    // Validate input
    if (!isset($data['column_widths']) || !is_array($data['column_widths'])) {
        $response['message'] = 'Invalid or missing column width data.';
        error_log("[Save Column Widths Error] Invalid column_widths data.");
    } elseif (!isset($_SESSION['user_id'])) {
        $response['message'] = 'User not authenticated.';
        error_log("[Save Column Widths Error] User not authenticated.");
    } elseif (!isset($pdo)) {
        $http_status_code = 500;
        $response['message'] = 'Database connection failed.';
        error_log("[Save Column Widths Error] PDO object not available.");
    } else {
        $user_id = $_SESSION['user_id'];
        $preference_type = 'column_widths';
        $preference_data = json_encode($data['column_widths']);
        
        try {
            // Check if preference already exists
            $check_sql = "SELECT id FROM user_preferences WHERE user_id = :user_id AND preference_type = :preference_type";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $check_stmt->bindParam(':preference_type', $preference_type, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing preference
                $sql = "UPDATE user_preferences SET preference_data = :preference_data WHERE user_id = :user_id AND preference_type = :preference_type";
            } else {
                // Insert new preference
                $sql = "INSERT INTO user_preferences (user_id, preference_type, preference_data) VALUES (:user_id, :preference_type, :preference_data)";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':preference_type', $preference_type, PDO::PARAM_STR);
            $stmt->bindParam(':preference_data', $preference_data, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $http_status_code = 200; // OK
                $response = [
                    'status' => 'success',
                    'message' => 'Column widths saved successfully.'
                ];
                error_log("[Save Column Widths] Success for user ID: {$user_id}");
            } else {
                $http_status_code = 500;
                $response['message'] = 'Failed to save column widths.';
                error_log("[Save Column Widths Error] Update execute failed for user ID: {$user_id}");
            }
        } catch (\PDOException $e) {
            $http_status_code = 500;
            $response['message'] = 'Database error occurred.';
            error_log("[Save Column Widths DB Error] PDOException: " . $e->getMessage());
        }
    }
} else {
    $http_status_code = 405; // Method Not Allowed
    $response['message'] = 'Invalid request method.';
}

// Send JSON response
http_response_code($http_status_code);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>