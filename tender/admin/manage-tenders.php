<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tender";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$result = $conn->query("SELECT username FROM users WHERE id = $admin_id");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $admin_name = $row['username'];
} else {
    $admin_name = 'Admin';
}

// Handle tender status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $tender_id = intval($_POST['tender_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if ($tender_id > 0 && in_array($status, ['open', 'closed', 'awarded'])) {
        $stmt = $conn->prepare("UPDATE tenders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $tender_id);
        try {
            $stmt->execute();
            $success = "Tender status updated successfully!";
        } catch(Exception $e) {
            $error = "Error updating tender status.";
        }
    }
}

// Fetch all tenders with bid counts
$tenders = []; // Initialize as empty array
$query = "SELECT t.*, 
          COUNT(b.id) as bid_count
          FROM tenders t 
          LEFT JOIN bids b ON t.id = b.tender_id 
          GROUP BY t.id 
          ORDER BY t.created_at DESC";
try {
    $result = $conn->query($query);
    if ($result === false) {
        throw new Exception("Query failed: " . $conn->error);
    }
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $tenders[] = $row;
        }
    }
} catch(Exception $e) {
    $error = "Error fetching tenders: " . $e->getMessage();
    error_log("Tender fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tenders - Tender Management</title>
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
                        <a class="nav-link" href="create-tender.php">Create Tender</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage-tenders.php">Manage Tenders</a>
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
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Manage Tenders</h5>
                <a href="create-tender.php" class="btn btn-primary">Create New Tender</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Budget</th>
                                <th>Deadline</th>
                                <th>Bids</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenders as $tender): ?>
                            <tr>
                                <td>TNDR-<?php echo str_pad($tender['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($tender['title']); ?></td>
                                <td><?php echo htmlspecialchars($tender['category'] ?? 'N/A'); ?></td>
                                <td>₹<?php echo number_format($tender['budget'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($tender['deadline'])); ?></td>
                                <td><?php echo $tender['bid_count']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $tender['status'] == 'open' ? 'success' : 
                                            ($tender['status'] == 'closed' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($tender['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="tender_id" value="<?php echo $tender['id']; ?>">
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="open" <?php echo $tender['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                            <option value="closed" <?php echo $tender['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                            <option value="awarded" <?php echo $tender['status'] == 'awarded' ? 'selected' : ''; ?>>Awarded</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
