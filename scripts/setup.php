<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate passcode
    $passcode = $_POST['passcode'];
    if (!preg_match('/^\d{4}$/', $passcode)) {
        $_SESSION['setup_error'] = "Invalid passcode. Please enter a 4-digit number.";
        header('Location: setup.php');
        exit;
    }

    // Validate player names
    $players = $_POST['players'] ?? [];
    $unique_players = array_unique($players);
    if (count($players) !== count($unique_players)) {
        $_SESSION['setup_error'] = "Duplicate player names are not allowed.";
        header('Location: setup.php');
        exit;
    }

    // Check if acknowledgement is checked
    if (!isset($_POST['acknowledgement']) || $_POST['acknowledgement'] !== 'yes') {
        $_SESSION['setup_error'] = "Please acknowledge the disclaimer to start the game.";
        header('Location: setup.php');
        exit;
    }

    // Save game data
    $game_name = $_POST['game_name'];
    $game_data = [
        'name' => $game_name,
        'passcode' => $passcode,
        'drop_score' => intval($_POST['drop_score']),
        'middle_drop' => intval($_POST['middle_drop']),
        'full_count' => intval($_POST['full_count']),
        'game_score' => intval($_POST['game_score']),
        'winner_score' => 0,
        'players' => $players,
        'rounds' => [],
        'wins' => array_fill_keys($players, 0),
        'cumulative_scores' => array_fill_keys($players, 0),
        'acknowledgement' => [
            'acknowledged' => 'yes',
            'time' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]
    ];

    if (!file_exists('data/games')) {
        mkdir('data/games');
    }
    file_put_contents("data/games/{$game_name}.json", json_encode($game_data));

    unset($_SESSION['setup_error']);
    header('Location: rounds.php?game=' . urlencode($game_name));
    exit;
}

$setup_error = $_SESSION['setup_error'] ?? '';
unset($_SESSION['setup_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Setup</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .remove-player {
            margin-left: 10px;
            background: #ff4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        #player-list li {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 5px;
            background: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .drag-handle {
            margin-left: 10px;
            cursor: move;
            color: #666;
        }
        .acknowledgement-container {
            margin-top: 20px;
            text-align: left;
            display: none;
        }
        .acknowledgement-container label {
            display: flex;
            align-items: center;
            color: var(--text-secondary);
        }
        .acknowledgement-container input {
            margin-right: 10px;
        }
        .ip-address {
            color: #aaa;
            font-size: 0.8em;
            margin-top: 5px;
        }
        .player-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'components/header.html'; ?>

    <div class="container">

    <?php if ($setup_error): ?>
            <div class="error-message"><?= $setup_error ?></div>
        <?php endif; ?>
        
        <form method="POST" id="setup-form" class="form">
        <!-- Add passcode field -->
        <div class="form-group">
                <label for="passcode">Set 4-Digit Passcode:</label>
                <input type="password" id="passcode" name="passcode" 
                       pattern="\d{4}" maxlength="4" required
                       placeholder="Enter 4-digit passcode">
                <small>This passcode will be required to access this game.</small>
            </div>    
        <div class="form-group">
                <label for="game_name">Game Name:</label>
                <input type="text" id="game_name" name="game_name" required>
            </div>

            <div class="form-group">
                <label for="drop_score">Drop Score:</label>
                <input type="number" id="drop_score" name="drop_score" value="20" required>
            </div>

            <div class="form-group">
                <label for="middle_drop">Middle Drop:</label>
                <input type="number" id="middle_drop" name="middle_drop" value="40" required>
            </div>

            <div class="form-group">
                <label for="full_count">Full Count:</label>
                <input type="number" id="full_count" name="full_count" value="80" required>
            </div>

            <div class="form-group">
                <label for="game_score">Game Score (Out Score):</label>
                <input type="number" id="game_score" name="game_score" value="251" required>
            </div>

            <div class="form-group">
                <label for="winner_score">Winner Score:</label>
                <input type="number" id="winner_score" name="winner_score" value="0" disabled>
            </div>

            <h2>Players</h2>
            <ul id="player-list">
                <li draggable="true">
                    <input type="text" name="players[]" required>
                    <span class="drag-handle">☰</span>
                    <button type="button" class="remove-player" onclick="removePlayer(this)">×</button>
                </li>
            </ul>
            <div class="player-actions">
                <button type="button" class="btn" onclick="addPlayer()">Add Player</button>
                <button type="button" class="btn btn-primary" id="done-button">Done</button>
            </div>
            <div class="acknowledgement-container">
                <label>
                    <input type="checkbox" name="acknowledgement" value="yes" id="acknowledgement-checkbox">
                    I acknowledge that this app is for entertainment purposes only and the developer is not liable for any outcomes.
                </label>
                <p class="ip-address">Your IP Address: <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
            </div>
            <br/><br/>
            <button type="submit" class="btn btn-primary" id="start-game-button" style="display: none;" disabled>Start Game</button>
        

        </form>
    </div>
    <?php include 'components/footer.html'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const playerList = document.getElementById('player-list');
            let draggedItem = null;
            let touchY = null;
            const acknowledgementCheckbox = document.getElementById('acknowledgement-checkbox');
            const startGameButton = document.getElementById('start-game-button');
            const acknowledgementContainer = document.querySelector('.acknowledgement-container');
            const doneButton = document.getElementById('done-button');

            // Add player function
            window.addPlayer = function() {
                const newPlayer = document.createElement('li');
                newPlayer.setAttribute('draggable', 'true');
                newPlayer.innerHTML = `
                    <input type="text" name="players[]" required>
                    <span class="drag-handle">☰</span>
                    <button type="button" class="remove-player" onclick="removePlayer(this)">×</button>
                `;
                playerList.appendChild(newPlayer);
                setupDragListeners(newPlayer);
            };

            // Remove player function
            window.removePlayer = function(button) {
                const li = button.parentElement;
                if (playerList.children.length > 1) {
                    li.remove();
                } else {
                    alert('At least one player is required!');
                }
            };

            // Setup drag listeners for an element
            function setupDragListeners(element) {
                element.addEventListener('dragstart', handleDragStart);
                element.addEventListener('dragend', handleDragEnd);
                element.addEventListener('touchstart', handleTouchStart, { passive: false });
                element.addEventListener('touchmove', handleTouchMove, { passive: false });
                element.addEventListener('touchend', handleTouchEnd);
            }

            // Setup initial drag listeners
            playerList.querySelectorAll('li').forEach(setupDragListeners);

            // Mouse events
            function handleDragStart(e) {
                draggedItem = e.target;
                setTimeout(() => {
                    e.target.style.opacity = '0.5';
                }, 0);
            }

            function handleDragEnd(e) {
                e.target.style.opacity = '1';
                draggedItem = null;
            }

            playerList.addEventListener('dragover', (e) => {
                e.preventDefault();
                const afterElement = getDragAfterElement(playerList, e.clientY);
                if (draggedItem) {
                    if (afterElement) {
                        playerList.insertBefore(draggedItem, afterElement);
                    } else {
                        playerList.appendChild(draggedItem);
                    }
                }
            });

            // Touch events
            function handleTouchStart(e) {
                if (e.target.tagName === 'LI') {
                    draggedItem = e.target;
                    touchY = e.touches[0].clientY;
                    e.target.style.opacity = '0.5';
                }
            }

            function handleTouchMove(e) {
                if (draggedItem) {
                    e.preventDefault();
                    const touch = e.touches[0];
                    const afterElement = getDragAfterElement(playerList, touch.clientY);
                    if (afterElement) {
                        playerList.insertBefore(draggedItem, afterElement);
                    } else {
                        playerList.appendChild(draggedItem);
                    }
                }
            }

            function handleTouchEnd() {
                if (draggedItem) {
                    draggedItem.style.opacity = '1';
                    draggedItem = null;
                    touchY = null;
                }
            }

            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('li:not(.dragging)')];
                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) {
                        return { offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }

            // Done button handler
            doneButton.addEventListener('click', () => {
                const playerInputs = document.querySelectorAll('#player-list input');
                let allFilled = true;
                playerInputs.forEach(input => {
                    if (!input.value.trim()) {
                        allFilled = false;
                    }
                });

                if (allFilled) {
                    acknowledgementContainer.style.display = 'block';
                    startGameButton.style.display = 'block';
                    doneButton.style.display = 'none';
                } else {
                    alert('Please fill in all player names before proceeding.');
                }
            });

            // Enable/disable start game button based on acknowledgement
            acknowledgementCheckbox.addEventListener('change', function() {
                startGameButton.disabled = !this.checked;
            });
        });
    </script>
</body>
</html>
