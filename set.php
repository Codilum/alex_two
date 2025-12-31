<?php
require_once 'config/config.php';
require_once 'auth_utils.php';

// Соединямся с БД
$conn_string = "host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS."";
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

ensureUsersColumns($conn);

if(isset($_POST['submit']))
{
    $login = "admin2";
    $password = md5(md5(trim($_POST['password'])));
    $office = trim($_POST['office'] ?? '');
    $role = trim($_POST['role'] ?? 'admin');

    if (!isPasswordUnique($conn, $password)) {
        echo "Error: пароль уже используется другим пользователем.";
    } else {
        $columns = ['userlogin', 'userpassword', 'userhash'];
        $params = [$login, $password, ''];

        if (usersHasColumn($conn, 'useroffice')) {
            $columns[] = 'useroffice';
            $params[] = $office;
        }

        if (usersHasColumn($conn, 'userrole')) {
            $columns[] = 'userrole';
            $params[] = $role;
        }

        $placeholders = [];
        for ($i = 1; $i <= count($params); $i++) {
            $placeholders[] = '$' . $i;
        }

        $sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        if (!pg_query_params($conn, $sql, $params)) {
            echo "Error: ";
        }
    }

    pg_close($conn);
    header("Location: index.php"); 
    exit();
}


?>
