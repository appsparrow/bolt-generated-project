<?php
session_start();

if (isset($_POST['acknowledge'])) {
    $_SESSION['acknowledged'] = true;
    header('Location: setup.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acknowledge Disclaimer</title>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: var(--dark-surface);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            text-align: center;
        }
        .disclaimer-text {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .acknowledge-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .acknowledge-btn:hover {
            background-color: var(--primary-dark);
        }
        .full-disclaimer-link {
            display: block;
            margin-top: 20px;
            color: var(--primary-light);
            text-decoration: none;
        }
        .full-disclaimer-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include 'components/header.html'; ?>
    <div class="container">
        <h2>Disclaimer</h2>
        <p class="disclaimer-text">
            This app is for entertainment purposes only. By using this app, you acknowledge that you are using it for fun and that the developer or company is not liable for any outcomes.
        </p>
        <form method="post">
            <button type="submit" name="acknowledge" class="acknowledge-btn">I Acknowledge</button>
        </form>
        <a href="disclaimer.html" class="full-disclaimer-link">View Full Disclaimer</a>
    </div>
    <?php include 'components/footer.html'; ?>
</body>
</html>
