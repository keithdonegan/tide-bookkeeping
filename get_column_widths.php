<?php
// Mark this as an API endpoint with JSON responses
define('API_AUTH_CHECK', true);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

// Default response
$response = ['status' => 'error', 'message' => 'Failed to retrieve column widths.'];
$http_status_code = 500; // Default to error

// Check if user is authenticated and DB is available
if (!isset($_SESSION['user_id'])) {
    $http_status_code = 401; // Unauthorized
    $response['message'] = 'User not authenticated.';
} elseif (!isset($pdo)) {
    $http_status_code = 500; // Internal Server Error
    $response['message'] = 'Database connection failed.';
} else {
    $user_id = $_SESSION['user_id'];
    $preference_type = 'column_widths';
    
    try {
        $sql = "SELECT preference_data FROM user_preferences WHERE user_id = :user_id AND preference_type = :preference_type";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':preference_type', $preference_type, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch();
        if ($result && !empty($result['preference_data'])) {
            $http_status_code = 200; // OK
            $response = [
                'status' => 'success',
                'message' => 'Column widths retrieved successfully.',
                'column_widths' => json_decode($result['preference_data'], true)
            ];
        } else {
            $http_status_code = 404; // Not found
            $response = ['status' => 'error', 'message' => 'No column width preferences found.'];
        }
    } catch (\PDOException $e) {
        error_log("[Get Column Widths DB Error] PDOException: " . $e->getMessage());
    }
}

// Send JSON response
http_response_code($http_status_code);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>