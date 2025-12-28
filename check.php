<?php

require_once 'auth_utils.php';

$conn = openDbConnection();
$user = getAuthenticatedUser($conn);

if ($user) {
    header("Location: main.php");
    exit();
}

include 'login.php';
?>
