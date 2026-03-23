<?php
session_start();
require_once 'config/database.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Получение статистики для дашборда
$stats = [
    'total_quizzes' => 0,
    'public_quizzes' => 0,
    'total_games' => 0
];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_quizzes'] = $result->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quizzes WHERE user_id = ? AND is_public = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['public_quizzes'] = $result->fetch_assoc()['count'];
$stmt->close();

// Получение последних викторин
$recent_quizzes = [];
$stmt = $conn->prepare("SELECT id, title, is_public, created_at FROM quizzes WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_quizzes[] = $row;
}
$stmt->close();

// В начале dashboard.php добавьте обработку действий
if (isset($_GET['action']) && isset($_GET['quiz_id'])) {
    $action = $_GET['action'];
    $quiz_id = intval($_GET['quiz_id']);

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $quiz_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header('Location: dashboard.php?module=quizzes');
        exit();
    }
}

// Получение всех викторин пользователя
$all_quizzes = [];
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_quizzes[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления - Quiz26</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        /* Шапка */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-size: 28px;
            cursor: pointer;
        }

        .logo p {
            font-size: 12px;
            opacity: 0.9;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            font-weight: 500;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Основной контейнер */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        /* Карточки статистики */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Модули */
        .modules-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .module-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }

        .module-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .module-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .module-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Таблица викторин */
        .quizzes-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .quizzes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .btn-create:hover {
            transform: translateY(-2px);
        }

        .quizzes-table {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .quiz-actions {
            display: flex;
            gap: 10px;
        }

        .quiz-actions button {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-play {
            background: #2ecc71;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-public {
            background: #d4edda;
            color: #155724;
        }

        .status-private {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .modules-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-content">
            <div class="logo" onclick="window.location.href='dashboard.php'" style="cursor: pointer;">
                <h1>🎮 Quiz26</h1>
                <p>Платформа для викторин</p>
            </div>
            <div class="user-info">
                <span class="user-name">👤 <?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" class="logout-btn">Выйти</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?php echo $stats['total_quizzes']; ?></div>
                <div class="stat-label">Всего викторин</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-value"><?php echo $stats['public_quizzes']; ?></div>
                <div class="stat-label">Публичных викторин</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?php echo $stats['total_games']; ?></div>
                <div class="stat-label">Проведено игр</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">0</div>
                <div class="stat-label">Рейтинг</div>
            </div>
        </div>

        <!-- Модули -->
        <div class="modules-section">
            <h2 class="section-title">📱 Модули приложения</h2>
            <div class="modules-grid">
                <a href="#" class="module-card" onclick="showPlaceholder('Управление категориями')">
                    <div class="module-icon">🏷️</div>
                    <div class="module-title">Категории</div>
                    <div class="module-description">Создание и управление категориями викторин с цветовой маркировкой</div>
                </a>

                <a href="#" class="module-card" onclick="showPlaceholder('Редактор викторин')">
                    <div class="module-icon">✏️</div>
                    <div class="module-title">Редактор</div>
                    <div class="module-description">Создание и редактирование слайдов, вопросов и ответов</div>
                </a>

                <a href="#" class="module-card" onclick="showPlaceholder('Проведение игры')">
                    <div class="module-icon">🎮</div>
                    <div class="module-title">Игра</div>
                    <div class="module-description">Запуск викторины и управление игровой сессией</div>
                </a>

                <a href="#" class="module-card" onclick="showPlaceholder('Статистика')">
                    <div class="module-icon">📊</div>
                    <div class="module-title">Статистика</div>
                    <div class="module-description">Анализ проведенных викторин и результатов игроков</div>
                </a>

                <a href="create_quiz.php" class="module-card">
                    <div class="module-icon">✨</div>
                    <div class="module-title">Создать викторину</div>
                    <div class="module-description">Создайте новую викторину с нуля</div>
                </a>
            </div>
        </div>

        <!-- Полный список викторин -->
        <div class="quizzes-section">
            <div class="quizzes-header">
                <h2 class="section-title" style="margin-bottom: 0;">📋 Все мои викторины</h2>
                <a href="create_quiz.php" class="btn-create">+ Создать викторину</a>
            </div>

            <div class="quizzes-table">
                <?php if (empty($all_quizzes)): ?>
                    <div class="empty-state">
                        <p>😊 У вас пока нет викторин</p>
                        <p style="font-size: 14px; margin-top: 10px;">Нажмите "Создать викторину", чтобы начать</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Категория</th>
                                <th>Доступ</th>
                                <th>Слайдов</th>
                                <th>Дата создания</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_quizzes as $quiz):
                                // Получаем количество слайдов
                                $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM slides WHERE quiz_id = ?");
                                $count_stmt->bind_param("i", $quiz['id']);
                                $count_stmt->execute();
                                $count_result = $count_stmt->get_result();
                                $slide_count = $count_result->fetch_assoc()['count'];
                                $count_stmt->close();
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                    <td><?php echo $quiz['category_id'] ? 'Категория ' . $quiz['category_id'] : '—'; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $quiz['is_public'] ? 'status-public' : 'status-private'; ?>">
                                            <?php echo $quiz['is_public'] ? '🌍 Публичная' : '🔒 Личная'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $slide_count; ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($quiz['created_at'])); ?></td>
                                    <td class="quiz-actions">
                                        <a href="quiz_editor.php?id=<?php echo $quiz['id']; ?>&mode=edit" class="btn-edit" style="text-decoration: none; display: inline-block;">✏️ Редакт.</a>
                                        <a href="quiz_editor.php?id=<?php echo $quiz['id']; ?>&mode=preview" class="btn-play" style="text-decoration: none; display: inline-block;">👁️ Просмотр</a>
                                        <a href="?action=delete&quiz_id=<?php echo $quiz['id']; ?>" class="btn-delete" style="text-decoration: none; display: inline-block;" onclick="return confirm('Удалить викторину?')">🗑️ Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showPlaceholder(moduleName) {
            alert(`Модуль "${moduleName}" будет доступен в следующей версии приложения.\n\nДанная функция находится в разработке.`);
        }

        // Добавляем эффекты при наведении
        document.querySelectorAll('.module-card').forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
            });
        });

        // Подтверждение удаления
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (confirm('Вы уверены, что хотите удалить эту викторину? Это действие нельзя отменить.')) {
                    showPlaceholder('Удаление викторины');
                }
            });
        });
    </script>
</body>

</html>