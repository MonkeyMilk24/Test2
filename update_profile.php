<?php
// update_profile.php - Update user profile information
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$success_message = "";
$error_message = "";

// Process form data
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Update username if provided
    if(!empty(trim($_POST["username"]))){
        $username = trim($_POST["username"]);
        
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "si", $username, $user_id);
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                if(mysqli_stmt_num_rows($stmt) > 0){
                    $error_message = "This username is already taken.";
                } else {
                    // Update username
                    $sql = "UPDATE users SET username = ? WHERE id = ?";
                    if($stmt2 = mysqli_prepare($conn, $sql)){
                        mysqli_stmt_bind_param($stmt2, "si", $username, $user_id);
                        if(mysqli_stmt_execute($stmt2)){
                            $_SESSION["username"] = $username;
                            $success_message = "Profile updated successfully!";
                        } else {
                            $error_message = "Something went wrong. Please try again later.";
                        }
                        mysqli_stmt_close($stmt2);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Update age if provided
    if(!empty($_POST["age"])){
        $age = intval($_POST["age"]);
        if($age > 0){
            $sql = "UPDATE users SET age = ? WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "ii", $age, $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $success_message = "Profile updated successfully!";
            }
        }
    }
    
    // Process profile picture upload
    if(isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0){
        $allowed = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "gif" => "image/gif"];
        $filename = $_FILES["profile_pic"]["name"];
        $filetype = $_FILES["profile_pic"]["type"];
        $filesize = $_FILES["profile_pic"]["size"];
        
        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if(!array_key_exists($ext, $allowed)) {
            $error_message = "Error: Please select a valid file format.";
        }
        
        // Verify file size - 5MB maximum
        $maxsize = 5 * 1024 * 1024;
        if($filesize > $maxsize) {
            $error_message = "Error: File size is larger than the allowed limit.";
        }
        
        // Verify MIME type of the file
        if(in_array($filetype, $allowed) && empty($error_message)){
            // Create upload directory if it doesn't exist
            if(!file_exists("uploads")) {
                mkdir("uploads", 0777, true);
            }
            
            // Create unique filename
            $new_filename = uniqid() . "." . $ext;
            $filepath = "uploads/" . $new_filename;
            
            // Attempt to move uploaded file
            if(move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $filepath)){
                // Update database with new profile pic path
                $sql = "UPDATE users SET profile_pic = ? WHERE id = ?";
                if($stmt = mysqli_prepare($conn, $sql)){
                    mysqli_stmt_bind_param($stmt, "si", $filepath, $user_id);
                    if(mysqli_stmt_execute($stmt)){
                        $success_message = "Profile picture updated successfully!";
                    } else {
                        $error_message = "Error updating profile picture in database.";
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                $error_message = "Error uploading file.";
            }
        }
    }
    
    // Redirect back to index page
    header("location: index.php" . 
           (!empty($success_message) ? "?success=" . urlencode($success_message) : "") . 
           (!empty($error_message) ? "?error=" . urlencode($error_message) : ""));
    exit;
}

mysqli_close($conn);
?>