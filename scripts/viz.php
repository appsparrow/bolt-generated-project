<?php
// viz.php
header('Content-Type: text/html; charset=utf-8');

// Function to read and validate JSON file
function readGameData($filename) {
    if (!file_exists($filename)) {
        die("Game data file not found");
    }
    
    $json = file_get_contents($filename);
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Invalid JSON data");
    }
    
    return $data;
}

// Read game data
$gameData = readGameData('data/games/PongalGalata.json'); // Adjust path as needed

// Extract required values
$gameScore = $gameData['game_score'];
$dropScore = $gameData['drop_score'];
$noDropZone = $gameScore - $dropScore;
$players = $gameData['players'];
$rounds = $gameData['rounds'];
$cumulativeScores = $gameData['cumulative_scores'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Visualization</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .chart-container {
            margin: 20px 0;
            height: <?= count($players) * 50 ?>px;
        }
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($gameData['name']) ?></h1>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background-color: #9c27b0"></div>
                <span>Safe Zone (0-<?= $noDropZone - 1 ?>)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ff9800"></div>
                <span>Drop Zone (<?= $noDropZone ?>-<?= $gameScore - 1 ?>)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ff0000"></div>
                <span>Out (<?= $gameScore ?>+)</span>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="progressChart"></canvas>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('progressChart').getContext('2d');
        const gameScore = <?= $gameScore ?>;
        const noDropZone = <?= $noDropZone ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($player) use ($cumulativeScores) {
                    return $player . ' - ' . $cumulativeScores[$player];
                }, $players)) ?>,
                datasets: [{
                    label: 'Total Score',
                    data: <?= json_encode(array_values($cumulativeScores)) ?>,
                    backgroundColor: (context) => {
                        const score = context.dataset.data[context.dataIndex];
                        if (score >= gameScore) return '#ff0000';
                        if (score >= noDropZone) return '#ff9800';
                        return '#9c27b0';
                    },
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        max: gameScore + 50,
                        grid: {
                            color: '#333333'
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>
