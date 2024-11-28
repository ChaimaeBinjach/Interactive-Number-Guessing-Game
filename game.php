<?php
/**
 * 
 * Number Guessing Game with News and Feedback Functionality
 * After the user logs in, they can play a number guessing game, view game statistics, and post news articles and feedback(Like any comment or feedback related to the game)
 * Overview:
 * This application combines a number guessing game with social and administrative features. Users can:
 * - Play a guessing game where they attempt to guess a randomly generated 5-digit number.
 * - View aggregated game statistics such as total users, games played, and average moves per game.
 * - Log in to access personalized features and game history.
 * - Post, edit, or delete news articles and feedback visible to all users, both before and after login.
 * - Admins have additional permissions to manage all users' news posts.
 * -The statistics will be displayed for the logged-in user once they complete the game, showing their total performance and results at the end.
 * 
 * Rules and Functionality:
 * - Users must log in to track game history and access advanced features.
 * - The target number is unique and randomly generated for each session.
 * - Feedback for guesses includes "correct" (right digit and position), "misplaced" (right digit, wrong position), and "wrong" (digit not in the number).
 * - News articles and feedback posts must adhere to platform guidelines and can be moderated by admins.
 * 
 * Key Features:
 * 1. **User Management:**
 *    - Users are stored in a database with unique usernames.
 *    - Sessions are managed for login/logout and user identification.
 * 
 * 2. **Number Guessing Game:**
 *    - Generates a 5-digit target number.
 *    - Provides feedback on each guess (correct, misplaced, wrong).
 *    - Tracks game statistics including total moves and outcomes.
 *    - Saves game data for logged-in users.
 *    - Supports resetting and starting new games.
 * 
 * 3. **Game Statistics:**
 *    - Aggregates data such as total users, games played, average moves, and total correct guesses.
 *    - Displays these statistics to the user.
 * 
 * 4. **News and Feedback System:**
 *    - Users can post feedback or news articles (e.g., sharing achievements like winning a game).
 *    - Posts are visible on the main page before and after login.
 *    - Users can edit or delete their own posts.
 *    - Admins can manage (edit/delete) all posts.
 * 
 * 5. **Database Design:**
 *    - `users`: Stores user information.
 *    - `guesses`: Stores game guesses and feedback.
 *    - `game_statistics`: Tracks game outcomes and performance.
 *    - `news`: Stores user-generated news and feedback.
 * 
 * 6. **Security Features:**
 *    - SQL injection protection via prepared statements.
 *    - Session management with ID regeneration for security.
 *    - Role-based access control for news management (user vs. admin).
 * 
 * 7. **Error Handling:**
 *    - Logs errors to avoid exposing sensitive details to users.
 *    - Provides user-friendly messages for login and input validation.
 * 
 * This application is an engaging platform combining gaming with a social component, encouraging user interaction and achievements sharing.
 */


 ?>

<?php
session_start();

// Database credentials
define('DB_HOST', 'localhost'); // Database server
define('DB_USER', 'root'); // Database username
define('DB_PASS', ''); // Database password
define('DB_NAME', 'number_guessing_game'); // Database name

// Create a new connection to the database server (without specifying the database yet)
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error); // Exit if the connection fails
}

// Create the database if it doesn't exist
$mysqli->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME); // Create the database if it doesn't exist
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
    )",
     "CREATE TABLE IF NOT EXISTS `news` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)"
];

foreach ($createTables as $sql) {
    if (!$mysqli->query($sql)) {
        die("Table creation failed: " . $mysqli->error); // Exit if the table creation fails
    }
}

// Ensure the user is logged in and the user_id is set
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id']; // Get the user ID from the session
    } else {
        echo "User ID is missing in session."; // Display an error message if the user ID is not set
        exit();
    }
} else {
    echo "User not logged in."; // Display an error message if the user is not logged in
    exit();
}
///
// Fetch user role if logged in
$userRole = null; // Initialize user role
$userId = null; // Initialize user ID
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id']; // Get the user ID from the session
    $result = $mysqli->query("SELECT role FROM users WHERE id = $userId"); // Query to get the user role
    if ($result) {
        $userRole = $result->fetch_assoc()['role']; // Get the user role
    }
}

// Handle News CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add a new news item
    if (isset($_POST['add_news']) && isset($_POST['title']) && isset($_POST['content'])) {
        $title = $mysqli->real_escape_string($_POST['title']);  // Escape special characters in the input
        $content = $mysqli->real_escape_string($_POST['content']);  // Escape special characters in the input
        $stmt = $mysqli->prepare("INSERT INTO news (title, content, user_id) VALUES (?, ?, ?)"); // Prepare the SQL statement
        $stmt->bind_param("ssi", $title, $content, $userId); // Bind the parameters
        if (!$stmt->execute()) {
            echo "Error adding news: " . $stmt->error; // Display an error message if the news item cannot be added
        }
        $stmt->close(); // Close the prepared statement
    }

    // Edit a news item
    if (isset($_POST['edit_news']) && isset($_POST['news_id']) && isset($_POST['title']) && isset($_POST['content'])) {
        $newsId = intval($_POST['news_id']); // Convert the news ID to an integer
        $title = $mysqli->real_escape_string($_POST['title']); // Escape special characters in the input
        $content = $mysqli->real_escape_string($_POST['content']); // Escape special characters in the input

        // Check permission
        $query = $userRole === 'admin'
            ? "UPDATE news SET title = ?, content = ? WHERE id = ?"
            : "UPDATE news SET title = ?, content = ? WHERE id = ? AND user_id = ?"; // Update the news item based on user role
        $stmt = $mysqli->prepare($query); // Prepare the SQL statement
        $userRole === 'admin' ? $stmt->bind_param("ssi", $title, $content, $newsId) : $stmt->bind_param("ssii", $title, $content, $newsId, $userId); // Bind the parameters

        if (!$stmt->execute()) {
            echo "Error editing news: " . $stmt->error; // Display an error message if the news item cannot be edited
        }
        $stmt->close();
    }

    // Delete a news item
    if (isset($_POST['delete_news']) && isset($_POST['news_id'])) {
        $newsId = intval($_POST['news_id']); // Convert the news ID to an integer

        // Check permission
        $query = $userRole === 'admin'
            ? "DELETE FROM news WHERE id = ?"
            : "DELETE FROM news WHERE id = ? AND user_id = ?";
        $stmt = $mysqli->prepare($query); // Prepare the SQL statement
        $userRole === 'admin' ? $stmt->bind_param("i", $newsId) : $stmt->bind_param("ii", $newsId, $userId); // Bind the parameters

        if (!$stmt->execute()) {
            echo "Error deleting news: " . $stmt->error; // Display an error message if the news item cannot be deleted
        }
        $stmt->close();
    }
}

// Fetch and Display News
$news = [];
$result = $mysqli->query("SELECT news.id, news.title, news.content, news.created_at, news.user_id, users.username
    FROM news
    JOIN users ON news.user_id = users.id
    ORDER BY news.created_at DESC"); // Query to get news items
if ($result) {
    $news = $result->fetch_all(MYSQLI_ASSOC); // Fetch all news items
}


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
    $_SESSION['target_number'] = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT); // Generate a new target number
    $_SESSION['attempts'] = 0; // Initialize attempts
    $_SESSION['guesses'] = []; // Initialize guesses
    $_SESSION['correct_guess'] = false; // Reset correct guess flag
    $_SESSION['game_status'] = 'in_progress'; // Set the game status to in progress
    session_regenerate_id(true); // Regenerate the session ID for security
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
            $feedback[$i] = "misplaced"; // Check if the digit is in the target number but in the wrong position
        } else {
            $feedback[$i] = "wrong"; // If the digit is not in the target number
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
    $stmt = $mysqli->prepare("INSERT INTO guesses (guess, feedback) VALUES (?, ?)"); // Prepare the SQL statement
    $stmt->bind_param("ss", $guess, $feedback_json); // Bind the parameters
    if (!$stmt->execute()) {
        error_log("Database insert error: " . $stmt->error); // Log any errors
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

// Function to get aggregated statistics
function getStatistics() {
    global $mysqli;

    $total_users = $total_games = $average_moves = $total_correct = 0; // Initialize variables
    
    // Check total users
    $result = $mysqli->query("SELECT COUNT(*) AS total_users FROM users"); // Query to get the total number of users
    if ($result) {
        $total_users = $result->fetch_assoc()['total_users']; // Get the total number of users
    } else {
        error_log("Error in total users query: " . $mysqli->error);     // Log an error if there is an issue with the query
        echo "Error in fetching total users.";
    }

    // Check total games
    $result = $mysqli->query("SELECT COUNT(*) AS total_games FROM guesses"); // Query to get the total number of games
    if ($result) {
        $total_games = $result->fetch_assoc()['total_games'];   // Get the total number of games
    } else {
        error_log("Error in total games query: " . $mysqli->error); // Log an error if there is an issue with the query
    }

    // Average moves per game
    $result = $mysqli->query("SELECT AVG(total_moves) AS average_moves FROM game_statistics"); // Query to get the average moves per game
    if ($result) {
        $average_moves = $result->fetch_assoc()['average_moves']; // Get the average moves per game
    } else {
        error_log("Error in average moves query: " . $mysqli->error); // Log an error if there is an issue with the query
    }

    // Total correct guesses
    $result = $mysqli->query("SELECT COUNT(*) AS total_correct FROM guesses WHERE feedback LIKE '%correct%'"); // Query to get the total number of correct guesses
    if ($result) {
        $total_correct = $result->fetch_assoc()['total_correct']; // Get the total number of correct guesses
    } else {
        error_log("Error in total correct guesses query: " . $mysqli->error); // Log an error if there is an issue with the query
    }
// Return the aggregated statistics
    return [
        'total_users' => $total_users,
        'total_games' => $total_games,
        'average_moves' => round($average_moves, 1),
        'total_correct' => $total_correct,
    ]; // Return the aggregated statistics
}
/*
$stats = getStatistics();
echo "Total Users: " . $stats['total_users'];
*/




// Get statistics
$stats = getStatistics();

// Function to generate feedback for the guess
function generateFeedback($guess) {
    $feedback = [];
    $target = str_split($_SESSION['target_number']); // Convert the target number to an array of digits
    $userGuess = str_split($guess);

    // Flags to track already checked digits
    $targetChecked = array_fill(0, 5, false); // Initialize an array with 5 elements set to false
    $guessChecked = array_fill(0, 5, false); // Initialize an array with 5 elements set to false

    // First pass: Check for correct positions
    for ($i = 0; $i < 5; $i++) {
        if ($userGuess[$i] == $target[$i]) {
            $feedback[] = "Digit $userGuess[$i] is in the correct position"; // Provide feedback for correct position
            $targetChecked[$i] = true; // Mark this digit as checked
            $guessChecked[$i] = true;  // Mark this digit as checked
        }
    }

    // Second pass: Check for digits in the number but in the wrong position
    for ($i = 0; $i < 5; $i++) {
        if (!$guessChecked[$i]) { // Skip already checked digits
            for ($j = 0; $j < 5; $j++) {
                if (!$targetChecked[$j] && $userGuess[$i] == $target[$j]) {
                    $feedback[] = "Digit $userGuess[$i] is in the number but in the wrong position"; // Provide feedback for misplaced digit
                    $targetChecked[$j] = true; // Mark this target digit as checked
                    break;
                }
            }
        }
    }

    // Final pass: Check for digits that are not in the number
    for ($i = 0; $i < 5; $i++) {
        if (!$guessChecked[$i]) {
            $feedback[] = "Digit $userGuess[$i] is not in the number"; // Provide feedback for digit not in the number
        }
    }

    // Return the feedback as a string
    return implode(", ", $feedback); // Combine the feedback array into a string
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
    $stmt = $mysqli->prepare("INSERT INTO guesses (guess, feedback) VALUES (?, ?)"); // Prepare the SQL statement
    $stmt->bind_param("ss", $guess, $feedback_json); // Bind the parameters

    if (!$stmt->execute()) {
        error_log("Database insert error: " . $stmt->error); // Log any errors
    }

    $stmt->close();
}

// Function to save game statistics
function saveGameStatistics() {
    global $mysqli;
    $userId = $_SESSION['user_id']; // Get the user ID from the session
    $totalMoves = $_SESSION['attempts']; // Get the total number of moves
    $outcome = $_SESSION['correct_guess'] ? 'win' : 'reset'; // Determine the game outcome

    // Only save if the game was won or lost, not if reset mid-game
    if ($outcome !== 'reset') {
        $totalCorrectGuesses = $_SESSION['correct_guess'] ? 1 : 0; // Track the total correct guesses

        $stmt = $mysqli->prepare("INSERT INTO game_statistics (user_id, total_moves, outcome, total_correct_guesses) VALUES (?, ?, ?, ?)"); // Prepare the SQL statement
        $stmt->bind_param("iisi", $userId, $totalMoves, $outcome, $totalCorrectGuesses); // Bind the parameters

        if (!$stmt->execute()) {
            error_log("Database insert error (statistics): " . $stmt->error); // Log any errors
        }
        $stmt->close();
    }
}


// Record game statistics
function recordGameStatistics($userId, $totalMoves, $outcome, $correctGuesses) {
    global $mysqli;

    $stmt = $mysqli->prepare("INSERT INTO game_statistics (user_id, total_moves, outcome, total_correct_guesses) VALUES (?, ?, ?, ?)"); // Prepare the SQL statement
    $stmt->bind_param("iisi", $userId, $totalMoves, $outcome, $correctGuesses); // Bind the parameters
    $stmt->execute();
    $stmt->close();
}
// Retrieve statistics for display, if needed
$stats = getStatistics();
function getUserStatistics($userId) {
    global $mysqli;

     // Total users
     $stmt = $mysqli->query("SELECT COUNT(*) AS total_users FROM users"); // Query to get the total number of users
     $total_users = $stmt->fetch_assoc()['total_users'] ?? 0; // Get the total number of users

    // Total games played by the user
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total_games FROM game_statistics WHERE user_id = ?"); // Prepare the SQL statement
    $stmt->bind_param("i", $userId); // Bind the parameters
    $stmt->execute(); // Execute the query
    $result = $stmt->get_result(); // Get the result
    $total_games = $result->fetch_assoc()['total_games'] ?? 0; // Get the total number of games played by the user
    $stmt->close();

    // Total wins by the user
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total_wins FROM game_statistics WHERE user_id = ? AND outcome = 'win'");
    $stmt->bind_param("i", $userId); // Bind the parameters
    $stmt->execute();   // Execute the query
    $result = $stmt->get_result(); // Get the result
    $total_wins = $result->fetch_assoc()['total_wins'] ?? 0; // Get the total number of wins by the user
    $stmt->close(); // Close the prepared statement

    // Average moves per game for the user
    $stmt = $mysqli->prepare("SELECT AVG(total_moves) AS average_moves FROM game_statistics WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute(); // Execute the query
    $result = $stmt->get_result(); // Get the result
    $average_moves = $result->fetch_assoc()['average_moves'] ?? 0;
    $stmt->close();
// Calculate the win ratio
    return [
        'total_users' => $total_users,
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
    display: flex;
    flex-direction: column;  /* Stack all content vertically */
    justify-content: flex-start;  /* Align from the top */
    align-items: center;
    min-height: 150vh; /* Ensure full viewport height */
    background: linear-gradient(135deg, #a8e0ff 0%, #e0e7ff 100%);
    font-family: 'Arial', sans-serif;
    color: #2d3748;
}

        .container {
            background: #ffffff; /* Background color for the main container */
            border-radius: 20px; /* Rounds container corners */
            padding: 50px; /* Sets padding around container content */
            max-width: 800px; /* Maximum width for the container */
            width: 200%; /* Container width at 90% of its parent */
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
            gap: 25px; /* Space between form elements */
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
           /* News Section */
           .news-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            max-width: 800px;
            width: 80%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-top: 50px;
            text-align: center;
        }
        
         /* News Section */
         .news-section h2 {
            font-size: 2rem;
            color: #4a5568;
            margin-bottom: 20px;
        }

        .news-section ul {
            list-style: none;
            padding: 0;
            text-align: left; /* Aligns news content to the left */
        }

        .news-section li {
            margin-bottom: 20px; /* Space between news items */
            background: #edf2f7;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .news-section h3 {
            font-size: 1.5rem;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .news-section p {
            font-size: 1.1rem;
            color: #555;
            margin: 5px 0;
        }

        .news-section small {
            display: block;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #777;
        }

        /* Buttons in News Section */
        .news-section form button {
            margin-top: 10px;
            padding: 10px 20px;
            background: #38a169;
            color: white;
            font-size: 1rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .news-section form button:hover {
            background: #2f855a;
        }

        /* Input fields in News Section */
        .news-section input[type="text"], .news-section textarea {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            font-size: 1rem;
            border: 2px solid #4a5568;
            border-radius: 10px;
            background: #f7fafc;
            color: #2d3748;
            transition: border-color 0.3s ease;
        }

        .news-section input[type="text"]:focus, .news-section textarea:focus {
            border-color: #38a169;
            outline: none;
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

        /* Styling for statistics section*/
        .statistics {
    border: 1px solid #ddd;
    padding: 20px;
    margin-bottom: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    font-family: 'Arial', sans-serif;
}

.statistics h2 {
    margin-top: 0;
    font-size: 1.5rem;
    color: #333;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.statistics p {
    font-size: 1.1rem;
    color: #555;
    margin: 5px 0;
}

.statistics p strong {
    font-weight: bold;
    color: #333;
}

.statistics .total-users {
    font-size: 1.3rem;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 20px;
}

.statistics .game-statistics {
    background-color: #f0f8ff;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.statistics .game-statistics p {
    margin-left: 20px;
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
<!-- Display feedback for each guess -->
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
<!-- Display statistics -->
        <div class="statistics">
    <h2>Total Users</h2>
    <p><strong>Total Users:</strong> <?php echo isset($stats['total_users']) ? $stats['total_users'] : 0; ?></p>

    <div class="game-statistics">
        <h2>Your Game Statistics</h2>
        <p><strong>Total Games Played:</strong> <?php echo isset($stats['total_games']) ? $stats['total_games'] : 0; ?></p>
        <p><strong>Total Wins:</strong> <?php echo isset($stats['total_wins']) ? $stats['total_wins'] : 0; ?></p>
        <p><strong>Average Moves per Game:</strong> <?php echo isset($stats['average_moves']) ? $stats['average_moves'] : 0; ?></p>
        <p><strong>Win Ratio:</strong> <?php echo isset($stats['win_ratio']) ? $stats['win_ratio'] : 0; ?>%</p>
    </div>
</div>

<!-- Log out button -->


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



          <!-- News Section -->
        <div class="news-section">
            <h2>📰 News</h2>
            <?php if ($userId): ?>
                <form method="POST">
                <h3 style="font-size: 1.8rem; color: #4a5568; text-align: center; margin-bottom: 20px; font-weight: bold; text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);">
    📝Share Your Feedback: Add News or Comments About the Game
</h3>


                    <input type="text" name="title" placeholder="News Title" required>
                    <textarea name="content" placeholder="News Content" rows="4" required></textarea>
                    <button type="submit" name="add_news">Add News </button>
                </form>
            <?php endif; ?>

            <ul>
                <?php foreach ($news as $newsItem): ?>
                    <li>
                        <h3><?php echo htmlspecialchars($newsItem['title']); ?></h3>
                        <p><?php echo htmlspecialchars($newsItem['content']); ?></p>
                        <small>By <?php echo htmlspecialchars($newsItem['username']); ?> at <?php echo $newsItem['created_at']; ?></small>
                        <?php if ($userRole === 'admin' || $newsItem['user_id'] == $userId): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="news_id" value="<?php echo $newsItem['id']; ?>">
                                <input type="text" name="title" value="<?php echo htmlspecialchars($newsItem['title']); ?>" required>
                                <textarea name="content" rows="2"><?php echo htmlspecialchars($newsItem['content']); ?></textarea>
                                <button type="submit" name="edit_news">Edit</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="news_id" value="<?php echo $newsItem['id']; ?>">
                                <button type="submit" name="delete_news">Delete</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script>
        <?php if ($_SESSION['correct_guess'] ?? false): ?>
            alert("Congratulations! You guessed the correct number!");
            <?php $_SESSION['correct_guess'] = false; // Reset the correct guess flag ?>
        <?php endif; ?>
    </script>
</body>
</html>

