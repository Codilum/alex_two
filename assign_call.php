<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'auth_utils.php';

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
    $auth = requireAuth('json');
    $currentUser = $auth['user'];
    $conn = $auth['conn'];
    $userid = (int)$currentUser['userid'];
    $isAdmin = isAdmin($currentUser);

    $callId = (int)($_POST['call_id'] ?? 0);
    $assignedTo = (int)($_POST['assigned_to'] ?? 0);

    if ($callId <= 0 || $assignedTo <= 0) {
        throw new Exception('Не удалось определить запись или пользователя.');
    }

    if ($assignedTo === $userid) {
        throw new Exception('Нельзя назначить запись самому себе.');
    }

    $userExists = pg_query_params($conn, 'SELECT userid FROM users WHERE userid = $1', [$assignedTo]);
    if (!$userExists || pg_num_rows($userExists) === 0) {
        throw new Exception('Пользователь не найден.');
    }

    if ($isAdmin) {
        $callCheck = pg_query_params($conn, 'SELECT id FROM calls WHERE id = $1', [$callId]);
    } else {
        $callCheck = pg_query_params(
            $conn,
            "SELECT id FROM calls WHERE id = $1 AND (userid = $2 OR EXISTS (SELECT 1 FROM call_assignments ca WHERE ca.call_id = calls.id AND ca.assigned_to = $2))",
            [$callId, $userid]
        );
    }

    if (!$callCheck || pg_num_rows($callCheck) === 0) {
        throw new Exception('Запись недоступна для передачи.');
    }

    $existing = pg_query_params(
        $conn,
        'SELECT id FROM call_assignments WHERE call_id = $1 AND assigned_to = $2',
        [$callId, $assignedTo]
    );

    if ($existing && pg_num_rows($existing) > 0) {
        $assignmentId = (int)pg_fetch_result($existing, 0, 'id');
        $result = pg_query_params(
            $conn,
            'UPDATE call_assignments SET assigned_by = $1, assigned_at = NOW(), read_at = NULL WHERE id = $2',
            [$userid, $assignmentId]
        );
    } else {
        $result = pg_query_params(
            $conn,
            'INSERT INTO call_assignments (call_id, assigned_to, assigned_by) VALUES ($1, $2, $3)',
            [$callId, $assignedTo, $userid]
        );
    }

    if (!$result) {
        throw new Exception('Не удалось сохранить передачу.');
    }

    $response['success'] = true;
    $response['message'] = 'Запись передана.';
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
