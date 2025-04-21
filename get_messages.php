<?php
// get_messages.php - Get messages for a chat session
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get session ID from query string
$session_id = isset($_GET["session_id"]) ? intval($_GET["session_id"]) : 0;
$user_id = $_SESSION["id"];

// Validate session belongs to current user
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

// Get messages for this session
$messages = [];
$sql = "SELECT id, sender, message, timestamp FROM messages WHERE session_id = ? ORDER BY timestamp ASC";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $session_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $messages[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

echo json_encode($messages);
mysqli_close($conn);
?>