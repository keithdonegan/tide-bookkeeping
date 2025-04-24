<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

// --- Basic Request and Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[Add Comment Error] Invalid request method.");
    header('Location: index.php');
    exit;
}

if (!isset($_POST['transaction_id']) || !filter_var($_POST['transaction_id'], FILTER_VALIDATE_INT)) {
     $_SESSION['error_message'] = "Invalid or missing Transaction ID for comment.";
     error_log("[Add Comment Error] Invalid or missing transaction_id POST data.");
     header('Location: index.php');
     exit;
}
$transaction_id = (int)$_POST['transaction_id'];

// Get comment text, trim whitespace. Allow empty comments to clear existing ones.
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// --- Database Update ---
if (!isset($pdo)) {
    $_SESSION['error_message'] = "Database connection failed. Cannot save comment.";
    error_log("[Add Comment Error] PDO object not available for TX ID: {$transaction_id}");
    header('Location: index.php');
    exit;
}

try {
    $sql = "UPDATE transactions SET comments = :comment WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    // Bind NULL if comment is empty, otherwise bind the string
    $stmt->bindValue(':comment', $comment === '' ? null : $comment, $comment === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':id', $transaction_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Comment saved successfully.";
    } else {
         $_SESSION['error_message'] = "Failed to save comment in database.";
         error_log("[Add Comment Error] stmt->execute() returned false for TX ID: {$transaction_id}");
    }

} catch (\PDOException $e) {
    $_SESSION['error_message'] = "Database error saving comment: " . $e->getCode();
    error_log("[Add Comment DB Error] PDOException for TX ID {$transaction_id}: " . $e->getMessage());
}

// Redirect back to the main page
header('Location: index.php');
exit;
?>