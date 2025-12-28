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

function ensureDefaultUsers($conn)
{
    $result = pg_query_params($conn, "SELECT userid FROM users WHERE userlogin = $1 LIMIT 1", ['test']);
    if ($result && pg_num_rows($result) === 0) {
        $passwordHash = md5(md5('123'));
        pg_query_params(
            $conn,
            'INSERT INTO users (userlogin, userpassword, userhash, useroffice, userrole) VALUES ($1, $2, $3, $4, $5)',
            ['test', $passwordHash, '', '', 'user']
        );
    }
}

function ensureAssignmentTables($conn)
{
    pg_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS call_assignments (
            id SERIAL PRIMARY KEY,
            call_id INTEGER NOT NULL,
            assigned_to INTEGER NOT NULL,
            assigned_by INTEGER NOT NULL,
            assigned_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
            read_at TIMESTAMP WITHOUT TIME ZONE
        )"
    );
    pg_query($conn, "CREATE INDEX IF NOT EXISTS call_assignments_call_id_idx ON call_assignments (call_id)");
    pg_query($conn, "CREATE INDEX IF NOT EXISTS call_assignments_assigned_to_idx ON call_assignments (assigned_to)");
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
    ensureAssignmentTables($conn);
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
