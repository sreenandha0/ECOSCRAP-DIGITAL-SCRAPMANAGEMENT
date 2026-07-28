<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    redirect("register.php");
}

// Sanitize Inputs
$name      = sanitize($_POST['name']);
$email     = sanitize($_POST['email']);
$phone     = sanitize($_POST['phone']);
$address   = sanitize($_POST['address']);
$place     = sanitize($_POST['place']);
$district  = sanitize($_POST['district']);
$state     = sanitize($_POST['state']);
$pincode   = sanitize($_POST['pincode']);

$password  = $_POST['password'];
$confirm   = $_POST['confirm_password'];


// --------------------
// Validation
// --------------------

if (
    empty($name) ||
    empty($email) ||
    empty($phone) ||
    empty($address) ||
    empty($pincode) ||
    empty($password) ||
    empty($confirm)
) {
    setMessage("danger","Please fill all required fields.");
    redirect("register.php");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage("danger","Invalid email address.");
    redirect("register.php");
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    setMessage("danger","Phone number must be 10 digits.");
    redirect("register.php");
}

if (!preg_match('/^[0-9]{6}$/', $pincode)) {
    setMessage("danger","Pincode must be 6 digits.");
    redirect("register.php");
}

if ($password !== $confirm) {
    setMessage("danger","Passwords do not match.");
    redirect("register.php");
}

if (strlen($password) < 8) {
    setMessage("danger","Password must be at least 8 characters.");
    redirect("register.php");
}


// --------------------
// Duplicate Email
// --------------------

$stmt = $conn->prepare("SELECT user_id FROM user WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$stmt->store_result();

if($stmt->num_rows>0){
    setMessage("danger","Email already registered.");
    redirect("register.php");
}

$stmt->close();


// --------------------
// Duplicate Phone
// --------------------

$stmt = $conn->prepare("SELECT user_id FROM user WHERE phone=?");
$stmt->bind_param("s",$phone);
$stmt->execute();
$stmt->store_result();

if($stmt->num_rows>0){
    setMessage("danger","Phone number already exists.");
    redirect("register.php");
}

$stmt->close();


// --------------------
// Upload Profile Image
// --------------------

$image = "default.png";

if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error']==0){

    $allowed = ['jpg','jpeg','png'];

    $extension = strtolower(pathinfo($_FILES['profile_image']['name'],PATHINFO_EXTENSION));

    if(in_array($extension,$allowed)){

        $image = uniqid().".".$extension;

        move_uploaded_file(
            $_FILES['profile_image']['tmp_name'],
            "../uploads/profile/".$image
        );

    }

}


// --------------------
// Hash Password
// --------------------

$hashedPassword = password_hash($password,PASSWORD_DEFAULT);


// --------------------
// Insert User
// --------------------

$stmt = $conn->prepare("
INSERT INTO user
(
name,
email,
password,
phone,
profile_image,
address,
place,
district,
state,
pincode
)
VALUES
(
?,?,?,?,?,?,?,?,?,?
)
");

$stmt->bind_param(
"ssssssssss",
$name,
$email,
$hashedPassword,
$phone,
$image,
$address,
$place,
$district,
$state,
$pincode
);

if($stmt->execute()){

    setMessage(
        "success",
        "Registration successful. Please login."
    );

    redirect("../login.php");

}else{

    setMessage(
        "danger",
        "Registration failed."
    );

    redirect("register.php");

}

$stmt->close();
$conn->close();

?>