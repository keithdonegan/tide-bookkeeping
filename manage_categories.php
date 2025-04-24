<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';

$categories = [];
$error_message = null;

if (isset($pdo)) {
    try {
        // Fetch all categories to display
        $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
        $categories = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $error_message = "Error fetching categories: " . $e->getCode();
        error_log("[Manage Categories DB Error] PDOException: " . $e->getMessage());
    }
} else {
    $error_message = "Database connection is not available.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories / Web Amigos Ltd: Tide Books</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 20px; font-size: 14px; line-height: 1.5; background-color: #f8f9fa; color: #212529;}
        h1, h2 { color: #343a40; }
        table { width: 100%; max-width: 600px; border-collapse: collapse; margin-bottom: 20px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #dee2e6; padding: 10px 12px; text-align: left; vertical-align: middle; }
        th { background-color: #e9ecef; font-weight: 600; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        button.delete-btn {
            padding: 4px 8px; font-size: 0.85em; border-radius: 3px; cursor: pointer;
            background-color: #dc3545; color: white; border: 1px solid #dc3545;
        }
        button.delete-btn:hover { background-color: #c82333; border-color: #bd2130; }
        button.delete-btn:disabled { background-color: #6c757d; border-color: #6c757d; cursor: not-allowed; opacity: 0.65; }
        .status-message { margin-left: 10px; font-size: 0.9em; font-weight: bold; }
        .status-success { color: #155724; }
        .status-error { color: #721c24; }
        .nav-link { display: inline-block; margin-bottom: 20px; color: #007bff; text-decoration: none; }
        .nav-link:hover { text-decoration: underline; }
        .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <h1>Manage Categories</h1>

    <p><a href="index.php" class="nav-link">&laquo; Back to Transactions</a></p>

    <?php if ($error_message): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (isset($pdo) && !$error_message): ?>
        <table>
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody id="category-list">
                <?php if (count($categories) > 0): ?>
                    <?php foreach ($categories as $category): ?>
                    <tr id="category-row-<?php echo $category['id']; ?>">
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td>
                            <button type="button" class="delete-btn delete-category-btn"
                                    data-id="<?php echo $category['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                Delete
                            </button>
                             <span class="status-message"></span> </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" style="text-align: center;">No categories found. Add some via the '[ Add New... ]' option in the transaction list.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categoryList = document.getElementById('category-list');

            if (categoryList) {
                categoryList.addEventListener('click', function(event) {
                    if (event.target.classList.contains('delete-category-btn')) {
                        const button = event.target;
                        const categoryId = button.dataset.id;
                        const categoryName = button.dataset.name;
                        const row = document.getElementById(`category-row-${categoryId}`);
                        const statusSpan = row ? row.querySelector('.status-message') : null;

                        // Confirmation dialog
                        if (confirm(`Are you sure you want to delete the category "${categoryName}"?\n\nAny transactions assigned to this category will become uncategorized.`)) {

                            button.disabled = true; // Disable button during request
                            if(statusSpan) statusSpan.textContent = 'Deleting...';
                            if(statusSpan) statusSpan.className = 'status-message'; // Reset color

                            // Send AJAX request to delete script
                            fetch('delete_category.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ category_id: categoryId })
                            })
                            .then(response => {
                                // Check if response is ok (status 200-299)
                                if (!response.ok) {
                                     // Try to get error message from JSON body, fallback to statusText
                                    return response.json().catch(() => null).then(errorData => {
                                        throw new Error(errorData?.message || `HTTP error! Status: ${response.status} ${response.statusText}`);
                                    });
                                }
                                return response.json(); // Parse JSON body of the successful response
                            })
                            .then(data => {
                                if (data.status === 'success') {
                                    // Remove row from table on success
                                    if (row) {
                                        row.style.transition = 'opacity 0.5s ease';
                                        row.style.opacity = '0';
                                        setTimeout(() => row.remove(), 500); // Remove after fade
                                    }
                                    // Note: Dropdowns on index.php won't update until page refresh
                                    alert(`Category "${categoryName}" deleted successfully.`); // Simple feedback
                                } else {
                                    // Handle errors reported by the server script
                                    throw new Error(data.message || 'Unknown error occurred during delete.');
                                }
                            })
                            .catch(error => {
                                console.error('Delete Category Error:', error);
                                if (statusSpan) {
                                    statusSpan.textContent = `Error: ${error.message}`;
                                    statusSpan.className = 'status-message status-error';
                                } else {
                                     alert(`Error deleting category: ${error.message}`);
                                }
                                button.disabled = false; // Re-enable button on error
                            });

                        } // end if confirm
                    } // end if delete button clicked
                }); // end event listener
            } // end if categoryList exists

        }); // end DOMContentLoaded
    </script>

</body>
</html>