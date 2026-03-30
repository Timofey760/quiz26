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
            <button class="btn btn-warning" id="nextSlideBtn" onclick="nextSlide()" style="display: none;">⏩ Следующий вопрос</button>
            <button class="btn btn-danger" id="endGameBtn" onclick="endGame()" disabled>⏹️ Завершить игру</button>
        </div>

        <div class="current-question" id="currentQuestionPanel">
            <h3>Текущий вопрос</h3>
            <div class="question-text" id="questionText"></div>
        </div>

        <div class="results-panel" id="resultsPanel">
            <h3>📊 Результаты вопроса</h3>
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
        const slideDuration = <?php echo $quiz['slide_duration'] ?? 30; ?>;

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
                console.log('Получено:', data);
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
            switch (data.type) {
                case 'game_created':
                    gameCode = data.code;
                    document.getElementById('gameCode').innerHTML = `<span>${gameCode}</span>`;
                    document.getElementById('startGameBtn').disabled = false;
                    break;

                case 'player_joined':
                    addPlayer(data.player);
                    document.getElementById('playersCount').textContent = data.totalPlayers;
                    break;

                case 'player_left':
                    removePlayer(data.player.id);
                    document.getElementById('playersCount').textContent = data.totalPlayers;
                    break;

                case 'game_started':
                    isGameActive = true;
                    document.getElementById('gameStatus').textContent = 'Игра идёт';
                    document.getElementById('gameStatus').className = 'status status-active';
                    document.getElementById('startGameBtn').style.display = 'none';
                    document.getElementById('nextSlideBtn').style.display = 'inline-block';
                    document.getElementById('endGameBtn').disabled = false;
                    break;

                case 'new_question':
                    showCurrentQuestion(data.slide, data.slideNumber, data.totalSlides);
                    document.getElementById('resultsPanel').style.display = 'none';
                    break;

                case 'slide_results':
                    showSlideResults(data);
                    break;

                case 'game_ended':
                    showGameResults(data);
                    isGameActive = false;
                    document.getElementById('gameStatus').textContent = 'Игра завершена';
                    document.getElementById('gameStatus').className = 'status status-finished';
                    document.getElementById('nextSlideBtn').style.display = 'none';
                    document.getElementById('endGameBtn').disabled = true;
                    break;
            }
        }

        function addPlayer(player) {
            const playersList = document.getElementById('playersList');
            const existing = document.getElementById(`player_${player.id}`);
            if (existing) return;
            const playerCard = document.createElement('div');
            playerCard.className = 'player-card';
            playerCard.id = `player_${player.id}`;
            playerCard.innerHTML = `
                <span class="player-name">${escapeHtml(player.name)}</span>
                <span class="player-score">0</span>
            `;
            playersList.appendChild(playerCard);
        }

        function removePlayer(playerId) {
            const playerCard = document.getElementById(`player_${playerId}`);
            if (playerCard) playerCard.remove();
        }

        function updatePlayerScore(playerId, score) {
            const card = document.getElementById(`player_${playerId}`);
            if (card) {
                const scoreSpan = card.querySelector('.player-score');
                if (scoreSpan) scoreSpan.textContent = score;
            }
        }

        function showCurrentQuestion(slide, slideNumber, totalSlides) {
            const panel = document.getElementById('currentQuestionPanel');
            const questionText = document.getElementById('questionText');
            panel.style.display = 'block';
            questionText.innerHTML = `<strong>Вопрос ${slideNumber} из ${totalSlides}</strong><br>${escapeHtml(slide.question_text)}`;
            questionText.style.fontSize = (slide.font_size || 24) + 'px';
            questionText.style.color = slide.font_color || '#000000';
        }

        function showSlideResults(data) {
            const panel = document.getElementById('resultsPanel');
            const resultsList = document.getElementById('resultsList');
            resultsList.innerHTML = `<h4>Результаты вопроса ${data.slideNumber}:</h4>`;

            data.results.forEach(result => {
                const div = document.createElement('div');
                div.className = 'result-item';
                div.innerHTML = `
                    <span><strong>${escapeHtml(result.playerName)}</strong></span>
                    <span class="${result.isCorrect ? 'result-correct' : 'result-wrong'}">
                        ${result.isCorrect ? `✓ +${result.points}` : '✗ 0'}
                    </span>
                    <span>Всего: ${result.score}</span>
                `;
                resultsList.appendChild(div);
                updatePlayerScore(result.playerId, result.score);
            });
            panel.style.display = 'block';
        }

        function showGameResults(data) {
            const panel = document.getElementById('resultsPanel');
            const resultsList = document.getElementById('resultsList');
            resultsList.innerHTML = '<h2>🏆 Итоговые результаты 🏆</h2>';
            data.results.forEach((result, idx) => {
                const div = document.createElement('div');
                div.className = 'result-item';
                div.innerHTML = `
                    <span>${idx + 1}. <strong>${escapeHtml(result.name)}</strong></span>
                    <span class="result-correct">${result.score} очков</span>
                `;
                resultsList.appendChild(div);
            });
            panel.style.display = 'block';
            document.getElementById('currentQuestionPanel').style.display = 'none';
        }

        function startGame() {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                alert('Нет подключения к серверу');
                return;
            }
            quizData = {
                id: quizId,
                title: quizTitle,
                slides: slides,
                slide_duration: slideDuration
            };
            ws.send(JSON.stringify({
                type: 'start_game',
                quizData: quizData
            }));
        }

        function nextSlide() {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'next_slide' }));
            }
        }

        function endGame() {
            if (confirm('Завершить игру?')) {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({ type: 'end_game' }));
                }
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        connect();
    </script>
</body>
</html>