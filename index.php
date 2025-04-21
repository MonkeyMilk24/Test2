<?php
// index.php - Main application page (requires login)
session_start();
require_once "config.php";

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Get user profile info
$user_id = $_SESSION["id"];
$username = $_SESSION["username"];
$profile_pic = "";
$age = "";

// Get profile information
$sql = "SELECT profile_pic, age FROM users WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_store_result($stmt);
        if(mysqli_stmt_num_rows($stmt) == 1){
            mysqli_stmt_bind_result($stmt, $profile_pic, $age);
            mysqli_stmt_fetch($stmt);
        }
    }
    mysqli_stmt_close($stmt);
}

// Get chat history
$chat_history = [];
$sql = "SELECT id, title, created_at FROM chat_sessions WHERE user_id = ? ORDER BY created_at DESC";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if(mysqli_stmt_execute($stmt)){
        $result = mysqli_stmt_get_result($stmt);
        while($row = mysqli_fetch_array($result)){
            $chat_history[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Healing Path - AI Therapist</title>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <!-- Slide-in Profile Panel -->
  <div class="profile-panel">
    <h2>👤 Your Profile</h2>
    <form action="update_profile.php" method="post" enctype="multipart/form-data">
      <?php if(!empty($profile_pic)): ?>
        <img src="<?php echo htmlspecialchars($profile_pic); ?>" id="profilePic" class="profile-img" alt="Profile Picture" />
      <?php else: ?>
        <img src="images/default-avatar.png" id="profilePic" class="profile-img" alt="Profile Picture" />
      <?php endif; ?>
      
      <input type="file" name="profile_pic" accept="image/*" />
      <input type="text" name="username" placeholder="Your Name" value="<?php echo htmlspecialchars($username); ?>" />
      <input type="number" name="age" placeholder="Your Age" value="<?php echo htmlspecialchars($age); ?>" />
      <button type="submit" style="width:100%; margin-top: 10px;">Update Profile</button>
    </form>
    <a href="logout.php" style="display:block; text-align:center; margin-top:20px; color:#354f52;">Logout</a>
  </div>

  <!-- Sidebar -->
  <div class="sidebar">
    <h2>Sessions</h2>
    <button id="newChatBtn" onclick="startNewChat()">+ New Chat</button>
    <ul id="history">
      <?php foreach($chat_history as $chat): ?>
        <li data-id="<?php echo $chat['id']; ?>" onclick="loadChat(<?php echo $chat['id']; ?>)">
          <?php echo htmlspecialchars($chat['title']); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <!-- Main Chat Window -->
  <div class="main">
    <header>
      <div>
        <h1>Healing Path</h1>
        <p>Welcome, <?php echo htmlspecialchars($username); ?>! A space to reflect and be heard</p>
      </div>
    </header>

    <div id="fadeInGreeting">We are here for you buddy</div>

    <div id="chatbox"></div>

    <form id="messageForm" onsubmit="return false;">
      <input type="text" id="userInput" placeholder="How are you feeling today?" />
      <button type="button" onclick="sendMessage()">Send</button>
    </form>
  </div>

  <script>
    let currentChatId = null;
    
    // Load messages for a specific chat session
    function loadChat(chatId) {
      currentChatId = chatId;
      document.getElementById("chatbox").innerHTML = "";
      
      fetch(`get_messages.php?session_id=${chatId}`)
        .then(response => response.json())
        .then(data => {
          data.forEach(msg => {
            appendMessage(msg.message, msg.sender);
          });
        })
        .catch(error => {
          console.error('Error loading chat:', error);
        });
    }
  
    function appendMessage(text, sender) {
      const div = document.createElement("div");
      div.className = `message ${sender}`;
      div.textContent = text;
      
      const chatbox = document.getElementById("chatbox");
      chatbox.appendChild(div);
      chatbox.scrollTop = chatbox.scrollHeight;
    }
  
    async function sendMessage() {
      const input = document.getElementById("userInput");
      const text = input.value.trim();
      if (!text) return;
      
      // Clear the input
      input.value = "";
      
      // Display user message
      appendMessage(text, "user");
      
      // Start a new chat if none is active
      if (!currentChatId) {
        try {
          const response = await fetch('create_chat.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({ title: text.slice(0, 30) + '...' }),
          });
          
          const data = await response.json();
          currentChatId = data.session_id;
          
          // Add to sidebar
          const li = document.createElement("li");
          li.textContent = data.title;
          li.dataset.id = currentChatId;
          li.onclick = () => loadChat(currentChatId);
          document.getElementById("history").prepend(li);
        } catch (error) {
          console.error('Error creating chat:', error);
        }
      }
      
      // Save user message
      saveMessage(text, 'user');
      
      // Get AI response
      try {
        const response = await fetch("chat_ai.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ 
            message: text,
            session_id: currentChatId
          }),
        });
  
        const data = await response.json();
        appendMessage(data.reply, "ai");
        
        // Save AI message
        saveMessage(data.reply, 'ai');
      } catch (error) {
        console.error("Error fetching AI response:", error);
        appendMessage("Sorry, something went wrong. Please try again later.", "ai");
      }
    }
    
    function saveMessage(text, sender) {
      fetch('save_message.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          session_id: currentChatId,
          message: text,
          sender: sender
        }),
      })
      .catch(error => {
        console.error('Error saving message:', error);
      });
    }
  
    function startNewChat() {
      currentChatId = null;
      document.getElementById("chatbox").innerHTML = "";
      appendMessage("Let's start fresh. What's on your mind?", "ai");
    }
  
    window.onload = () => {
      document.getElementById("fadeInGreeting").style.opacity = "1";
      
      // Start with a welcome message
      appendMessage("Hello! How are you feeling today? I'm here to listen and chat.", "ai");
      
      // Handle form submission on enter
      document.getElementById("userInput").addEventListener("keypress", function(event) {
        if (event.key === "Enter") {
          event.preventDefault();
          sendMessage();
        }
      });
    };
  </script>
</body>
</html>