<?php

class BlogPost {
    private $pdo;
    private $table = 'blog_posts';
    private $db_name = 'db_users';
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';

    public function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(["success" => false, "message" => "Database connection failed."]));
        }
    }

    public function createPost($title, $content) {
        $sql = "INSERT INTO {$this->table} (title, content, created_at) VALUES (?, ?, NOW())";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$title, $content]);
            return true;
        } catch (PDOException $e) {
            error_log("Create post failed: " . $e->getMessage());
            return false;
        }
    }

    public function getPosts() {
        $sql = "SELECT id, title, content, created_at FROM {$this->table} ORDER BY created_at DESC";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get posts failed: " . $e->getMessage());
            return [];
        }
    }

    public function updatePost($id, $title, $content) {
        $sql = "UPDATE {$this->table} SET title = ?, content = ? WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$title, $content, $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Update post failed: " . $e->getMessage());
            return false;
        }
    }

    public function deletePost($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Delete post failed: " . $e->getMessage());
            return false;
        }
    }
}

?>