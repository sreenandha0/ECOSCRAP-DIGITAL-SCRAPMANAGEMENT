<?php

$password = "Admin@123";

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Generated Hash</h3>";
echo $hash;

echo "<hr>";

echo "<h3>Verification</h3>";

if(password_verify("Admin@123", $hash)){
    echo "SUCCESS";
}else{
    echo "FAILED";
}