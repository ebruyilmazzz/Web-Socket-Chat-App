<?php
class Database {
    private $db;

    public function __construct() {
        try {
            $this->db = new SQLite3('chat.db');
            
           
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS users (
                    id TEXT PRIMARY KEY,
                    username TEXT NOT NULL,
                    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ');
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function saveUser($userId, $username) {
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO users (id, username, last_seen) 
            VALUES (:id, :username, CURRENT_TIMESTAMP)
        ');
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getUser($userId) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }

    public function updateLastSeen($userId) {
        $stmt = $this->db->prepare('
            UPDATE users SET last_seen = CURRENT_TIMESTAMP 
            WHERE id = :id
        ');
        $stmt->bindValue(':id', $userId, SQLITE3_TEXT);
        return $stmt->execute();
    }

    public function getActiveUsers() {
        $stmt = $this->db->prepare('
            SELECT * FROM users 
            WHERE datetime(last_seen) >= datetime("now", "-1 minute")
        ');
        $result = $stmt->execute();
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        return $users;
    }
}
?>
