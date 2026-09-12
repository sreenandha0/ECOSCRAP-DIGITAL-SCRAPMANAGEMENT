<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";


// --------------------
// Request Validation
// --------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("register.php");
}


// --------------------
// Sanitize Inputs
// --------------------

$name     = sanitize($_POST['name'] ?? '');
$email    = sanitize($_POST['email'] ?? '');
$phone    = sanitize($_POST['phone'] ?? '');
$address  = sanitize($_POST['address'] ?? '');
$place    = sanitize($_POST['place'] ?? '');
$district = sanitize($_POST['district'] ?? '');
$state    = sanitize($_POST['state'] ?? '');
$pincode  = sanitize($_POST['pincode'] ?? '');

$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';


// --------------------
// Valid Kerala Districts
// --------------------

$keralaDistricts = [
    "Alappuzha",
    "Ernakulam",
    "Idukki",
    "Kannur",
    "Kasargod",
    "Kollam",
    "Kottayam",
    "Kozhikode",
    "Malappuram",
    "Palakkad",
    "Pathanamthitta",
    "Thiruvananthapuram",
    "Thrissur",
    "Wayanad"
];


// --------------------
// Required Field Validation
// --------------------

if (
    empty($name) ||
    empty($email) ||
    empty($phone) ||
    empty($address) ||
    empty($place) ||
    empty($district) ||
    empty($state) ||
    empty($pincode) ||
    empty($password) ||
    empty($confirm)
) {
    setMessage("danger", "Please fill all required fields.");
    redirect("register.php");
}


// --------------------
// Name Validation
// --------------------

if (!preg_match('/^[a-zA-Z\s.]{2,100}$/', $name)) {
    setMessage("danger", "Please enter a valid name.");
    redirect("register.php");
}


// --------------------
// Email Validation
// --------------------

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage("danger", "Invalid email address.");
    redirect("register.php");
}


// --------------------
// Phone Validation
// --------------------

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    setMessage("danger", "Phone number must be exactly 10 digits.");
    redirect("register.php");
}


// --------------------
// Kerala State Validation
// --------------------

if ($state !== "Kerala") {
    setMessage(
        "danger",
        "EcoScrap currently operates only in Kerala."
    );

    redirect("register.php");
}


// --------------------
// District Validation
// --------------------

if (!in_array($district, $keralaDistricts, true)) {
    setMessage(
        "danger",
        "Please select a valid district in Kerala."
    );

    redirect("register.php");
}


// --------------------
// Pincode Validation
// --------------------

if (!preg_match('/^[0-9]{6}$/', $pincode)) {
    setMessage(
        "danger",
        "Pincode must be exactly 6 digits."
    );

    redirect("register.php");
}


// --------------------
// Password Match Validation
// --------------------

if ($password !== $confirm) {
    setMessage(
        "danger",
        "Passwords do not match."
    );

    redirect("register.php");
}


// --------------------
// Password Length Validation
// --------------------

if (strlen($password) < 8) {
    setMessage(
        "danger",
        "Password must be at least 8 characters."
    );

    redirect("register.php");
}


// --------------------
// Duplicate Email Check
// --------------------

$stmt = $conn->prepare(
    "SELECT user_id FROM user WHERE email = ?"
);

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {

    $stmt->close();

    setMessage(
        "danger",
        "Email already registered."
    );

    redirect("register.php");
}

$stmt->close();


// --------------------
// Duplicate Phone Check
// --------------------

$stmt = $conn->prepare(
    "SELECT user_id FROM user WHERE phone = ?"
);

$stmt->bind_param("s", $phone);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {

    $stmt->close();

    setMessage(
        "danger",
        "Phone number already exists."
    );

    redirect("register.php");
}

$stmt->close();


// --------------------
// Upload Profile Image
// --------------------

$image = "default.png";

if (
    isset($_FILES['profile_image']) &&
    $_FILES['profile_image']['error'] === UPLOAD_ERR_OK
) {

    $allowedExtensions = [
        'jpg',
        'jpeg',
        'png'
    ];

    $extension = strtolower(
        pathinfo(
            $_FILES['profile_image']['name'],
            PATHINFO_EXTENSION
        )
    );

    if (in_array($extension, $allowedExtensions, true)) {

        $image = uniqid(
            "profile_",
            true
        ) . "." . $extension;

        move_uploaded_file(
            $_FILES['profile_image']['tmp_name'],
            "../uploads/profile/" . $image
        );

    } else {

        setMessage(
            "danger",
            "Profile image must be JPG, JPEG or PNG."
        );

        redirect("register.php");
    }
}


// --------------------
// Hash Password
// --------------------

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// --------------------
// Insert User
// --------------------

$stmt = $conn->prepare(
    "
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
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
    "
);

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


// --------------------
// Registration Result
// --------------------

if ($stmt->execute()) {

    $stmt->close();
    $conn->close();

    setMessage(
        "success",
        "Registration successful. Please login."
    );

    redirect("../login.php");

} else {

    $stmt->close();
    $conn->close();

    setMessage(
        "danger",
        "Registration failed. Please try again."
    );

    redirect("register.php");
}

?>
