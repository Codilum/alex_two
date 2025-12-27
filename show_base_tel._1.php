<?php

require_once 'config/config.php';

$callClass = "";
$today = date("j F Y");

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
    $page = $_GET['page'];
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
$page -= 1;
$no_of_records_per_page = 10;
$previous_btn = true;
$next_btn = true;
$first_btn = true;
$last_btn = true;

//$start_page = $page - 1;
$start = $page * $no_of_records_per_page;


$offset = abs($page) * $no_of_records_per_page;


$conn_string = "host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS . "";
$conn = pg_connect($conn_string);

$total_pages = "SELECT COUNT(*) FROM calls";
$result = pg_query($conn, $total_pages);
$total_rows = pg_fetch_array($result)[0];
$total_pages = ceil($total_rows / $no_of_records_per_page);
$_POST['total'] = $total_pages;

function findPagesNumber($query)
{
    global $conn;
    $result = pg_query($conn, $query);
    $total_rows = pg_fetch_array($result)[0];
    $total_pages = ceil($total_rows / 10);
    return $total_pages;
}

if ($find_query) {
    $sql = "SELECT * FROM calls 
            WHERE sitedomen = '$find_query' 
            AND userid = '$userid'
            ORDER BY nextcalldate ASC
            LIMIT $no_of_records_per_page
            OFFSET $offset";
    $sql_pages = "SELECT COUNT(*) FROM calls 
                  WHERE sitedomen = '$find_query' ";
    $total_pages = findPagesNumber($sql_pages);

} else if ($phone_query) {
    $sql = "SELECT * FROM calls 
            WHERE phonenumber = '$phone_query' 
            AND userid = '$userid'
            ORDER BY nextcalldate ASC
            LIMIT $no_of_records_per_page
            OFFSET $offset";
    $sql_pages = "SELECT COUNT(*) FROM calls 
                  WHERE phonenumber = '$phone_query' ";
    $total_pages = findPagesNumber($sql_pages);
} else {
    $sql = "SELECT * FROM calls 
            WHERE userid = '$userid'
            ORDER BY nextcalldate ASC
            LIMIT $no_of_records_per_page
            OFFSET $offset";
}

if ($result = pg_query($conn, $sql)) {

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

        echo '<tr>';
        echo '<td class="phone"><a href="tel:' . $array['phonenumber'] . '">' . $array['phonenumber'] . '</td>';
        echo '<td class="site"><a href="https://' . $array['sitedomen'] . '" target="_blank">' . $array['sitedomen'] . '</a></td>';
        echo '<td>' . $array['commenttext'] . '</td>';
        echo '<td class="call ' . $callClass . '" value="' . $array['id'] . '">' . $callDate . '</td>';
        echo '</tr>';
    }

    echo '</tbody>
        </table>
    </div>';

    $response .= "
		<div class='pagination'>
            <ul>";

    $no_of_paginations = $total_pages;

    if ($first_btn && $cur_page > 1) {
        $response .= "<li p='1' class='active'>Первый</li>";
    } else if ($first_btn) {
        $response .= "<li p='1' class='inactive'>Первый</li>";
    }

    if ($previous_btn && $cur_page > 1) {
        $pre = $cur_page - 1;
        $response .= "<li p='" . $pre . "' class='active'>&lt; Пред</li>";
    } else if ($previous_btn) {
        $response .= "<li class='inactive'>&lt; Пред</li>";
    }

    if ($next_btn && $cur_page < $no_of_paginations) {
        $nex = $cur_page + 1;
        $response .= "<li p='" . $nex . "' class='active'>След &gt;</li>";
    } else if ($next_btn) {
        $response .= "<li class='inactive'>След &gt;</li>";
    }

    if ($last_btn && $cur_page < $no_of_paginations) {
        $response .= "<li p='" . $no_of_paginations . "' class='active'>Последний</li>";
    } else if ($last_btn) {
        $response .= "<li p='" . $no_of_paginations . "' class='inactive'>Последний</li>";
    }

    $response .= "
			</ul>
        </div>";
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

