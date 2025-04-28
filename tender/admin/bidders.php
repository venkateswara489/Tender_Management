<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tender";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// Handle bidder status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $bidder_id = intval($_POST['bidder_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if ($bidder_id > 0 && in_array($status, ['active', 'suspended', 'blocked'])) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND user_type = 'bidder'");
        $stmt->bind_param("si", $status, $bidder_id);
        try {
            $stmt->execute();
            $success = "Bidder status updated successfully!";
        } catch(Exception $e) {
            $error = "Error updating bidder status.";
        }
    }
}

// Handle bid status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bid_status'])) {
    $bid_id = intval($_POST['bid_id'] ?? 0);
    $bid_status = trim($_POST['bid_status'] ?? '');
    
    if ($bid_id > 0 && in_array($bid_status, ['pending', 'accepted', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE bids SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $bid_status, $bid_id);
        try {
            $stmt->execute();
            $success = "Bid status updated successfully!";
        } catch(Exception $e) {
            $error = "Error updating bid status.";
        }
    }
}

// Fetch all bidders with their bid counts
$bidders = []; // Initialize as empty array
$query = "SELECT u.*, 
          COUNT(b.id) as bid_count,
          SUM(CASE WHEN b.status = 'accepted' THEN 1 ELSE 0 END) as won_bids
          FROM users u 
          LEFT JOIN bids b ON u.id = b.user_id 
          WHERE u.user_type = 'bidder'
          GROUP BY u.id 
          ORDER BY u.created_at DESC";

try {
    $result = $conn->query($query);
    if ($result === false) {
        throw new Exception("Query failed: " . $conn->error);
    }
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Get all bids for this bidder
            $bid_query = "SELECT b.*, t.title as tender_title, t.status as tender_status 
                         FROM bids b 
                         JOIN tenders t ON b.tender_id = t.id 
                         WHERE b.user_id = ? 
                         ORDER BY b.created_at DESC";
            $stmt = $conn->prepare($bid_query);
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            $bids_result = $stmt->get_result();
            $row['bids'] = [];
            while($bid = $bids_result->fetch_assoc()) {
                $row['bids'][] = $bid;
            }
            $bidders[] = $row;
        }
    }
} catch(Exception $e) {
    $error = "Error fetching bidders: " . $e->getMessage();
    error_log("Bidder fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bidders - Tender Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .bid-details {
            background-color: #f8f9fa;
            border-radius: 5px;
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
                        <a class="nav-link" href="admin-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create-tender.php">Create Tender</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage-tenders.php">Manage Tenders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="bidders.php">Bidders</a>
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
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Manage Bidders</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Company</th>
                                <th>Total Bids</th>
                                <th>Won Bids</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bidders as $bidder): ?>
                            <tr class="bidder-row" data-bidder-id="<?php echo $bidder['id']; ?>">
                                <td>BDR-<?php echo str_pad($bidder['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($bidder['username']); ?></td>
                                <td><?php echo htmlspecialchars($bidder['email']); ?></td>
                                <td><?php echo htmlspecialchars($bidder['company'] ?? 'N/A'); ?></td>
                                <td><?php echo $bidder['bid_count']; ?></td>
                                <td><?php echo $bidder['won_bids']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($bidder['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $bidder['status'] == 'active' ? 'success' : 
                                            ($bidder['status'] == 'suspended' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($bidder['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="bidder_id" value="<?php echo $bidder['id']; ?>">
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto">
                                            <option value="active" <?php echo $bidder['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="suspended" <?php echo $bidder['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                            <option value="blocked" <?php echo $bidder['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                                    </form>
                                    <button class="btn btn-info btn-sm ms-2" onclick="toggleBids(<?php echo $bidder['id']; ?>)">View Bids</button>
                                </td>
                            </tr>
                            <tr id="bids-<?php echo $bidder['id']; ?>" style="display: none;">
                                <td colspan="9">
                                    <div class="table-responsive bid-details p-3">
                                        <h6 class="mb-3">Bid History</h6>
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Tender Title</th>
                                                    <th>Bid Amount</th>
                                                    <th>Bid Date</th>
                                                    <th>Bid Status</th>
                                                    <th>Tender Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($bidder['bids'])): ?>
                                                    <?php foreach ($bidder['bids'] as $bid): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($bid['tender_title']); ?></td>
                                                        <td class="fw-bold">₹<?php echo number_format($bid['amount'], 2); ?></td>
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
                                                            <span class="badge bg-<?php 
                                                                echo $bid['tender_status'] == 'open' ? 'info' : 
                                                                    ($bid['tender_status'] == 'closed' ? 'secondary' : 
                                                                    ($bid['tender_status'] == 'awarded' ? 'success' : 'warning')); 
                                                            ?>">
                                                                <?php echo ucfirst($bid['tender_status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                                                <select name="bid_status" class="form-select form-select-sm d-inline-block w-auto">
                                                                    <option value="pending" <?php echo $bid['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="accepted" <?php echo $bid['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                                    <option value="rejected" <?php echo $bid['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                                </select>
                                                                <button type="submit" name="update_bid_status" class="btn btn-primary btn-sm">Update</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center">No bids found</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
    <script>
    function toggleBids(bidderId) {
        const bidsRow = document.getElementById('bids-' + bidderId);
        if (bidsRow.style.display === 'none') {
            bidsRow.style.display = 'table-row';
        } else {
            bidsRow.style.display = 'none';
        }
    }
    </script>
</body>
</html>
