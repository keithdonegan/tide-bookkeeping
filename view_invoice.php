<?php
session_start(); // Optional: For potential future authentication checks

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST PATH AS NEEDED

// Define the base directory where invoices are stored RELATIVE to web root/this script
// This should match the prefix used in $relative_path_for_db in upload_invoice.php
define('INVOICE_REL_BASE_DIR', 'uploads/invoices/');

// --- Input Validation ---
if (!isset($_GET['tx_id']) || !filter_var($_GET['tx_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); die('Error: Invalid or missing Transaction ID.');
}
$transaction_id = (int)$_GET['tx_id'];

// --- Fetch File Path from Database ---
$invoice_path_relative = null; // Path as stored in DB (e.g., uploads/invoices/2025/04/...)
try {
    if (!isset($pdo)) { throw new \RuntimeException("Database connection not available."); }

    $sql = "SELECT invoice_path FROM transactions WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result && !empty($result['invoice_path'])) {
        $invoice_path_relative = $result['invoice_path'];
    } else {
        http_response_code(404);
        error_log("[View Invoice Error] No invoice path found in DB for TX ID: {$transaction_id}");
        die('Error: Invoice not found for this transaction.');
    }
} catch (\Exception $e) { // Catch PDO or Runtime exceptions
    http_response_code(500);
    error_log("[View Invoice DB/Config Error] Exception for TX ID {$transaction_id}: " . $e->getMessage());
    die('Error: Server error occurred while retrieving file information.');
}


// --- Security & File Handling ---
// Construct the full, absolute path to the file on the server's filesystem
// Assumes the stored path is relative to this script's directory (__DIR__)
$full_filepath_absolute = __DIR__ . '/' . $invoice_path_relative;

// Use realpath to resolve '..' etc., and check it exists and is within the expected base dir
$real_filepath = realpath($full_filepath_absolute);
$expected_base_dir_absolute = realpath(__DIR__ . '/' . rtrim(INVOICE_REL_BASE_DIR, '/')); // Resolve base path too

if ($real_filepath === false || !$expected_base_dir_absolute || strpos($real_filepath, $expected_base_dir_absolute) !== 0) {
    http_response_code(404);
    error_log("[View Invoice Error] File not found or invalid path. Relative Path: {$invoice_path_relative}, Resolved Absolute: {$real_filepath}, Expected Base: {$expected_base_dir_absolute}, TX ID: {$transaction_id}");
    die('Error: File record invalid or file missing.');
}

if (!is_readable($real_filepath)) {
    http_response_code(403);
    error_log("[View Invoice Error] File not readable (permissions?): {$real_filepath}, TX ID: {$transaction_id}");
    die('Error: Cannot access file due to permissions.');
}

// --- Determine MIME Type & Send Headers ---
$mime_type = mime_content_type($real_filepath);
if ($mime_type === false) {
    $extension = strtolower(pathinfo($real_filepath, PATHINFO_EXTENSION));
    $mime_map = [
        'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain', 'csv' => 'text/csv', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    $mime_type = $mime_map[$extension] ?? 'application/octet-stream';
}

// Clear output buffer
if (ob_get_level()) { ob_end_clean(); }

// Send Headers
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . basename($invoice_path_relative) . '"'); // Use basename of stored path
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
$filesize = filesize($real_filepath);
if ($filesize !== false) {
    header('Content-Length: ' . $filesize);
}

// --- Output File Content ---
if (!readfile($real_filepath)) {
    // If readfile fails, it often means headers were already sent or file is unreadable mid-stream
    error_log("[View Invoice Error] readfile() failed for: {$real_filepath}, TX ID: {$transaction_id}. Output may be corrupted.");
    // Avoid sending die() message here as headers are likely sent
}
exit; // Ensure script termination

?>