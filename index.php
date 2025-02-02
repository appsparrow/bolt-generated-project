<?php
    session_start();
    
    
    
    
    
    

    // Add default passcode to existing games
    $games_dir = 'data/games';
    if (file_exists($games_dir)) {
        $game_files = scandir($games_dir);
        foreach ($game_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $file_path = "$games_dir/$file";
                $game_data = json_decode(file_get_contents($file_path), true);
                if (!isset($game_data['passcode'])) {
                    $game_data['passcode'] = '0990';
                    file_put_contents($file_path, json_encode($game_data));
                }
            }
        }
    }

    // Handle delete request with passcode verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game'])) {
        $game_to_delete = $_POST['delete_game'];
        $source_file = "data/games/{$game_to_delete}.json";
        
        // Verify passcode
        if (!isset($_POST['delete_passcode'])) {
            $_SESSION['delete_error'] = "Passcode is required for deletion.";
            echo 'delete_error';
            exit;
        } else {
            $game_data = json_decode(file_get_contents($source_file), true);
            if ($_POST['delete_passcode'] !== $game_data['passcode']) {
                $_SESSION['delete_error'] = "Incorrect passcode. Game not deleted.";
                echo 'delete_error';
                exit;
            } else {
                // Create deleted folder if it doesn't exist
                if (!file_exists('data/deleted')) {
                    mkdir('data/deleted', 0777, true);
                }
                
                // Move file to deleted folder
                if (file_exists($source_file)) {
                    rename($source_file, "data/deleted/{$game_to_delete}.json");
                    unset($_SESSION['delete_error']);
                    echo 'success';
                    exit;
                }
            }
        }
    }

    $delete_error = $_SESSION['delete_error'] ?? '';
    unset($_SESSION['delete_error']);

    // Load existing games
   // Load existing games with creation time and completion status
        // Load existing games with creation time and completion status
            $games = [];
            if (file_exists('data/games')) {
                $game_files = scandir('data/games');
                foreach ($game_files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                        $file_path = "data/games/$file";
                        $game_data = json_decode(file_get_contents($file_path), true);
                        
                        // Check if game is completed (only one player remaining)
                        $active_players = 0;
                        if (isset($game_data['players'])) {
                            foreach ($game_data['players'] as $player) {
                                // Count players who are not eliminated
                                if (!isset($player['eliminated']) || $player['eliminated'] === false) {
                                    $active_players++;
                                }
                            }
                        }
                        
                        // Debug line - remove after testing
                        error_log("Game: " . basename($file, '.json') . " Active players: " . $active_players);
                        
                        $games[] = [
                            'name' => basename($file, '.json'),
                            'created' => filectime($file_path),
                            'completed' => ($active_players <= 1),
                            'active_players' => $active_players  // Add this for debugging
                        ];
                    }
                }
                
                // Sort games by creation time (newest first)
                usort($games, function($a, $b) {
                    return $b['created'] - $a['created'];
                });
            }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Scorecard App</title>
        <link rel="stylesheet" href="/styles/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">        

<style>
        /* .game-card {
                position: relative;
            } */
            
            .delete-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                background: none;
                border: none;
                color: #ff4444;
                cursor: pointer;
                font-size: 1.2em;
                padding: 5px;
                z-index: 2;
            }

            .delete-btn:hover {
                color: #ff0000;
                transform: scale(1.1);
            }

            .confirm-dialog {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                z-index: 1000;
            }

            /* .dialog-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            } */

            .confirm-dialog button {
                margin: 0 10px;
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }

            .confirm-dialog .confirm-yes {
                background: #ff4444;
                color: white;
            }

            .confirm-dialog .confirm-no {
                background: #666;
                color: white;
            }

            .delete-error {
                color: #ff4444;
                margin: 1rem 0;
                display: none;
            }


          
            .delete-dialog {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #2d2d2d;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                z-index: 1001;
                width: 400px;
                max-width: 90%;
            }
            .delete-dialog h3 {
                margin-top: 0;
                color: #ff4444;
            }
            .delete-dialog input {
                width: 100%;
                padding: 0.8rem;
                margin: 1rem 0;
                border: 1px solid #444;
                border-radius: 4px;
                background: #1a1a1a;
                color: #fff;
                font-size: 1rem;
                text-align: center;
            }
            .delete-dialog button {
                margin: 0.5rem;
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
            }
            .delete-confirm {
                background: #ff4444;
                color: white;
            }
            .delete-cancel {
                background: #666;
                color: white;
            }
    /* Modal overlay */
            .modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                z-index: 1000;
            }

            /* Modal dialog */
            .modal-dialog {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #2d2d2d;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                z-index: 1001;
                width: 400px;
                max-width: 90%;
                color: white;
            }

            .modal-dialog h3 {
                margin-top: 0;
                color: #ff4444;
            }

            .modal-dialog input {
                width: 100%;
                padding: 0.8rem;
                margin: 1rem 0;
                border: 1px solid #444;
                border-radius: 4px;
                background: #1a1a1a;
                color: #fff;
                font-size: 1rem;
                text-align: center;
            }

            .modal-dialog button {
                margin: 0.5rem;
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
            }

            .delete-confirm {
                background: #ff4444;
                color: white;
            }

            .delete-cancel {
                background: #666;
                color: white;
            }

            .delete-error {
                color: #ff4444;
                margin: 1rem 0;
                display: none;
            }

            .delete-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                background: none;
                border: none;
                color: #666;
                cursor: pointer;
                font-size: 1.2em;
                padding: 5px;
                z-index: 2;
                transition: color 0.3s ease;
            }

            .delete-btn:hover {
                color: #ff4444;
            }
            
            
            
            
            
            .search-container {
    width: 100%;
    max-width: 600px;
    margin: 20px auto;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 12px 45px 12px 20px;
    border: 1px solid #ddd;
    border-radius: 24px;
    font-size: 16px;
    background: #2d2d2d;
    color: #fff;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    box-shadow: 0 0 5px rgba(81, 203, 238, 1);
    border-color: #51cbee;
}

.search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    pointer-events: none;
}

.game-card {
    transition: all 0.3s ease;
}

.game-card.hidden {
    display: none;
}

.creation-date {
    font-size: 0.8em;
    color: #888;
    margin: 5px 0;
}

.game-card-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}


.completed-badge {
    display: inline-block;
    background-color: #28a745;
    color: white;
    font-size: 0.7em;
    padding: 2px 8px;
    border-radius: 12px;
    margin-left: 8px;
    vertical-align: middle;
}

.game-card.completed {
    position: relative;
    opacity: 0.8;
}

.game-card.completed::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border: 2px solid #28a745;
    border-radius: inherit;
    pointer-events: none;
}

.game-card.completed .game-link {
    background-color: #28a745;
}


.completed-badge {
    display: inline-block;
    background-color: #28a745;
    color: white;
    font-size: 0.8em;
    padding: 4px 10px;
    border-radius: 12px;
    margin-left: 10px;
    vertical-align: middle;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
}

.game-card.completed {
    position: relative;
    background-color: rgba(40, 167, 69, 0.1);
    border: 2px solid #28a745;
}

.game-card.completed .game-title {
    color: #28a745;
    font-weight: bold;
}


        </style>


        




        <script>
    function showInfo() {
        document.getElementById('infoOverlay').style.display = 'block';
    }

    function hideInfo() {
        document.getElementById('infoOverlay').style.display = 'none';
    }

    let gameToDelete = '';
            
    function showDeleteConfirm(game) {
        gameToDelete = game;
        document.getElementById('deletePasscode').value = '';
        document.getElementById('deleteError').style.display = 'none';
        document.getElementById('modalOverlay').style.display = 'block';
        document.getElementById('deleteDialog').style.display = 'block';
    }
            
    async function confirmDelete() {
        const passcode = document.getElementById('deletePasscode').value;
        const errorElement = document.getElementById('deleteError');
        
        if (!passcode || passcode.length !== 4 || !/^\d{4}$/.test(passcode)) {
            errorElement.textContent = 'Please enter a valid 4-digit passcode';
            errorElement.style.display = 'block';
            return;
        }
        
        const formData = new FormData();
        formData.append('delete_game', gameToDelete);
        formData.append('delete_passcode', passcode);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const text = await response.text();
            
            if (text.includes('delete_error')) {
                errorElement.textContent = 'Incorrect passcode';
                errorElement.style.display = 'block';
            } else {
                window.location.reload();
            }
        } catch (error) {
            errorElement.textContent = 'An error occurred';
            errorElement.style.display = 'block';
        }
    }
            
    function cancelDelete() {
        document.getElementById('modalOverlay').style.display = 'none';
        document.getElementById('deleteDialog').style.display = 'none';
        gameToDelete = '';
    }

    // Modal and Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Hide modal on page load
        document.getElementById('modalOverlay').style.display = 'none';
        document.getElementById('deleteDialog').style.display = 'none';

        // Search functionality
        const searchInput = document.getElementById('gameSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                const gameCards = document.querySelectorAll('.game-card');
                
                gameCards.forEach(card => {
                    const gameTitle = card.querySelector('.game-title').textContent.toLowerCase();
                    const isCompleted = card.classList.contains('completed');
                    
                    const titleMatch = gameTitle.includes(searchTerm);
                    const completedMatch = isCompleted && 'completed'.includes(searchTerm);
                    
                    if (titleMatch || completedMatch || searchTerm === '') {
                        card.style.display = ''; // Show card
                    } else {
                        card.style.display = 'none'; // Hide card
                    }
                });
            });
        }
    });
</script>
        
        
    </head>
    <body>
    <?php include 'components/header.html'; ?>
        
        <span onclick="showInfo()">
            <i class="fas fa-info-circle info-button"></i>
        </span>

        <div id="infoOverlay" class="overlay">
            <button class="close-btn" onclick="hideInfo()">×</button>
            <div class="overlay-content">
                <iframe class="info-frame" src="info.html"></iframe>
            </div>
        </div>
        
        <!-- Modal Overlay -->
        <div id="modalOverlay" class="modal-overlay"></div>
        
        <!-- Delete Confirmation Modal -->
        <div id="deleteDialog" class="modal-dialog">
            <h3>Delete Game</h3>
            <p>Warning: This action cannot be undone.</p>
            <p>Enter passcode to confirm deletion:</p>
            <?php if ($delete_error): ?>
                <div id="deleteError" class="delete-error" style="display: block;"><?= $delete_error ?></div>
            <?php else: ?>
                <div id="deleteError" class="delete-error"></div>
            <?php endif; ?>
            <input type="password" id="deletePasscode" 
                   pattern="\d{4}" maxlength="4" required
                   placeholder="Enter 4-digit passcode">
            <button class="delete-cancel" onclick="cancelDelete()">Cancel</button>
            <button class="delete-confirm" onclick="confirmDelete()">Delete</button>
        </div>

        <div class="container">
            <div style="display: flex; justify-content: flex-end; align-items: center;">
                <a href="setup.php" class="btn">New Game</a>
            </div>
            
            
            <div class="search-container">
    <input type="text" id="gameSearch" placeholder="Search games..." class="search-input">
    <i class="fas fa-search search-icon"></i>
</div>



            <h2>Resume Game</h2>
            <div class="game-grid">
                <?php foreach ($games as $game): ?>
                    <div class="game-card <?= $game['completed'] ? 'completed' : '' ?>">
                        <button class="delete-btn" onclick="showDeleteConfirm('<?= htmlspecialchars($game['name']) ?>')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <div class="game-card-content">
                            <img src="images/cards-icon.svg" alt="Playing Cards Icon" width="24" height="24">
                            <h3 class="game-title">
                                <?= htmlspecialchars($game['name']) ?>
                                <?php if ($game['completed']): ?>
                                    <span class="completed-badge">Completed</span>
                                <?php endif; ?>
                            </h3>
                            <!--<div class="creation-date">-->
                            <!--    Created: <?= date('M j, Y g:i A', $game['created']) ?>-->
                            <!--</div>-->
                            <div class="game-actions">
                                <a href="rounds.php?game=<?= urlencode($game['name']) ?>" class="game-link">
                                    <?= $game['completed'] ? 'View' : 'Resume' ?>
                                </a>
                                <a href="visualize.php?game=<?= urlencode($game['name']) ?>" class="game-link viz">📊 Stats</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php include 'components/footer.html'; ?>
        

    </body>
    </html>
