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

// Get statistics
// Active Bids (status = 'pending')
$stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM bids WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$active_bids = $stmt->get_result()->fetch_assoc();

// Accepted Bids
$stmt = $conn->prepare("SELECT COUNT(*) as accepted_count FROM bids WHERE user_id = ? AND status = 'accepted'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$accepted_bids = $stmt->get_result()->fetch_assoc();

// Total Bids
$stmt = $conn->prepare("SELECT COUNT(*) as total_count FROM bids WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$total_bids = $stmt->get_result()->fetch_assoc();

// Total Amount Bidded
$stmt = $conn->prepare("SELECT SUM(amount) as total_amount FROM bids WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$total_amount = $stmt->get_result()->fetch_assoc();

// Get recent bids (limit 5)
$stmt = $conn->prepare("SELECT b.*, t.title as tender_title, t.deadline, t.budget 
        FROM bids b 
        JOIN tenders t ON b.tender_id = t.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC LIMIT 5");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_bids = $stmt->get_result();

// Get available tenders count
$stmt = $conn->prepare("SELECT COUNT(*) as available_count FROM tenders WHERE status = 'open' AND deadline >= CURDATE()");
$stmt->execute();
$available_tenders = $stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidder Dashboard - Tender Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .quick-action-card {
            text-decoration: none;
            color: inherit;
        }
        .quick-action-card:hover {
            color: inherit;
        }
    </style>
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
                        <a class="nav-link active" href="bidder-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="available-tenders.php">Available Tenders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-bids.php">My Bids</a>
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
                        <h5 class="card-title">Active Bids</h5>
                        <h2 class="display-4"><?php echo $active_bids['active_count']; ?></h2>
                        <p class="text-white mb-0">Pending Review</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Won Tenders</h5>
                        <h2 class="display-4"><?php echo $accepted_bids['accepted_count']; ?></h2>
                        <p class="text-white mb-0">Successfully Won</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Bids</h5>
                        <h2 class="display-4"><?php echo $total_bids['total_count']; ?></h2>
                        <p class="text-white mb-0">All Time</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Amount</h5>
                        <h2 class="display-4">₹<?php echo number_format($total_amount['total_amount'] ?? 0, 2); ?></h2>
                        <p class="text-white mb-0">Bid Value</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-6">
                <a href="available-tenders.php" class="quick-action-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title">Available Tenders</h5>
                                    <p class="card-text mb-0"><?php echo $available_tenders['available_count']; ?> tenders open for bidding</p>
                                </div>
                                <i class="bi bi-arrow-right-circle fs-1 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="my-bids.php" class="quick-action-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h5 class="card-title">My Bids</h5>
                                    <p class="card-text mb-0">View and manage your bid history</p>
                                </div>
                                <i class="bi bi-list-check fs-1 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Bids -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Bids</h5>
                <a href="my-bids.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tender</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($bid = mysqli_fetch_assoc($recent_bids)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bid['tender_title']); ?></td>
                                <td>₹<?php echo number_format($bid['amount'], 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $bid['status'] == 'pending' ? 'warning' : 
                                            ($bid['status'] == 'accepted' ? 'success' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($bid['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($bid['deadline'])); ?></td>
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
