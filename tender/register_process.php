<?php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tender";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check DB connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collect form data
$name = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirmPassword = $_POST['confirm_password'];
$userType = $_POST['userType'];
$company = $_POST['company'];
$contact = $_POST['contact'];

// Validate inputs
if (!preg_match("/^[a-zA-Z ]+$/", $name)) {
    header("Location: register.html?error=name_invalid");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.html?error=email_invalid");
    exit();
}

if (strlen($password) < 8 || !preg_match("/[%$@*#]/", $password) || !preg_match("/[a-z]/", $password)) {
    header("Location: register.html?error=password_weak");
    exit();
}

if ($password !== $confirmPassword) {
    header("Location: register.html?error=failed");
    exit();
}

if (!in_array($userType, ['bidder', 'admin'])) {
    header("Location: register.html?error=usertype_invalid");
    exit();
}

if (empty($company)) {
    header("Location: register.html?error=company_required");
    exit();
}

if (!preg_match("/^\d{10}$/", $contact)) {
    header("Location: register.html?error=contact_invalid");
    exit();
}

// Check if email already exists
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: register.html?error=email_exists");
    exit();
}

// Insert new user into database
$stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, company, contact) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $name, $email, $password, $userType, $company, $contact);

if ($stmt->execute()) {
    header("Location: login.html?success=registered");
    exit();
} else {
    header("Location: register.html?error=failed");
    exit();
}

$conn->close();
?>
