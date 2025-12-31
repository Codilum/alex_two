<?php
require_once 'auth_utils.php';

$auth = requireAuth('page');
$currentUser = $auth['user'];
$conn = $auth['conn'];
$isAdmin = isAdmin($currentUser);
$hasOffice = usersHasColumn($conn, 'useroffice');

$today = date('Y-m-d');
$weekAgoDay = date('Y-m-d', strtotime('-7 days'));

$officeColumn = $hasOffice ? 'users.useroffice' : "''";
$groupByOffice = $hasOffice ? 'users.useroffice' : "''";

$sql = "SELECT
            users.userid,
            users.userlogin,
            {$officeColumn} AS office,
            (SELECT COUNT(*) FROM calls
             WHERE calls.nextcalldate = $1 AND users.userid = calls.userid) AS daytotal,
            (SELECT COUNT(*) FROM calls
             WHERE calls.nextcalldate > $2 AND users.userid = calls.userid) AS weektotal
        FROM
            users
        LEFT JOIN calls ON users.userid = calls.userid
        GROUP BY users.userid, users.userlogin, {$groupByOffice}
        ORDER BY users.userlogin";

$result = pg_query_params($conn, $sql, [$today, $weekAgoDay]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Cache-control" content="no-cache">
    <title>Статистика по пользователям</title>

    <link href="style.css" media="all" rel="Stylesheet" type="text/css" />
    <link href="jquery-ui.css" media="all" rel="Stylesheet" type="text/css">

    <script src="jquery.min.js" type="text/javascript"></script>
    <script src="jquery.maskedinput.js" type="text/javascript"></script>
    <script src="jquery-ui.js" type="text/javascript"></script>
</head>
<body>
    <div class="container">
        <h1>Статистика по пользователям</h1>
        <?php
        if ($result) {
            echo '<div class="usersStat">
                    <table>
                        <thead>
                            <tr>
                                <td>Сотрудник</td>
                                <td>Офис</td>
                                <td>Сегодня</td>
                                <td>За неделю</td>
                            </tr>
                        </thead>
                        <tbody id="tbody">';

            while ($array = pg_fetch_array($result)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($array['userlogin']) . '</td>';
                echo '<td>' . htmlspecialchars($array['office']) . '</td>';
                echo '<td>' . htmlspecialchars($array['daytotal']) . '</td>';
                echo '<td>' . htmlspecialchars($array['weektotal']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>
                </table>
            </div>';
        } else {
            echo 'Ошибка: ' . htmlspecialchars(pg_last_error($conn));
        }

        pg_close($conn);
        ?>

        <?php if ($isAdmin) : ?>
            <div class="newUser">
                <form method="post" id="sendForm" action="">
                    <div class="row first-row">
                        <div class="input-column">
                            <div class="find-row">
                                <input name="login" placeholder="Логин" type="text" id="login">
                            </div>
                        </div>
                        <div class="input-column">
                            <div class="find-row">
                                <input name="office" placeholder="Офис" type="text" id="office">
                            </div>
                        </div>
                        <div class="input-column">
                            <div class="find-row">
                                <input name="password" placeholder="Пароль" type="password" id="password">
                            </div>
                        </div>
                        <div class="input-column save-button">
                            <input type="submit" value="Создать" id="btn">
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>