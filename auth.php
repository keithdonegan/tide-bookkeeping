<?php
/**
 * Authentication requirement script
 * 
 * Include this file at the beginning of any page that requires user authentication.
 * Usage: require_once __DIR__ . '/includes/auth_required.php';
 * 
 * Options:
 * - For API endpoints that return JSON, define API_AUTH_CHECK before including this file
 * - For AJAX requests, define AJAX_AUTH_CHECK before including this file
 */

// Always start the session
session_start();

// Check if the user ID session variable is NOT set
if (!isset($_SESSION['user_id'])) {
    if (defined('API_AUTH_CHECK')) {
        // API endpoints should return JSON response with 401 status
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
        exit;
    } elseif (defined('AJAX_AUTH_CHECK')) {
        // AJAX endpoints can return either JSON or text based on what the client expects
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
        }
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
        exit;
    } else {
        // Standard web pages should redirect to login
        header('Location: login.php');
        exit;
    }
}

// Continue execution if user is authenticated
// Optionally, you can include any other session validation logic here
// For example, checking user permissions, account status, etc.