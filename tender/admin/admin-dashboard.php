<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
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

// Get admin name
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$admin_name = $admin_data['username'];

// Create Tender
if (isset($_POST['create_tender'])) {
    $stmt = $conn->prepare("INSERT INTO tenders (title, description, deadline, budget, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->bind_param("sssd", $_POST['title'], $_POST['description'], $_POST['deadline'], $_POST['budget']);
    
    if ($stmt->execute()) {
        header("Location: admin-dashboard.php?success=tender_created");
        exit();
    } else {
        header("Location: admin-dashboard.php?error=tender_creation_failed");
        exit();
    }
}

// Update Tender Status
if (isset($_POST['update_status'])) {
    $stmt = $conn->prepare("UPDATE tenders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $_POST['status'], $_POST['tender_id']);
    
    if ($stmt->execute()) {
        header("Location: admin-dashboard.php?success=status_updated");
        exit();
    } else {
        header("Location: admin-dashboard.php?error=status_update_failed");
        exit();
    }
}

// Update Bid Status
if (isset($_POST['update_bid'])) {
    $stmt = $conn->prepare("UPDATE bids SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $_POST['bid_status'], $_POST['bid_id']);
    
    if ($stmt->execute()) {
        // If bid is accepted, close the tender and reject other bids
        if ($_POST['bid_status'] === 'accepted') {
            // Update tender status to awarded
            $stmt = $conn->prepare("UPDATE tenders SET status = 'awarded' WHERE id = ?");
            $stmt->bind_param("i", $_POST['tender_id']);
            $stmt->execute();
            
            // Reject other bids for this tender
            $stmt = $conn->prepare("UPDATE bids SET status = 'rejected' WHERE tender_id = ? AND id != ?");
            $stmt->bind_param("ii", $_POST['tender_id'], $_POST['bid_id']);
            $stmt->execute();
        }
        header("Location: admin-dashboard.php?success=bid_updated");
        exit();
    } else {
        header("Location: admin-dashboard.php?error=bid_update_failed");
        exit();
    }
}

// Get statistics
// Active Tenders (status = 'open')
$stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM tenders WHERE status = 'open'");
$stmt->execute();
$tender_stats = $stmt->get_result()->fetch_assoc();

// Total Bids
$stmt = $conn->prepare("SELECT COUNT(*) as total_count FROM bids");
$stmt->execute();
$bid_stats = $stmt->get_result()->fetch_assoc();

// Pending Approvals (bids with status = 'pending')
$stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM bids WHERE status = 'pending'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];

// Total Bidders
$stmt = $conn->prepare("SELECT COUNT(*) as total_count FROM users WHERE user_type = 'bidder'");
$stmt->execute();
$bidder_stats = $stmt->get_result()->fetch_assoc();

// Get All Tenders
$stmt = $conn->prepare("SELECT t.*, COUNT(b.id) as bid_count 
                       FROM tenders t 
                       LEFT JOIN bids b ON t.id = b.tender_id 
                       GROUP BY t.id 
                       ORDER BY t.created_at DESC");
$stmt->execute();
$tenders = $stmt->get_result();

// Get All Bids with user and tender information
$stmt = $conn->prepare("SELECT b.*, u.username as bidder_name, t.title as tender_title, t.status as tender_status 
        FROM bids b 
        JOIN users u ON b.user_id = u.id 
        JOIN tenders t ON b.tender_id = t.id 
        ORDER BY b.created_at DESC");
$stmt->execute();
$bids = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tender Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
        .card-body {
            padding: 1.5rem;
        }
        .display-4 {
            font-weight: bold;
            margin-bottom: 0;
        }
        .text-muted {
            font-size: 0.875rem;
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
                        <a class="nav-link active" href="admin-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create-tender.php">Create Tender</a>
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
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Active Tenders</h5>
                        <h2 class="display-4"><?php echo $tender_stats['active_count']; ?></h2>
                        <p class="text-white mb-0">Currently Open</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Bids</h5>
                        <h2 class="display-4"><?php echo $bid_stats['total_count']; ?></h2>
                        <p class="text-white mb-0">All Time</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Pending Approvals</h5>
                        <h2 class="display-4"><?php echo $pending_count; ?></h2>
                        <p class="text-white mb-0"><?php echo $pending_count > 0 ? 'Need attention' : 'All caught up!'; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Registered Bidders</h5>
                        <h2 class="display-4"><?php echo $bidder_stats['total_count']; ?></h2>
                        <p class="text-white mb-0">Total Users</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tenders -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Recent Tenders</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tender ID</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Bids</th>
                                <th>Latest Bid Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($tender = mysqli_fetch_assoc($tenders)) { 
                                // Get the latest bid amount for this tender
                                $bid_stmt = $conn->prepare("SELECT amount FROM bids WHERE tender_id = ? ORDER BY created_at DESC LIMIT 1");
                                $bid_stmt->bind_param("i", $tender['id']);
                                $bid_stmt->execute();
                                $latest_bid = $bid_stmt->get_result()->fetch_assoc();
                            ?>
                            <tr>
                                <td>TNDR-<?php echo str_pad($tender['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($tender['title']); ?></td>
                                <td><?php echo htmlspecialchars($tender['category']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($tender['created_at'])); ?></td>
                                <td><?php echo $tender['deadline']; ?></td>
                                <td><span class="badge bg-<?php echo $tender['status'] == 'open' ? 'success' : ($tender['status'] == 'pending' ? 'warning' : 'danger'); ?>"><?php echo ucfirst($tender['status']); ?></span></td>
                                <td><?php echo $tender['bid_count']; ?></td>
                                <td><?php echo $latest_bid ? '₹' . number_format($latest_bid['amount'], 2) : 'No bids'; ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Bids -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Recent Bids</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bid ID</th>
                                <th>Tender</th>
                                <th>Bidder</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($bid = mysqli_fetch_assoc($bids)) { ?>
                            <tr>
                                <td>BID-<?php echo str_pad($bid['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($bid['tender_title']); ?></td>
                                <td><?php echo htmlspecialchars($bid['bidder_name']); ?></td>
                                <td>₹<?php echo number_format($bid['amount'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($bid['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $bid['status'] == 'pending' ? 'warning' : 
                                            ($bid['status'] == 'accepted' ? 'success' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($bid['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($bid['status'] == 'pending' && $bid['tender_status'] == 'open'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                        <input type="hidden" name="tender_id" value="<?php echo $bid['tender_id']; ?>">
                                        <select name="bid_status" class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="accepted">Accept</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <button type="submit" name="update_bid" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
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