<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'auth_utils.php';

$response = ['success' => false, 'notifications' => [], 'unread_count' => 0];

try {
    $auth = requireAuth('json');
    $currentUser = $auth['user'];
    $conn = $auth['conn'];
    $userid = (int)$currentUser['userid'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_read') {
            $result = pg_query_params(
                $conn,
                'UPDATE call_assignments SET read_at = NOW() WHERE assigned_to = $1 AND read_at IS NULL',
                [$userid]
            );
            if (!$result) {
                throw new Exception('Не удалось обновить уведомления.');
            }
            $response['success'] = true;
        }
    }

    $notificationsResult = pg_query_params(
        $conn,
        "SELECT ca.id,
                ca.call_id,
                ca.assigned_at,
                ca.read_at,
                calls.nextcalldate,
                users.userlogin AS assigned_by
         FROM call_assignments ca
         JOIN calls ON calls.id = ca.call_id
         LEFT JOIN users ON users.userid = ca.assigned_by
         WHERE ca.assigned_to = $1
         ORDER BY ca.assigned_at DESC
         LIMIT 50",
        [$userid]
    );

    if ($notificationsResult) {
        while ($row = pg_fetch_assoc($notificationsResult)) {
            $response['notifications'][] = [
                'id' => (int)$row['id'],
                'call_id' => (int)$row['call_id'],
                'assigned_at' => $row['assigned_at'] ? date('d.m.Y H:i', strtotime($row['assigned_at'])) : '',
                'read_at' => $row['read_at'],
                'nextcalldate' => $row['nextcalldate'] ? date('d.m.Y', strtotime($row['nextcalldate'])) : '',
                'assigned_by' => $row['assigned_by'] ?? ''
            ];
        }
    }

    $unreadResult = pg_query_params(
        $conn,
        'SELECT COUNT(*) FROM call_assignments WHERE assigned_to = $1 AND read_at IS NULL',
        [$userid]
    );
    $response['unread_count'] = $unreadResult ? (int)pg_fetch_result($unreadResult, 0, 0) : 0;

    if (!$response['success']) {
        $response['success'] = true;
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
