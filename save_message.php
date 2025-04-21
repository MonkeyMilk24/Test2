<?php
// save_message.php - Save a message to the database
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents("php://input"), true);
$session_id = $data["session_id"] ?? 0;
$message = $data["message"] ?? "";
$sender = $data["sender"] ?? "user";

// Validate sender value
if($sender !== "user" && $sender !== "ai") {
    http_response_code(400);
    echo json_encode(["error" => "Invalid sender"]);
    exit;
}

// Validate session belongs to current user
$user_id = $_SESSION["id"];
$valid_session = false;

if($session_id > 0) {
    $sql = "SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?";
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "ii", $session_id, $user_id);
        if(mysqli_stmt_execute($stmt)){
            mysqli_stmt_store_result($stmt);
            if(mysqli_stmt_num_rows($stmt) == 1){
                $valid_session = true;
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if(!$valid_session){
    http_response_code(400);
    echo json_encode(["error" => "Invalid session"]);
    exit;
}

// Save message to database
$sql = "INSERT INTO messages (session_id, sender, message) VALUES (?, ?, ?)";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "iss", $session_id, $sender, $message);
    
    if(mysqli_stmt_execute($stmt)){
        echo json_encode(["success" => true, "message_id" => mysqli_insert_id($conn)]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to save message"]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}

mysqli_close($conn);
?>