<?php
$gameCode = isset($_GET['code']) ? $_GET['code'] : '';
$playerName = isset($_GET['name']) ? $_GET['name'] : '';

if (!$gameCode || !$playerName) {
    header('Location: game_join.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Игрок - <?php echo htmlspecialchars($playerName); ?></title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .game-container {
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .game-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .game-code {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .score {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(0,0,0,0.5);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: bold;
        }

        .timer {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.5);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 18px;
            font-weight: bold;
        }

        .game-content {
            padding: 40px;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .waiting-message {
            text-align: center;
            color: #666;
        }

        .waiting-message h2 {
            margin-bottom: 20px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .question-container {
            width: 100%;
            display: none;
        }

        .question-text {
            font-size: 32px;
            text-align: center;
            margin-bottom: 30px;
            word-wrap: break-word;
        }

        .question-image {
            text-align: center;
            margin-bottom: 30px;
        }

        .question-image img {
            max-width: 400px;
            max-height: 300px;
            border-radius: 10px;
        }

        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .option-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .option-card:hover:not(.disabled) {
            transform: scale(1.05);
            border-color: #667eea;
            background: #f0f4ff;
        }

        .option-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .option-shape {
            font-size: 40px;
        }

        .option-text {
            flex: 1;
            font-size: 18px;
        }

        .results-container {
            width: 100%;
            display: none;
        }

        .result-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
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

        .countdown {
            text-align: center;
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .options-grid {
                grid-template-columns: 1fr;
            }
            
            .game-content {
                padding: 20px;
            }
            
            .question-text {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <h1>🎮 Quiz26</h1>
            <div class="game-code">Код игры: <?php echo htmlspecialchars($gameCode); ?></div>
            <div class="score" id="score">Счёт: 0</div>
            <div class="timer" id="timer" style="display: none;">⏱️ --</div>
        </div>
        
        <div class="game-content" id="gameContent">
            <div class="waiting-message" id="waitingMessage">
                <h2>Ожидание начала игры...</h2>
                <div class="spinner"></div>
                <p>Игрок: <?php echo htmlspecialchars($playerName); ?></p>
            </div>
            
            <div class="question-container" id="questionContainer">
                <div class="question-text" id="questionText"></div>
                <div class="question-image" id="questionImage"></div>
                <div class="options-grid" id="optionsGrid"></div>
            </div>
            
            <div class="results-container" id="resultsContainer">
                <h3>📊 Результаты вопроса</h3>
                <div id="resultsList"></div>
            </div>
        </div>
    </div>

    <script>
        let ws = null;
        let playerId = null;
        let currentScore = 0;
        let hasAnswered = false;
        let timerInterval = null;
        
        const gameCode = <?php echo json_encode($gameCode); ?>;
        const playerName = <?php echo json_encode($playerName); ?>;
        
        const shapes = ['●', '■', '◆', '★'];
        
        // Подключение к WebSocket
        function connect() {
            ws = new WebSocket(`ws://localhost:8080?role=player&code=${gameCode}&name=${encodeURIComponent(playerName)}`);
            
            ws.onopen = () => {
                console.log('Подключено к серверу');
            };
            
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                console.log('Получено сообщение:', data);
                handleMessage(data);
            };
            
            ws.onerror = (error) => {
                console.error('WebSocket ошибка:', error);
                alert('Ошибка подключения к серверу');
            };
            
            ws.onclose = () => {
                console.log('Отключено от сервера');
                alert('Соединение потеряно. Обновите страницу.');
            };
        }
        
        // Обработка сообщений
        function handleMessage(data) {
            switch(data.type) {
                case 'connected':
                    playerId = data.playerId;
                    break;
                    
                case 'game_started':
                    document.getElementById('waitingMessage').style.display = 'none';
                    document.getElementById('questionContainer').style.display = 'none';
                    document.getElementById('resultsContainer').style.display = 'none';
                    document.getElementById('waitingMessage').innerHTML = `
                        <h2>Игра начинается!</h2>
                        <div class="countdown" id="countdown">3</div>
                    `;
                    
                    let countdown = 3;
                    const countdownInterval = setInterval(() => {
                        countdown--;
                        const countdownEl = document.getElementById('countdown');
                        if (countdownEl) countdownEl.textContent = countdown;
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            document.getElementById('waitingMessage').style.display = 'none';
                        }
                    }, 1000);
                    break;
                    
                case 'new_question':
                    showQuestion(data.slide);
                    document.getElementById('timer').style.display = 'block';
                    startTimer(data.slide.duration);
                    break;
                    
                case 'slide_results_player':
                    showResults(data.results);
                    if (timerInterval) clearInterval(timerInterval);
                    document.getElementById('timer').style.display = 'none';
                    break;
                    
                case 'next_slide_countdown':
                    showCountdown(data.seconds);
                    break;
                    
                case 'game_ended':
                    showGameEnd(data.results);
                    if (timerInterval) clearInterval(timerInterval);
                    document.getElementById('timer').style.display = 'none';
                    break;
                    
                case 'answer_received':
                    console.log('Ответ принят');
                    break;
                    
                case 'error':
                    alert(data.message);
                    break;
            }
        }
        
        // Показ вопроса
        function showQuestion(slide) {
            hasAnswered = false;
            document.getElementById('questionContainer').style.display = 'block';
            document.getElementById('resultsContainer').style.display = 'none';
            
            const questionText = document.getElementById('questionText');
            questionText.textContent = slide.question_text;
            questionText.style.fontSize = (slide.font_size || 24) + 'px';
            questionText.style.color = slide.font_color || '#000000';
            
            const questionImage = document.getElementById('questionImage');
            if (slide.image_path && slide.image_path !== 'null') {
                questionImage.innerHTML = `<img src="uploads/${slide.image_path}?t=${Date.now()}" alt="Question image">`;
                questionImage.style.display = 'block';
            } else {
                questionImage.style.display = 'none';
            }
            
            const optionsGrid = document.getElementById('optionsGrid');
            optionsGrid.innerHTML = '';
            
            slide.options.forEach((option, index) => {
                if (option.option_text) {
                    const optionCard = document.createElement('div');
                    optionCard.className = 'option-card';
                    optionCard.onclick = () => selectAnswer(index);
                    optionCard.innerHTML = `
                        <div class="option-shape">${shapes[index]}</div>
                        <div class="option-text">${escapeHtml(option.option_text)}</div>
                    `;
                    optionsGrid.appendChild(optionCard);
                }
            });
        }
        
        // Выбор ответа
        function selectAnswer(optionIndex) {
            if (hasAnswered) {
                alert('Вы уже ответили на этот вопрос');
                return;
            }
            
            hasAnswered = true;
            
            // Отключаем все опции
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.add('disabled');
            });
            
            ws.send(JSON.stringify({
                type: 'answer',
                optionIndex: optionIndex
            }));
        }
        
        // Таймер
        function startTimer(duration) {
            let timeLeft = duration;
            const timerElement = document.getElementById('timer');
            
            if (timerInterval) clearInterval(timerInterval);
            
            timerElement.textContent = `⏱️ ${timeLeft}`;
            
            timerInterval = setInterval(() => {
                timeLeft--;
                timerElement.textContent = `⏱️ ${timeLeft}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    timerElement.textContent = '⏱️ 0';
                }
            }, 1000);
        }
        
        // Показ результатов
        function showResults(results) {
            document.getElementById('questionContainer').style.display = 'none';
            document.getElementById('resultsContainer').style.display = 'block';
            
            const resultsList = document.getElementById('resultsList');
            resultsList.innerHTML = '';
            
            const myResult = results.find(r => r.playerName === playerName);
            if (myResult) {
                currentScore = myResult.score;
                document.getElementById('score').textContent = `Счёт: ${currentScore}`;
            }
            
            results.forEach(result => {
                const resultDiv = document.createElement('div');
                resultDiv.className = 'result-item';
                resultDiv.innerHTML = `
                    <span><strong>${escapeHtml(result.playerName)}</strong></span>
                    <span class="${result.isCorrect ? 'result-correct' : 'result-wrong'}">
                        ${result.isCorrect ? '✓ +' + result.points : '✗ 0'}
                    </span>
                    <span>${result.score} очков</span>
                `;
                resultsList.appendChild(resultDiv);
            });
        }
        
        // Показ обратного отсчета
        function showCountdown(seconds) {
            document.getElementById('resultsContainer').style.display = 'none';
            document.getElementById('waitingMessage').style.display = 'block';
            document.getElementById('waitingMessage').innerHTML = `
                <h2>Следующий вопрос через ${seconds} секунд</h2>
                <div class="countdown" id="countdown">${seconds}</div>
            `;
            
            let countdown = seconds;
            const countdownInterval = setInterval(() => {
                countdown--;
                const countdownEl = document.getElementById('countdown');
                if (countdownEl) countdownEl.textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('waitingMessage').style.display = 'none';
                }
            }, 1000);
        }
        
        // Показ окончания игры
        function showGameEnd(results) {
            document.getElementById('questionContainer').style.display = 'none';
            document.getElementById('resultsContainer').style.display = 'block';
            document.getElementById('resultsContainer').innerHTML = `
                <h2>🏆 Игра окончена! 🏆</h2>
                <div id="resultsList"></div>
            `;
            
            const resultsList = document.getElementById('resultsList');
            resultsList.innerHTML = '';
            
            results.forEach((result, index) => {
                const resultDiv = document.createElement('div');
                resultDiv.className = 'result-item';
                resultDiv.innerHTML = `
                    <span>${index + 1}. <strong>${escapeHtml(result.name)}</strong></span>
                    <span class="result-correct">${result.score} очков</span>
                `;
                resultsList.appendChild(resultDiv);
            });
            
            const myFinal = results.find(r => r.name === playerName);
            if (myFinal) {
                document.getElementById('score').textContent = `Счёт: ${myFinal.score}`;
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Подключаемся
        connect();
    </script>
</body>
</html>