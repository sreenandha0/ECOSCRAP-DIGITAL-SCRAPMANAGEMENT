<?php

require_once "../includes/db.php";
require_once "../includes/phpqrcode/lib/qrlib.php";


$activity_id = $_GET['id'];


// QR Data
$data = "EcoScrap Pickup Verification ID: ".$activity_id;


// QR location

$folder = "../qr_codes/";

if(!file_exists($folder))
{
    mkdir($folder);
}


$file = $folder."pickup_".$activity_id.".png";


// Generate QR

QRcode::png(
    $data,
    $file,
    QR_ECLEVEL_L,
    5
);


echo "
<h2>QR Generated Successfully</h2>

<img src='../qr_codes/pickup_$activity_id.png'>

<br><br>

<a href='../qr_codes/pickup_$activity_id.png' download>
Download QR
</a>
";

?>