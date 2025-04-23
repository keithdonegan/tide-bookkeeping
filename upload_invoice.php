<?php
session_start();

// !!! IMPORTANT: Ensure this path is correct for your server structure !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST THIS LINE AS NEEDED

// --- Configuration ---
// Define where to store invoices, RELATIVE to the 'public' directory or web root
// Script will build absolute path using __DIR__ for saving
define('INVOICE_UPLOAD_REL_DIR', 'uploads/invoices/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB limit
define('ALLOWED_EXTENSIONS', ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx']); // Allowed file types

// --- Basic Request and Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[Upload Invoice Error] Invalid request method.");
    header('Location: index.php');
    exit;
}

if (!isset($_POST['transaction_id']) || !filter_var($_POST['transaction_id'], FILTER_VALIDATE_INT)) {
     $_SESSION['error_message'] = "Invalid or missing Transaction ID.";
     error_log("[Upload Invoice Error] Invalid or missing transaction_id POST data.");
     header('Location: index.php');
     exit;
}
$transaction_id = (int)$_POST['transaction_id']; // Use the DB primary key 'id'

// --- File Upload Validation ---
if (!isset($_FILES['invoice_file']) || !is_uploaded_file($_FILES['invoice_file']['tmp_name']) || $_FILES['invoice_file']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [ /* Error map */
        UPLOAD_ERR_INI_SIZE => "File exceeds server's max upload size.", UPLOAD_ERR_FORM_SIZE => "File exceeds form's max size.",
        UPLOAD_ERR_PARTIAL => "File only partially uploaded.", UPLOAD_ERR_NO_FILE => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Server missing temporary folder.", UPLOAD_ERR_CANT_WRITE => "Server failed to write file.",
        UPLOAD_ERR_EXTENSION => "Upload stopped by PHP extension.",
    ];
    $error_code = $_FILES['invoice_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $_SESSION['error_message'] = "Invoice upload error: " . ($upload_errors[$error_code] ?? 'Unknown error');
    error_log("[Upload Invoice Error] Upload Error Code: {$error_code} for transaction ID {$transaction_id}");
    header('Location: index.php');
    exit;
}

$file = $_FILES['invoice_file'];
$original_filename = basename($file['name']);
$tmp_path = $file['tmp_name'];
$file_size = $file['size'];
$file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

if ($file_size > MAX_FILE_SIZE) {
    $_SESSION['error_message'] = "Invoice file is too large (Max: " . (MAX_FILE_SIZE / 1024 / 1024) . " MB).";
    error_log("[Upload Invoice Error] File too large ({$file_size} bytes) for transaction ID {$transaction_id}");
    header('Location: index.php');
    exit;
}

if (!in_array($file_ext, ALLOWED_EXTENSIONS)) {
    $_SESSION['error_message'] = "Invalid invoice file type ('{$file_ext}').";
    error_log("[Upload Invoice Error] Invalid extension '{$file_ext}' for transaction ID {$transaction_id}");
    header('Location: index.php');
    exit;
}

// --- File Storage ---
$year = date('Y');
$month = date('m'); // Month with leading zero (e.g., 04)

// Absolute path to the base directory for saving files
$absolute_base_dir = __DIR__ . '/' . rtrim(INVOICE_UPLOAD_REL_DIR, '/');
// Absolute path to the target subdirectory
$target_subdir_absolute = $absolute_base_dir . '/' . $year . '/' . $month . '/';
// Relative path for storing in DB (relative to web root / this script's dir)
$target_subdir_relative = rtrim(INVOICE_UPLOAD_REL_DIR, '/') . '/' . $year . '/' . $month . '/';


// Create year/month directory if it doesn't exist
if (!is_dir($target_subdir_absolute)) {
    if (!mkdir($target_subdir_absolute, 0775, true)) { // Create recursively
        $_SESSION['error_message'] = "Failed to create upload subdirectory. Check permissions.";
        error_log("[Upload Invoice Error] Failed to create directory recursively: {$target_subdir_absolute}");
        header('Location: index.php');
        exit;
    }
}

if (!is_writable($target_subdir_absolute)) {
     $_SESSION['error_message'] = "Upload subdirectory is not writable.";
     error_log("[Upload Invoice Error] Directory not writable: {$target_subdir_absolute}");
     header('Location: index.php');
     exit;
}

// Create unique filename
$safe_original_filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $original_filename);
$new_filename = "tx_" . $transaction_id . "_" . time() . "_" . $safe_original_filename;
$destination_path_absolute = $target_subdir_absolute . $new_filename;
// Path to store in DB includes subdirs now
$relative_path_for_db = $target_subdir_relative . $new_filename;


// --- Move the Uploaded File ---
if (move_uploaded_file($tmp_path, $destination_path_absolute)) {
    // --- Update Database ---
    if (!isset($pdo)) {
         $_SESSION['error_message'] = "Database connection failed. Cannot save invoice link.";
         error_log("[Upload Invoice Error] PDO object not available for DB update. TX ID: {$transaction_id}");
         unlink($destination_path_absolute); // Attempt to delete orphan file
         header('Location: index.php');
         exit;
    }

    try {
        $sql = "UPDATE transactions SET invoice_path = :path WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':path', $relative_path_for_db, PDO::PARAM_STR);
        $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Invoice '" . htmlspecialchars($original_filename) . "' linked.";
        } else {
            $_SESSION['error_message'] = "Failed to link invoice in database.";
            error_log("[Upload Invoice Error] stmt->execute() failed for TX ID: {$transaction_id}");
             unlink($destination_path_absolute); // Delete orphan file
        }
    } catch (\PDOException $e) {
        unlink($destination_path_absolute); // Delete orphan file if DB fails
        $_SESSION['error_message'] = "Database error linking invoice: " . $e->getCode();
        error_log("[Upload Invoice DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
    } // End catch PDOException

} else { // move_uploaded_file failed
    $_SESSION['error_message'] = "Failed to save uploaded file. Check server permissions or disk space.";
    error_log("[Upload Invoice Error] move_uploaded_file() failed. Dest: {$destination_path_absolute}, TX ID: {$transaction_id}");
}

header('Location: index.php');
exit;
?>