<?php
// create_chat.php - Create a new chat session
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
$title = $data["title"] ?? "New Chat";
$user_id = $_SESSION["id"];

// Create new chat session
$sql = "INSERT INTO chat_sessions (user_id, title) VALUES (?, ?)";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "is", $user_id, $title);
    
    if(mysqli_stmt_execute($stmt)){
        $session_id = mysqli_insert_id($conn);
        echo json_encode([
            "success" => true, 
            "session_id" => $session_id,
            "title" => $title
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create chat session"]);
    }
    
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}

mysqli_close($conn);
?>