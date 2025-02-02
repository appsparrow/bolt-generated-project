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
        <link rel="stylesheet" href="/styles/style.css">
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

// Load game data
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

    // Check for duplicate scores with previous round
    $isDuplicateRound = false;
    if (!empty($game_data['rounds'])) {
        $lastRound = end($game_data['rounds']);
        $duplicateCount = 0;
        $totalScores = 0;
        
        foreach ($_POST['score'] as $index => $score) {
            $player = $game_data['players'][$index];
            if (isset($lastRound[$player]) && $lastRound[$player] == $score && $score !== 'NA') {
                $duplicateCount++;
            }
            if ($score !== 'NA') {
                $totalScores++;
            }
        }
        
        // If more than 75% of scores are identical to the previous round
        if ($duplicateCount > 0 && ($duplicateCount / $totalScores) >= 0.75) {
            $isDuplicateRound = true;
        }
    }

    // If duplicate round detected and not confirmed, show confirmation dialog
    if ($isDuplicateRound && !isset($_POST['confirmed'])) {
        echo json_encode([
            'status' => 'confirm',
            'message' => 'Most scores are identical to the previous round. Is this correct?',
            'lastRound' => $lastRound
        ]);
        exit;
    }

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
    <link rel="stylesheet" href="/styles/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    
    <!-- Rest of your existing styles here -->
</head>
<body>
    <?php include 'components/header.html'; ?>

    <div class="container">
        <!-- Your existing HTML structure -->
        
        <form method="POST" id="scoreForm">
            <?php foreach ($game_data['players'] as $index => $player): ?>
                <!-- Your existing player score inputs -->
            <?php endforeach; ?>
            <button type="submit" class="next-round">Next Round (<?= count($game_data['rounds']) + 1 ?>)</button>
        </form>

        <!-- Rest of your existing HTML -->
    </div>

    <script>
        // Your existing JavaScript functions

        // Modified form submission handler
        document.getElementById('scoreForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                try {
                    const jsonData = JSON.parse(data);
                    if (jsonData.status === 'confirm') {
                        if (confirm(jsonData.message + '\n\nPrevious round scores:\n' + 
                            Object.entries(jsonData.lastRound)
                                .map(([player, score]) => `${player}: ${score}`)
                                .join('\n') +
                            '\n\nClick OK to proceed or Cancel to review scores.')) {
                            formData.append('confirmed', 'true');
                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (response.redirected) {
                                    window.location.href = response.url;
                                } else {
                                    document.documentElement.innerHTML = response.text();
                                }
                            });
                        }
                    }
                } catch (e) {
                    // If data is not JSON, it's probably HTML
                    document.documentElement.innerHTML = data;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting scores.');
            });
        });

        // Rest of your existing JavaScript code
    </script>
</body>
</html>
