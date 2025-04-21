<?php
// config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // default password is empty for XAMPP
define('DB_NAME', 'therapist_ai');

$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
define('GEMINI_API_KEY', 'shhh'); // Replace with your actual key
?>
