<?php
require_once 'config/config.php';
// Страница авторизации

// Функция для генерации случайной строки
function generateCode($length=6) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHI JKLMNOPRQSTUVWXYZ0123456789";
    $code = "";
    $clen = strlen($chars) - 1;
    while (strlen($code) < $length) {
            $code .= $chars[mt_rand(0,$clen)];
    }
    return $code;
}

// Соединямся с БД
$conn_string = "host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS."";
$conn = pg_connect($conn_string);

if (!$conn) {
    die("Connection failed: " . pg_last_error());
}

require_once 'auth_utils.php';
ensureDefaultUsers($conn);

if(isset($_POST['submit']))
{
    $password = trim($_POST['password'] ?? '');

    if ($password === '') {
        header("Location: index.php?error=empty");
        exit();
    }

    $passwordHash = md5(md5($password));
    $result = pg_query_params(
        $conn,
        "SELECT userid FROM users WHERE userpassword = $1 LIMIT 2",
        [$passwordHash]
    );

    if ($result && pg_num_rows($result) === 1) {
        $data = pg_fetch_array($result);

        // Генерируем случайное число и шифруем его
        $hash = md5(generateCode(10));

        // Записываем в БД новый хеш авторизации и IP
        pg_query_params($conn, "UPDATE users SET userhash=$1 WHERE userid=$2", [$hash, $data['userid']]);

        // Ставим куки
        setcookie("userid", $data['userid'], time()+60*60*24*30, "/");
        setcookie("hash", $hash, time()+60*60*24*30, "/", null, null, true); // httponly !!!

        // Переадресовываем браузер на страницу проверки нашего скрипта
        header("Location: check.php");
        exit();
    }

    header("Location: index.php?error=invalid");
    exit();
}
?>
