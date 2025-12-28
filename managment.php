<?php
require_once 'auth_utils.php';

$auth = requireAuth('page');
$currentUser = $auth['user'];
$conn = $auth['conn'];
$hasOffice = usersHasColumn($conn, 'useroffice');
$hasRole = usersHasColumn($conn, 'userrole');

if (!isAdmin($currentUser)) {
    http_response_code(403);
    echo '<div class="container"><div class="forms">Доступ запрещен.</div></div>';
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $login = trim($_POST['login'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $password = trim($_POST['password'] ?? '');

    if (!$hasRole) {
        $role = 'user';
    } elseif (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    if ($action === 'create') {
        if ($login === '' || $password === '') {
            $error = 'Заполните имя пользователя и пароль.';
        } elseif (!isPasswordUnique($conn, md5(md5($password)))) {
            $error = 'Этот пароль уже используется другим пользователем.';
        } else {
            $passwordHash = md5(md5($password));
            $columns = ['userlogin', 'userpassword', 'userhash'];
            $params = [$login, $passwordHash, ''];

            if ($hasOffice) {
                $columns[] = 'useroffice';
                $params[] = $office;
            }

            if ($hasRole) {
                $columns[] = 'userrole';
                $params[] = $role;
            }

            $placeholders = [];
            for ($i = 1; $i <= count($params); $i++) {
                $placeholders[] = '$' . $i;
            }

            $result = pg_query_params(
                $conn,
                'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
                $params
            );

            if ($result) {
                $message = 'Пользователь добавлен.';
            } else {
                $error = 'Не удалось добавить пользователя.';
            }
        }
    }

    if ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0 || $login === '') {
            $error = 'Заполните имя пользователя.';
        } else {
            if ($password !== '' && !isPasswordUnique($conn, md5(md5($password)), $userId)) {
                $error = 'Этот пароль уже используется другим пользователем.';
            } elseif ($password !== '') {
                $passwordHash = md5(md5($password));
                $updates = ['userlogin = $1', 'userpassword = $2'];
                $params = [$login, $passwordHash];
                if ($hasOffice) {
                    $updates[] = 'useroffice = $' . (count($params) + 1);
                    $params[] = $office;
                }
                if ($hasRole) {
                    $updates[] = 'userrole = $' . (count($params) + 1);
                    $params[] = $role;
                }
                $params[] = $userId;
                $result = pg_query_params(
                    $conn,
                    'UPDATE users SET ' . implode(', ', $updates) . ' WHERE userid = $' . count($params),
                    $params
                );
            } else {
                $updates = ['userlogin = $1'];
                $params = [$login];
                if ($hasOffice) {
                    $updates[] = 'useroffice = $' . (count($params) + 1);
                    $params[] = $office;
                }
                if ($hasRole) {
                    $updates[] = 'userrole = $' . (count($params) + 1);
                    $params[] = $role;
                }
                $params[] = $userId;
                $result = pg_query_params(
                    $conn,
                    'UPDATE users SET ' . implode(', ', $updates) . ' WHERE userid = $' . count($params),
                    $params
                );
            }

            if ($result) {
                $message = 'Данные пользователя обновлены.';
            } else {
                $error = 'Не удалось обновить пользователя.';
            }
        }
    }
}

$editUser = null;
if (isset($_GET['user_id'])) {
    $editId = (int)$_GET['user_id'];
    if ($editId > 0) {
        $fields = ['userid', 'userlogin'];
        if ($hasOffice) {
            $fields[] = 'useroffice';
        }
        if ($hasRole) {
            $fields[] = 'userrole';
        }
        $result = pg_query_params(
            $conn,
            'SELECT ' . implode(', ', $fields) . ' FROM users WHERE userid = $1',
            [$editId]
        );
        if ($result && pg_num_rows($result) > 0) {
            $editUser = pg_fetch_assoc($result);
        }
    }
}

$listFields = ['userid', 'userlogin'];
if ($hasOffice) {
    $listFields[] = 'useroffice';
}
if ($hasRole) {
    $listFields[] = 'userrole';
}
$users = pg_query($conn, 'SELECT ' . implode(', ', $listFields) . ' FROM users ORDER BY userlogin');
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
    <title>Управление пользователями</title>

    <link href="style.css" media="all" rel="Stylesheet" type="text/css" />
</head>
<body>
    <div class="container">
        <div class="top-actions">
            <a href="/main.php" class="home-button">На главную</a>
            <div class="top-actions-right">
                <form class="exit" method="POST" action="logout.php">
                    <input name="submit" type="submit" value="Выйти">
                </form>
            </div>
        </div>

        <div class="forms">
            <h1>Управление пользователями</h1>

            <?php if ($message !== '') { ?>
                <div class="dialog-message" style="color: var(--success);"><?php echo htmlspecialchars($message); ?></div>
            <?php } ?>
            <?php if ($error !== '') { ?>
                <div class="dialog-message" style="color: var(--danger);"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <div class="row">
                <div class="input-column">
                    <h2>Добавить пользователя</h2>
                    <form method="post" class="management-form">
                        <input type="hidden" name="action" value="create">
                        <input name="login" type="text" placeholder="Имя пользователя" required>
                        <input name="password" type="password" placeholder="Пароль" required>
                        <?php if ($hasOffice) { ?>
                            <input name="office" type="text" placeholder="Офис">
                        <?php } ?>
                        <?php if ($hasRole) { ?>
                            <select name="role">
                                <option value="user">Обычный пользователь</option>
                                <option value="admin">Администратор</option>
                            </select>
                        <?php } ?>
                        <input type="submit" value="Добавить">
                    </form>
                </div>
                <div class="input-column">
                    <h2>Редактировать пользователя</h2>
                    <?php if ($editUser) { ?>
                        <form method="post" class="management-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="<?php echo (int)$editUser['userid']; ?>">
                            <input name="login" type="text" placeholder="Имя пользователя" value="<?php echo htmlspecialchars($editUser['userlogin']); ?>" required>
                            <input name="password" type="password" placeholder="Новый пароль (необязательно)">
                            <?php if ($hasOffice) { ?>
                                <input name="office" type="text" placeholder="Офис" value="<?php echo htmlspecialchars($editUser['useroffice'] ?? ''); ?>">
                            <?php } ?>
                            <?php if ($hasRole) { ?>
                                <select name="role">
                                    <option value="user" <?php echo ($editUser['userrole'] ?? '') === 'user' ? 'selected' : ''; ?>>Обычный пользователь</option>
                                    <option value="admin" <?php echo ($editUser['userrole'] ?? '') === 'admin' ? 'selected' : ''; ?>>Администратор</option>
                                </select>
                            <?php } ?>
                            <input type="submit" value="Сохранить">
                        </form>
                    <?php } else { ?>
                        <div class="dialog-message">Выберите пользователя из списка ниже.</div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div id="data">
            <table>
                <thead>
                    <tr>
                        <td>Имя пользователя</td>
                        <?php if ($hasOffice) { ?>
                            <td>Офис</td>
                        <?php } ?>
                        <?php if ($hasRole) { ?>
                            <td>Роль</td>
                        <?php } ?>
                        <td>Действие</td>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users) { ?>
                        <?php while ($user = pg_fetch_assoc($users)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['userlogin']); ?></td>
                                <?php if ($hasOffice) { ?>
                                    <td><?php echo htmlspecialchars($user['useroffice'] ?? ''); ?></td>
                                <?php } ?>
                                <?php if ($hasRole) { ?>
                                    <td><?php echo ($user['userrole'] ?? 'user') === 'admin' ? 'Администратор' : 'Обычный пользователь'; ?></td>
                                <?php } ?>
                                <td><a href="managment.php?user_id=<?php echo (int)$user['userid']; ?>">Редактировать</a></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="data-block">
            <h2>Передача записей</h2>
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
