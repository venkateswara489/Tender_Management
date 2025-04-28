<?php
session_start();

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tender";

// Create connection using mysqli object-oriented approach
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process login only if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Prepare and bind
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if ($password == $user['password']) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect based on user type
            if ($user['user_type'] == 'admin') {
                header("Location: admin-dashboard.php");
            } else {
                header("Location: bidder-dashboard.php");
            }
            exit();
        } else {
            header("Location: login.html?error=invalid");
            exit();
        }
    } else {
        header("Location: login.html?error=invalid");
        exit();
    }
}

$conn->close();
?>
