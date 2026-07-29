<?php

require_once "../includes/db.php";
require_once "../includes/phpqrcode/lib/qrlib.php";


if(!isset($_GET['id'])){
    die("Invalid Request");
}


$activity_id = $_GET['id'];


// QR Content
$data = "EcoScrap Pickup Verification ID: ".$activity_id;


// QR Folder
$folder = "../uploads/qr/";


// Create folder if missing
if(!file_exists($folder)){
    mkdir($folder,0777,true);
}


// Filename
$filename = "pickup_".$activity_id."_".time().".png";

$file = $folder.$filename;


// Generate QR
QRcode::png(
    $data,
    $file,
    QR_ECLEVEL_L,
    5
);


// Save filename in database
$sql = "UPDATE activity 
        SET qr_code='$filename'
        WHERE activity_id='$activity_id'";


$conn->query($sql);



echo "
<h2>QR Generated Successfully</h2>

<img src='../uploads/qr/$filename' width='250'>

<br><br>

<a href='../uploads/qr/$filename' download>
Download QR
</a>

";

?>