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

function getUsersTableColumns($conn)
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $result = pg_query_params(
        $conn,
        "SELECT column_name FROM information_schema.columns WHERE table_name = $1",
        ['users']
    );

    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $columns[$row['column_name']] = true;
        }
    }

    return $columns;
}

function usersHasColumn($conn, $column)
{
    $columns = getUsersTableColumns($conn);

    return isset($columns[$column]);
}

function isPasswordUnique($conn, $passwordHash, $excludeUserId = null)
{
    $params = [$passwordHash];
    $query = 'SELECT userid FROM users WHERE userpassword = $1';

    if ($excludeUserId !== null) {
        $params[] = (int)$excludeUserId;
        $query .= ' AND userid <> $2';
    }

    $query .= ' LIMIT 1';

    $result = pg_query_params($conn, $query, $params);

    return !$result || pg_num_rows($result) === 0;
}

function ensureDefaultUsers($conn)
{
    ensureUsersColumns($conn);

    $result = pg_query_params($conn, "SELECT userid FROM users WHERE userlogin = $1 LIMIT 1", ['test']);
    if ($result && pg_num_rows($result) === 0) {
        $passwordHash = md5(md5('123'));
        $columns = ['userlogin', 'userpassword', 'userhash'];
        $params = ['test', $passwordHash, ''];

        if (usersHasColumn($conn, 'useroffice')) {
            $columns[] = 'useroffice';
            $params[] = '';
        }

        if (usersHasColumn($conn, 'userrole')) {
            $columns[] = 'userrole';
            $params[] = 'user';
        }

        $placeholders = [];
        for ($i = 1; $i <= count($params); $i++) {
            $placeholders[] = '$' . $i;
        }

        pg_query_params(
            $conn,
            'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );
    }
}

function ensureUsersColumns($conn)
{
    pg_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS useroffice TEXT");
    pg_query($conn, "ALTER TABLE users ADD COLUMN IF NOT EXISTS userrole VARCHAR(20) DEFAULT 'user'");
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
    ensureIndex($conn, 'call_assignments_call_id_idx', 'call_assignments', 'call_id');
    ensureIndex($conn, 'call_assignments_assigned_to_idx', 'call_assignments', 'assigned_to');
}

function ensureIndex($conn, $indexName, $tableName, $columnName)
{
    $result = pg_query_params(
        $conn,
        "SELECT 1 FROM pg_indexes WHERE indexname = $1 LIMIT 1",
        [$indexName]
    );

    if ($result && pg_num_rows($result) > 0) {
        return;
    }

    $indexIdentifier = pg_escape_identifier($conn, $indexName);
    $tableIdentifier = pg_escape_identifier($conn, $tableName);
    $columnIdentifier = pg_escape_identifier($conn, $columnName);

    pg_query(
        $conn,
        "CREATE INDEX {$indexIdentifier} ON {$tableIdentifier} ({$columnIdentifier})"
    );
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

    $fields = ['userid', 'userlogin', 'userhash'];
    if (usersHasColumn($conn, 'userrole')) {
        $fields[] = 'userrole';
    }
    if (usersHasColumn($conn, 'useroffice')) {
        $fields[] = 'useroffice';
    }

    $result = pg_query_params(
        $conn,
        "SELECT " . implode(', ', $fields) . " FROM users WHERE userid = $1 LIMIT 1",
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
    ensureUsersColumns($conn);
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
