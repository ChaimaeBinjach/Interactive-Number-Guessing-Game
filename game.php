<?php
session_start();

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'number_guessing_game');

// Create a new connection to the database server (without specifying the database yet)
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create the database if it doesn't exist
$mysqli->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$mysqli->select_db(DB_NAME); // Switch to the created database

// Create tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS guesses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guess VARCHAR(5) NOT NULL,
        feedback JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS game_statistics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_moves INT DEFAULT 0,
        outcome VARCHAR(10) NOT NULL,
        total_correct_guesses INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

foreach ($createTables as $sql) {
    if (!$mysqli->query($sql)) {
        die("Table creation failed: " . $mysqli->error);
    }
}

// Ensure the user is logged in and the user_id is set
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    } else {
        echo "User ID is missing in session.";
        exit();
    }
} else {
    echo "User not logged in.";
    exit();
}

// Continue with the rest of your code...

// Initialize a new game or reset if requested
if (!isset($_SESSION['target_number']) || isset($_POST['new_game'])) {
    // If restarting mid-game, do not save statistics
    if (isset($_POST['new_game']) && $_SESSION['attempts'] > 0 && !$_SESSION['correct_guess']) {
        $_SESSION['game_status'] = 'reset'; // Mid-game reset
    }
    initializeGame();
}

// Store user ID in session after it is fetched
$_SESSION['user_id'] = $userId;  // Ensure the user_id is properly set in session



// Initialize $guess and $feedback to avoid undefined variable warning
$guess = null;  // Default value for guess
$feedback = null; // Initialize feedback variable

// Ensure $_SESSION['guesses'] is always an array
if (!isset($_SESSION['guesses'])) {
    $_SESSION['guesses'] = []; // Initialize it as an empty array if not set
}

// Check if the form is submitted with a guess
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guess']) && !empty($_POST['guess'])) {
    $guess = $_POST['guess']; // Get the guess from the POST data
    processGuess($guess); // Call the function to process the guess
}


// Reset game function without saving stats
function initializeGame() {
    $_SESSION['target_number'] = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
    $_SESSION['attempts'] = 0;
    $_SESSION['guesses'] = [];
    $_SESSION['correct_guess'] = false;
    $_SESSION['game_status'] = 'in_progress';
    session_regenerate_id(true);
}






// Handle the user's guess and provide feedback
function processGuess($guess) {
    global $mysqli;
    $target = $_SESSION['target_number'];
    $feedback = [];
    $_SESSION['attempts']++; // Increase attempts

    // Provide feedback for each digit
    for ($i = 0; $i < 5; $i++) {
        if ($guess[$i] === $target[$i]) {
            $feedback[$i] = "correct";
        } elseif (strpos($target, $guess[$i]) !== false) {
            $feedback[$i] = "misplaced";
        } else {
            $feedback[$i] = "wrong";
        }
    }

    // Check if the guess is correct
    if ($guess === $target) {
        $_SESSION['correct_guess'] = true; // Mark the game as completed successfully
        saveGameStatistics(); // Save the statistics
         // Start a new game automatically upon correct guess
         
    }

    // Save guess and feedback to session
    $_SESSION['guesses'][] = ['guess' => $guess, 'feedback' => $feedback];

    // Store the guess in the database
    $feedback_json = json_encode($feedback);
    $stmt = $mysqli->prepare("INSERT INTO guesses (guess, feedback) VALUES (?, ?)");
    $stmt->bind_param("ss", $guess, $feedback_json);
    if (!$stmt->execute()) {
        error_log("Database insert error: " . $stmt->error);
    }
    $stmt->close();
}


// Start a new game or reset if requested
if (!isset($_SESSION['target_number']) || isset($_POST['new_game'])) { // Check if a game needs to be started or reset
    initializeGame(); // Call the function to initialize a new game
}

// Check if the user submitted a guess
$guess = ''; // Initialize an empty string for the user's guess
if (isset($_POST['guess'])) { // Check if the form was submitted with a guess
    $guess = implode('', array_map('trim', $_POST['digit'])); // Collect the digits, trim whitespace, and combine into a single string

    // Ensure the guess is exactly 5 digits
    if (preg_match('/^\d{5}$/', $guess)) { // Validate the guess format to ensure it is exactly 5 digits
        processGuess($guess); // Call the processGuess function if the guess is valid
    } else {
        echo "<script>alert('Please enter exactly 5 digits.');</script>"; // Show an alert if the guess is invalid
    }
}
// Check if the "Start New Game" button was clicked
if (isset($_POST['new_game'])) {
    // Save statistics if it’s a mid-game reset
    if ($_SESSION['attempts'] > 0 && $_SESSION['game_status'] === 'in_progress') {
        saveGameStatistics(); // Save the stats if necessary
    }
    initializeGame(); // Reset the game session variables
}


// Handle logout
if (isset($_POST['logout'])) {
    session_destroy(); // Destroy the session
    header("Location: index.php"); // Redirect to the index page
    exit();
}

function getStatistics() {
    global $mysqli;

    // Get the total number of users
    $result = $mysqli->query("SELECT COUNT(*) AS total_users FROM users");
    $total_users = $result->fetch_assoc()['total_users'];

    // Get the total number of games played
    $result = $mysqli->query("SELECT COUNT(*) AS total_games FROM guesses");
    $total_games = $result->fetch_assoc()['total_games'];

    // Get the average moves per game
    $result = $mysqli->query("SELECT AVG(total_moves) AS average_moves FROM game_statistics");
    $average_moves = $result->fetch_assoc()['average_moves'];

    // Get the total correct guesses
    $result = $mysqli->query("SELECT COUNT(*) AS total_correct FROM guesses WHERE feedback LIKE '%correct%'");
    $total_correct = $result->fetch_assoc()['total_correct'];

    return [
        'total_users' => $total_users,
        'total_games' => $total_games,
        'average_moves' => round($average_moves, 1),
        'total_correct' => $total_correct,
    ];
}



// Get statistics
$stats = getStatistics();

// Function to generate feedback for the guess
function generateFeedback($guess) {
    $feedback = [];
    $target = str_split($_SESSION['target_number']);
    $userGuess = str_split($guess);

    // Flags to track already checked digits
    $targetChecked = array_fill(0, 5, false);
    $guessChecked = array_fill(0, 5, false);

    // First pass: Check for correct positions
    for ($i = 0; $i < 5; $i++) {
        if ($userGuess[$i] == $target[$i]) {
            $feedback[] = "Digit $userGuess[$i] is in the correct position";
            $targetChecked[$i] = true; // Mark this digit as checked
            $guessChecked[$i] = true;  // Mark this digit as checked
        }
    }

    // Second pass: Check for digits in the number but in the wrong position
    for ($i = 0; $i < 5; $i++) {
        if (!$guessChecked[$i]) { // Skip already checked digits
            for ($j = 0; $j < 5; $j++) {
                if (!$targetChecked[$j] && $userGuess[$i] == $target[$j]) {
                    $feedback[] = "Digit $userGuess[$i] is in the number but in the wrong position";
                    $targetChecked[$j] = true; // Mark this target digit as checked
                    break;
                }
            }
        }
    }

    // Final pass: Check for digits that are not in the number
    for ($i = 0; $i < 5; $i++) {
        if (!$guessChecked[$i]) {
            $feedback[] = "Digit $userGuess[$i] is not in the number";
        }
    }

    // Return the feedback as a string
    return implode(", ", $feedback);
}
// Handle logout
if (isset($_POST['logout'])) {
    session_destroy(); // Destroy the session
    header("Location: index.php"); // Redirect to the index page
    exit();
}


// Function to save guesses to the database
function saveGuessToDatabase($guess, $feedback) {
    global $mysqli;

    $feedback_json = json_encode($feedback);
    $stmt = $mysqli->prepare("INSERT INTO guesses (guess, feedback) VALUES (?, ?)");
    $stmt->bind_param("ss", $guess, $feedback_json);

    if (!$stmt->execute()) {
        error_log("Database insert error: " . $stmt->error);
    }

    $stmt->close();
}

// Function to save game statistics
function saveGameStatistics() {
    global $mysqli;
    $userId = $_SESSION['user_id'];
    $totalMoves = $_SESSION['attempts'];
    $outcome = $_SESSION['correct_guess'] ? 'win' : 'reset';

    // Only save if the game was won or lost, not if reset mid-game
    if ($outcome !== 'reset') {
        $totalCorrectGuesses = $_SESSION['correct_guess'] ? 1 : 0;

        $stmt = $mysqli->prepare("INSERT INTO game_statistics (user_id, total_moves, outcome, total_correct_guesses) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $userId, $totalMoves, $outcome, $totalCorrectGuesses);

        if (!$stmt->execute()) {
            error_log("Database insert error (statistics): " . $stmt->error);
        }
        $stmt->close();
    }
}


// Record game statistics
function recordGameStatistics($userId, $totalMoves, $outcome, $correctGuesses) {
    global $mysqli;

    $stmt = $mysqli->prepare("INSERT INTO game_statistics (user_id, total_moves, outcome, total_correct_guesses) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $userId, $totalMoves, $outcome, $correctGuesses);
    $stmt->execute();
    $stmt->close();
}
// Retrieve statistics for display, if needed
$stats = getStatistics();
function getUserStatistics($userId) {
    global $mysqli;

    // Total games played by the user
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total_games FROM game_statistics WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_games = $result->fetch_assoc()['total_games'] ?? 0;
    $stmt->close();

    // Total wins by the user
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total_wins FROM game_statistics WHERE user_id = ? AND outcome = 'win'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_wins = $result->fetch_assoc()['total_wins'] ?? 0;
    $stmt->close();

    // Average moves per game for the user
    $stmt = $mysqli->prepare("SELECT AVG(total_moves) AS average_moves FROM game_statistics WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $average_moves = $result->fetch_assoc()['average_moves'] ?? 0;
    $stmt->close();

    return [
        'total_games' => $total_games,
        'total_wins' => $total_wins,
        'average_moves' => round($average_moves, 2),
        'win_ratio' => $total_games > 0 ? round(($total_wins / $total_games) * 100, 2) : 0
    ];
}

// Usage: Assume $userId is retrieved from the session
$stats = getUserStatistics($userId);
/*
echo "Total Games Played: " . $stats['total_games'];
echo "Average Moves per Game: " . $stats['average_moves'];
echo "Total Guesses: " . $stats['total_guesses'];
echo "Total Correct Guesses: " . $stats['total_correct'];
*/

// Close the database connection
$mysqli->close();
?>



<!DOCTYPE html>
<html lang="en"> <!-- Declares the document type as HTML and sets the language to English -->
<head>
    <meta charset="UTF-8"> <!-- Sets the character encoding for the document to UTF-8 for broad character support -->
    <title>Number Guessing Game</title> <!-- Specifies the title of the page, displayed in the browser tab -->
    <style>
        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box; /* Ensures consistent box-sizing for all elements */
        }
        body {
            display: flex; /* Enables flexbox layout for centering */
            justify-content: center; /* Horizontally centers content */
            align-items: center; /* Vertically centers content */
            min-height: 100vh; /* Sets minimum height to full viewport */
            background: linear-gradient(135deg, #a8e0ff 0%, #e0e7ff 100%); /* Applies a gradient background */
            font-family: 'Arial', sans-serif; /* Sets the font */
            color: #2d3748; /* Base color for text */
        }
        .container {
            background: #ffffff; /* Background color for the main container */
            border-radius: 20px; /* Rounds container corners */
            padding: 50px; /* Sets padding around container content */
            max-width: 700px; /* Maximum width for the container */
            width: 150%; /* Container width at 90% of its parent */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); /* Adds shadow effect */
            text-align: center; /* Centers text inside the container */
            transition: transform 0.3s ease; /* Adds a smooth transition on hover */
        }
        .container:hover {
            transform: translateY(-5px); /* Slightly lifts the container on hover */
        }
        h1 {
            font-size: 2.8rem; /* Sets font size for the main heading */
            color: #4a5568; /* Color for the heading */
            margin-bottom: 30px; /* Space below the heading */
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1); /* Adds subtle shadow to heading */
        }
        form {
            display: flex; /* Flex container for form elements */
            flex-direction: column; /* Arranges children in a column */
            align-items: center; /* Centers form elements */
            gap: 20px; /* Space between form elements */
        }
        .input-group {
            display: flex; /* Flex container for input fields */
            justify-content: center; /* Centers input fields */
            gap: 10px; /* Space between inputs */
        }
        .input-group input[type="text"] {
            width: 70px; /* Width of each input */
            height: 70px; /* Height of each input */
            font-size: 32px; /* Font size for input text */
            text-align: center; /* Centers text in input */
            border-radius: 15px; /* Rounds input corners */
            border: 2px solid #4a5568; /* Border color and width */
            background-color: #edf2f7; /* Background color for inputs */
            color: #2d3748; /* Text color */
            transition: all 0.3s ease; /* Smooth transition on focus */
        }
        .input-group input[type="text"]:focus {
            border-color: #38a169; /* Changes border color on focus */
            outline: none; /* Removes default outline */
            background-color: #f7fafc; /* Changes background color on focus */
        }
        .btn {
            padding: 15px 30px; /* Padding around button text */
            background: #38a169; /* Background color for button */
            color: #ffffff; /* Text color */
            font-size: 1.2rem; /* Font size */
            font-weight: bold; /* Bold text */
            border-radius: 15px; /* Rounds button corners */
            border: none; /* Removes default border */
            cursor: pointer; /* Changes cursor to pointer */
            transition: background-color 0.3s ease, transform 0.2s ease; /* Smooth hover effects */
        }
        .btn:hover {
            background: #2f855a; /* Darker background on hover */
            transform: translateY(-3px); /* Lifts button on hover */
        }
        .feedback-section {
            margin-top: 30px; /* Space above feedback section */
            text-align: left; /* Left-aligns feedback */
        }
        .feedback-section h2 {
            font-size: 1.6rem; /* Font size for feedback heading */
            margin-bottom: 15px; /* Space below heading */
            color: #4a5568; /* Color for feedback heading */
        }
        .feedback-section ul {
            list-style: none; /* Removes default bullet points */
            padding: 0; /* Removes default padding */
        }
        .feedback-section li {
            display: flex; /* Flex container for feedback items */
            align-items: center; /* Centers items vertically */
            justify-content: space-between; /* Space between feedback text and icon */
            padding: 15px; /* Padding around feedback */
            margin: 10px 0; /* Space between feedback items */
            background: #e2e8f0; /* Background color for feedback item */
            border-radius: 10px; /* Rounds feedback item corners */
            font-size: 1.1rem; /* Font size for feedback */
            color: #2d3748; /* Text color */
            border-left: 6px solid transparent; /* Placeholder for colored border */
        }
        .correct {
            color: #2ecc71; /* Green color for correct feedback */
            border-color: #2ecc71; /* Green border for correct feedback */
        }
        .misplaced {
            color: #f39c12; /* Orange color for misplaced feedback */
            border-color: #f39c12; /* Orange border for misplaced feedback */
        }
        .wrong {
            color: #e74c3c; /* Red color for wrong feedback */
            border-color: #e74c3c; /* Red border for wrong feedback */
        }
        .result-message {
            margin-top: 30px; /* Space above result message */
            color: #2ecc71; /* Green color for success message */
            font-size: 2rem; /* Font size for result message */
            font-weight: bold; /* Bold text */
            animation: fadeIn 1s ease; /* Adds fade-in animation */
        }

        /* Animations */
        @keyframes fadeIn {
            0% {
                opacity: 0; /* Starts with transparent */
                transform: scale(0.9); /* Starts slightly scaled down */
            }
            100% {
                opacity: 1; /* Fully visible at end */
                transform: scale(1); /* Scales to normal size */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔢 Number Guessing Game</h1>

        <?php if ($guess && $guess === $_SESSION['target_number']): ?>
            <p class="result-message">🎉 Congratulations! You guessed the number in <?= count($_SESSION['guesses']) ?> moves!</p>
            <p>The correct number was: <?= htmlspecialchars($_SESSION['target_number']) ?></p>
            
            <!-- Offer a button to start a new game -->
            <form method="post">
                <button type="submit" class="btn" name="new_game" value="1">Start New Game</button>
            </form>
        <?php else: ?>
            <form method="post">
                <div class="input-group">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <input type="text" name="digit[]" maxlength="1" required>
                    <?php endfor; ?>
                </div>
                <button type="submit" class="btn" name="guess">Submit Guess</button>
                <button type="submit" class="btn" name="new_game" value="Start New Game">Start New Game</button>
            </form>
        <?php endif; ?>

        <div class="feedback-section">
            <h2>Your Guesses</h2>
            <ul>
                <?php if (empty($_SESSION['guesses'])): ?>
                    <li>No guesses yet.</li>
                <?php else: ?>
                    <?php foreach ($_SESSION['guesses'] as $attempt): ?>
                        <li>
                            <span>Guess: <?= htmlspecialchars($attempt['guess']) ?></span>
                            <span>
                                <?php foreach ($attempt['feedback'] as $result): ?>
                                    <span class="feedback-icon <?= $result ?>">
                                        <?= $result === "correct" ? "✅" : ($result === "misplaced" ? "⚠️" : "❌") ?>
                                    </span>
                                <?php endforeach; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="statistics">
    <h2>Game Statistics</h2>
    <p>Total Games Played: <?php echo $stats['total_games']; ?></p>
    <p>Total Wins: <?php echo $stats['total_wins']; ?></p>
    <p>Average Moves per Game: <?php echo $stats['average_moves']; ?></p>
    <p>Win Ratio: <?php echo $stats['win_ratio']; ?>%</p>
</div>


        <form method="post">
            <button type="submit" class="btn" name="logout">Log Out</button>
        </form>
    </div>

    <script>
        <?php if ($_SESSION['correct_guess'] ?? false): ?>
            alert("Congratulations! You guessed the correct number!");
            <?php $_SESSION['correct_guess'] = false; // Reset the correct guess flag ?>
        <?php endif; ?>
    </script>
</body>
</html>

