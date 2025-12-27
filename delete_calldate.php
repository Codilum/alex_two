<?php
require_once 'config/config.php';

if (isset($_POST['id'])) {

    $id = $_POST['id'];
    var_dump($_POST);
    
    //$today = date("j M G:i");  
    //$comment = $today.' '.$comment; 

    $conn_string = "host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS."";
    $conn = pg_connect($conn_string);

    if (!$conn) {
        die("Connection failed: " . pg_last_error());
    }

    $sql = "UPDATE calls SET nextcalldate = NULL
            WHERE id='$id'";

    //$sql = "INSERT INTO calls (phonenumber, sitedomen, commenttext, nextcalldate) VALUES ('$phone', '$site', '$comment', '$date')";

    if (!pg_query($conn, $sql)) {
          echo "Error: " . $sql . "<br>" . pg_last_error();
    }

    pg_close($conn);
}

?>