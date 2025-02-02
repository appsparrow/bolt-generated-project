<?php
session_start();

if (!isset($_GET['game'])) {
    die("Game name is required");
}

$game_name = $_GET['game'];
$game_file = "data/games/{$game_name}.json";

if (!file_exists($game_file)) {
    die("Game not found!");
}

// Check if already authenticated
if (!isset($_SESSION['authenticated_games'][$game_name])) {
    // Validate passcode if submitted
    if (isset($_POST['passcode'])) {
        $game_data = json_decode(file_get_contents($game_file), true);
        if ($_POST['passcode'] !== $game_data['passcode']) {
            $error = "Incorrect passcode. Please try again.";
        } else {
            // Store authentication in session
            if (!isset($_SESSION['authenticated_games'])) {
                $_SESSION['authenticated_games'] = [];
            }
            $_SESSION['authenticated_games'][$game_name] = true;
            // Refresh to hide modal
            header("Location: rounds.php?game=" . urlencode($game_name));
            exit;
        }
    }

    // Show passcode modal
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enter Passcode</title>
        <link rel="stylesheet" href="styles/style.css">
        <style>
            .passcode-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }
            .passcode-content {
                background: #2d2d2d;
                padding: 2rem;
                border-radius: 8px;
                width: 100%;
                max-width: 400px;
                text-align: center;
            }
            .passcode-content h2 {
                margin-bottom: 1.5rem;
                color: #fff;
            }
            .passcode-content input {
                width: 100%;
                padding: 0.8rem;
                margin-bottom: 1rem;
                border: 1px solid #444;
                border-radius: 4px;
                background: #1a1a1a;
                color: #fff;
                font-size: 1.2rem;
                text-align: center;
            }
            .passcode-content button {
                width: 100%;
                padding: 0.8rem;
                background: #9c27b0;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
                transition: background 0.3s;
            }
            .passcode-content button:hover {
                background: #7b1fa2;
            }
            .error-message {
                color: #ff4444;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class="passcode-modal">
            <div class="passcode-content">
                <h2>Enter Passcode for ' . htmlspecialchars($game_name) . '</h2>
                ' . (isset($error) ? '<div class="error-message">' . $error . '</div>' : '') . '
                <form method="POST">
                    <input type="password" name="passcode" 
                           pattern="\d{4}" maxlength="4" required
                           placeholder="Enter 4-digit passcode"
                           autofocus>
                    <button type="submit">Submit</button>
                </form>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

// Rest of the rounds.php code
$game_data = json_decode(file_get_contents($game_file), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid game data");
}

// Initialize cumulative scores if not set
if (!isset($game_data['cumulative_scores'])) {
    $game_data['cumulative_scores'] = [];
}

// Ensure all players have a cumulative score
foreach ($game_data['players'] as $player) {
    if (!isset($game_data['cumulative_scores'][$player])) {
        $game_data['cumulative_scores'][$player] = 0;
    }
}

// Initialize wins array if not set
if (!isset($game_data['wins'])) {
    $game_data['wins'] = [];
}

// Save the initialized data
file_put_contents($game_file, json_encode($game_data));

// Handle POST request for new round
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $round = [];
    $winner_count = 0;
    $active_players = 0;

    // Validate that we have scores for all players
    if (!isset($_POST['score']) || !is_array($_POST['score'])) {
        $error_message = "Invalid score data submitted";
    } else {
        foreach ($game_data['players'] as $index => $player) {
            // Skip players who are already out
            if ($game_data['cumulative_scores'][$player] >= $game_data['game_score']) {
                continue;
            }
            
            // Get the score value
            $score = $_POST['score'][$index];
            
            // Skip if score is NA (rejoined player)
            if ($score === 'NA') {
                continue;
            }
            
            // Validate the score
            if ($score === null || $score === '') {
                $error_message = "Missing score for player " . htmlspecialchars($player);
                break;
            }
            
            $score = intval($score);
            
            if ($score === 0) {
                $winner_count++;
            }
            $round[$player] = $score;
            $active_players++;
        }

        // Process the round if there are no errors
        if (!isset($error_message) && $active_players > 0) {
            if ($winner_count !== 1) {
                $error_message = "There must be exactly one winner (score of 0) among active players in every round!";
            } else {
                // Save the round data
                $game_data['rounds'][] = $round;

                // Update cumulative scores
                foreach ($round as $player => $score) {
                    $game_data['cumulative_scores'][$player] += $score;
                }

                // Update wins
                $winner = array_search(0, $round);
                if ($winner !== false) {
                    if (!isset($game_data['wins'][$winner])) {
                        $game_data['wins'][$winner] = 0;
                    }
                    $game_data['wins'][$winner]++;
                }

                // Clear the rejoin score for the next round
                if (isset($game_data['current_round_rejoin_score'])) {
                    unset($game_data['current_round_rejoin_score']);
                }

                // Save game data and redirect
                file_put_contents($game_file, json_encode($game_data));
                header('Location: rounds.php?game=' . urlencode($game_name));
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rounds - <?= htmlspecialchars($game_name) ?></title>
    <link rel="stylesheet" href="styles/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
     <style>
        .player-score.rejoined input {
            color: #666;
            cursor: not-allowed;
            background-color: transparent;
        }
        .rounds-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            max-width: 100vw;
            overflow-x: auto;
        }
        .rounds-table th, .rounds-table td {
            border: 1px solid #1a1a1a;
            padding: 8px;
            text-align: center;
        }
        .rounds-table th {
            background-color: #333;
            color: white;
        }
        .player-score.rejoined {
            border: none;
        }
        .chart-table-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .player-score-table {
            width: auto;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .player-score-table th, .player-score-table td {
            border: 1px solid #1a1a1a;
            padding: 8px;
            text-align: center;
        }
        .player-score-table th {
            background-color: #333;
            color: white;
        }
        .player-score-table td {
            color: #f3f3f3;
        }
        .game-info {
            text-align: center;
            margin-bottom: 1rem;
        }
        </style>
</head>
<body>
    <?php include 'components/header.html'; ?>

    <div class="container">
        <header class="header" style="background-color: transparent;">
            <div class="game-info">
                <h2 style="margin-bottom: 0.5rem; font-size: 2rem;"><?= htmlspecialchars($game_name) ?></h2>
                <h4>Game Score: <?= $game_data['game_score'] ?>, Rounds: <?= count($game_data['rounds']) ?> </h4>
            </div>
        </header>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?= $error_message ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($game_data['players'] as $index => $player): ?>
                <div class="player-score <?= ($game_data['cumulative_scores'][$player] >= $game_data['game_score']) ? 'rejoined' : '' ?>">
                    <label for="score<?= $index ?>">
                        <?= htmlspecialchars($player) ?> 
                        (Total: <?= $game_data['cumulative_scores'][$player] ?>, 
                        Wins: <?= $game_data['wins'][$player] ?? 0 ?>)
                    </label>
                    <?php if ($game_data['cumulative_scores'][$player] >= $game_data['game_score']): ?>
                        <input type="text" id="score<?= $index ?>" name="score[]" value="NA" readonly>
                        <button type="button" class="rejoin-btn" onclick="showRejoinDialog(<?= $index ?>)">Rejoin</button>
                    <?php else: ?>
                        <input type="number" id="score<?= $index ?>" name="score[]" required>
                        <button type="button" class="score-button" onclick="cycleScore(<?= $index ?>)">Drop (<?= $game_data['drop_score'] ?>)</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="next-round">Next Round (<?= count($game_data['rounds']) + 1 ?>)</button>
        </form>

        <div class="chart-table-container">
            <div style="width: 90%; margin: 20px auto;">
                <canvas id="progress-chart"></canvas>
            </div>
        </div>
        
        <!-- Rounds Table -->
        <div style="overflow-x: auto;">
            <table class="rounds-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Score</th>
                        <?php for ($i = 1; $i <= count($game_data['rounds']); $i++): ?>
                            <th>R<?= $i ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($game_data['players'] as $player): ?>
                        <tr>
                            <td><?= htmlspecialchars($player) ?></td>
                            <td><?= $game_data['cumulative_scores'][$player] ?></td>
                            <?php foreach ($game_data['rounds'] as $round): ?>
                                <td><?= isset($round[$player]) ? $round[$player] : '-' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Rejoin Dialog -->
    <div id="dialogOverlay" class="dialog-overlay"></div>
    <div id="rejoinDialog" class="rejoin-dialog">
        <h3>Rejoin Game</h3>
        <p>Current highest score among active players: <span id="currentHighest" class="score-highlight">0</span></p>
        <p>Your new score will be: <span id="newScore" class="score-highlight">1</span></p>
        <p>Do you want to rejoin the game?</p>
        <button class="rejoin-understand" onclick="confirmRejoin()">I'll Rejoin</button>
        <button class="rejoin-quit" onclick="cancelRejoin()">I'll Just Watch</button>
    </div>
    <?php include 'components/footer.html'; ?>
    <script>
        const scores = [<?= $game_data['drop_score'] ?>, <?= $game_data['middle_drop'] ?>, <?= $game_data['full_count'] ?>, 0];
        const scoreLabels = ['Drop', 'Middle Drop', 'Full Count', 'Winner'];
        let rejoiningPlayer = '';
        let rejoiningIndex = '';

        function cycleScore(index) {
            const scoreInput = document.getElementById(`score${index}`);
            if (!scoreInput || scoreInput.readOnly) return;
            
            const currentScore = parseInt(scoreInput.value) || 0;
            const currentIndex = scores.indexOf(currentScore);
            const nextIndex = (currentIndex + 1) % scores.length;
            
            scoreInput.value = scores[nextIndex];
            
            const button = document.querySelector(`button[onclick="cycleScore(${index})"]`);
            if (button) {
                button.textContent = `${scoreLabels[nextIndex]} (${scores[nextIndex]})`;
            }
        }

        function showRejoinDialog(index) {
            const playerScoreDiv = document.getElementById(`score${index}`).closest('.player-score');
            const playerName = playerScoreDiv.querySelector('label').textContent.split(' (')[0].trim();
            
            rejoiningPlayer = playerName;
            rejoiningIndex = index;

            fetch('update_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    checkScore: true,
                    game: '<?= $game_name ?>',
                    player: playerName,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('currentHighest').textContent = data.currentHighest;
                    document.getElementById('newScore').textContent = data.rejoinScore;
                    document.getElementById('dialogOverlay').style.display = 'block';
                    document.getElementById('rejoinDialog').style.display = 'block';
                } else {
                    alert('Failed to get current scores: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while checking scores.');
            });
        }

        function confirmRejoin() {
            if (!rejoiningPlayer) return;

            fetch('update_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    game: '<?= $game_name ?>',
                    player: rejoiningPlayer,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to rejoin: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while trying to rejoin.');
            });
        }

        function cancelRejoin() {
            document.getElementById('dialogOverlay').style.display = 'none';
            document.getElementById('rejoinDialog').style.display = 'none';
            rejoiningPlayer = '';
            rejoiningIndex = '';
        }

        // Initialize progress chart
        const ctx = document.getElementById('progress-chart').getContext('2d');
        const gameScore = <?= $game_data['game_score'] ?>;
        const dropScore = <?= $game_data['drop_score'] ?>;
        const threshold = gameScore - dropScore;
        const chartHeight = <?= count($game_data['players']) ?> * 50;
        ctx.canvas.parentNode.style.height = `${chartHeight}px`;
        const progressChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($player, $score) {
                        return $player . ' - ' . $score;
                    }, $game_data['players'], $game_data['cumulative_scores'])) ?>,
                datasets: [{
                    label: 'Total Score',
                    data: <?= json_encode(array_values($game_data['cumulative_scores'])) ?>,
                     backgroundColor: (context) => {
                        const index = context.dataIndex;
                        const score = context.dataset.data[index];
                        if (score >= gameScore) {
                            return 'maroon'; // Maroon for out players
                        } else if (score >= threshold) {
                            return 'orange'; // Orange for players near out
                        } else {
                            return 'rgba(156, 39, 176, 0.6)'; // Default color
                        }
                    },
                    borderColor: 'rgba(156, 39, 176, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        max: gameScore,
                        title: {
                            display: true,
                            text: 'Cumulative Score'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Players'
                        },
                        ticks: {
                            color: '#f3f3f3',
                            min: 30
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                     datalabels: {
                        color: '#fff',
                        anchor: 'end',
                        align: 'right',
                        offset: 4,
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value) {
                            return value.toLocaleString();
                        },
                        display: false,
                        clamp: true,
                        clip: false,
                        padding: {
                            right: 6,
                            left: 6
                        },
                        textStrokeColor: 'black',
                        textStrokeWidth: 1
                    },
                     bar: {
                        barThickness: 30
                    }
                }
            }
        });
    </script>
</body>
</html>
