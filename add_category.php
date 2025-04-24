<?php
// Mark this as an API endpoint with JSON responses
define('API_AUTH_CHECK', true);

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/auth.php'; 
require_once __DIR__ . '/includes/db_config.php';

// Default response
$response = ['status' => 'error', 'message' => 'Invalid request.'];
$http_status_code = 400; // Bad Request

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle both JSON and form data
        if (isset($_POST['category_name'])) {
            // Regular form data
            $category_name = trim($_POST['category_name']);
        } else {
            // Try to read JSON data
            $json_data = file_get_contents('php://input');
            $data = json_decode($json_data, true); // Decode as associative array
            $category_name = isset($data['name']) ? trim($data['name']) : '';
        }

        // Validate input
        if (empty($category_name)) {
            $response['message'] = 'Invalid or missing category name.';
            error_log("[Add Category Error] Empty category name.");
        } elseif (strlen($category_name) > 100) { // Check length against DB column size
            $response['message'] = 'Category name is too long (max 100 chars).';
        } elseif (!isset($pdo)) {
             // Check if DB connection exists (from db_config.php)
             $http_status_code = 500; // Internal Server Error
             $response['message'] = 'Database connection failed.';
             error_log("[Add Category Error] PDO object not available.");
        } else {
            try {
                // Check if category already exists (case-insensitive recommended)
                $sql_check = "SELECT id, name FROM categories WHERE LOWER(name) = LOWER(:name)";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->bindParam(':name', $category_name, PDO::PARAM_STR);
                $stmt_check->execute();
                $existing = $stmt_check->fetch();

                if ($existing) {
                    // Category already exists, return existing data successfully
                    $http_status_code = 200; // OK
                    $response = [
                        'status' => 'success',
                        'message' => 'Category already exists.',
                        'id' => $existing['id'],
                        'name' => $existing['name'] // Return the exact name from DB
                    ];
                } else {
                    // Insert the new category
                    $sql_insert = "INSERT INTO categories (name) VALUES (:name)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->bindParam(':name', $category_name, PDO::PARAM_STR);

                    if ($stmt_insert->execute()) {
                        $new_id = $pdo->lastInsertId();
                        $http_status_code = 201; // Created
                        $response = [
                            'status' => 'success',
                            'message' => 'Category added successfully.',
                            'id' => $new_id,
                            'name' => $category_name // Return the name we inserted
                        ];
                    } else {
                        // Insert failed for unknown reason
                        $http_status_code = 500;
                        $response['message'] = 'Failed to insert new category into database.';
                        error_log("[Add Category Error] Insert execute failed.");
                    }
                }
            } catch (\PDOException $e) {
                $http_status_code = 500; // Internal Server Error
                // Check specifically for unique constraint violation (error code 23000 or 1062)
                 if ($e->getCode() == '23000' || $e->getCode() == 1062) {
                     $response['message'] = 'Category name already exists.'; // More specific error
                     $http_status_code = 409; // Conflict
                } else {
                    $response['message'] = 'Database error occurred while adding category.';
                }
                error_log("[Add Category DB Error] PDOException: " . $e->getMessage());
            }
        }
    } else { // Not a POST request
        $http_status_code = 405; // Method Not Allowed
        $response['message'] = 'Invalid request method.';
    }
} catch (Exception $e) {
    // Catch any unexpected errors to ensure we always return JSON
    $http_status_code = 500;
    $response['message'] = 'Unexpected server error occurred.';
    error_log("[Add Category Critical Error] " . $e->getMessage());
}

// --- Send Final JSON Response ---
http_response_code($http_status_code);
echo json_encode($response);
exit;
?>