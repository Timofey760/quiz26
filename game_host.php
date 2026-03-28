<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$user_id = $_SESSION['user_id'];

// Получаем данные викторины
$quiz = null;
$slides = [];

if ($quiz_id) {
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $quiz_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quiz = $result->fetch_assoc();
    $stmt->close();
    
    if ($quiz) {
        $stmt = $conn->prepare("SELECT * FROM slides WHERE quiz_id = ? ORDER BY slide_order ASC");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $options_stmt = $conn->prepare("SELECT * FROM answer_options WHERE slide_id = ? ORDER BY option_order ASC");
            $options_stmt->bind_param("i", $row['id']);
            $options_stmt->execute();
            $options_result = $options_stmt->get_result();
            $row['options'] = [];
            while ($option = $options_result->fetch_assoc()) {
                $row['options'][] = $option;
            }
            $options_stmt->close();
            $slides[] = $row;
        }
        $stmt->close();
    }
}

// Проверяем наличие слайдов
if (!$quiz || empty($slides)) {
    header('Location: dashboard.php?msg=' . urlencode('Викторина не найдена или не содержит вопросов'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ведущий - <?php echo htmlspecialchars($quiz['title']); ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .game-code {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
        }

        .game-code span {
            background: #f0f0f0;
            padding: 10px 20px;
            border-radius: 10px;
            letter-spacing: 5px;
        }

        .players-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .players-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .player-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .player-name {
            font-weight: bold;
        }

        .player-score {
            background: rgba(255,255,255,0.3);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
        }

        .game-controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin: 5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .results-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: none;
        }

        .result-item {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .result-correct {
            color: #28a745;
            font-weight: bold;
        }

        .result-wrong {
            color: #dc3545;
        }

        .current-question {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .question-text {
            font-size: 24px;
            margin-bottom: 20px;
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

        .status {
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }

        .status-waiting {
            background: #ffc107;
            color: #333;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-finished {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">Подключение к серверу...</div>
    
    <div class="container">
        <div class="header">
            <h1>🎮 Ведущий: <?php echo htmlspecialchars($quiz['title']); ?></h1>
            <div class="game-code">
                Код игры: <span id="gameCode">---</span>
            </div>
        </div>
        
        <div class="players-panel">
            <h3>👥 Игроки (<span id="playersCount">0</span>)</h3>
            <div class="players-list" id="playersList"></div>
        </div>
        
        <div class="game-controls">
            <div class="status" id="gameStatus">Ожидание подключения игроков</div>
            <button class="btn btn-primary" id="startGameBtn" onclick="startGame()" disabled>🚀 Начать игру</button>
            <button class="btn btn-danger" id="endGameBtn" onclick="endGame()" disabled>⏹️ Завершить игру</button>
            <button class="btn btn-warning" id="stopAnswersBtn" onclick="stopAnswers()" style="display: none;">⏸️ Завершить приём ответов</button>
        </div>
        
        <div class="current-question" id="currentQuestionPanel">
            <h3>Текущий вопрос</h3>
            <div class="question-text" id="questionText"></div>
        </div>
        
        <div class="results-panel" id="resultsPanel">
            <h3>📊 Результаты</h3>
            <div id="resultsList"></div>
        </div>
    </div>

    <script>
        let ws = null;
        let gameCode = null;
        let quizData = null;
        let isGameActive = false;
        
        const quizId = <?php echo $quiz_id; ?>;
        const quizTitle = <?php echo json_encode($quiz['title']); ?>;
        const slides = <?php echo json_encode($slides); ?>;
        
        // Подключение к WebSocket
        function connect() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
            
            ws = new WebSocket('ws://localhost:8080?role=host');
            
            ws.onopen = () => {
                loading.style.display = 'none';
                console.log('Подключено к серверу');
            };
            
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                console.log('Получено сообщение:', data);
                handleMessage(data);
            };
            
            ws.onerror = (error) => {
                console.error('WebSocket ошибка:', error);
                loading.style.display = 'none';
                alert('Ошибка подключения к серверу. Убедитесь, что сервер запущен.');
            };
            
            ws.onclose = () => {
                console.log('Отключено от сервера');
                if (isGameActive) {
                    alert('Соединение потеряно');
                }
            };
        }
        
        // Обработка сообщений
        function handleMessage(data) {
            switch(data.type) {
                case 'game_created':
                    gameCode = data.code;
                    document.getElementById('gameCode').innerHTML = `<span>${gameCode}</span>`;
                    document.getElementById('startGameBtn').disabled = false;
                    break;
                    
                case 'player_joined':
                    updatePlayersList(data.player, 'add');
                    document.getElementById('playersCount').textContent = data.totalPlayers;
                    break;
                    
                case 'player_left':
                    updatePlayersList(data.player, 'remove');
                    document.getElementById('playersCount').textContent = data.totalPlayers;
                    break;
                    
                case 'game_started':
                    isGameActive = true;
                    document.getElementById('gameStatus').textContent = 'Игра идёт';
                    document.getElementById('gameStatus').className = 'status status-active';
                    document.getElementById('startGameBtn').disabled = true;
                    document.getElementById('endGameBtn').disabled = false;
                    break;
                    
                case 'new_question':
                    showCurrentQuestion(data);
                    document.getElementById('stopAnswersBtn').style.display = 'inline-block';
                    break;
                    
                case 'slide_results':
                    showSlideResults(data);
                    document.getElementById('stopAnswersBtn').style.display = 'none';
                    break;
                    
                case 'game_ended':
                    showGameResults(data);
                    isGameActive = false;
                    document.getElementById('gameStatus').textContent = 'Игра завершена';
                    document.getElementById('gameStatus').className = 'status status-finished';
                    document.getElementById('endGameBtn').disabled = true;
                    break;
            }
        }
        
        // Обновление списка игроков
        function updatePlayersList(player, action) {
            const playersList = document.getElementById('playersList');
            
            if (action === 'add') {
                const playerCard = document.createElement('div');
                playerCard.className = 'player-card';
                playerCard.id = `player_${player.id}`;
                playerCard.innerHTML = `
                    <span class="player-name">${escapeHtml(player.name)}</span>
                    <span class="player-score">0</span>
                `;
                playersList.appendChild(playerCard);
            } else if (action === 'remove') {
                const playerCard = document.getElementById(`player_${player.id}`);
                if (playerCard) playerCard.remove();
            }
        }
        
        // Обновление очков игроков
        function updatePlayerScore(playerId, score) {
            const playerCard = document.getElementById(`player_${playerId}`);
            if (playerCard) {
                const scoreSpan = playerCard.querySelector('.player-score');
                if (scoreSpan) scoreSpan.textContent = score;
            }
        }
        
        // Показ текущего вопроса
        function showCurrentQuestion(data) {
            const panel = document.getElementById('currentQuestionPanel');
            const questionText = document.getElementById('questionText');
            panel.style.display = 'block';
            questionText.innerHTML = data.slide.question_text;
            questionText.style.fontSize = (data.slide.font_size || 24) + 'px';
            questionText.style.color = data.slide.font_color || '#000000';
        }
        
        // Показ результатов слайда
        function showSlideResults(data) {
            const resultsPanel = document.getElementById('resultsPanel');
            const resultsList = document.getElementById('resultsList');
            
            resultsList.innerHTML = '<h4>Результаты вопроса ' + data.slideNumber + ':</h4>';
            
            data.results.forEach(result => {
                const resultDiv = document.createElement('div');
                resultDiv.className = 'result-item';
                resultDiv.innerHTML = `
                    <span><strong>${escapeHtml(result.playerName)}</strong></span>
                    <span class="${result.isCorrect ? 'result-correct' : 'result-wrong'}">
                        ${result.isCorrect ? '✓ Правильно! +' + result.points : '✗ Неправильно'}
                    </span>
                    <span>Всего очков: ${result.score}</span>
                `;
                resultsList.appendChild(resultDiv);
                updatePlayerScore(result.playerId, result.score);
            });
            
            resultsPanel.style.display = 'block';
            
            setTimeout(() => {
                resultsPanel.style.display = 'none';
            }, 4000);
        }
        
        // Показ финальных результатов
        function showGameResults(data) {
            const resultsPanel = document.getElementById('resultsPanel');
            const resultsList = document.getElementById('resultsList');
            
            resultsList.innerHTML = '<h3>🏆 Итоговые результаты 🏆</h3>';
            
            data.results.forEach((result, index) => {
                const resultDiv = document.createElement('div');
                resultDiv.className = 'result-item';
                resultDiv.innerHTML = `
                    <span>${index + 1}. <strong>${escapeHtml(result.name)}</strong></span>
                    <span class="result-correct">${result.score} очков</span>
                `;
                resultsList.appendChild(resultDiv);
            });
            
            resultsPanel.style.display = 'block';
            document.getElementById('currentQuestionPanel').style.display = 'none';
        }
        
        // Начать игру
        function startGame() {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                alert('Нет подключения к серверу');
                return;
            }
            
            quizData = {
                id: quizId,
                title: quizTitle,
                slides: slides,
                slide_duration: <?php echo $quiz['slide_duration'] ?? 30; ?>
            };
            
            ws.send(JSON.stringify({
                type: 'start_game',
                quizData: quizData
            }));
        }
        
        // Завершить приём ответов
        function stopAnswers() {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'stop_answers'
                }));
            }
        }
        
        // Завершить игру
        function endGame() {
            if (confirm('Завершить игру?')) {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        type: 'end_game'
                    }));
                }
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Подключаемся при загрузке страницы
        connect();
    </script>
</body>
</html>