<?php
session_start();

// 1. Clear all session data from the $_SESSION superglobal
$_SESSION = array();

// 2. If it's desired to kill the session, also delete the session cookie.
// This is an extra security step that ensures the browser forgets the session ID.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finalize the destruction of the session on the server
session_destroy();

// 4. Redirect the user to the login page
header("Location: login.php");
exit();
?>