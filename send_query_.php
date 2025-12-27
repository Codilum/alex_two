<?php
require_once 'config/config.php';

date_default_timezone_set('Europe/Moscow');

if (isset($_POST['phone'])) { $phone = $_POST['phone']; }
if (isset($_POST['site'])) { $site = $_POST['site']; }
if (isset($_POST['comment'])) { $comment = $_POST['comment']; }
if (isset($_POST['date'])) { $date = $_POST['date']; }
if (isset($_COOKIE['userid'])) { $userid = $_COOKIE['userid']; }

if (isset($_POST['phone']) && isset($_POST['site']) && isset($_POST['comment'])) {
    
    $today = date("j M G:i");  
    $comment = $today.' '.$comment; 

    $conn_string = "host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS."";
    $conn = pg_connect($conn_string);

    if (!$conn) {
        die("Connection failed: " . pg_last_error());
    }

    $sql = "INSERT INTO calls (phonenumber, sitedomen, commenttext, nextcalldate, userid) VALUES ('$phone', '$site', '$comment', '$date', '$userid')";

    if (!pg_query($conn, $sql)) {
          echo "Error: " . $sql . "<br>" . pg_last_error();
    }

    pg_close($conn);
	
}

?>