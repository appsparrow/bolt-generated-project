<?php
// Get the game name from the query string and handle errors
if (!isset($_GET['game'])) {
    die("Game name is required in the query string");
}

$game_name = $_GET['game'];
$game_file = "data/games/{$game_name}.json";

if (!file_exists($game_file)) {
    die("Game file not found: {$game_name}");
}

$game_data = json_decode(file_get_contents($game_file), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON data in game file");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Visualization - <?= htmlspecialchars($game_name) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: #fff;
            font-family: 'Roboto', sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .chart-container {
            background: #2d2d2d;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #444;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .chart-wrapper {
            height: 400px;
            margin-bottom: 20px;
        }
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #2d2d2d;
            border-radius: 8px;
            overflow: hidden;
        }
        .stats-table th, .stats-table td {
            padding: 12px;
            text-align: center;
            border: 1px solid #444;
            color: #fff;
        }
        .stats-table th {
            background: #333;
            color: #9c27b0;
            font-weight: 500;
        }
        .stats-table tr:nth-child(even) {
            background: #363636;
        }
        h2 {
            color: #9c27b0;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .game-info {
            text-align: center;
            margin-bottom: 1rem;
        }
        .game-info h2 {
            color: #9c27b0;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }
        .game-info h4 {
            color: #fff;
            font-weight: 400;
        }
        .drop-highlight {
            background-color: rgba(255, 99, 132, 0.2) !important;
        }
    </style>
</head>
<body>
    <?php include 'components/header.html'; ?>

    <div class="container">
        <div class="game-info">
            <h2><?= htmlspecialchars($game_name) ?></h2>
            <h4>Game Score: <?= $game_data['game_score'] ?>, Rounds: <?= count($game_data['rounds']) ?></h4>
        </div>

        <!-- Player Stats Table -->
        <div class="chart-container">
            <h2>Player Statistics</h2>
            <table class="stats-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Total Score</th>
                        <th>Wins</th>
                        <th>Drops</th>
                        <th>Drop Rate</th>
                    </tr>
                </thead>
                <tbody id="statsTableBody"></tbody>
            </table>
        </div>

        <!-- Player Wins Chart -->
        <div class="chart-container">
            <h2>Player Wins Distribution</h2>
            <div class="chart-wrapper">
                <canvas id="winsChart"></canvas>
            </div>
        </div>

        <!-- Cumulative Scores Chart -->
        <div class="chart-container">
            <h2>Cumulative Scores</h2>
            <div class="chart-wrapper">
                <canvas id="scoresChart"></canvas>
            </div>
        </div>

        <!-- Drop Statistics Chart -->
        <div class="chart-container">
            <h2>Drop Statistics</h2>
            <div class="chart-wrapper">
                <canvas id="dropsChart"></canvas>
            </div>
        </div>

        <!-- Rounds History -->
        <div class="chart-container">
            <h2>Rounds History</h2>
            <div style="overflow-x: auto;">
                <table class="stats-table" id="roundsTable">
                    <thead>
                        <tr id="roundsHeader">
                            <th>Player</th>
                        </tr>
                    </thead>
                    <tbody id="roundsBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Register Chart.js plugins
        Chart.register(ChartDataLabels);

        // Get game data from PHP
        const gameData = <?= json_encode($game_data) ?>;

        // Calculate drops for a player
        function calculateDrops(player) {
            return gameData.rounds.filter(round => 
                round[player] >= gameData.drop_score
            ).length;
        }

        // Calculate drop rate
        function calculateDropRate(player) {
            const drops = calculateDrops(player);
            return ((drops / gameData.rounds.length) * 100).toFixed(1) + '%';
        }

        // Populate Stats Table
        const statsBody = document.getElementById('statsTableBody');
        gameData.players.forEach(player => {
            const row = statsBody.insertRow();
            row.insertCell().textContent = player;
            row.insertCell().textContent = gameData.cumulative_scores[player];
            row.insertCell().textContent = gameData.wins[player] || 0;
            row.insertCell().textContent = calculateDrops(player);
            row.insertCell().textContent = calculateDropRate(player);
        });

        // Initialize Charts
        // Wins Chart
        new Chart(document.getElementById('winsChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: gameData.players,
                datasets: [{
                    data: gameData.players.map(player => gameData.wins[player] || 0),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#fff'
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        formatter: (value) => value || ''
                    }
                }
            }
        });

        // Cumulative Scores Chart
        new Chart(document.getElementById('scoresChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: gameData.players,
                datasets: [{
                    label: 'Cumulative Score',
                    data: gameData.players.map(player => gameData.cumulative_scores[player]),
                    backgroundColor: 'rgba(156, 39, 176, 0.6)',
                    borderColor: 'rgba(156, 39, 176, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#fff'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#fff'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        color: '#fff',
                        anchor: 'end',
                        align: 'right',
                        offset: 4
                    }
                }
            }
        });

        // Drops Chart
        new Chart(document.getElementById('dropsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: gameData.players,
                datasets: [{
                    label: 'Number of Drops',
                    data: gameData.players.map(player => calculateDrops(player)),
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        color: '#fff',
                        anchor: 'end',
                        align: 'top'
                    }
                }
            }
        });

        // Populate Rounds Table
        const roundsHeader = document.getElementById('roundsHeader');
        const roundsBody = document.getElementById('roundsBody');

        // Add round numbers to header
        gameData.rounds.forEach((_, index) => {
            const th = document.createElement('th');
            th.textContent = `Round ${index + 1}`;
            roundsHeader.appendChild(th);
        });

        // Add player scores for each round
        gameData.players.forEach(player => {
            const row = roundsBody.insertRow();
            row.insertCell().textContent = player;
            
            gameData.rounds.forEach(round => {
                const cell = row.insertCell();
                cell.textContent = round[player] || '-';
                if (round[player] >= gameData.drop_score) {
                    cell.classList.add('drop-highlight');
                }
            });
        });
    </script>
</body>
</html>
