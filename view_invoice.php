<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

// Define the base directory where invoices are stored RELATIVE to web root/this script
// This should match the prefix used in upload_invoice.php
define('INVOICE_REL_BASE_DIR', 'uploads/invoices/');

// --- Input Validation ---
if (!isset($_GET['tx_id']) || !filter_var($_GET['tx_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); 
    die('Error: Invalid or missing Transaction ID.');
}
$transaction_id = (int)$_GET['tx_id'];

// --- Fetch File Path from Database ---
try {
    if (!isset($pdo)) { 
        throw new \RuntimeException("Database connection not available.");
    }

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
} catch (\Exception $e) {
    http_response_code(500);
    error_log("[View Invoice DB Error] Exception for TX ID {$transaction_id}: " . $e->getMessage());
    die('Error: Server error occurred while retrieving file information.');
}

// --- Security & File Handling ---
// Construct the full, absolute path to the file on the server's filesystem
// This path should work regardless of whether invoice_path has the full structure or just the filename
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
$mime_type = function_exists('mime_content_type') ? mime_content_type($real_filepath) : 'application/octet-stream';

// If mime_content_type is not available or returns false, guess based on extension
if ($mime_type === false) {
    $extension = strtolower(pathinfo($real_filepath, PATHINFO_EXTENSION));
    $mime_map = [
        'pdf'   => 'application/pdf',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'   => 'application/vnd.ms-excel',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt'   => 'text/plain',
        'csv'   => 'text/csv',
        'webp'  => 'image/webp'
    ];
    $mime_type = isset($mime_map[$extension]) ? $mime_map[$extension] : 'application/octet-stream';
}

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Send appropriate headers
header('Content-Type: ' . $mime_type);
// For PDFs and images, strongly encourage inline viewing
if (strpos($mime_type, 'image/') === 0 || $mime_type === 'application/pdf') {
    header('Content-Disposition: inline; filename="' . basename($invoice_path_relative) . '"');
} else {
    // For other file types, still try inline but browser may decide to download
    header('Content-Disposition: inline; filename="' . basename($invoice_path_relative) . '"');
}

// Add this header for better browser compatibility
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Send file size if available
$filesize = filesize($real_filepath);
if ($filesize !== false) {
    header('Content-Length: ' . $filesize);
}

// --- Output File Content ---
readfile($real_filepath);
exit;
?>