<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $tags = trim($_POST['tags']);
    $slide_duration = intval($_POST['slide_duration']);
    
    if (empty($title)) {
        $error = 'Введите название викторины';
    } else {
        $stmt = $conn->prepare("INSERT INTO quizzes (user_id, title, description, is_public, tags, slide_duration) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issisi", $user_id, $title, $description, $is_public, $tags, $slide_duration);
        
        if ($stmt->execute()) {
            $quiz_id = $stmt->insert_id;
            $stmt->close();
            header("Location: quiz_editor.php?id=$quiz_id&mode=edit");
            exit();
        } else {
            $error = 'Ошибка создания викторины';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание викторины - Quiz26</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            margin-bottom: 30px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        .btn-create {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-back {
            display: inline-block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✨ Создание новой викторины</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Название викторины *</label>
                <input type="text" name="title" required>
            </div>
            
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" placeholder="Краткое описание викторины..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Метки (через точку с запятой)</label>
                <input type="text" name="tags" placeholder="например: спорт;история;развлечение">
            </div>
            
            <div class="form-group">
                <label>Время на слайд (секунд)</label>
                <input type="number" name="slide_duration" value="30" min="5" max="120">
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_public" id="is_public">
                    <label for="is_public" style="margin: 0;">Сделать викторину публичной</label>
                </div>
            </div>
            
            <button type="submit" class="btn-create">Создать викторину</button>
            <a href="dashboard.php" class="btn-back">← Вернуться на главную</a>
        </form>
    </div>
</body>
</html>