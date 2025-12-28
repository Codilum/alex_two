<?php
require_once 'auth_utils.php';

$auth = requireAuth('page');
$currentUser = $auth['user'];
$conn = $auth['conn'];

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

    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    if ($action === 'create') {
        if ($login === '' || $password === '') {
            $error = 'Заполните имя пользователя и пароль.';
        } else {
            $passwordHash = md5(md5($password));
            $result = pg_query_params(
                $conn,
                'INSERT INTO users (userlogin, userpassword, userhash, useroffice, userrole) VALUES ($1, $2, $3, $4, $5)',
                [$login, $passwordHash, '', $office, $role]
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
            if ($password !== '') {
                $passwordHash = md5(md5($password));
                $result = pg_query_params(
                    $conn,
                    'UPDATE users SET userlogin = $1, userpassword = $2, useroffice = $3, userrole = $4 WHERE userid = $5',
                    [$login, $passwordHash, $office, $role, $userId]
                );
            } else {
                $result = pg_query_params(
                    $conn,
                    'UPDATE users SET userlogin = $1, useroffice = $2, userrole = $3 WHERE userid = $4',
                    [$login, $office, $role, $userId]
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
        $result = pg_query_params(
            $conn,
            'SELECT userid, userlogin, useroffice, userrole FROM users WHERE userid = $1',
            [$editId]
        );
        if ($result && pg_num_rows($result) > 0) {
            $editUser = pg_fetch_assoc($result);
        }
    }
}

$users = pg_query($conn, 'SELECT userid, userlogin, useroffice, userrole FROM users ORDER BY userlogin');
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
                        <input name="office" type="text" placeholder="Офис">
                        <select name="role">
                            <option value="user">Обычный пользователь</option>
                            <option value="admin">Администратор</option>
                        </select>
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
                            <input name="office" type="text" placeholder="Офис" value="<?php echo htmlspecialchars($editUser['useroffice'] ?? ''); ?>">
                            <select name="role">
                                <option value="user" <?php echo ($editUser['userrole'] ?? '') === 'user' ? 'selected' : ''; ?>>Обычный пользователь</option>
                                <option value="admin" <?php echo ($editUser['userrole'] ?? '') === 'admin' ? 'selected' : ''; ?>>Администратор</option>
                            </select>
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
                        <td>Офис</td>
                        <td>Роль</td>
                        <td>Действие</td>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users) { ?>
                        <?php while ($user = pg_fetch_assoc($users)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['userlogin']); ?></td>
                                <td><?php echo htmlspecialchars($user['useroffice'] ?? ''); ?></td>
                                <td><?php echo ($user['userrole'] ?? 'user') === 'admin' ? 'Администратор' : 'Обычный пользователь'; ?></td>
                                <td><a href="managment.php?user_id=<?php echo (int)$user['userid']; ?>">Редактировать</a></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
