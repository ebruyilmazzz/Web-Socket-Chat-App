<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Uygulaması</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
            height: 100vh;
            overflow: hidden;
        }
        .chat-container {
            height: 100vh;
            display: flex;
        }
        .users-sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        .user-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            background: #f8f9fa;
        }
        .users-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .user-item {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .user-item:hover {
            background-color: #f0f2f5;
        }
        .user-item.active {
            background-color: #e7f3ff;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0084ff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: bold;
        }
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            background: #f8f9fa;
            display: flex;
            align-items: center;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fff;
        }
        .message {
            margin-bottom: 10px;
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 20px;
            position: relative;
        }
        .message.sent {
            background-color: #0084ff;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .message.received {
            background-color: #e4e6eb;
            color: black;
            border-bottom-left-radius: 5px;
        }
        .chat-input {
            padding: 20px;
            border-top: 1px solid #ddd;
            background: #f8f9fa;
        }
        .input-group {
            background: white;
            border-radius: 30px;
            padding: 5px;
        }
        .form-control {
            border: none;
            border-radius: 30px;
            padding-left: 15px;
        }
        .form-control:focus {
            box-shadow: none;
        }
        .btn-send {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #userIcon 
        {
            font-size: 32px;
            color: #555;
        }

    </style>
</head>
<body>
    <div class="chat-container">
        <div class="users-sidebar">
            <div class="user-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sohbetler</h5>
                    <div class="dropdown">
                        <button class="btn btn-light" type="button" id="userMenu" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="users-list" id="usersList">
                <!-- Kullanıcılar buraya gelecek -->
            </div>
        </div>
        
        <div class="chat-area">
            <div class="chat-header" id="chatHeader">
                <div class="user-avatar"><i class="fa fa-user-circle" id="userIcon"></i></div>
                <h5 class="mb-0 ms-2">Sohbet başlatmak için kullanıcı seçin</h5>
            </div>
            <div class="chat-messages" id="chatMessages">
                <!-- Mesajlar buraya gelecek -->
            </div>
            <div class="chat-input">
                <div class="input-group">
                    <input type="text" id="messageInput" class="form-control" placeholder="Mesajınızı yazın...">
                    <button class="btn btn-primary btn-send" type="button" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;
        const currentUsername = "<?php echo $_SESSION['username']; ?>";
        let ws;
        let selectedUser = null;

        function connect() {
            ws = new WebSocket('ws://localhost:8080');

            ws.onopen = () => {
                console.log('Bağlantı kuruldu');
                // Kullanıcı bilgilerini gönder
                ws.send(JSON.stringify({
                    type: 'auth',
                    userId: currentUserId,
                    username: currentUsername
                }));
            };

            ws.onmessage = (e) => {
                const data = JSON.parse(e.data);
                
                switch(data.type) {
                    case 'users':
                        updateUsersList(data.users);
                        break;
                    case 'message':
                        if ((data.senderId === currentUserId && data.receiverId === selectedUser?.id) ||
                            (data.senderId === selectedUser?.id && data.receiverId === currentUserId)) {
                            displayMessage(data);
                        }
                        break;
                }
            };

            ws.onclose = () => {
                console.log('Bağlantı kapandı');
                setTimeout(connect, 1000);
            };
        }

        function updateUsersList(users) {
            const usersList = document.getElementById('usersList');
            usersList.innerHTML = '';
            
            users.forEach(user => {
                if (user.id != currentUserId) {
                    const div = document.createElement('div');
                    div.className = `user-item ${selectedUser?.id === user.id ? 'active' : ''}`;
                    div.innerHTML = `
                        <div class="user-avatar">${user.username.charAt(0).toUpperCase()}</div>
                        <div class="user-info">
                            <h6 class="mb-0">${user.username}</h6>
                        </div>
                    `;
                    div.onclick = () => selectUser(user);
                    usersList.appendChild(div);
                }
            });
        }

        function selectUser(user) {
            selectedUser = user;
            document.querySelectorAll('.user-item').forEach(item => item.classList.remove('active'));
            document.querySelector(`[onclick="selectUser(${JSON.stringify(user)})"]`).classList.add('active');
            
            const chatHeader = document.getElementById('chatHeader');
            chatHeader.innerHTML = `
                <div class="user-avatar">${user.username.charAt(0).toUpperCase()}</div>
                <h5 class="mb-0 ms-2">${user.username}</h5>
            `;
            
            document.getElementById('chatMessages').innerHTML = '';
            loadMessages(user.id);
        }

        function loadMessages(userId) {
            fetch(`get_messages.php?user_id=${userId}`)
                .then(response => response.json())
                .then(messages => {
                    messages.forEach(message => {
                        displayMessage({
                            senderId: message.sender_id,
                            message: message.message,
                            timestamp: message.created_at
                        });
                    });
                });
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (message && selectedUser) {
                ws.send(JSON.stringify({
                    type: 'message',
                    receiverId: selectedUser.id,
                    message: message
                }));
                
                input.value = '';
            }
        }

        function displayMessage(data) {
            const messages = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = `message ${data.senderId === currentUserId ? 'sent' : 'received'}`;
            div.textContent = data.message;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }

        document.getElementById('messageInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        connect();
    </script>
</body>
</html>
