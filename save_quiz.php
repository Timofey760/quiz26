<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'edit';

// Если нет ID викторины, перенаправляем на создание
if ($quiz_id === 0) {
    header('Location: create_quiz.php');
    exit();
}

// Получение информации о викторине
$quiz = null;
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $quiz_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$quiz = $result->fetch_assoc();
$stmt->close();

if (!$quiz) {
    header('Location: dashboard.php');
    exit();
}

// Получение всех слайдов викторины
$slides = [];
$stmt = $conn->prepare("SELECT * FROM slides WHERE quiz_id = ? ORDER BY slide_order ASC");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Получение вариантов ответов для слайда
    $options_stmt = $conn->prepare("SELECT * FROM answer_options WHERE slide_id = ? ORDER BY option_order ASC");
    $options_stmt->bind_param("i", $row['id']);
    $options_stmt->execute();
    $options_result = $options_stmt->get_result();
    $row['options'] = [];
    while ($option = $options_result->fetch_assoc()) {
        $row['options'][] = $option;
    }
    $options_stmt->close();
    
    // Проверяем существование файла изображения
    if ($row['image_path'] && file_exists(__DIR__ . '/' . $row['image_path'])) {
        $row['image_path_display'] = $row['image_path'];
    } else {
        $row['image_path_display'] = null;
        if ($row['image_path']) {
            $row['image_path'] = null;
        }
    }
    
    $slides[] = $row;
}
$stmt->close();

// Если слайдов нет, создаем один пустой
if (empty($slides)) {
    $slides[] = [
        'id' => 0,
        'question_text' => '',
        'image_path' => null,
        'image_path_display' => null,
        'font_size' => 24,
        'font_color' => '#000000',
        'slide_order' => 1,
        'options' => [
            ['id' => 0, 'option_text' => '', 'is_correct' => 0, 'option_order' => 1, 'shape_type' => 'circle'],
            ['id' => 0, 'option_text' => '', 'is_correct' => 0, 'option_order' => 2, 'shape_type' => 'square'],
            ['id' => 0, 'option_text' => '', 'is_correct' => 0, 'option_order' => 3, 'shape_type' => 'diamond'],
            ['id' => 0, 'option_text' => '', 'is_correct' => 0, 'option_order' => 4, 'shape_type' => 'star']
        ]
    ];
}

$current_slide_index = isset($_GET['slide']) ? intval($_GET['slide']) : 0;
if ($current_slide_index >= count($slides)) {
    $current_slide_index = 0;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'preview' ? 'Просмотр' : 'Редактор'; ?> викторины - Quiz26</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .quiz-title {
            font-size: 18px;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn-header {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border: none;
            border-radius: 20px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-header:hover {
            background: rgba(255,255,255,0.3);
        }

        .main-container {
            display: flex;
            height: calc(100vh - 70px);
        }

        .slides-sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-add-slide {
            background: #667eea;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }

        .btn-add-slide:hover {
            transform: scale(1.1);
        }

        .slides-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .slide-thumb {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .slide-thumb:hover {
            border-color: #667eea;
            transform: translateX(5px);
        }

        .slide-thumb.active {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .slide-number {
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .slide-preview {
            font-size: 12px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .slide-actions {
            position: absolute;
            top: 5px;
            right: 5px;
            display: none;
            gap: 5px;
        }

        .slide-thumb:hover .slide-actions {
            display: flex;
        }

        .btn-icon {
            background: white;
            border: 1px solid #ddd;
            padding: 3px 6px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 10px;
        }

        .btn-icon:hover {
            background: #667eea;
            color: white;
        }

        .editor-area {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }

        .slide-editor {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .image-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .image-upload:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .image-preview {
            max-width: 100%;
            margin-top: 10px;
            position: relative;
            display: inline-block;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
        }

        .remove-image-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 16px;
        }

        .options-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .options-table td {
            padding: 10px;
            border: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .option-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .correct-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .shape-badge {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            font-size: 20px;
            background: #f0f0f0;
            border-radius: 50%;
        }

        .editor-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .preview-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 70px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .preview-slide {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            color: white;
            overflow-y: auto;
        }

        .preview-question {
            text-align: center;
            margin-bottom: 40px;
            max-width: 800px;
            word-wrap: break-word;
        }

        .preview-image {
            max-width: 400px;
            max-height: 300px;
            margin-bottom: 40px;
            border-radius: 10px;
            object-fit: contain;
        }

        .preview-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 800px;
            width: 100%;
        }

        .preview-option {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
            backdrop-filter: blur(10px);
        }

        .preview-option:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        .option-shape {
            font-size: 30px;
        }

        .preview-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 20px;
            background: rgba(0,0,0,0.5);
        }

        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 20px;
            border-radius: 10px;
            z-index: 1000;
            display: none;
        }

        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 1000;
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .slides-sidebar {
                width: 200px;
            }
            
            .preview-options {
                grid-template-columns: 1fr;
            }
            
            .editor-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div>⏳ Сохранение...</div>
    </div>
    <div class="notification" id="notification"></div>
    
    <div class="header">
        <div class="logo" onclick="window.location.href='dashboard.php'">🎮 Quiz26</div>
        <div class="quiz-title">
            <?php echo $mode === 'preview' ? '👁️ Просмотр: ' : '✏️ Редактирование: '; ?>
            <?php echo htmlspecialchars($quiz['title']); ?>
        </div>
        <div class="header-actions">
            <?php if ($mode === 'edit'): ?>
                <a href="?id=<?php echo $quiz_id; ?>&mode=preview" class="btn-header">👁️ Просмотр</a>
            <?php else: ?>
                <a href="?id=<?php echo $quiz_id; ?>&mode=edit" class="btn-header">✏️ Редактировать</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-header">🏠 На главную</a>
        </div>
    </div>

    <?php if ($mode === 'edit'): ?>
    <div class="main-container">
        <div class="slides-sidebar">
            <div class="sidebar-header">
                <span>📊 Слайды (<?php echo count($slides); ?>)</span>
                <button class="btn-add-slide" onclick="addSlide()">+</button>
            </div>
            <div class="slides-list" id="slidesList">
                <?php foreach ($slides as $index => $slide): ?>
                <div class="slide-thumb <?php echo $index === $current_slide_index ? 'active' : ''; ?>" onclick="selectSlide(<?php echo $index; ?>)">
                    <div class="slide-number">Слайд <?php echo $index + 1; ?></div>
                    <div class="slide-preview">
                        <?php echo htmlspecialchars(mb_substr($slide['question_text'], 0, 50)) ?: 'Новый вопрос'; ?>
                    </div>
                    <div class="slide-actions">
                        <button class="btn-icon" onclick="event.stopPropagation(); duplicateSlide(<?php echo $index; ?>)">📋</button>
                        <button class="btn-icon" onclick="event.stopPropagation(); deleteSlide(<?php echo $index; ?>)">🗑️</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="editor-area">
            <div class="slide-editor">
                <input type="hidden" id="slideId" value="<?php echo $slides[$current_slide_index]['id'] ?? 0; ?>">
                
                <div class="form-group">
                    <label>📝 Вопрос</label>
                    <textarea id="questionText" placeholder="Введите текст вопроса..."><?php echo htmlspecialchars($slides[$current_slide_index]['question_text'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>🖼️ Изображение</label>
                    <div class="image-upload" onclick="document.getElementById('imageInput').click()">
                        📤 Нажмите для загрузки изображения (JPG, PNG, GIF, до 5MB)
                        <input type="file" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: <?php echo (!empty($slides[$current_slide_index]['image_path_display'])) ? 'inline-block' : 'none'; ?>">
                        <?php if (!empty($slides[$current_slide_index]['image_path_display']) && file_exists(__DIR__ . '/' . $slides[$current_slide_index]['image_path_display'])): ?>
                            <img src="<?php echo htmlspecialchars($slides[$current_slide_index]['image_path_display']); ?>?t=<?php echo time(); ?>">
                            <button type="button" class="remove-image-btn" onclick="removeImage()">×</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>🎨 Настройки текста</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <div style="flex: 1;">
                            <label style="font-size: 12px;">Размер шрифта (px)</label>
                            <input type="number" id="fontSize" value="<?php echo $slides[$current_slide_index]['font_size'] ?? 24; ?>" min="12" max="72" style="width: 100%;">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 12px;">Цвет шрифта</label>
                            <input type="color" id="fontColor" value="<?php echo $slides[$current_slide_index]['font_color'] ?? '#000000'; ?>" style="width: 100%; height: 40px;">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>💎 Варианты ответов</label>
                    <div style="margin-bottom: 10px;">
                        <label style="font-size: 12px;">Баллов за правильный ответ:</label>
                        <input type="number" id="points" value="1" min="1" max="100" style="width: 80px; margin-left: 10px;">
                    </div>
                    <table class="options-table">
                        <tbody>
                            <?php 
                            $options = $slides[$current_slide_index]['options'];
                            $shapes = ['●', '■', '◆', '★'];
                            for ($i = 0; $i < 4; $i++): 
                                $option = isset($options[$i]) ? $options[$i] : ['option_text' => '', 'is_correct' => 0];
                            ?>
                            <tr>
                                <td style="width: 50px; text-align: center;">
                                    <span class="shape-badge"><?php echo $shapes[$i]; ?></span>
                                </td>
                                <td>
                                    <input type="text" class="option-input" data-option-index="<?php echo $i; ?>" 
                                           value="<?php echo htmlspecialchars($option['option_text']); ?>" 
                                           placeholder="Вариант ответа <?php echo $i+1; ?>">
                                </td>
                                <td style="width: 120px; text-align: center;">
                                    <input type="checkbox" class="correct-checkbox" data-option-index="<?php echo $i; ?>" 
                                           <?php echo ($option['is_correct'] ?? 0) ? 'checked' : ''; ?>>
                                    <label style="font-size: 12px;">Правильный</label>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="editor-actions">
                    <button type="button" class="btn btn-primary" onclick="saveSlide()">💾 Сохранить слайд</button>
                    <button type="button" class="btn btn-secondary" onclick="addSlide()">➕ Добавить слайд</button>
                    <button type="button" class="btn btn-success" onclick="duplicateCurrentSlide()">📋 Дублировать</button>
                    <button type="button" class="btn btn-danger" onclick="deleteCurrentSlide()">🗑️ Удалить слайд</button>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="preview-container">
        <div class="preview-slide" id="previewSlide">
            <div class="preview-question" id="previewQuestion"></div>
            <img id="previewImage" class="preview-image" style="display: none;">
            <div class="preview-options" id="previewOptions"></div>
        </div>
        <div class="preview-controls">
            <button class="btn btn-primary" onclick="previousSlide()">◀ Предыдущий</button>
            <span id="slideCounter" style="color: white; padding: 10px; background: rgba(0,0,0,0.5); border-radius: 20px;">Слайд 1 / 1</span>
            <button class="btn btn-primary" onclick="nextSlide()">Следующий ▶</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        let slidesData = <?php 
            $cleanSlides = [];
            foreach ($slides as $slide) {
                $cleanSlides[] = [
                    'id' => $slide['id'],
                    'question_text' => $slide['question_text'],
                    'image_path' => $slide['image_path'],
                    'font_size' => $slide['font_size'],
                    'font_color' => $slide['font_color'],
                    'slide_order' => $slide['slide_order'],
                    'options' => $slide['options']
                ];
            }
            echo json_encode($cleanSlides, JSON_UNESCAPED_UNICODE); 
        ?>;
        let currentSlideIndex = <?php echo $current_slide_index; ?>;
        let quizId = <?php echo $quiz_id; ?>;
        let previewMode = <?php echo $mode === 'preview' ? 'true' : 'false'; ?>;
        let isSaving = false;
        
        function showNotification(message, isError = false) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.style.backgroundColor = isError ? '#dc3545' : '#28a745';
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
        
        function selectSlide(index) {
            if (previewMode) return;
            window.location.href = `?id=${quizId}&mode=edit&slide=${index}`;
        }
        
        function addSlide() {
            if (previewMode) return;
            const newSlide = {
                id: 0,
                question_text: 'Новый вопрос',
                image_path: null,
                font_size: 24,
                font_color: '#000000',
                slide_order: slidesData.length + 1,
                options: [
                    { id: 0, option_text: '', is_correct: 0, option_order: 1, shape_type: 'circle' },
                    { id: 0, option_text: '', is_correct: 0, option_order: 2, shape_type: 'square' },
                    { id: 0, option_text: '', is_correct: 0, option_order: 3, shape_type: 'diamond' },
                    { id: 0, option_text: '', is_correct: 0, option_order: 4, shape_type: 'star' }
                ]
            };
            slidesData.push(newSlide);
            saveAllSlides(false);
        }
        
        function deleteSlide(index) {
            if (previewMode) return;
            if (slidesData.length === 1) {
                showNotification('Нельзя удалить единственный слайд', true);
                return;
            }
            if (confirm('Удалить этот слайд?')) {
                slidesData.splice(index, 1);
                if (currentSlideIndex >= slidesData.length) {
                    currentSlideIndex = slidesData.length - 1;
                }
                saveAllSlides(true);
            }
        }
        
        function deleteCurrentSlide() {
            deleteSlide(currentSlideIndex);
        }
        
        function duplicateSlide(index) {
            if (previewMode) return;
            const duplicated = JSON.parse(JSON.stringify(slidesData[index]));
            duplicated.id = 0;
            duplicated.question_text = duplicated.question_text + ' (копия)';
            slidesData.splice(index + 1, 0, duplicated);
            saveAllSlides(true);
        }
        
        function duplicateCurrentSlide() {
            duplicateSlide(currentSlideIndex);
        }
        
        function removeImage() {
            if (previewMode) return;
            if (confirm('Удалить изображение?')) {
                const slide = slidesData[currentSlideIndex];
                slide.image_path = null;
                const previewDiv = document.getElementById('imagePreview');
                previewDiv.innerHTML = '';
                previewDiv.style.display = 'none';
                saveAllSlides(false);
            }
        }
        
        function saveSlide() {
            const slide = slidesData[currentSlideIndex];
            slide.question_text = document.getElementById('questionText').value;
            slide.font_size = parseInt(document.getElementById('fontSize').value);
            slide.font_color = document.getElementById('fontColor').value;
            
            for (let i = 0; i < 4; i++) {
                const optionInput = document.querySelector(`.option-input[data-option-index="${i}"]`);
                const checkbox = document.querySelector(`.correct-checkbox[data-option-index="${i}"]`);
                if (optionInput && slide.options[i]) {
                    slide.options[i].option_text = optionInput.value;
                    slide.options[i].is_correct = checkbox.checked ? 1 : 0;
                }
            }
            
            saveAllSlides(false);
        }
        
        function saveAllSlides(shouldReload = false) {
            if (isSaving) return;
            isSaving = true;
            
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            const slidesToSave = slidesData.map(slide => ({
                id: slide.id,
                question_text: slide.question_text,
                image_path: slide.image_path,
                font_size: slide.font_size,
                font_color: slide.font_color,
                slide_order: slide.slide_order,
                options: slide.options
            }));
            
            const formData = new FormData();
            formData.append('quiz_id', quizId);
            formData.append('slides', JSON.stringify(slidesToSave));
            
            fetch('save_quiz.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                isSaving = false;
                
                if (data.success) {
                    showNotification('Сохранено успешно!');
                    if (shouldReload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    showNotification('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), true);
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                isSaving = false;
                console.error('Error:', error);
                showNotification('Ошибка соединения: ' + error.message, true);
            });
        }
        
        function updatePreview() {
            if (!previewMode) return;
            const slide = slidesData[currentSlideIndex];
            if (!slide) return;
            
            const previewQuestion = document.getElementById('previewQuestion');
            previewQuestion.textContent = slide.question_text || 'Вопрос не задан';
            previewQuestion.style.fontSize = (slide.font_size || 24) + 'px';
            previewQuestion.style.color = slide.font_color || '#ffffff';
            
            const previewImage = document.getElementById('previewImage');
            if (slide.image_path && slide.image_path !== 'null' && slide.image_path !== '') {
                const imageUrl = slide.image_path + (slide.image_path.includes('?') ? '&' : '?') + 't=' + Date.now();
                previewImage.src = imageUrl;
                previewImage.style.display = 'block';
            } else {
                previewImage.style.display = 'none';
            }
            
            const previewOptions = document.getElementById('previewOptions');
            const shapes = ['●', '■', '◆', '★'];
            previewOptions.innerHTML = '';
            if (slide.options) {
                slide.options.forEach((option, idx) => {
                    if (option.option_text && option.option_text.trim()) {
                        const optionDiv = document.createElement('div');
                        optionDiv.className = 'preview-option';
                        optionDiv.innerHTML = `
                            <div class="option-shape">${shapes[idx]}</div>
                            <div style="flex: 1; text-align: left;">${escapeHtml(option.option_text)}</div>
                            ${option.is_correct ? '<div style="color: #4caf50;">✓ Правильно</div>' : ''}
                        `;
                        previewOptions.appendChild(optionDiv);
                    }
                });
            }
            
            document.getElementById('slideCounter').textContent = `Слайд ${currentSlideIndex + 1} / ${slidesData.length}`;
        }
        
        function nextSlide() {
            if (currentSlideIndex < slidesData.length - 1) {
                currentSlideIndex++;
                updatePreview();
            }
        }
        
        function previousSlide() {
            if (currentSlideIndex > 0) {
                currentSlideIndex--;
                updatePreview();
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Загрузка изображения
        if (document.getElementById('imageInput')) {
            document.getElementById('imageInput').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        showNotification('Файл слишком большой. Максимальный размер 5MB', true);
                        return;
                    }
                    
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        showNotification('Пожалуйста, выберите изображение в формате JPG, PNG, GIF или WEBP', true);
                        return;
                    }
                    
                    showNotification('Загрузка изображения...');
                    
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const previewDiv = document.getElementById('imagePreview');
                        previewDiv.innerHTML = `<img src="${event.target.result}"><button type="button" class="remove-image-btn" onclick="removeImage()">×</button>`;
                        previewDiv.style.display = 'inline-block';
                        
                        const slide = slidesData[currentSlideIndex];
                        slide.image_path = event.target.result;
                        
                        saveAllSlides(false);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Показываем превью изображения при загрузке страницы
        const imagePreviewDiv = document.getElementById('imagePreview');
        if (imagePreviewDiv && imagePreviewDiv.innerHTML.trim() && imagePreviewDiv.querySelector('img')) {
            imagePreviewDiv.style.display = 'inline-block';
        }
        
        if (previewMode) {
            updatePreview();
        }
        
        // Автосохранение
        let saveTimeout;
        function autoSave() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                if (!previewMode && !isSaving) {
                    saveSlide();
                }
            }, 2000);
        }
        
        const questionText = document.getElementById('questionText');
        if (questionText) {
            questionText.addEventListener('input', autoSave);
        }
        
        const fontSize = document.getElementById('fontSize');
        if (fontSize) {
            fontSize.addEventListener('change', autoSave);
        }
        
        const fontColor = document.getElementById('fontColor');
        if (fontColor) {
            fontColor.addEventListener('change', autoSave);
        }
        
        document.querySelectorAll('.option-input, .correct-checkbox').forEach(el => {
            el.addEventListener('change', autoSave);
            el.addEventListener('input', autoSave);
        });
    </script>
</body>
</html>