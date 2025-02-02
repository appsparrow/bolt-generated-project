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
        } else {
            $game_data = json_decode(file_get_contents($source_file), true);
            if ($_POST['delete_passcode'] !== $game_data['passcode']) {
                $_SESSION['delete_error'] = "Incorrect passcode. Game not deleted.";
            } else {
                // Create deleted folder if it doesn't exist
                if (!file_exists('data/deleted')) {
                    mkdir('data/deleted', 0777, true);
                }
                
                // Move file to deleted folder
                if (file_exists($source_file)) {
                    rename($source_file, "data/deleted/{$game_to_delete}.json");
                    unset($_SESSION['delete_error']);
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $delete_error = $_SESSION['delete_error'] ?? '';
    unset($_SESSION['delete_error']);

    // Load existing games
    $games = [];
    if (file_exists('data/games')) {
        $game_files = scandir('data/games');
        foreach ($game_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $games[] = basename($file, '.json');
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Scorecard App</title>
        <link rel="stylesheet" href="styles/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            .game-card {
                position: relative;
            }
            
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

            .dialog-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }

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


            <style>
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
            
            function confirmDelete() {
                const passcode = document.getElementById('deletePasscode').value;
                const errorElement = document.getElementById('deleteError');
                
                if (!passcode || passcode.length !== 4 || !/^\d{4}$/.test(passcode)) {
                    errorElement.textContent = 'Please enter a valid 4-digit passcode';
                    errorElement.style.display = 'block';
                    return;
                }
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const gameInput = document.createElement('input');
                gameInput.type = 'hidden';
                gameInput.name = 'delete_game';
                gameInput.value = gameToDelete;
                
                const passcodeInput = document.createElement('input');
                passcodeInput.type = 'hidden';
                passcodeInput.name = 'delete_passcode';
                passcodeInput.value = passcode;
                
                form.appendChild(gameInput);
                form.appendChild(passcodeInput);
                document.body.appendChild(form);
                form.submit();
            }
            
            function cancelDelete() {
                document.getElementById('modalOverlay').style.display = 'none';
                document.getElementById('deleteDialog').style.display = 'none';
                gameToDelete = '';
            }

            // Ensure modal is hidden on page load
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('modalOverlay').style.display = 'none';
                document.getElementById('deleteDialog').style.display = 'none';
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
            <h2>Resume Game</h2>
            <div class="game-grid">
                <?php foreach ($games as $game): ?>
                    <div class="game-card">
                        <button class="delete-btn" onclick="showDeleteConfirm('<?= htmlspecialchars($game) ?>')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <div class="game-card-content">
                            <img src="images/cards-icon.svg" alt="Playing Cards Icon" width="24" height="24">
                            <h3 class="game-title"><?= htmlspecialchars($game) ?></h3>
                            <div class="game-actions">
                                <a href="rounds.php?game=<?= urlencode($game) ?>" class="game-link">Resume</a>
                                <a href="visualize.php?game=<?= urlencode($game) ?>" class="game-link viz">📊 Stats</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php include 'components/footer.html'; ?>
    </body>
    </html>
