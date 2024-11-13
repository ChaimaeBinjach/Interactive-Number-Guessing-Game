<?php
session_start(); // Start a new session or resume the existing session

// Database connection parameters
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'number_guessing_game';

// Create a connection to the database server (without specifying the database yet)
$link = mysqli_connect($host, $user, $password);

// Check connection
if (!$link) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create the database if it doesn't exist
$db_create_query = "CREATE DATABASE IF NOT EXISTS $dbname";
if (mysqli_query($link, $db_create_query)) {
    mysqli_select_db($link, $dbname);
} else {
    die("Error creating database: " . mysqli_error($link));
}

// Now that the database exists, connect to it
$link = mysqli_connect($host, $user, $password, $dbname);

// Create the users table if it doesn't exist
$table_create_query = "
CREATE TABLE IF NOT EXISTS `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user'
)";
if (!mysqli_query($link, $table_create_query)) {
    die("Error creating table: " . mysqli_error($link));
}

// Insert default users if the table is empty
$check_empty_query = "SELECT COUNT(*) AS count FROM `users`";
$result = mysqli_query($link, $check_empty_query);
$row = mysqli_fetch_assoc($result);

if ($row['count'] == 0) {
    $default_password = password_hash("pass123", PASSWORD_DEFAULT);
    $insert_users_query = "
    INSERT INTO `users` (username, password, role) VALUES 
    ('admin', '$default_password', 'admin'), 
    ('user1', '$default_password', 'user'), 
    ('user2', '$default_password', 'user')";
    if (!mysqli_query($link, $insert_users_query)) {
        die("Error inserting users: " . mysqli_error($link));
    }
}



// Function to handle and display messages
function display_message() { 
    if (isset($_SESSION['message'])) { 
        echo "<p class='message'>{$_SESSION['message']}</p>"; 
        unset($_SESSION['message']); 
    }
    if (isset($_SESSION['error'])) { 
        echo "<p class='error'>{$_SESSION['error']}</p>"; 
        unset($_SESSION['error']); 
    }
}

// Handle login logic with prepared statements
if (isset($_POST['login'])) { 
    $username = trim($_POST['username']); 
    $password = $_POST['password']; 

    $stmt = mysqli_prepare($link, "SELECT * FROM users WHERE username = ?"); 
    mysqli_stmt_bind_param($stmt, "s", $username); 
    mysqli_stmt_execute($stmt); 
    $result = mysqli_stmt_get_result($stmt); 

    if (mysqli_num_rows($result) == 1) { 
        $user = mysqli_fetch_assoc($result); 
        if (password_verify($password, $user['password'])) { 
            // Set session variables
            $_SESSION['logged_in'] = true; 
            $_SESSION['username'] = $username; 
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $user['id']; // This line sets the user_id in the session
            $_SESSION['message'] = "Welcome, $username!"; 
        } else {
            $_SESSION['error'] = "Incorrect password!"; 
        }
    } else {
        $_SESSION['error'] = "Username does not exist!"; 
    }
    
}
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    } else {
        echo "User ID is missing in session.";
        exit();
    }
}


// Handle registration logic
if (isset($_POST['register'])) {
    $username = trim($_POST['new_username']);
    $password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
    } else {
        // Hash password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if username already exists
        $stmt = mysqli_prepare($link, "SELECT * FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $_SESSION['error'] = "Username already exists!";
        } else {
            // Insert the new user
            $stmt = mysqli_prepare($link, "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            mysqli_stmt_bind_param($stmt, "ss", $username, $hashed_password);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Registration successful. You can now log in.";
            } else {
                $_SESSION['error'] = "Error registering user: " . mysqli_error($link);
            }
        }
    }
}
function displayUserStatistics($userId) {
    global $mysqli;

    // Retrieve user statistics
    $stmt = $mysqli->prepare("SELECT COUNT(*) AS games_played, AVG(total_moves) AS avg_moves, SUM(total_correct_guesses) AS wins FROM game_statistics WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();

    echo "<h3>Game Statistics:</h3>";
    echo "<p>Games Played: " . $stats['games_played'] . "</p>";
    echo "<p>Average Moves: " . round($stats['avg_moves'], 2) . "</p>";
    echo "<p>Total Wins: " . $stats['wins'] . "</p>";
}


// Handle logout
if (isset($_GET['logout'])) { 
    session_destroy(); 
    header("Location: index.php"); 
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
