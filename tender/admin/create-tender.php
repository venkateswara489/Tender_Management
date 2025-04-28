<?php
session_start();

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tender";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get admin name from database
$admin_id = $_SESSION['user_id'];
$result = $pdo->prepare("SELECT username FROM users WHERE id = :id");
$result->execute(['id' => $admin_id]);
if ($result && $result->rowCount() > 0) {
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $admin_name = $row['username'];
} else {
    $admin_name = 'Admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);
    $deadline = trim($_POST['deadline'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    
    if (!empty($title) && !empty($category) && $budget > 0 && !empty($deadline) && !empty($description)) {
        try {
            // First check if user is logged in and is admin
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
                throw new Exception("Unauthorized access");
            }

            $stmt = $pdo->prepare("INSERT INTO tenders (title, category, budget, deadline, description, requirements, created_by, created_at, status) 
                                VALUES (:title, :category, :budget, :deadline, :description, :requirements, :created_by, NOW(), 'open')");
            
            $params = [
                'title' => $title,
                'category' => $category,
                'budget' => $budget,
                'deadline' => $deadline,
                'description' => $description,
                'requirements' => $requirements,
                'created_by' => $_SESSION['user_id']
            ];
            
            if ($stmt->execute($params)) {
                $success = "Tender created successfully!";
            } else {
                throw new Exception("Failed to insert tender");
            }
        } catch(PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
            error_log("Tender creation error: " . $e->getMessage());
        } catch(Exception $e) {
            $error = $e->getMessage();
            error_log("Tender creation error: " . $e->getMessage());
        }
    } else {
        $error = "Please fill in all required fields with valid values.";
        if (empty($title)) $error .= " Title is required.";
        if (empty($category)) $error .= " Category is required.";
        if ($budget <= 0) $error .= " Budget must be greater than 0.";
        if (empty($deadline)) $error .= " Deadline is required.";
        if (empty($description)) $error .= " Description is required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tender - Tender Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="home.php">Tender Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create-tender.php">Create Tender</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-tenders.php">Manage Tenders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bidders.php">Bidders</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                    <a href="logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Create New Tender</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="construction">Construction</option>
                            <option value="supplies">Supplies</option>
                            <option value="services">Services</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Budget</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="budget" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="deadline" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" class="form-control" rows="4" required></textarea>
                        <small class="text-muted">Enter each requirement on a new line</small>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="create_tender" class="btn btn-primary">Create Tender</button>
                        <a href="admin-dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
