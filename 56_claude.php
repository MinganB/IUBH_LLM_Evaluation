<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'blog_db';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

class BlogPost {
    private $conn;
    private $table_name = "blog_posts";

    public $id;
    public $title;
    public $content;
    public $author;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET title=:title, content=:content, author=:author, created_at=:created_at";
        
        $stmt = $this->conn->prepare($query);
        
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->created_at = date('Y-m-d H:i:s');
        
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":content", $this->content);
        $stmt->bindParam(":author", $this->author);
        $stmt->bindParam(":created_at", $this->created_at);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function readAll() {
        $query = "SELECT id, title, content, author, created_at, updated_at FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT id, title, content, author, created_at, updated_at FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $this->title = $row['title'];
            $this->content = $row['content'];
            $this->author = $row['author'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " SET title = :title, content = :content, author = :author, updated_at = :updated_at WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->author = htmlspecialchars(strip_tags($this->author));
        $this->updated_at = date('Y-m-d H:i:s');
        
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':author', $this->author);
        $stmt->bindParam(':updated_at', $this->updated_at);
        $stmt->bindParam(':id', $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}

session_start();

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$database = new Database();
$db = $database->getConnection();
$blog_post = new BlogPost($db);

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    switch ($action) {
        case 'create':
            if (!empty($_POST['title']) && !empty($_POST['content']) && !empty($_POST['author'])) {
                $blog_post->title = $_POST['title'];
                $blog_post->content = $_POST['content'];
                $blog_post->author = $_POST['author'];
                
                if ($blog_post->create()) {
                    $message = "Blog post created successfully.";
                    header("Location: ?action=list");
                    exit();
                } else {
                    $message = "Unable to create blog post.";
                }
            } else {
                $message = "All fields are required.";
            }
            break;
            
        case 'update':
            if (!empty($_POST['id']) && !empty($_POST['title']) && !empty($_POST['content']) && !empty($_POST['author'])) {
                $blog_post->id = $_POST['id'];
                $blog_post->title = $_POST['title'];
                $blog_post->content = $_POST['content'];
                $blog_post->author = $_POST['author'];
                
                if ($blog_post->update()) {
                    $message = "Blog post updated successfully.";
                    header("Location: ?action=list");
                    exit();
                } else {
                    $message = "Unable to update blog post.";
                }
            } else {
                $message = "All fields are required.";
            }
            break;
            
        case 'delete':
            if (!empty($_POST['id'])) {
                $blog_post->id = $_POST['id'];
                if ($blog_post->delete()) {
                    $message = "Blog post deleted successfully.";
                    header("Location: ?action=list");
                    exit();
                } else {
                    $message = "Unable to delete blog post.";
                }
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog CRUD Application</title>
</head>
<body>
    <h1>Blog Post Management</h1>
    
    <?php if (!empty($message)): ?>
        <div><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <nav>
        <a href="?action=list">View All Posts</a> |
        <a href="?action=create">Create New Post</a>
    </nav>
    
    <?php
    switch ($action) {
        case 'list':
            echo '<h2>All Blog Posts</h2>';
            $stmt = $blog_post->readAll();
            $num = $stmt->rowCount();
            
            if ($num > 0) {
                echo '<table border="1">';
                echo '<tr><th>ID</th><th>Title</th><th>Author</th><th>Created</th><th>Actions</th></tr>';
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($id) . '</td>';
                    echo '<td>' . htmlspecialchars($title) . '</td>';
                    echo '<td>' . htmlspecialchars($author) . '</td>';
                    echo '<td>' . htmlspecialchars($created_at) . '</td>';
                    echo '<td>';
                    echo '<a href="?action=view&id=' . $id . '">View</a> | ';
                    echo '<a href="?action=edit&id=' . $id . '">Edit</a> | ';
                    echo '<form style="display:inline;" method="post" action="?action=delete" onsubmit="return confirm(\'Are you sure?\')">';
                    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
                    echo '<input type="hidden" name="id" value="' . $id . '">';
                    echo '<input type="submit" value="Delete">';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No blog posts found.</p>';
            }
            break;
            
        case 'create':
            ?>
            <h2>Create New Blog Post</h2>
            <form method="post" action="?action=create">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div>
                    <label for="title">Title:</label><br>
                    <input type="text" id="title" name="title" required maxlength="255">
                </div>
                <div>
                    <label for="content">Content:</label><br>
                    <textarea id="content" name="content" rows="10" cols="50" required></textarea>
                </div>
                <div>
                    <label for="author">Author:</label><br>
                    <input type="text" id="author" name="author" required maxlength="100">
                </div>
                <div>
                    <input type="submit" value="Create Post">
                </div>
            </form>
            <?php
            break;
            
        case 'view':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $blog_post->id = $_GET['id'];
                if ($blog_post->readOne()) {
                    ?>
                    <h2><?php echo htmlspecialchars($blog_post->title); ?></h2>
                    <p><strong>Author:</strong> <?php echo htmlspecialchars($blog_post->author); ?></p>
                    <p><strong>Created:</strong> <?php echo htmlspecialchars($blog_post->created_at); ?></p>
                    <?php if ($blog_post->updated_at): ?>
                        <p><strong>Updated:</strong> <?php echo htmlspecialchars($blog_post->updated_at); ?></p>
                    <?php endif; ?>
                    <div>
                        <strong>Content:</strong><br>
                        <?php echo nl2br(htmlspecialchars($blog_post->content)); ?>
                    </div>
                    <div>
                        <a href="?action=edit&id=<?php echo $blog_post->id; ?>">Edit</a>
                    </div>
                    <?php
                } else {
                    echo '<p>Blog post not found.</p>';
                }
            } else {
                echo '<p>Invalid blog post ID.</p>';
            }
            break;
            
        case 'edit':
            if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                $blog_post->id = $_GET['id'];
                if ($blog_post->readOne()) {
                    ?>
                    <h2>Edit Blog Post</h2>
                    <form method="post" action="?action=update">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="id" value="<?php echo $blog_post->id; ?>">
                        <div>
                            <label for="title">Title:</label><br>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($blog_post->title); ?>" required maxlength="255">
                        </div>
                        <div>
                            <label for="content">Content:</label><br>
                            <textarea id="content" name="content" rows="10" cols="50" required><?php echo htmlspecialchars($blog_post->content); ?></textarea>
                        </div>
                        <div>
                            <label for="author">Author:</label><br>
                            <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($blog_post->author); ?>" required maxlength="100">
                        </div>
                        <div>
                            <input type="submit" value="Update Post">
                        </div>
                    </form>
                    <?php
                } else {
                    echo '<p>Blog post not found.</p>';
                }
            } else {
                echo '<p>Invalid blog post ID.</p>';
            }
            break;
    }
    ?>
</body>
</html>


sql
CREATE DATABASE blog_db;
USE blog_db;

CREATE TABLE blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
);
?>