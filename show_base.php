<?php

require_once 'config/config.php';

$callClass = "";
$today = date("j F Y");

// Параметры
$no_of_records_per_page = 10;

if (isset($_GET['id'])) {
    $id = $_GET['id'];
} else {
    $id = '';
}

if (isset($_GET['search'])) {
    $find_query = $_GET['search'];
} else {
    $find_query = ''; 
}

if (isset($_GET['phone'])) {
    $phone_query = $_GET['phone'];
} else {
    $phone_query = '';
}

if (isset($_GET['page'])) {
    $page = (int)$_GET['page'];
} else {
    $page = 1;
}

if (isset($_COOKIE['userid'])) {
    $userid = $_COOKIE['userid'];
} else {
    $userid = 0;
}

$response = '';
$cur_page = $page;
// Для БД отступ (offset) считается от 0, но страница у нас 1
$page_db = $page - 1; 

$offset = abs($page_db) * $no_of_records_per_page;

$conn_string = "host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . "";
$conn = pg_connect($conn_string);

// --- СЧИТАЕМ ОБЩЕЕ КОЛИЧЕСТВО СТРАНИЦ ---
// (Оставляем твою логику, но упрощаем подсчет)
$total_pages = 1; 

function findPagesNumber($query)
{
    global $conn;
    $result = pg_query($conn, $query);
    if ($result) {
        $total_rows = pg_fetch_array($result)[0];
        return ceil($total_rows / 10);
    }
    return 1;
}

// --- ЛОГИКА ЗАПРОСОВ ---

if ($id) {
    $sql = "SELECT
                next_id,
                next_phonenumber,
                next_sitedomen,
                next_commenttext,
                next_nextcalldate
            FROM (
                select id,
                       lead(id) over (order by id) as next_id,
                       lead(phonenumber) over (order by id) as next_phonenumber,
                       lead(sitedomen) over (order by id) as next_sitedomen,
                       lead(commenttext) over (order by id) as next_commenttext,
                       lead(nextcalldate) over (order by id) as next_nextcalldate
                from calls
            ) as t
            WHERE id = '$id'";
            
    if ($result = pg_query($conn, $sql)) {
        $array = pg_fetch_array($result);
        // var_dump($array); // Убрал вар_дамп, чтобы не мусорить на экране
        if ($array && isset($array['next_sitedomen'])) {
            $find_query = $array['next_sitedomen'];
        }
    }
}

// Формируем основной SQL
if ($find_query) {
    $sql = "SELECT * FROM calls 
            WHERE sitedomen = '$find_query' 
            AND userid = '$userid'
            ORDER BY nextcalldate ASC
            LIMIT $no_of_records_per_page
            OFFSET $offset";
            
    $sql_pages = "SELECT COUNT(*) FROM calls 
                  WHERE sitedomen = '$find_query' AND userid = '$userid'";
    $total_pages = findPagesNumber($sql_pages);

} else if ($phone_query) {
    $sql = "SELECT * FROM calls 
            WHERE phonenumber = '$phone_query' 
            AND userid = '$userid'
            ORDER BY nextcalldate ASC
            LIMIT $no_of_records_per_page
            OFFSET $offset";
            
    $sql_pages = "SELECT COUNT(*) FROM calls 
                  WHERE phonenumber = '$phone_query' AND userid = '$userid'";
    $total_pages = findPagesNumber($sql_pages);
} else {
    $sql = "SELECT * FROM calls 
            WHERE userid = '$userid'
            ORDER BY nextcalldate ASC
            LIMIT $no_of_records_per_page
            OFFSET $offset";
            
    $sql_pages = "SELECT COUNT(*) FROM calls WHERE userid = '$userid'";
    $total_pages = findPagesNumber($sql_pages);
}

$_POST['total'] = $total_pages;

// Выполняем запрос
if ($result = pg_query($conn, $sql)) {
    
    // !!! УБРАЛ СТРОКУ: $array = pg_fetch_array($result); 
    // Она съедала первую запись. Теперь цикл начнется сразу с первой строки.

    echo '<div id="table">
            <table>
                <thead>
                    <tr>
                        <td class="phone">Телефон</td>
                        <td class="site">Сайт</td>
                        <td>Комментарий</td>
                        <td class="nextcall">Следующий звонок</td>
                    </tr>
                </thead>
                <tbody id="tbody">';

    while ($array = pg_fetch_array($result)) {
        if ($array['nextcalldate'] != NULL) {
            $callDate = date("j F Y", strtotime($array['nextcalldate']));
        } else {
            $callDate = "";
        }

        $callClass = checkCallDate($callDate, $today);
        $tel_link = $array['phonenumber'];

        echo '<tr value="' . $array['id'] . '">';
        echo '<td class="phone"><a href="tel:' .str_replace([' ', '(', ')','-'], ["","","",""], $tel_link). '">'.$array['phonenumber'].'</td>';
        echo '<td class="site"><a href="https://' . $array['sitedomen'] . '" target="_blank">' . $array['sitedomen'] . '</a></td>';
        // Добавил htmlspecialchars для безопасности
        echo '<td class="comment">' . htmlspecialchars($array['commenttext']) . '</td>';
        echo '<td class="call ' . $callClass . '" value="' . $array['id'] . '">' . $callDate . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
        </table>
    </div>';

    // === ГЕНЕРАЦИЯ ПАГИНАЦИИ (ЦИФРЫ 1 2 3...) ===
    // Это заменит старый блок "Первый/Последний" на нужный дизайн
    
    $response .= "<div class='pagination'><ul>";

    // Кнопка НАЗАД
    if ($cur_page > 1) {
        $prev = $cur_page - 1;
        $response .= "<li><a href='javascript:void(0);' p='" . $prev . "'>&laquo;</a></li>";
    } else {
        $response .= "<li class='disabled'><a>&laquo;</a></li>";
    }

    // Ограничение количества кнопок (чтобы не было 1...500)
    $range = 2; 
    $start_loop = ($cur_page - $range) > 1 ? ($cur_page - $range) : 1;
    $end_loop   = ($cur_page + $range) < $total_pages ? ($cur_page + $range) : $total_pages;

    // Первая страница + точки, если нужно
    if ($start_loop > 1) {
        $response .= "<li><a href='javascript:void(0);' p='1'>1</a></li>";
        if ($start_loop > 2) {
            $response .= "<li class='disabled'><a>...</a></li>";
        }
    }

    // Цикл страниц
    for ($i = $start_loop; $i <= $end_loop; $i++) {
        if ($cur_page == $i) {
            $response .= "<li class='active'><a>" . $i . "</a></li>";
        } else {
            $response .= "<li><a href='javascript:void(0);' p='" . $i . "'>" . $i . "</a></li>";
        }
    }

    // Последняя страница + точки, если нужно
    if ($end_loop < $total_pages) {
        if ($end_loop < $total_pages - 1) {
            $response .= "<li class='disabled'><a>...</a></li>";
        }
        $response .= "<li><a href='javascript:void(0);' p='" . $total_pages . "'>" . $total_pages . "</a></li>";
    }

    // Кнопка ВПЕРЕД
    if ($cur_page < $total_pages) {
        $next = $cur_page + 1;
        $response .= "<li><a href='javascript:void(0);' p='" . $next . "'>&raquo;</a></li>";
    } else {
        $response .= "<li class='disabled'><a>&raquo;</a></li>";
    }

    $response .= "</ul></div>";
    echo($response);

} else {
    echo "Error: " . $sql . "<br>" . pg_last_error();
}

pg_close($conn);

function checkCallDate($checkDate, $today)
{
    $dateTimestamp1 = strtotime($checkDate);
    $dateTimestamp2 = strtotime($today);

    $callType = "";

    if ($dateTimestamp1 < $dateTimestamp2) {
        $callType = "pastCall";
    } else if ($dateTimestamp1 == $dateTimestamp2) {
        $callType = "todayCall";
    } else {
        $callType = "nexCall";
    }

    return $callType;
}
?>