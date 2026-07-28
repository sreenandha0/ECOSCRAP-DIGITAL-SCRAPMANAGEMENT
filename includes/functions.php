<?php

/**
 * Sanitize User Input
 */
function sanitize($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect
 */
function redirect($location)
{
    header("Location: $location");
    exit();
}

/**
 * Flash Message
 */
function setMessage($type, $message)
{
    $_SESSION['message'] = [
        'type' => $type,
        'text' => $message
    ];
}

/**
 * Display Flash Message
 */
function displayMessage()
{
    if (isset($_SESSION['message'])) {

        $type = $_SESSION['message']['type'];
        $text = $_SESSION['message']['text'];

        echo '<div class="alert alert-' . $type . '">' . $text . '</div>';

        unset($_SESSION['message']);
    }
}

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid request token. Please return to the previous page and try again.');
    }
}
