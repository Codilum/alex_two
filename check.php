<?php

require_once 'config/config.php';

$conn_string = "host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS."";
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

if (isset($_COOKIE['userid']) and isset($_COOKIE['hash']))
{
    $query = pg_query($conn, "SELECT * FROM users WHERE userid = '".intval($_COOKIE['userid'])."' LIMIT 1");
    $userdata = pg_fetch_array($query);

    if(($userdata['userhash'] !== $_COOKIE['hash']) or ($userdata['userid'] !== $_COOKIE['userid']))
    {
        setcookie("userid", "", time() - 3600*24*30*12, "/");
        setcookie("hash", "", time() - 3600*24*30*12, "/", null, null, true); // httponly !!!
        print "Хм, что-то не получилось";
    }
    else
    {
        header("Location: main.php"); 
        exit();
    }
}
else
{
    include 'login.php';
}
?>