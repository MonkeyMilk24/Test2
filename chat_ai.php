<?php
// chat_ai.php - Process AI responses
session_start();
require_once "config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents("php://input"), true);
$message = $data["message"] ?? "";
$session_id = $data["session_id"] ?? 0;

// Validate session belongs to current user
$user_id = $_SESSION["id"];
$valid_session = false;

if ($session_id > 0) {
    $sql = "SELECT id FROM chat_sessions WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $session_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) == 1) {
                $valid_session = true;
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if (!$valid_session) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid session"]);
    exit;
}

// Configure context for the AI response
$ai_context = "You are a therapeutic AI assistant called 'Healing Path'. Your purpose is to provide empathetic, supportive responses to users seeking emotional support or guidance. Do not diagnose medical conditions, but focus on emotional support and general wellness advice. Keep responses concise but warm and helpful.";

// Get previous conversation for context
$conversation_history = "";
$sql = "SELECT sender, message FROM messages WHERE session_id = ? ORDER BY timestamp ASC LIMIT 10";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $session_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_array($result)) {
            $conversation_history .= ($row['sender'] == 'user' ? "User: " : "AI: ") . $row['message'] . "\n";
        }
    }
    mysqli_stmt_close($stmt);
}

// Build the full prompt
$full_prompt = $ai_context . "\n\nConversation history:\n" . $conversation_history . "\nUser: " . $message . "\nAI:";

// Process with Gemini API
try {
    $api_key = GEMINI_API_KEY;
    $model = "gemini-1.5-flash";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $post_data = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $full_prompt]
                ]
            ]
        ]
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $response_data = json_decode($response, true);

    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $ai_reply = $response_data['candidates'][0]['content']['parts'][0]['text'];

        // Save AI response to database
        $sql = "INSERT INTO messages (session_id, sender, message) VALUES (?, 'ai', ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "is", $session_id, $ai_reply);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        echo json_encode(["reply" => $ai_reply]);
    } else {
        throw new Exception("Invalid API response format");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "AI response failed", "details" => $e->getMessage()]);
    exit;
}
?>
