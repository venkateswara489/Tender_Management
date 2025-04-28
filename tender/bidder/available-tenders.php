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

// Submit Bid
if (isset($_POST['submit_bid'])) {
    $tender_id = $_POST['tender_id'];
    $amount = $_POST['amount'];
    $user_id = $_SESSION['user_id'];

    // Check tender status using prepared statement
    $stmt = $conn->prepare("SELECT status, deadline FROM tenders WHERE id = ?");
    $stmt->bind_param("i", $tender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tender = $result->fetch_assoc();

    if (!$tender || $tender['status'] != 'open') {
        header("Location: available-tenders.php?error=tender_closed");
        exit();
    }

    if ($tender['deadline'] < date('Y-m-d')) {
        header("Location: available-tenders.php?error=tender_expired");
        exit();
    }

    // Check if user already bid on this tender
    $stmt = $conn->prepare("SELECT id FROM bids WHERE tender_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $tender_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: available-tenders.php?error=already_bid");
        exit();
    }

    // Insert new bid
    $stmt = $conn->prepare("INSERT INTO bids (tender_id, user_id, amount, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("iid", $tender_id, $user_id, $amount);

    if ($stmt->execute()) {
        header("Location: available-tenders.php?success=bid_submitted");
        exit();
    } else {
        header("Location: available-tenders.php?error=bid_failed");
        exit();
    }
}

// Get Open Tenders
$stmt = $conn->prepare("SELECT * FROM tenders WHERE status = 'open' AND deadline >= CURDATE() ORDER BY deadline ASC");
$stmt->execute();
$tenders = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Tenders - Tender Management</title>
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
                        <a class="nav-link" href="bidder-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="available-tenders.php">Available Tenders</a>
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
        <?php
        if (isset($_GET['error'])) {
            $message = '';
            switch ($_GET['error']) {
                case 'tender_closed': $message = 'This tender is no longer accepting bids'; break;
                case 'tender_expired': $message = 'This tender has expired'; break;
                case 'already_bid': $message = 'You have already submitted a bid for this tender'; break;
                case 'bid_failed': $message = 'Failed to submit bid. Please try again'; break;
                default: $message = 'An error occurred'; break;
            }
            echo '<div class="alert alert-danger">' . $message . '</div>';
        } else if (isset($_GET['success']) && $_GET['success'] === 'bid_submitted') {
            echo '<div class="alert alert-success">Your bid has been submitted successfully</div>';
        }
        ?>

        <!-- Available Tenders -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Available Tenders</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Deadline</th>
                                <th>Budget</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($tender = mysqli_fetch_assoc($tenders)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tender['title']); ?></td>
                                <td><?php echo htmlspecialchars($tender['category']); ?></td>
                                <td><?php echo htmlspecialchars(substr($tender['description'], 0, 100)) . '...'; ?></td>
                                <td><?php echo date('d M Y', strtotime($tender['deadline'])); ?></td>
                                <td>₹<?php echo number_format($tender['budget'], 2); ?></td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="toggleBidForm('bidForm<?php echo $tender['id']; ?>')">Submit Bid</button>
                                    <form method="POST" id="bidForm<?php echo $tender['id']; ?>" style="display:none; margin-top:10px">
                                        <input type="hidden" name="tender_id" value="<?php echo $tender['id']; ?>">
                                        <div class="input-group">
                                            <input type="number" name="amount" class="form-control form-control-sm" required placeholder="Enter amount">
                                            <button type="submit" name="submit_bid" class="btn btn-primary btn-sm">Submit</button>
                                        </div>
                                    </form>
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
    <script>
    function toggleBidForm(formId) {
        const form = document.getElementById(formId);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</body>
</html> 