<?php

require_once 'config/config.php';

$callClass = "";
$today = date("Y-m-d");

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

if (isset($_GET['mode'])) {
    $mode = $_GET['mode'];
} else {
    $mode = 'dates';
}

if (isset($_GET['sort'])) {
    $sort = $_GET['sort'];
} else {
    $sort = 'asc';
}

if (isset($_GET['site_page'])) {
    $site_page = (int)$_GET['site_page'];
} else {
    $site_page = 1;
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

if ($site_page < 1) {
    $site_page = 1;
}

$sort_direction = $sort === 'desc' ? 'DESC' : 'ASC';
$site_navigation = '';

// Формируем основной SQL
if ($mode === 'history') {
    $site_offset = ($site_page - 1);
    $site_filter = "";
    if ($find_query) {
        $site_filter = " AND sitedomen = '$find_query'";
    }
    $sql_sites_total = "SELECT COUNT(DISTINCT sitedomen) FROM calls WHERE userid = '$userid' $site_filter";
    $result_sites_total = pg_query($conn, $sql_sites_total);
    $total_sites = $result_sites_total ? (int)pg_fetch_array($result_sites_total)[0] : 0;

    $sql_site = "SELECT sitedomen, COUNT(*) as total, MAX(id) as last_id
                 FROM calls
                 WHERE userid = '$userid' $site_filter
                 GROUP BY sitedomen
                 ORDER BY last_id DESC
                 LIMIT 1 OFFSET $site_offset";
    $result_site = pg_query($conn, $sql_site);
    $current_site = '';
    $current_site_total = 0;
    if ($result_site && $site_row = pg_fetch_array($result_site)) {
        $current_site = $site_row['sitedomen'];
        $current_site_total = (int)$site_row['total'];
    }

    if ($current_site) {
        $sql = "SELECT * FROM calls 
                WHERE sitedomen = '$current_site'
                AND userid = '$userid'
                ORDER BY id DESC";
    } else {
        $sql = null;
    }

    $prev_site_page = $site_page > 1 ? $site_page - 1 : 0;
    $next_site_page = $site_page < $total_sites ? $site_page + 1 : 0;
    $site_label = $current_site ? $current_site . " (" . $current_site_total . ")" : "Нет данных";

    $site_navigation = "<div class='site-navigation'>";
    if ($prev_site_page) {
        $site_navigation .= "<a href='javascript:void(0);' class='site-nav' data-page='" . $prev_site_page . "'>Предыдущий сайт</a>";
    } else {
        $site_navigation .= "<span class='site-nav disabled'>Предыдущий сайт</span>";
    }
    $site_navigation .= "<span class='site-current'>" . $site_label . "</span>";
    if ($next_site_page) {
        $site_navigation .= "<a href='javascript:void(0);' class='site-nav' data-page='" . $next_site_page . "'>Следующий сайт</a>";
    } else {
        $site_navigation .= "<span class='site-nav disabled'>Следующий сайт</span>";
    }
    $site_navigation .= "</div>";
} else {
    if ($find_query) {
        $sql = "SELECT * FROM calls 
                WHERE sitedomen = '$find_query' 
                AND userid = '$userid'
                ORDER BY nextcalldate " . $sort_direction . " NULLS LAST, id " . $sort_direction . "
                LIMIT $no_of_records_per_page
                OFFSET $offset";
                
        $sql_pages = "SELECT COUNT(*) FROM calls 
                      WHERE sitedomen = '$find_query' AND userid = '$userid'";
        $total_pages = findPagesNumber($sql_pages);

    } else if ($phone_query) {
        $sql = "SELECT * FROM calls 
                WHERE phonenumber = '$phone_query' 
                AND userid = '$userid'
                ORDER BY nextcalldate " . $sort_direction . " NULLS LAST, id " . $sort_direction . "
                LIMIT $no_of_records_per_page
                OFFSET $offset";
                
        $sql_pages = "SELECT COUNT(*) FROM calls 
                      WHERE phonenumber = '$phone_query' AND userid = '$userid'";
        $total_pages = findPagesNumber($sql_pages);
    } else {
        $sql = "SELECT * FROM calls 
                WHERE userid = '$userid'
                ORDER BY nextcalldate " . $sort_direction . " NULLS LAST, id " . $sort_direction . "
                LIMIT $no_of_records_per_page
                OFFSET $offset";
                
        $sql_pages = "SELECT COUNT(*) FROM calls WHERE userid = '$userid'";
        $total_pages = findPagesNumber($sql_pages);
    }
}

$_POST['total'] = $total_pages;

// Выполняем запрос
if ($sql && ($result = pg_query($conn, $sql))) {
    
    // !!! УБРАЛ СТРОКУ: $array = pg_fetch_array($result); 
    // Она съедала первую запись. Теперь цикл начнется сразу с первой строки.

    echo $site_navigation;
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
        $commentText = $array['commenttext'] ?? '';
        $commentBody = $commentText;
        $commentTimestamp = extractCommentTimestamp($commentText);
        if ($commentTimestamp) {
            $commentBody = trim(preg_replace('/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}\s*/', '', $commentText));
        }
        $editable = $commentTimestamp ? isCommentEditable($commentTimestamp) : false;

        if ($array['nextcalldate'] != NULL) {
            $callDateValue = date("Y-m-d", strtotime($array['nextcalldate']));
            $callDate = date("d.m.Y", strtotime($array['nextcalldate']));
        } else {
            $callDateValue = "";
            $callDate = "";
        }

        $callClass = checkCallDate($callDateValue, $today);
        $tel_link = $array['phonenumber'];

        echo '<tr value="' . $array['id'] . '" data-id="' . $array['id'] . '" data-phone="' . htmlspecialchars($array['phonenumber'], ENT_QUOTES) . '" data-site="' . htmlspecialchars($array['sitedomen'], ENT_QUOTES) . '" data-comment="' . htmlspecialchars($commentBody, ENT_QUOTES) . '" data-editable="' . ($editable ? 1 : 0) . '">';
        echo '<td class="phone"><a href="tel:' .str_replace([' ', '(', ')','-'], ["","","",""], $tel_link). '">'.$array['phonenumber'].'</td>';
        echo '<td class="site"><a href="https://' . $array['sitedomen'] . '" target="_blank">' . $array['sitedomen'] . '</a></td>';
        // Добавил htmlspecialchars для безопасности
        $commentDisplay = '';
        if ($commentTimestamp) {
            $commentDisplay .= '<strong>' . htmlspecialchars($commentTimestamp) . '</strong><br>';
        }
        if ($commentBody !== '') {
            $commentDisplay .= nl2br(htmlspecialchars($commentBody));
        }
        echo '<td class="comment"><div class="comment-userid">ID: ' . htmlspecialchars($array['userid']) . '</div><div class="comment-text">' . $commentDisplay . '</div></td>';
        echo '<td class="call ' . $callClass . '" value="' . $array['id'] . '">' . $callDate . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
        </table>
    </div>';

    if ($mode !== 'history') {
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
    }

} else {
    if ($mode === 'history') {
        echo $site_navigation;
        echo '<div class="empty-state">Нет данных для отображения.</div>';
    } else {
        echo "Error: " . $sql . "<br>" . pg_last_error();
    }
}

pg_close($conn);

function checkCallDate($checkDate, $today)
{
    if (!$checkDate) {
        return "";
    }
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

function extractCommentTimestamp($commentText)
{
    if (preg_match('/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2})/', $commentText, $matches)) {
        return $matches[1];
    }
    return null;
}

function isCommentEditable($timestamp)
{
    $commentTime = DateTime::createFromFormat('d.m.Y H:i:s', $timestamp);
    if (!$commentTime) {
        return false;
    }
    $now = new DateTime();
    $diffSeconds = $now->getTimestamp() - $commentTime->getTimestamp();
    return $diffSeconds >= 0 && $diffSeconds <= 3600;
}
?>
