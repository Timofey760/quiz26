<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Отключаем вывод ошибок в ответ
header('Content-Type: application/json');

require_once 'config/database.php';

// Функция для отправки JSON ответа
function sendJsonResponse($success, $message = '', $data = null) {
    $response = ['success' => $success];
    if ($message) $response['error'] = $message;
    if ($data) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'Не авторизован');
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
$slides_json = isset($_POST['slides']) ? $_POST['slides'] : '';

if (!$quiz_id || !$slides_json) {
    sendJsonResponse(false, 'Недостаточно данных');
}

// Проверяем, принадлежит ли викторина пользователю
$stmt = $conn->prepare("SELECT id FROM quizzes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $quiz_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    sendJsonResponse(false, 'Викторина не найдена или доступ запрещен');
}
$stmt->close();

$slides = json_decode($slides_json, true);
if (!$slides || !is_array($slides)) {
    sendJsonResponse(false, 'Ошибка парсинга данных слайдов');
}

// Начинаем транзакцию
$conn->begin_transaction();

try {
    // Удаляем старые слайды и варианты ответов
    $stmt = $conn->prepare("DELETE FROM answer_options WHERE slide_id IN (SELECT id FROM slides WHERE quiz_id = ?)");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM slides WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $stmt->close();
    
    // Сохраняем новые слайды
    foreach ($slides as $order => $slide) {
        // Проверяем обязательные поля
        $question_text = isset($slide['question_text']) ? $slide['question_text'] : '';
        $font_size = isset($slide['font_size']) ? intval($slide['font_size']) : 24;
        $font_color = isset($slide['font_color']) ? $slide['font_color'] : '#000000';
        $image_path = isset($slide['image_path']) && $slide['image_path'] ? $slide['image_path'] : null;
        
        $stmt = $conn->prepare("INSERT INTO slides (quiz_id, slide_order, question_text, image_path, font_size, font_color) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $quiz_id, $order, $question_text, $image_path, $font_size, $font_color);
        
        if (!$stmt->execute()) {
            throw new Exception('Ошибка сохранения слайда: ' . $stmt->error);
        }
        
        $slide_id = $stmt->insert_id;
        $stmt->close();
        
        // Сохраняем варианты ответов
        if (isset($slide['options']) && is_array($slide['options'])) {
            foreach ($slide['options'] as $opt_order => $option) {
                $option_text = isset($option['option_text']) ? $option['option_text'] : '';
                $is_correct = isset($option['is_correct']) ? intval($option['is_correct']) : 0;
                $shape_type = isset($option['shape_type']) ? $option['shape_type'] : 'circle';
                
                $stmt = $conn->prepare("INSERT INTO answer_options (slide_id, option_text, is_correct, option_order, shape_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isiss", $slide_id, $option_text, $is_correct, $opt_order, $shape_type);
                
                if (!$stmt->execute()) {
                    throw new Exception('Ошибка сохранения варианта ответа: ' . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
    
    $conn->commit();
    sendJsonResponse(true, 'Сохранено успешно');
    
} catch (Exception $e) {
    $conn->rollback();
    sendJsonResponse(false, $e->getMessage());
}

$conn->close();
?>