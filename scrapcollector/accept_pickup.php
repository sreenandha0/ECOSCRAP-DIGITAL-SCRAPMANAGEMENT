
//accept_pickup 
<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";


if (
    !isset($_SESSION['collector_id']) ||
    $_SESSION['role'] != "Collector"
) {
    redirect("../login.php");
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: assigned_requests.php");
    exit();
}

verifyCsrfToken();

$activity_id = (int) ($_POST['id'] ?? 0);
$collector_id = $_SESSION['collector_id'];


// Check request belongs to collector and status is Assigned

$stmt = $conn->prepare("
    SELECT activity_id
    FROM activity
    WHERE activity_id = ?
    AND collector_id = ?
    AND status = 'Assigned'
");

$stmt->bind_param(
    "ii",
    $activity_id,
    $collector_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows == 0) {

    die("Invalid pickup request.");

}

$stmt->close();



/*
|--------------------------------------------------------------------------
| Start Transaction
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {


    // Update pickup status

    $stmt = $conn->prepare("
        UPDATE activity
        SET status = 'In Progress'
        WHERE activity_id = ?
        AND collector_id = ?
    ");


    $stmt->bind_param(
        "ii",
        $activity_id,
        $collector_id
    );


    $stmt->execute();

    $stmt->close();



    // Update collector availability

    $stmt = $conn->prepare("
        UPDATE scrapcollector
        SET availability_status = 'Busy'
        WHERE collector_id = ?
    ");


    $stmt->bind_param(
        "i",
        $collector_id
    );


    $stmt->execute();

    $stmt->close();



    // Commit changes

    $conn->commit();


    $_SESSION['msg'] = "Pickup started successfully!";


}


catch(Exception $e)
{

    $conn->rollback();

    $_SESSION['error'] = "Failed to accept pickup.";

}



header("Location: assigned_requests.php?success=started");

exit();

?>
