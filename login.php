<?php
session_start(); // Start session handling

// If user is already logged in, redirect them to the main page
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// !!! IMPORTANT: Ensure this path is correct !!!
require_once __DIR__ . '/includes/db_config.php'; // <-- ADJUST PATH AS NEEDED

$login_error = null; // Variable to hold login error messages

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't trim password yet

    if (empty($username) || empty($password)) {
        $login_error = "Username and password are required.";
    } elseif (!isset($pdo)) {
        $login_error = "Database connection error.";
        error_log("[Login Error] PDO object not available on POST.");
    } else {
        // Database lookup
        try {
            $sql = "SELECT id, username, password_hash FROM users WHERE username = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            // Verify user exists and password is correct
            if ($user && password_verify($password, $user['password_hash'])) {
                // Password matches! Login successful.

                // Regenerate session ID for security
                session_regenerate_id(true);

                // Store user info in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // Redirect to the main application page
                header('Location: index.php');
                exit;

            } else {
                // Invalid credentials
                $login_error = "Invalid username or password.";
                error_log("[Login Failed] Invalid credentials attempt for username: " . $username);
            }

        } catch (\PDOException $e) {
            $login_error = "Database query failed.";
            error_log("[Login DB Error] PDOException: " . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Web Amigos Ltd: Tide Books</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; min-height: 90vh; }
        .login-container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: #343a40; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #495057; }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box;
        }
        .login-button {
            width: 100%; padding: 12px; background-color: #007bff; color: white; border: none;
            border-radius: 4px; font-size: 1em; cursor: pointer; transition: background-color 0.2s ease;
        }
        .login-button:hover { background-color: #0056b3; }
        .error-message { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>

        <?php if ($login_error): ?>
            <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
    </div>
</body>
</html>