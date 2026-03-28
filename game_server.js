const WebSocket = require('ws');
const http = require('http');
const mysql = require('mysql2');

// Конфигурация базы данных
const db = mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'quiz26'
});

db.connect((err) => {
    if (err) {
        console.error('Ошибка подключения к БД:', err);
        process.exit(1);
    }
    console.log('Подключено к MySQL');
});

const server = http.createServer();
const wss = new WebSocket.Server({ server });

// Хранилище активных игр
const games = new Map();

// Функция генерации случайного кода
function generateGameCode() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let code = '';
    for (let i = 0; i < 4; i++) {
        code += letters[Math.floor(Math.random() * letters.length)];
    }
    return code;
}

// Функция отправки сообщения всем игрокам в игре
function broadcastToGame(gameCode, message, excludeClient = null) {
    const game = games.get(gameCode);
    if (!game) return;
    
    game.players.forEach((player, client) => {
        if (client !== excludeClient && client.readyState === WebSocket.OPEN) {
            client.send(JSON.stringify(message));
        }
    });
}

// Функция отправки сообщения ведущему
function sendToHost(gameCode, message) {
    const game = games.get(gameCode);
    if (game && game.host && game.host.readyState === WebSocket.OPEN) {
        game.host.send(JSON.stringify(message));
    }
}

// Расчёт очков за правильный ответ в зависимости от скорости
function calculatePoints(responseTime, slideDurationMs) {
    const maxPoints = 1000;
    const minPoints = 100;
    // responseTime в миллисекундах, slideDurationMs в миллисекундах
    const ratio = Math.max(0, Math.min(1, 1 - (responseTime / slideDurationMs)));
    return Math.floor(minPoints + (maxPoints - minPoints) * ratio);
}

// Переход к следующему слайду
function nextSlide(gameCode) {
    const game = games.get(gameCode);
    if (!game || game.status !== 'active') return;
    
    game.currentSlide++;
    // Очищаем ответы для нового слайда
    game.currentSlideAnswers.clear();
    game.slideStartTime = Date.now();
    
    if (game.currentSlide >= game.quizData.slides.length) {
        endGame(gameCode);
        return;
    }
    
    const slide = game.quizData.slides[game.currentSlide];
    const slideDuration = (game.quizData.slide_duration || 30) * 1000;
    
    // Отправляем вопрос игрокам
    broadcastToGame(gameCode, {
        type: 'new_question',
        slide: {
            question_text: slide.question_text,
            image_path: slide.image_path,
            options: slide.options,
            duration: game.quizData.slide_duration,
            font_size: slide.font_size,
            font_color: slide.font_color
        },
        slideNumber: game.currentSlide + 1,
        totalSlides: game.quizData.slides.length
    });
    
    // Устанавливаем таймер на автоматическое завершение слайда
    if (game.slideTimer) clearTimeout(game.slideTimer);
    game.slideTimer = setTimeout(() => {
        finishSlide(gameCode);
    }, slideDuration);
}

// Завершение текущего слайда (подсчёт результатов)
function finishSlide(gameCode) {
    const game = games.get(gameCode);
    if (!game || game.status !== 'active') return;
    
    if (game.slideTimer) {
        clearTimeout(game.slideTimer);
        game.slideTimer = null;
    }
    
    const slide = game.quizData.slides[game.currentSlide];
    const slideDurationMs = (game.quizData.slide_duration || 30) * 1000;
    const results = [];
    
    // Обрабатываем ответы всех игроков
    for (let [playerWs, player] of game.players) {
        const answer = game.currentSlideAnswers.get(player.id);
        let isCorrect = false;
        let points = 0;
        
        if (answer) {
            const selectedOption = slide.options[answer.optionIndex];
            isCorrect = selectedOption && selectedOption.is_correct == 1;
            if (isCorrect) {
                points = calculatePoints(answer.responseTime, slideDurationMs);
                player.score += points;
            }
        }
        
        results.push({
            playerId: player.id,
            playerName: player.name,
            isCorrect: isCorrect,
            points: points,
            responseTime: answer ? answer.responseTime : null,
            score: player.score
        });
    }
    
    // Сортируем по времени ответа (быстрее первыми)
    results.sort((a, b) => (a.responseTime || Infinity) - (b.responseTime || Infinity));
    
    // Отправляем результаты ведущему
    sendToHost(gameCode, {
        type: 'slide_results',
        slideNumber: game.currentSlide + 1,
        results: results,
        totalPlayers: game.players.size
    });
    
    // Отправляем результаты игрокам
    broadcastToGame(gameCode, {
        type: 'slide_results_player',
        results: results.map(r => ({
            playerName: r.playerName,
            isCorrect: r.isCorrect,
            points: r.points,
            score: r.score
        }))
    });
    
    // Пауза 5 секунд, затем следующий слайд или окончание
    setTimeout(() => {
        if (game.currentSlide + 1 < game.quizData.slides.length) {
            // Показываем обратный отсчёт
            broadcastToGame(gameCode, {
                type: 'next_slide_countdown',
                seconds: 5
            });
            setTimeout(() => {
                nextSlide(gameCode);
            }, 5000);
        } else {
            endGame(gameCode);
        }
    }, 5000);
}

// Завершение игры
function endGame(gameCode) {
    const game = games.get(gameCode);
    if (!game) return;
    
    const finalResults = [];
    for (let [playerWs, player] of game.players) {
        finalResults.push({
            name: player.name,
            score: player.score
        });
    }
    
    finalResults.sort((a, b) => b.score - a.score);
    
    broadcastToGame(gameCode, {
        type: 'game_ended',
        results: finalResults
    });
    
    // Сохраняем статистику в БД
    if (game.quizData && game.quizData.id) {
        const totalPlayers = game.players.size;
        const averageScore = finalResults.reduce((sum, p) => sum + p.score, 0) / totalPlayers;
        
        db.query(
            'INSERT INTO quiz_statistics (quiz_id, total_players, average_score, completed_at) VALUES (?, ?, ?, NOW())',
            [game.quizData.id, totalPlayers, averageScore],
            (err) => {
                if (err) console.error('Ошибка сохранения статистики:', err);
            }
        );
    }
    
    games.delete(gameCode);
}

// Обработка WebSocket соединений
wss.on('connection', (ws, req) => {
    const url = new URL(req.url, `http://${req.headers.host}`);
    const role = url.searchParams.get('role');
    const gameCode = url.searchParams.get('code');
    const playerName = url.searchParams.get('name');
    
    console.log(`Новое подключение: role=${role}, code=${gameCode}, name=${playerName}`);
    
    if (role === 'host') {
        // Создание новой игры
        const newGameCode = generateGameCode();
        
        games.set(newGameCode, {
            code: newGameCode,
            host: ws,
            players: new Map(),
            quizData: null,
            currentSlide: -1,
            slideStartTime: null,
            status: 'waiting', // waiting, active, finished
            currentSlideAnswers: new Map(),
            slideTimer: null
        });
        
        ws.send(JSON.stringify({
            type: 'game_created',
            code: newGameCode,
            message: 'Игра создана успешно'
        }));
        
        console.log(`Создана игра с кодом: ${newGameCode}`);
        
    } else if (role === 'player' && gameCode) {
        // Подключение игрока к существующей игре
        const game = games.get(gameCode);
        
        if (!game) {
            ws.send(JSON.stringify({
                type: 'error',
                message: 'Игра не найдена'
            }));
            ws.close();
            return;
        }
        
        if (game.status !== 'waiting') {
            ws.send(JSON.stringify({
                type: 'error',
                message: 'Игра уже началась'
            }));
            ws.close();
            return;
        }
        
        const playerId = Math.random().toString(36).substr(2, 9);
        game.players.set(ws, {
            id: playerId,
            name: playerName,
            score: 0,
            ws: ws
        });
        
        ws.send(JSON.stringify({
            type: 'connected',
            playerId: playerId,
            message: `Добро пожаловать, ${playerName}!`
        }));
        
        // Уведомляем ведущего о новом игроке
        sendToHost(gameCode, {
            type: 'player_joined',
            player: {
                id: playerId,
                name: playerName
            },
            totalPlayers: game.players.size
        });
        
        console.log(`Игрок ${playerName} присоединился к игре ${gameCode}`);
    }
    
    // Обработка сообщений от клиентов
    ws.on('message', (data) => {
        try {
            const message = JSON.parse(data);
            console.log('Получено сообщение:', message);
            
            if (role === 'host') {
                handleHostMessage(ws, gameCode, message);
            } else if (role === 'player') {
                handlePlayerMessage(ws, gameCode, message);
            }
        } catch (error) {
            console.error('Ошибка обработки сообщения:', error);
        }
    });
    
    ws.on('close', () => {
        console.log('Клиент отключился');
        
        if (role === 'host' && gameCode) {
            // Ведущий отключился - завершаем игру
            const game = games.get(gameCode);
            if (game) {
                broadcastToGame(gameCode, {
                    type: 'game_ended',
                    message: 'Ведущий завершил игру'
                });
                games.delete(gameCode);
            }
        } else if (role === 'player' && gameCode) {
            // Игрок отключился
            const game = games.get(gameCode);
            if (game) {
                let disconnectedPlayer = null;
                for (let [client, player] of game.players) {
                    if (client === ws) {
                        disconnectedPlayer = player;
                        game.players.delete(client);
                        break;
                    }
                }
                
                if (disconnectedPlayer) {
                    sendToHost(gameCode, {
                        type: 'player_left',
                        player: {
                            id: disconnectedPlayer.id,
                            name: disconnectedPlayer.name
                        },
                        totalPlayers: game.players.size
                    });
                }
            }
        }
    });
});

// Обработка сообщений от ведущего
function handleHostMessage(ws, gameCode, message) {
    const game = games.get(gameCode);
    if (!game) return;
    
    switch (message.type) {
        case 'start_game':
            if (game.players.size === 0) {
                ws.send(JSON.stringify({
                    type: 'error',
                    message: 'Нет подключенных игроков'
                }));
                return;
            }
            
            game.status = 'active';
            game.quizData = message.quizData;
            game.currentSlide = -1;
            game.currentSlideAnswers.clear();
            
            // Уведомляем всех игроков о начале игры
            broadcastToGame(gameCode, {
                type: 'game_started',
                quizTitle: game.quizData.title,
                totalSlides: game.quizData.slides.length
            });
            
            // Показываем заставку 3 секунды, затем первый вопрос
            setTimeout(() => {
                nextSlide(gameCode);
            }, 3000);
            break;
            
        case 'stop_answers':
            if (game.status === 'active' && game.slideTimer) {
                finishSlide(gameCode);
            }
            break;
            
        case 'end_game':
            endGame(gameCode);
            break;
    }
}

// Обработка сообщений от игроков
function handlePlayerMessage(ws, gameCode, message) {
    const game = games.get(gameCode);
    if (!game || game.status !== 'active') return;
    
    switch (message.type) {
        case 'answer':
            const player = game.players.get(ws);
            if (!player) return;
            
            // Проверяем, не отвечал ли уже игрок на этот слайд
            if (game.currentSlideAnswers.has(player.id)) {
                ws.send(JSON.stringify({
                    type: 'error',
                    message: 'Вы уже ответили на этот вопрос'
                }));
                return;
            }
            
            const responseTime = Date.now() - game.slideStartTime;
            const slideDuration = (game.quizData.slide_duration || 30) * 1000;
            
            // Проверяем, не истекло ли время
            if (responseTime > slideDuration) {
                ws.send(JSON.stringify({
                    type: 'error',
                    message: 'Время вышло'
                }));
                return;
            }
            
            game.currentSlideAnswers.set(player.id, {
                optionIndex: message.optionIndex,
                responseTime: responseTime
            });
            
            ws.send(JSON.stringify({
                type: 'answer_received',
                message: 'Ответ принят'
            }));
            break;
    }
}

// Запуск сервера
const PORT = 8080;
server.listen(PORT, () => {
    console.log(`WebSocket сервер запущен на порту ${PORT}`);
    console.log(`WS URL: ws://localhost:${PORT}`);
});