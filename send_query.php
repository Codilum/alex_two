<?php
// 1. Гарантируем, что ответ будет JSON
header('Content-Type: application/json; charset=utf-8');

// 2. Скрываем ошибки PHP от вывода, чтобы не ломать JSON
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once 'config/config.php';

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
    // Подключение к БД
    $conn = pg_connect("host=".DB_HOST." dbname=".DB_NAME." user=".DB_USER." password=".DB_PASS);
    if (!$conn) {
        throw new Exception("Ошибка подключения к БД");
    }

    // Получение данных
    $phone = trim($_POST['phone'] ?? '');
    $site = trim($_POST['site'] ?? '');
    $dateRaw = trim($_POST['date'] ?? ''); 
    $comment = trim($_POST['comment'] ?? '');
    $status = $_POST['status'] ?? '';

    if (empty($phone) && empty($site)) {
        throw new Exception("Заполните Телефон или Сайт");
    }

    // === ПРЕОБРАЗОВАНИЕ ДАТЫ (Русский -> YYYY-MM-DD) ===
    $nextCallDate = null;
    if (!empty($dateRaw)) {
        $ru_months = [
            'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 
            'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
            'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
        ];
        $en_months = [
            'January', 'February', 'March', 'April', 'May', 'June', 
            'July', 'August', 'September', 'October', 'November', 'December',
            'January', 'February', 'March', 'April', 'May', 'June', 
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        $dateEn = str_ireplace($ru_months, $en_months, $dateRaw);
        $ts = strtotime($dateEn);
        
        if ($ts) {
            $nextCallDate = date('Y-m-d', $ts);
        }
    }

    // Формируем комментарий
    $fullComment = $comment;
    if (!empty($status)) {
        $fullComment .= " [Статус: $status]";
    }

    $userid = isset($_COOKIE['userid']) ? (int)$_COOKIE['userid'] : 0;

    // === SQL ЗАПРОС ===
    // Убрали fio и calldate. Оставили только то, что точно есть.
    $sql = "INSERT INTO calls (phonenumber, sitedomen, nextcalldate, commenttext, userid) 
            VALUES ($1, $2, $3, $4, $5)";

    $params = [
        $phone,
        $site,
        $nextCallDate, 
        $fullComment,
        $userid
    ];

    // Выполняем запрос
    $result = @pg_query_params($conn, $sql, $params);

    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Запись успешно добавлена';
    } else {
        throw new Exception("Ошибка SQL: " . pg_last_error($conn));
    }

    pg_close($conn);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Выводим ответ
echo json_encode($response);
?>