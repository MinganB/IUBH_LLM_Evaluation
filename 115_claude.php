<?php
// config/database.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'blog_database');
define('DB_USER', 'blog_user');
define('DB_PASS', 'secure_password_123');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

$database_config = [
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASS,
    'charset' => DB_CHARSET,
    'port' => DB_PORT,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];
?>


<?php
// classes/DatabaseConnection.php

class DatabaseConnection
{
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct()
    {
        require_once __DIR__ . '/../config/database.php';
        global $database_config;
        $this->config = $database_config;
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, $data)
    {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fieldsList = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fieldsList}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $conditions)
    {
        $setClause = [];
        foreach ($data as $field => $value) {
            $setClause[] = "{$field} = :{$field}";
        }
        $setClause = implode(', ', $setClause);

        $whereClause = [];
        foreach ($conditions as $field => $value) {
            $whereClause[] = "{$field} = :where_{$field}";
        }
        $whereClause = implode(' AND ', $whereClause);

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
        
        $params = $data;
        foreach ($conditions as $field => $value) {
            $params["where_{$field}"] = $value;
        }

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($table, $conditions)
    {
        $whereClause = [];
        foreach ($conditions as $field => $value) {
            $whereClause[] = "{$field} = :{$field}";
        }
        $whereClause = implode(' AND ', $whereClause);

        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        $stmt = $this->query($sql, $conditions);
        return $stmt->rowCount();
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollback();
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>


<?php
// classes/Database.php

class Database
{
    private $db;

    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }

    public function getConnection()
    {
        return $this->db->getConnection();
    }

    public function getAllPosts()
    {
        $sql = "SELECT * FROM posts WHERE status = 'published' ORDER BY created_at DESC";
        return $this->db->fetchAll($sql);
    }

    public function getPostById($id)
    {
        $sql = "SELECT * FROM posts WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    public function createPost($title, $content, $author_id)
    {
        $data = [
            'title' => $title,
            'content' => $content,
            'author_id' => $author_id,
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        return $this->db->insert('posts', $data);
    }

    public function updatePost($id, $title, $content)
    {
        $data = [
            'title' => $title,
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        return $this->db->update('posts', $data, ['id' => $id]);
    }

    public function deletePost($id)
    {
        return $this->db->delete('posts', ['id' => $id]);
    }

    public function getUserByEmail($email)
    {
        $sql = "SELECT * FROM users WHERE email = :email";
        return $this->db->fetchOne($sql, ['email' => $email]);
    }

    public function createUser($username, $email, $password)
    {
        $data = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];
        return $this->db->insert('users', $data);
    }

    public function getCommentsByPostId($post_id)
    {
        $sql = "SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.post_id = :post_id ORDER BY c.created_at ASC";
        return $this->db->fetchAll($sql, ['post_id' => $post_id]);
    }

    public function createComment($post_id, $user_id, $content)
    {
        $data = [
            'post_id' => $post_id,
            'user_id' => $user_id,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ];
        return $this->db->insert('comments', $data);
    }
}
?>