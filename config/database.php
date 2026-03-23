<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '123456'); // Если у вас есть пароль для MySQL, укажите его
define('DB_NAME', 'quiz26');

// Создание подключения
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Проверка подключения
if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

// Установка кодировки
$conn->set_charset("utf8mb4");

// Функция для выполнения запросов с защитой от SQL инъекций
function executeQuery($conn, $sql, $types = null, ...$params) {
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
?>