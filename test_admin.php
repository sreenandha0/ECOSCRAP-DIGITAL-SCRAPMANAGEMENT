<?php
require_once "includes/db.php";

$email = "admin@ecoscrap.com";
$password = "Admin@123";

$stmt = $conn->prepare("SELECT * FROM admin WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows==1){

    $admin = $result->fetch_assoc();

    if(password_verify($password,$admin['password'])){

        echo "Password Correct";

    }else{

        echo "Password Incorrect";

    }

}else{

    echo "Admin Not Found";

}
?>