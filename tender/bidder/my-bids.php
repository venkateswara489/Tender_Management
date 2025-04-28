<?php
session_start();

// Check if user is logged in and is bidder
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'bidder') {
    header("Location: login.html");
    exit();
}

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tender";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get bidder name
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$bidder_data = $result->fetch_assoc();
$bidder_name = $bidder_data['username'];

// Get User's Bids with tender details
$stmt = $conn->prepare("SELECT b.*, t.title as tender_title, t.deadline, t.budget, t.description, t.category 
        FROM bids b 
        JOIN tenders t ON b.tender_id = t.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$my_bids = $stmt->get_result();

// Get statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_bids,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bids,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_bids,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bids,
    SUM(amount) as total_amount
    FROM bids WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bids - Tender Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="home.html">Tender Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="bidder-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="available-tenders.php">Available Tenders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my-bids.php">My Bids</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($bidder_name); ?></span>
                    <a href="logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Bids</h5>
                        <h2 class="display-4"><?php echo $stats['total_bids']; ?></h2>
                        <p class="text-white mb-0">All Time</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Pending Bids</h5>
                        <h2 class="display-4"><?php echo $stats['pending_bids']; ?></h2>
                        <p class="text-white mb-0">Awaiting Decision</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Accepted Bids</h5>
                        <h2 class="display-4"><?php echo $stats['accepted_bids']; ?></h2>
                        <p class="text-white mb-0">Won Tenders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Amount</h5>
                        <h2 class="display-4">₹<?php echo number_format($stats['total_amount'], 2); ?></h2>
                        <p class="text-white mb-0">Bid Value</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Bids Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">My Bids History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tender Title</th>
                                <th>Category</th>
                                <th>Bid Amount</th>
                                <th>Tender Budget</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Submitted On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($bid = mysqli_fetch_assoc($my_bids)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bid['tender_title']); ?></td>
                                <td><?php echo htmlspecialchars($bid['category']); ?></td>
                                <td>₹<?php echo number_format($bid['amount'], 2); ?></td>
                                <td>₹<?php echo number_format($bid['budget'], 2); ?></td>
                                <td><?php echo date('d M Y', strtotime($bid['deadline'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $bid['status'] == 'pending' ? 'warning' : 
                                            ($bid['status'] == 'accepted' ? 'success' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($bid['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($bid['created_at'])); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 