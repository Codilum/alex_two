<?php
require_once 'config/config.php';

// Соединямся с БД
$conn_string = "host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS."";
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

if(isset($_POST['submit']))
{
    $login = "admin2";
    $password = md5(md5(trim($_POST['password'])));
    $office = trim($_POST['office'] ?? '');
    $role = trim($_POST['role'] ?? 'admin');

    $sql = "INSERT INTO users (userlogin, userpassword, userhash, useroffice, userrole) VALUES ($1, $2, $3, $4, $5)";

    if (!pg_query_params($conn, $sql, [$login, $password, '', $office, $role])) {
        echo "Error: ";
    }

    pg_close($conn);
    header("Location: index.php"); 
    exit();
}


?>
