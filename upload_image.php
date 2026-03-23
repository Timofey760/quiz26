<?php
session_start();
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
$slide_index = isset($_POST['slide_index']) ? intval($_POST['slide_index']) : 0;

if (!$quiz_id) {
    echo json_encode(['success' => false, 'error' => 'ID викторины не указан']);
    exit();
}

// Создаем папку для загрузок если её нет
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Проверяем, был ли загружен файл
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Файл превышает максимальный размер',
        UPLOAD_ERR_FORM_SIZE => 'Файл превышает максимальный размер формы',
        UPLOAD_ERR_PARTIAL => 'Файл был загружен частично',
        UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION => 'Загрузка файла была остановлена расширением'
    ];
    $error = isset($errorMessages[$_FILES['image']['error']]) ? $errorMessages[$_FILES['image']['error']] : 'Неизвестная ошибка';
    echo json_encode(['success' => false, 'error' => $error]);
    exit();
}

$file = $_FILES['image'];

// Проверяем тип файла
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Разрешенные форматы: JPG, PNG, GIF, WEBP']);
    exit();
}

// Проверяем размер файла (макс 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Максимальный размер файла 5MB']);
    exit();
}

// Генерируем уникальное имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'quiz_' . $quiz_id . '_slide_' . $slide_index . '_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Перемещаем загруженный файл
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
}
?>