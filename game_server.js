const WebSocket = require('ws');
const http = require('http');
const url = require('url');
const mysql = require('mysql2');

// Конфигурация БД (опционально, для статистики)
const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'quiz26'
});

db.connect(err => {
    if (err) console.error('DB connection error:', err);
    else console.log('Connected to MySQL');
});

const server = http.createServer();
const wss = new WebSocket.Server({ server });

// Хранилище активных игр: gameCode -> объект игры
const games = new Map();

// Генерация случайного 4-буквенного кода
function generateGameCode() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let code;
    do {
        code = '';
        for (let i = 0; i < 4; i++) {
            code += letters[Math.floor(Math.random() * letters.length)];
        }
    } while (games.has(code));
    return code;
}

// Отправка сообщения всем игрокам (и ведущему, если нужно)
function broadcastToGame(game, message, excludeClient = null) {
    if (game.host && game.host !== excludeClient && game.host.readyState === WebSocket.OPEN) {
        game.host.send(JSON.stringify(message));
    }
    for (let [client, player] of game.players) {
        if (client !== excludeClient && client.readyState === WebSocket.OPEN) {
            client.send(JSON.stringify(message));
        }
    }
}

// Отправка только ведущему
function sendToHost(game, message) {
    if (game.host && game.host.readyState === WebSocket.OPEN) {
        game.host.send(JSON.stringify(message));
    }
}

// Остановка таймера слайда
function stopSlideTimer(game) {
    if (game.slideTimer) {
        clearTimeout(game.slideTimer);
        game.slideTimer = null;
    }
}

// Завершение текущего слайда и показ результатов
function finishSlide(game) {
    if (game.state !== 'active' || game.currentSlide < 0) return;

    stopSlideTimer(game);

    const slide = game.quizData.slides[game.currentSlide];
    const results = [];
    const playersArray = Array.from(game.players.values());

    // Собираем ответы
    for (let player of playersArray) {
        const answer = game.answers.get(player.id);
        let isCorrect = false;
        let points = 0;
        if (answer) {
            const selectedOption = slide.options[answer.optionIndex];
            isCorrect = selectedOption && selectedOption.is_correct == 1;
            if (isCorrect) {
                // Бонус за скорость: max 10 очков, скорость в процентах от времени
                const maxPoints = 10;
                const timePercent = 1 - (answer.responseTime / (game.quizData.slide_duration * 1000));
                points = Math.floor(maxPoints * Math.max(0, timePercent));
                player.score += points;
            }
        }
        results.push({
            playerId: player.id,
            playerName: player.name,
            isCorrect: isCorrect,
            points: points,
            score: player.score,
            responseTime: answer ? answer.responseTime : null
        });
    }

    // Сортируем по времени ответа (быстрее – выше)
    results.sort((a, b) => (a.responseTime || Infinity) - (b.responseTime || Infinity));

    // Отправляем результаты ведущему и игрокам
    broadcastToGame(game, {
        type: 'slide_results',
        slideNumber: game.currentSlide + 1,
        results: results,
        totalPlayers: playersArray.length
    });

    // Переходим к следующему слайду или завершаем игру
    if (game.currentSlide + 1 < game.quizData.slides.length) {
        // Пауза 5 секунд, затем следующий слайд
        setTimeout(() => {
            if (game.state === 'active') {
                nextSlide(game);
            }
        }, 5000);
    } else {
        endGame(game);
    }
}

// Переход к следующему слайду
function nextSlide(game) {
    if (game.state !== 'active') return;

    game.currentSlide++;
    if (game.currentSlide >= game.quizData.slides.length) {
        endGame(game);
        return;
    }

    const slide = game.quizData.slides[game.currentSlide];
    const duration = game.quizData.slide_duration || 30;

    // Очищаем ответы на новый слайд
    game.answers.clear();

    // Отправляем вопрос всем
    broadcastToGame(game, {
        type: 'new_question',
        slide: {
            question_text: slide.question_text,
            image_path: slide.image_path,
            options: slide.options,
            duration: duration,
            font_size: slide.font_size,
            font_color: slide.font_color
        },
        slideNumber: game.currentSlide + 1,
        totalSlides: game.quizData.slides.length
    });

    // Запускаем таймер
    stopSlideTimer(game);
    game.slideTimer = setTimeout(() => {
        finishSlide(game);
    }, duration * 1000);
}

// Завершение игры
function endGame(game) {
    if (game.state === 'finished') return;
    game.state = 'finished';
    stopSlideTimer(game);

    // Подсчет финальных результатов
    const finalResults = Array.from(game.players.values())
        .map(p => ({ name: p.name, score: p.score }))
        .sort((a, b) => b.score - a.score);

    broadcastToGame(game, {
        type: 'game_ended',
        results: finalResults
    });

    // Сохранение статистики в БД (опционально)
    if (game.quizData && game.quizData.id) {
        const totalPlayers = game.players.size;
        const averageScore = finalResults.reduce((sum, p) => sum + p.score, 0) / totalPlayers;
        db.query(
            'INSERT INTO quiz_statistics (quiz_id, total_players, average_score, completed_at) VALUES (?, ?, ?, NOW())',
            [game.quizData.id, totalPlayers, averageScore],
            err => { if (err) console.error('Stat save error:', err); }
        );
    }

    // Удаляем игру из хранилища через 10 секунд
    setTimeout(() => {
        games.delete(game.code);
    }, 10000);
}

// Обработка сообщений от ведущего
function handleHostMessage(ws, game, message) {
    switch (message.type) {
        case 'start_game':
            if (game.state !== 'waiting') return;
            game.state = 'active';
            game.quizData = message.quizData;
            game.currentSlide = -1; // будет увеличен в nextSlide
            game.answers = new Map();

            // Сообщаем игрокам о старте
            broadcastToGame(game, {
                type: 'game_started',
                quizTitle: game.quizData.title,
                totalSlides: game.quizData.slides.length
            });

            // Пауза 3 секунды, затем первый слайд
            setTimeout(() => {
                if (game.state === 'active') {
                    nextSlide(game);
                }
            }, 3000);
            break;

        case 'next_slide':
            if (game.state === 'active') {
                // Принудительное завершение текущего слайда
                finishSlide(game);
            }
            break;

        case 'end_game':
            endGame(game);
            break;
    }
}

// Обработка сообщений от игрока
function handlePlayerMessage(ws, game, player, message) {
    if (game.state !== 'active') return;
    if (message.type === 'answer') {
        // Проверяем, не отвечал ли уже игрок на этот слайд
        if (game.answers.has(player.id)) {
            ws.send(JSON.stringify({ type: 'error', message: 'Вы уже ответили' }));
            return;
        }
        const responseTime = Date.now() - game.slideStartTime;
        const duration = (game.quizData.slide_duration || 30) * 1000;
        if (responseTime > duration) {
            ws.send(JSON.stringify({ type: 'error', message: 'Время вышло' }));
            return;
        }
        game.answers.set(player.id, {
            optionIndex: message.optionIndex,
            responseTime: responseTime
        });
        ws.send(JSON.stringify({ type: 'answer_received' }));
    }
}

// WebSocket обработка подключений
wss.on('connection', (ws, req) => {
    const params = url.parse(req.url, true).query;
    const role = params.role; // 'host' или 'player'
    const code = params.code;
    const playerName = params.name;

    if (role === 'host') {
        // Создание новой игры
        const gameCode = generateGameCode();
        const game = {
            code: gameCode,
            host: ws,
            players: new Map(),
            state: 'waiting', // waiting, active, finished
            quizData: null,
            currentSlide: -1,
            answers: null,
            slideTimer: null,
            slideStartTime: null
        };
        games.set(gameCode, game);

        ws.send(JSON.stringify({
            type: 'game_created',
            code: gameCode,
            message: 'Игра создана'
        }));
        console.log(`Игра ${gameCode} создана`);

        ws.on('message', data => {
            try {
                const message = JSON.parse(data);
                handleHostMessage(ws, game, message);
            } catch (err) {
                console.error('Host message error:', err);
            }
        });

        ws.on('close', () => {
            // Ведущий отключился – завершаем игру
            if (games.has(gameCode)) {
                endGame(game);
                games.delete(gameCode);
                console.log(`Игра ${gameCode} завершена (хост отключился)`);
            }
        });

    } else if (role === 'player' && code) {
        const game = games.get(code);
        if (!game) {
            ws.send(JSON.stringify({ type: 'error', message: 'Игра не найдена' }));
            ws.close();
            return;
        }
        if (game.state !== 'waiting') {
            ws.send(JSON.stringify({ type: 'error', message: 'Игра уже началась' }));
            ws.close();
            return;
        }
        if (!playerName || playerName.trim() === '') {
            ws.send(JSON.stringify({ type: 'error', message: 'Имя не указано' }));
            ws.close();
            return;
        }

        const playerId = Math.random().toString(36).substr(2, 9);
        const player = {
            id: playerId,
            name: playerName.trim(),
            score: 0,
            ws: ws
        };
        game.players.set(ws, player);

        ws.send(JSON.stringify({
            type: 'connected',
            playerId: playerId,
            message: `Добро пожаловать, ${playerName}!`
        }));

        // Уведомляем ведущего о новом игроке
        sendToHost(game, {
            type: 'player_joined',
            player: { id: playerId, name: player.name },
            totalPlayers: game.players.size
        });

        ws.on('message', data => {
            try {
                const message = JSON.parse(data);
                handlePlayerMessage(ws, game, player, message);
            } catch (err) {
                console.error('Player message error:', err);
            }
        });

        ws.on('close', () => {
            // Игрок отключился
            game.players.delete(ws);
            sendToHost(game, {
                type: 'player_left',
                player: { id: player.id, name: player.name },
                totalPlayers: game.players.size
            });
        });
    } else {
        ws.close();
    }
});

const PORT = 8080;
server.listen(PORT, () => {
    console.log(`WebSocket сервер запущен на порту ${PORT}`);
});