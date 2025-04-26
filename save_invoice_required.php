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
    error_log("[Save Invoice Required] Raw Input: " . $json_data);
    $data = json_decode($json_data, true);

    // Validate input
    $transaction_id = filter_var($data['transaction_id'] ?? null, FILTER_VALIDATE_INT);
    $invoice_required = isset($data['invoice_required']) ? (bool)$data['invoice_required'] : null;

    if ($transaction_id === false || $transaction_id === null) {
        $response['message'] = 'Invalid or missing Transaction ID.';
        error_log("[Save Invoice Required Error] Invalid transaction_id.");
    } elseif (!isset($data['invoice_required'])) {
        $response['message'] = 'Invoice required flag is missing.';
        error_log("[Save Invoice Required Error] invoice_required key missing.");
    } elseif (!isset($pdo)) {
        $http_status_code = 500;
        $response['message'] = 'Database connection failed.';
        error_log("[Save Invoice Required Error] PDO object not available.");
    } else {
        // Data seems valid, attempt database update
        try {
            $sql = "UPDATE transactions SET invoice_required = :invoice_required WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $invoice_required_value = $invoice_required ? 1 : 0;
            $stmt->bindValue(':invoice_required', $invoice_required_value, PDO::PARAM_INT);
            $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Success
                $http_status_code = 200; // OK
                $response = [
                    'status' => 'success', 
                    'message' => 'Invoice requirement updated.',
                    'invoice_required' => $invoice_required
                ];
                error_log("[Save Invoice Required] Success for TX ID: {$transaction_id}, Value: {$invoice_required_value}");
            } else {
                // Update failed for unknown reason
                $http_status_code = 500;
                $response['message'] = 'Failed to update invoice requirement in database.';
                error_log("[Save Invoice Required Error] Update execute failed for TX ID: {$transaction_id}");
            }
        } catch (\PDOException $e) {
            $http_status_code = 500; // Internal Server Error
            $response['message'] = 'Database error occurred saving invoice requirement.';
            error_log("[Save Invoice Required DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
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