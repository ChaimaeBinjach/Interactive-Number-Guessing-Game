<?php
/*
 * 
 -------------------------------------------------------------------------------
 * all the users have the same password "pass123".
 * The passwords are hashed using the password_hash() function.
 * The default users are inserted into the database with predefined usernames (admin, user1, user2) and a hashed password (pass123).
This PHP file serves as the core logic for user authentication, registration, and statistics management for a number guessing game.
 It establishes a connection to a MySQL database and ensures that the necessary database and tables are created if they do not exist.
  
 Key Features:
    1. Database Connection: 
   - The script connects to a MySQL database using MySQLi and creates the database (`number_guessing_game`) and a `users` table if they don't exist.
   - It handles potential database errors and ensures the necessary structure is in place before proceeding with user operations.
 
    2. User Management:
    - Login: Handles user login using a secure process with prepared statements to prevent SQL injection. 
    It verifies the user's credentials by comparing the hashed password stored in the database.
    - Registration: Allows new users to register with a username and password. Passwords are hashed using PHP's `password_hash()` function for secure storage.
    The script also checks if the username already exists in the database to prevent duplicate registrations.
    - Session Management: Uses PHP's session functionality to track whether a user is logged in or not. Session variables are set to store user information (username, user ID, role).
 
    3. Default Users:
    - If the users table is empty (e.g., on a fresh setup), default users are inserted into the database with predefined usernames (`admin`, `user1`, `user2`) and a hashed password (`pass123`).
 
    4. Error Handling:
    - The script includes error handling for database connections, user authentication issues, and session handling.
    - Error messages are displayed to the user in case of invalid input (e.g., incorrect password, username not found).
 
    5. User Statistics:
    - Displays user-specific game statistics such as games played, average moves per game, and total wins, by querying the `game_statistics` table.
    - The statistics are displayed when a user is logged in, providing a personalized experience based on the user's activity.

    6. Frontend UI:
    - The script dynamically generates HTML content to show either the login form or a welcome message with options to play the game and log out, depending on the user's session status.
    - A registration form is hidden by default and can be toggled for users who do not have an account yet.
 
    7. Security:
    - Passwords are never stored in plain text. Instead, they are hashed using the `password_hash()` function, and the login validation is done using `password_verify()`.
    - SQL injection is mitigated by using prepared statements for all database queries involving user input.

 The primary purpose of this file is to handle the user interaction and authentication for the game, ensuring secure login and registration, 
 as well as managing user sessions and displaying personalized game statistics.
 */
?>


<?php
session_start(); // Start a new session or resume the existing session

// Database connection parameters
$host = 'localhost'; // Define the hostname for the database server
$user = 'root'; //Define the username for the database server
$password = ''; // Define the password for the database server
$dbname = 'number_guessing_game'; // Define the database name

// Create a connection to the database server (without specifying the database yet)
$link = mysqli_connect($host, $user, $password); // Create a new connection to the MySQL database server

// Check connection
if (!$link) {
    die("Connection failed: " . mysqli_connect_error());// Check if the connection to the database server is successful
}

// Create the database if it doesn't exist
$db_create_query = "CREATE DATABASE IF NOT EXISTS $dbname"; // Create a new database if it doesn't exist
if (mysqli_query($link, $db_create_query)) {
    mysqli_select_db($link, $dbname); // Select the database to work with
} else {
    die("Error creating database: " . mysqli_error($link));  // Check if the database creation is successful
}

// Now that the database exists, connect to it
$link = mysqli_connect($host, $user, $password, $dbname); // Create a new connection to the MySQL database server

// Create the users table if it doesn't exist
$table_create_query = "
CREATE TABLE IF NOT EXISTS `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user'
)";
if (!mysqli_query($link, $table_create_query)) {
    die("Error creating table: " . mysqli_error($link)); // Check if the table creation is successful
}

// Insert default users if the table is empty
$check_empty_query = "SELECT COUNT(*) AS count FROM `users`"; // Check if the users table is empty
$result = mysqli_query($link, $check_empty_query); // Execute the query
$row = mysqli_fetch_assoc($result); // Fetch the result as an associative array

if ($row['count'] == 0) {
    $default_password = password_hash("pass123", PASSWORD_DEFAULT); // Hash the default password
    $insert_users_query = "
    INSERT INTO `users` (username, password, role) VALUES 
    ('admin', '$default_password', 'admin'), 
    ('user1', '$default_password', 'user'), 
    ('user2', '$default_password', 'user')";
    if (!mysqli_query($link, $insert_users_query)) {
        die("Error inserting users: " . mysqli_error($link)); // Check if the user insertion is successful
    }
}



// Function to handle and display messages
function display_message() { 
    if (isset($_SESSION['message'])) { 
        echo "<p class='message'>{$_SESSION['message']}</p>";  // Display the message
        unset($_SESSION['message']);  // Unset the message
    }
    if (isset($_SESSION['error'])) { 
        echo "<p class='error'>{$_SESSION['error']}</p>"; // Display the error
        unset($_SESSION['error']); 
    }
}

// Handle login logic with prepared statements
if (isset($_POST['login'])) { 
    $username = trim($_POST['username']); // Get the username and remove whitespace
    $password = $_POST['password']; // Get the password

    $stmt = mysqli_prepare($link, "SELECT * FROM users WHERE username = ?"); // Prepare the SQL query
    mysqli_stmt_bind_param($stmt, "s", $username); // Bind the parameters
    mysqli_stmt_execute($stmt); 
    $result = mysqli_stmt_get_result($stmt); // Get the result

    if (mysqli_num_rows($result) == 1) { 
        $user = mysqli_fetch_assoc($result); 
        if (password_verify($password, $user['password'])) { 
            // Set session variables
            $_SESSION['logged_in'] = true; // Set the logged_in session variable to true
            $_SESSION['username'] = $username; // Set the username in the session
            $_SESSION['role'] = $user['role']; //   this line sets the role in the session
            $_SESSION['user_id'] = $user['id']; // This line sets the user_id in the session
            $_SESSION['message'] = "Welcome, $username!"; 
        } else {
            $_SESSION['error'] = "Incorrect password!"; //Display an error message if the password is incorrect
        }
    } else {
        $_SESSION['error'] = "Username does not exist!"; // Display an error message if the username does not exist
    }
    
}
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id']; // Get the user ID from the session
    } else {
        echo "User ID is missing in session."; // Display an error message if the user ID is missing
        exit();
    }
}


// Handle registration logic
if (isset($_POST['register'])) {
    $username = trim($_POST['new_username']); // Get the new username and remove whitespace
    $password = $_POST['new_password']; //Define the new password
    $confirm_password = $_POST['confirm_password']; // Define the confirm password

    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!"; // Display an error message if the passwords do not match
    } else {
        // Hash password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hash the password

        // Check if username already exists
        $stmt = mysqli_prepare($link, "SELECT * FROM users WHERE username = ?"); //Query to check if the username already exists
        mysqli_stmt_bind_param($stmt, "s", $username); // Bind the parameters
        mysqli_stmt_execute($stmt); // Execute the query
        $result = mysqli_stmt_get_result($stmt); // Get the result

        if (mysqli_num_rows($result) > 0) {
            $_SESSION['error'] = "Username already exists!"; // Display an error message if the username already exists
        } else {
            // Insert the new user
            $stmt = mysqli_prepare($link, "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')"); // Insert the new user
            mysqli_stmt_bind_param($stmt, "ss", $username, $hashed_password); // Bind the parameters
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Registration successful. You can now log in."; // Display a success message if the registration is successful
            } else {
                $_SESSION['error'] = "Error registering user: " . mysqli_error($link); // Display an error message if there is an error in registering the user
            }
        }
    }
}
function displayUserStatistics($userId) {
    global $mysqli;

    // Retrieve user statistics
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS games_played, AVG(total_moves) AS avg_moves, SUM(total_correct_guesses) AS wins FROM game_statistics WHERE user_id = ?"); // Query to retrieve user statistics
    $stmt->bind_param("i", $userId); // Bind the parameters
    $stmt->execute();
    $result = $stmt->get_result(); // Get the result
    $stats = $result->fetch_assoc();
    $stmt->close(); // Close the statement

    echo "<h3>Game Statistics:</h3>"; // Display the game statistics
    echo "<p>Games Played: " . $stats['games_played'] . "</p>"; // Display the number of games played
    echo "<p>Average Moves: " . round($stats['avg_moves'], 2) . "</p>"; // Display the average moves per game
    echo "<p>Total Wins: " . $stats['wins'] . "</p>"; // Display the total wins
}


// Handle logout
if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: index.php");  // Redirect to the login page after logging out
    exit(); 
}

?>

<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>User Management System</title>
    <script>
        // Toggle registration form visibility
        function toggleRegisterForm() {
            var form = document.getElementById("register-form");
            form.style.display = (form.style.display === "none" || form.style.display === "") ? "block" : "none";
        }
    </script>
    <style>
        /* Styling for the overall body */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #eef2f3;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }
/* Styling for the container*/
        .container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
            transition: transform 0.2s;
        }

        .container:hover {
            transform: scale(1.02);
        }

        h2, h3, h4 {
            color: #333;
            margin: 10px 0;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            transition: border 0.3s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #28a745;
            outline: none;
        }

        input[type="submit"] {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #218838;
        }

        .message {
            color: #28a745;
            margin: 15px 0;
        }

        .error {
            color: #dc3545;
            margin: 15px 0;
        }

        #register-form {
            display: none;
        }
        /* Additional styling for the buttons */
.game-button, .logout-button {
    display: inline-block;
    background-color: #28a745;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    width: 80%;
    text-align: center;
    transition: background-color 0.3s;
}

.game-button:hover, .logout-button:hover {
    background-color: #218838;
}

    </style>
</head>
<body>

<div class="container">
    <?php
        display_message(); // Display login success/error messages
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) { 
            echo "<h2>Welcome, " . $_SESSION['username'] . "!</h2>"; 
            echo "<a href='game.php' class='game-button'>Play Game</a>"; 
            echo "<br><br><a href='index.php?logout=true' class='logout-button'>Logout</a>"; 
        } else {
    ?>
        <!-- Login Form -->
        <h2>Login</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Enter Username" required><br>
            <input type="password" name="password" placeholder="Enter Password" required><br>
            <input type="submit" name="login" value="Login">
        </form>

        <h3>Don't have an account?</h3>
        <h4><a href="javascript:void(0);" onclick="toggleRegisterForm()">Register</a></h4>

        <!-- Registration Form (Initially Hidden) -->
        <form method="POST" id="register-form">
            <input type="text" name="new_username" placeholder="Enter Username" required><br>
            <input type="password" name="new_password" placeholder="Enter Password" required><br>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required><br>
            <input type="submit" name="register" value="Register">
        </form>
    <?php } ?>
</div>


</body>
</html>
