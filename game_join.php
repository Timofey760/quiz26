<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение к игре - Quiz26</title>
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

        .join-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        h1 {
            text-align: center;
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

        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .code-input {
            text-transform: uppercase;
            text-align: center;
            font-size: 24px;
            letter-spacing: 5px;
        }

        .btn-join {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .game-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="join-container">
        <h1>🎮 Подключение к игре</h1>
        
        <form id="joinForm">
            <div class="form-group">
                <label>Код игры (4 буквы)</label>
                <input type="text" id="gameCode" class="code-input" maxlength="4" placeholder="ABCD" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label>Ваше имя</label>
                <input type="text" id="playerName" maxlength="20" placeholder="Введите ваше имя" required>
            </div>
            
            <button type="submit" class="btn-join">Присоединиться</button>
        </form>
        
        <div class="game-info">
            💡 Введите 4-буквенный код, который показывает ведущий
        </div>
    </div>

    <script>
        document.getElementById('joinForm').addEventListener('submit', (e) => {
            e.preventDefault();
            
            const gameCode = document.getElementById('gameCode').value.toUpperCase().trim();
            const playerName = document.getElementById('playerName').value.trim();
            
            if (!gameCode || gameCode.length !== 4) {
                alert('Введите корректный код игры (4 буквы)');
                return;
            }
            
            if (!playerName) {
                alert('Введите ваше имя');
                return;
            }
            
            // Перенаправляем на страницу игры
            window.location.href = `game_player.php?code=${gameCode}&name=${encodeURIComponent(playerName)}`;
        });
        
        // Автоматический перевод в верхний регистр
        document.getElementById('gameCode').addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>