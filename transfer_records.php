<?php
require_once 'auth_utils.php';

$auth = requireAuth('page');
$currentUser = $auth['user'];
$conn = $auth['conn'];
$currentUserRoleLabel = isAdmin($currentUser) ? 'Администратор' : 'Пользователь';

$assignments = pg_query(
    $conn,
    "SELECT ca.id,
            ca.call_id,
            ca.assigned_at,
            ca.read_at,
            calls.sitedomen,
            calls.phonenumber,
            from_user.userlogin AS from_user,
            to_user.userlogin AS to_user
     FROM call_assignments ca
     LEFT JOIN calls ON calls.id = ca.call_id
     LEFT JOIN users from_user ON from_user.userid = ca.assigned_by
     LEFT JOIN users to_user ON to_user.userid = ca.assigned_to
     ORDER BY ca.assigned_at DESC"
);
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Cache-control" content="no-cache">
    <title>Передача записей</title>

    <link href="style.css" media="all" rel="Stylesheet" type="text/css" />
</head>
<body>
    <div class="container admin-page">
        <div class="top-actions">
            <a href="/main.php" class="home-button">На главную</a>
            <a href="/managment.php" class="home-button">Управление пользователями</a>
            <div class="current-user">
                Вы вошли как: <?php echo htmlspecialchars($currentUser['userlogin']); ?>
                <span class="current-user-role"><?php echo htmlspecialchars($currentUserRoleLabel); ?></span>
            </div>
            <div class="top-actions-right">
                <form class="exit" method="POST" action="logout.php">
                    <input name="submit" type="submit" value="Выйти">
                </form>
            </div>
        </div>

        <div class="data-block">
            <h1>Передача записей</h1>
            <table>
                <thead>
                    <tr>
                        <td>ID записи</td>
                        <td>Телефон</td>
                        <td>Сайт</td>
                        <td>Кому передано</td>
                        <td>От кого</td>
                        <td>Дата передачи</td>
                        <td>Статус</td>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assignments) { ?>
                        <?php while ($assignment = pg_fetch_assoc($assignments)) { ?>
                            <tr>
                                <td><?php echo (int)$assignment['call_id']; ?></td>
                                <td><?php echo htmlspecialchars($assignment['phonenumber'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($assignment['sitedomen'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($assignment['to_user'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($assignment['from_user'] ?? ''); ?></td>
                                <td><?php echo $assignment['assigned_at'] ? htmlspecialchars(date('d.m.Y H:i', strtotime($assignment['assigned_at']))) : ''; ?></td>
                                <td><?php echo $assignment['read_at'] ? 'Прочитано' : 'Не прочитано'; ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
