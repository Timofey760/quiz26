<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'config/database.php';

$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

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

function saveBase64Image($base64String, $quiz_id, $slide_index) {
    global $uploadDir;
    
    if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
        $imageType = strtolower($matches[1]);
        $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        
        if (!in_array($imageType, $allowedTypes)) {
            return null;
        }
        
        $base64Data = substr($base64String, strpos($base64String, ',') + 1);
        $base64Data = str_replace(' ', '+', $base64Data);
        $imageData = base64_decode($base64Data);
        
        if ($imageData === false) {
            return null;
        }
        
        $extension = ($imageType == 'jpg') ? 'jpg' : $imageType;
        $filename = 'quiz_' . $quiz_id . '_slide_' . $slide_index . '_' . time() . '_' . uniqid() . '.' . $extension;
        $fullPath = $uploadDir . $filename;
        
        if (file_put_contents($fullPath, $imageData)) {
            return $filename;
        }
    }
    
    return null;
}

$conn->begin_transaction();

try {
    // Получаем текущие изображения для этого quiz
    $currentImages = [];
    $stmt = $conn->prepare("SELECT image_path FROM slides WHERE quiz_id = ? AND image_path IS NOT NULL");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $currentImages[] = $row['image_path'];
    }
    $stmt->close();
    
    // Удаляем старые данные
    $stmt = $conn->prepare("DELETE FROM answer_options WHERE slide_id IN (SELECT id FROM slides WHERE quiz_id = ?)");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM slides WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $stmt->close();
    
    // Сохраняем новые слайды
    $newImages = [];
    foreach ($slides as $order => $slide) {
        $question_text = $slide['question_text'] ?? '';
        $font_size = $slide['font_size'] ?? 24;
        $font_color = $slide['font_color'] ?? '#000000';
        
        $image_path = null;
        
        if (!empty($slide['image_path'])) {
            // Если это base64, сохраняем новый файл
            if (strpos($slide['image_path'], 'data:image') === 0) {
                $image_path = saveBase64Image($slide['image_path'], $quiz_id, $order);
                if ($image_path) {
                    $newImages[] = $image_path;
                }
            } 
            // Если это уже имя файла и файл существует
            elseif (file_exists($uploadDir . $slide['image_path'])) {
                $image_path = $slide['image_path'];
                $newImages[] = $image_path;
            }
        }
        
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
                $option_text = $option['option_text'] ?? '';
                $is_correct = isset($option['is_correct']) ? intval($option['is_correct']) : 0;
                $shape_type = $option['shape_type'] ?? 'circle';
                
                $stmt = $conn->prepare("INSERT INTO answer_options (slide_id, option_text, is_correct, option_order, shape_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isiss", $slide_id, $option_text, $is_correct, $opt_order, $shape_type);
                
                if (!$stmt->execute()) {
                    throw new Exception('Ошибка сохранения варианта ответа: ' . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
    
    // Удаляем изображения, которые больше не используются
    $imagesToDelete = array_diff($currentImages, $newImages);
    foreach ($imagesToDelete as $image) {
        $fullPath = $uploadDir . $image;
        if (file_exists($fullPath)) {
            unlink($fullPath);
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