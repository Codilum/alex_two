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

    $sql = "INSERT INTO users (userlogin, userpassword, userhash) VALUES ('$login', '$password', '')";

    if (!pg_query($conn, $sql)) {
        echo "Error: ";
    }

    pg_close($conn);
    header("Location: index.php"); 
    exit();
}


?>
