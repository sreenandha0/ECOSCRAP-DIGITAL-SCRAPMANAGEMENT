<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if(
    !isset($_SESSION['admin_id']) ||
    $_SESSION['role']!="Admin"
){
    redirect("../login.php");
}

$id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if($id==0){

    redirect("approve_collectors.php");

}

if($action=="approve"){

    $status="Approved";
    $availability="Available";

}elseif($action=="reject"){

    $status="Rejected";
    $availability="Offline";

}else{

    redirect("approve_collectors.php");

}

$stmt=$conn->prepare("
UPDATE scrapcollector

SET

verification_status=?,
availability_status=?

WHERE collector_id=?
");

$stmt->bind_param(
"ssi",
$status,
$availability,
$id
);

if($stmt->execute()){

    // Audit Log

    $actionText = ($status=="Approved")
        ? "Collector Approved"
        : "Collector Rejected";

    $description = "Collector ID ".$id." ".$status." by Admin.";

    $log=$conn->prepare("
    INSERT INTO audit_log
    (
    actor_role,
    actor_id,
    action,
    description
    )
    VALUES
    (
    'Admin',
    ?,
    ?,
    ?
    )
    ");

    $log->bind_param(
    "iss",
    $_SESSION['admin_id'],
    $actionText,
    $description
    );

    $log->execute();

    $log->close();

    setMessage(
        "success",
        "Collector ".$status." successfully."
    );

}else{

    setMessage(
        "danger",
        "Something went wrong."
    );

}

$stmt->close();

redirect("approve_collectors.php");
?>