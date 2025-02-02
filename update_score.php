<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

// Check if this is a score check request (preview only)
if (isset($data['checkScore'])) {
    $game_name = $data['game'];
    $player = $data['player'];
    
    // Load the game data
    $game_file = "data/games/{$game_name}.json";
    if (!file_exists($game_file)) {
        echo json_encode(['success' => false, 'error' => 'Game file not found']);
        exit;
    }

    $game_data = json_decode(file_get_contents($game_file), true);
    
    // Find the highest score among players who are still in game
    $highest_score = 0;
    foreach ($game_data['cumulative_scores'] as $player_name => $score) {
        if ($score < $game_data['game_score']) {
            $highest_score = max($highest_score, $score);
        }
    }

    // Calculate rejoin score
    $rejoin_score = $highest_score + 1;

    // Store the rejoin score for the round if it doesn't exist
    if (!isset($game_data['current_round_rejoin_score'])) {
        $game_data['current_round_rejoin_score'] = $rejoin_score;
        file_put_contents($game_file, json_encode($game_data));
    } else {
        // Use the existing rejoin score for this round
        $rejoin_score = $game_data['current_round_rejoin_score'];
    }

    echo json_encode([
        'success' => true,
        'currentHighest' => $highest_score,
        'rejoinScore' => $rejoin_score
    ]);
    exit;
}

// Handle actual rejoin
if (isset($data['game']) && isset($data['player'])) {
    $game_name = $data['game'];
    $player = $data['player'];

    $game_file = "data/games/{$game_name}.json";
    if (!file_exists($game_file)) {
        echo json_encode(['success' => false, 'error' => 'Game file not found']);
        exit;
    }

    $game_data = json_decode(file_get_contents($game_file), true);
    
    // Use the stored rejoin score for this round
    if (isset($game_data['current_round_rejoin_score'])) {
        $rejoin_score = $game_data['current_round_rejoin_score'];
    } else {
        // Calculate new rejoin score if not set
        $highest_score = 0;
        foreach ($game_data['cumulative_scores'] as $player_name => $score) {
            if ($score < $game_data['game_score']) {
                $highest_score = max($highest_score, $score);
            }
        }
        $rejoin_score = $highest_score + 1;
        $game_data['current_round_rejoin_score'] = $rejoin_score;
    }

    // Update only the specific player's score
    $game_data['cumulative_scores'][$player] = $rejoin_score;

    if (file_put_contents($game_file, json_encode($game_data))) {
        echo json_encode([
            'success' => true,
            'newScore' => $rejoin_score,
            'message' => "Player {$player} rejoined with score {$rejoin_score}"
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save game data']);
    }
}
?>
