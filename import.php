<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config/config.php';

$response = ['success' => false, 'message' => 'Неизвестная ошибка'];

try {
    if (!isset($_FILES['import_file'])) {
        throw new Exception('Файл не выбран.');
    }

    $file = $_FILES['import_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'txt'], true)) {
        throw new Exception('Поддерживаются только файлы CSV или TXT.');
    }

    $userid = isset($_COOKIE['userid']) ? (int)$_COOKIE['userid'] : 0;
    if ($userid === 0) {
        throw new Exception('Не удалось определить пользователя.');
    }

    $conn = pg_connect("host=" . DB_HOST . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS);
    if (!$conn) {
        throw new Exception('Ошибка подключения к БД.');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Не удалось открыть файл.');
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        throw new Exception('Файл пустой.');
    }

    $delimiter = detectDelimiter($firstLine);
    $rowsInserted = 0;
    $rowsSkipped = 0;
    $lineNumber = 0;

    rewind($handle);

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        $lineNumber++;
        if ($lineNumber === 1 && isHeaderRow($data)) {
            continue;
        }

        $data = array_map('trim', $data);
        $phone = $data[0] ?? '';
        $site = $data[1] ?? '';
        $dateRaw = $data[2] ?? '';
        $comment = $data[3] ?? '';

        if ($phone === '' && $site === '') {
            $rowsSkipped++;
            continue;
        }

        $nextCallDate = parseDate($dateRaw);
        $commentTimestamp = date('d.m.Y H:i:s');
        $fullComment = $commentTimestamp;
        if ($comment !== '') {
            $fullComment .= ' ' . $comment;
        }

        $sql = "INSERT INTO calls (phonenumber, sitedomen, nextcalldate, commenttext, userid) VALUES ($1, $2, $3, $4, $5)";
        $result = pg_query_params($conn, $sql, [$phone, $site, $nextCallDate, $fullComment, $userid]);
        if ($result) {
            $rowsInserted++;
        } else {
            $rowsSkipped++;
        }
    }

    fclose($handle);
    pg_close($conn);

    $response['success'] = true;
    $response['message'] = "Импорт завершен. Добавлено: $rowsInserted, пропущено: $rowsSkipped.";
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

function detectDelimiter($line)
{
    if (substr_count($line, ';') >= 1) {
        return ';';
    }
    if (substr_count($line, ',') >= 1) {
        return ',';
    }
    if (substr_count($line, "\t") >= 1) {
        return "\t";
    }
    return ';';
}

function isHeaderRow(array $data)
{
    $first = mb_strtolower(trim($data[0] ?? ''));
    return in_array($first, ['телефон', 'phone', 'phonenumber'], true);
}

function parseDate($value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('d.m.Y', $value);
    if ($date instanceof DateTime) {
        return $date->format('Y-m-d');
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if ($date instanceof DateTime) {
        return $date->format('Y-m-d');
    }

    return null;
}
?>
