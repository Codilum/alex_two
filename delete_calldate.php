<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'auth_utils.php';

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
    $auth = requireAuth('json');
    $currentUser = $auth['user'];
    $conn = $auth['conn'];
    $userid = (int)$currentUser['userid'];
    $isAdmin = isAdmin($currentUser);

    if (!isset($_POST['id'])) {
        throw new Exception('Не указан идентификатор записи.');
    }

    $id = (int)$_POST['id'];

    if ($isAdmin) {
        $sql = "UPDATE calls SET nextcalldate = NULL WHERE id = $1";
        $params = [$id];
    } else {
        $sql = "UPDATE calls SET nextcalldate = NULL WHERE id = $1 AND userid = $2";
        $params = [$id, $userid];
    }

    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        throw new Exception('Не удалось обновить запись.');
    }

    $response['success'] = true;
    $response['message'] = 'Дата следующего звонка удалена.';

    pg_close($conn);
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
