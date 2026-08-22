<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== "User") {
    redirect("../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$activity_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

if ($activity_id <= 0) {
    setMessage("danger", "Invalid request.");
    redirect("history.php");
    exit();
}

// Check activity ownership and status
$stmt = $conn->prepare("SELECT status, rating, scrap_type, collector_id FROM activity WHERE activity_id = ? AND user_id = ?");
$stmt->bind_param("ii", $activity_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setMessage("danger", "Pickup request not found or unauthorized access.");
    redirect("history.php");
    exit();
}

$activity = $result->fetch_assoc();
$stmt->close();

if (strtolower(trim($activity['status'])) !== 'completed' && strtolower(trim($activity['status'])) !== 'verified') {
    setMessage("danger", "Feedback can only be submitted for completed pickups.");
    redirect("history.php");
    exit();
}

if (!empty($activity['rating'])) {
    setMessage("warning", "You have already submitted feedback for this pickup.");
    redirect("history.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $rating = (int)($_POST['rating'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please provide a valid rating between 1 and 5.";
    } elseif (strlen($feedback) > 1000) {
        $error = "Feedback is too long (maximum 1000 characters).";
    } else {
        $safe_feedback = htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');
        $stmt = $conn->prepare("UPDATE activity SET rating = ?, feedback_text = ? WHERE activity_id = ? AND user_id = ?");
        $stmt->bind_param("isii", $rating, $safe_feedback, $activity_id, $user_id);
        
        if ($stmt->execute()) {
            setMessage("success", "Feedback submitted successfully. Thank you!");
            redirect("history.php");
            exit();
        } else {
            $error = "An error occurred while saving your feedback. Please try again.";
        }
        $stmt->close();
    }
}

// Get collector name for display
$collector_name = "the collector";
if (!empty($activity['collector_id'])) {
    $c_stmt = $conn->prepare("SELECT name FROM scrapcollector WHERE collector_id = ?");
    $c_stmt->bind_param("i", $activity['collector_id']);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result();
    if ($c_row = $c_res->fetch_assoc()) {
        $collector_name = htmlspecialchars($c_row['name']);
    }
    $c_stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Feedback | EcoScrap</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .feedback-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            max-width: 600px;
            margin: 60px auto;
            text-align: center;
        }
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
        }
        .star-rating input { display: none; }
        .star-rating label {
            font-size: 40px;
            color: #cbd5e1;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f59e0b;
        }
        .form-control {
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            border-color: #10b981;
        }
        .btn-submit {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-submit:hover { background: #059669; }
        .success-icon {
            color: #10b981;
            font-size: 50px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="feedback-card">
            <div class="success-icon">
                <i class="ri-checkbox-circle-fill"></i>
            </div>
            <h2 class="fw-bold mb-3">Pickup Completed</h2>
            <p class="text-muted mb-4">How was your scrap collection experience for the <strong><?= htmlspecialchars($activity['scrap_type']) ?></strong> pickup with <?= $collector_name ?>?</p>
            
            <?php if (isset($error)) : ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST" action="feedback.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $activity_id ?>">
                
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5" required />
                    <label for="star5" title="5 stars"><i class="ri-star-fill"></i></label>
                    
                    <input type="radio" id="star4" name="rating" value="4" />
                    <label for="star4" title="4 stars"><i class="ri-star-fill"></i></label>
                    
                    <input type="radio" id="star3" name="rating" value="3" />
                    <label for="star3" title="3 stars"><i class="ri-star-fill"></i></label>
                    
                    <input type="radio" id="star2" name="rating" value="2" />
                    <label for="star2" title="2 stars"><i class="ri-star-fill"></i></label>
                    
                    <input type="radio" id="star1" name="rating" value="1" />
                    <label for="star1" title="1 star"><i class="ri-star-fill"></i></label>
                </div>
                
                <textarea name="feedback" class="form-control" rows="4" placeholder="Share your experience (optional)..."></textarea>
                
                <button type="submit" class="btn-submit">Submit Review</button>
                <a href="history.php" class="btn btn-link text-muted mt-3">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
