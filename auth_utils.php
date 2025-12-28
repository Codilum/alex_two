<?php
require_once 'config/config.php';

function openDbConnection()
{
    $conn_string = "host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . "";
    $conn = pg_connect($conn_string);

    if (!$conn) {
        die("Connection failed: " . pg_last_error());
    }

    return $conn;
}

function clearAuthCookies()
{
    setcookie("userid", "", time() - 3600 * 24 * 30 * 12, "/");
    setcookie("hash", "", time() - 3600 * 24 * 30 * 12, "/", null, null, true);
}

function getAuthenticatedUser($conn)
{
    if (!isset($_COOKIE['userid'], $_COOKIE['hash'])) {
        return null;
    }

    $userid = (int)$_COOKIE['userid'];
    $hash = $_COOKIE['hash'];

    if ($userid <= 0 || $hash === '') {
        return null;
    }

    $result = pg_query_params(
        $conn,
        "SELECT userid, userlogin, userhash, userrole, useroffice FROM users WHERE userid = $1 LIMIT 1",
        [$userid]
    );

    if (!$result || pg_num_rows($result) === 0) {
        return null;
    }

    $user = pg_fetch_assoc($result);

    if ($user['userhash'] !== $hash) {
        return null;
    }

    return $user;
}

function requireAuth($mode = 'redirect')
{
    $conn = openDbConnection();
    $user = getAuthenticatedUser($conn);

    if (!$user) {
        clearAuthCookies();

        if ($mode === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Требуется авторизация.']);
            exit();
        }

        if ($mode === 'silent') {
            http_response_code(401);
            exit();
        }

        header("Location: index.php");
        exit();
    }

    return ['conn' => $conn, 'user' => $user];
}

function isAdmin($user)
{
    return isset($user['userrole']) && $user['userrole'] === 'admin';
}
?>
