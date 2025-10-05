<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'blog_db';
    private $username = 'root';
    private $password = '';
    public $conn;

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
?>


<?php
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
        $query = "INSERT INTO " . $this->table_name . " 
                  SET title=:title, content=:content, author=:author, created_at=:created_at";
        
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

    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readOne() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
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
        $query = "UPDATE " . $this->table_name . " 
                  SET title = :title, content = :content, author = :author, updated_at = :updated_at 
                  WHERE id = :id";

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
?>


<?php
require_once 'database.php';
require_once 'blogpost.php';

$database = new Database();
$db = $database->getConnection();
$blog_post = new BlogPost($db);

if ($_POST) {
    $blog_post->title = $_POST['title'];
    $blog_post->content = $_POST['content'];
    $blog_post->author = $_POST['author'];
    
    if($blog_post->create()) {
        echo "<div>Post was created successfully.</div>";
    } else {
        echo "<div>Unable to create post.</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Blog Post</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <table>
            <tr>
                <td>Title</td>
                <td><input type="text" name="title" required /></td>
            </tr>
            <tr>
                <td>Content</td>
                <td><textarea name="content" required></textarea></td>
            </tr>
            <tr>
                <td>Author</td>
                <td><input type="text" name="author" required /></td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input type="submit" value="Create Post" />
                    <a href="read_posts.php">View All Posts</a>
                </td>
            </tr>
        </table>
    </form>
</body>
</html>


<?php
require_once 'database.php';
require_once 'blogpost.php';

$database = new Database();
$db = $database->getConnection();
$blog_post = new BlogPost($db);

$stmt = $blog_post->read();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <a href="create_post.php">Create New Post</a>
    
    <?php
    $num = $stmt->rowCount();
    
    if($num > 0) {
        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>Title</th>";
        echo "<th>Author</th>";
        echo "<th>Created</th>";
        echo "<th>Actions</th>";
        echo "</tr>";
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            extract($row);
            echo "<tr>";
            echo "<td>{$title}</td>";
            echo "<td>{$author}</td>";
            echo "<td>{$created_at}</td>";
            echo "<td>";
            echo "<a href='view_post.php?id={$id}'>View</a> | ";
            echo "<a href='update_post.php?id={$id}'>Edit</a> | ";
            echo "<a href='delete_post.php?id={$id}' onclick='return confirm(\"Are you sure?\")'>Delete</a>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<div>No posts found.</div>";
    }
    ?>
</body>
</html>


<?php
require_once 'database.php';
require_once 'blogpost.php';

$database = new Database();
$db = $database->getConnection();
$blog_post = new BlogPost($db);

$blog_post->id = isset($_GET['id']) ? $_GET['id'] : die('ERROR: missing ID.');

if($blog_post->readOne()) {
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Blog Post</title>
</head>
<body>
    <h1><?php echo htmlspecialchars($blog_post->title, ENT_QUOTES); ?></h1>
    
    <p><strong>Author:</strong> <?php echo htmlspecialchars($blog_post->author, ENT_QUOTES); ?></p>
    <p><strong>Created:</strong> <?php echo $blog_post->created_at; ?></p>
    <?php if($blog_post->updated_at): ?>
    <p><strong>Updated:</strong> <?php echo $blog_post->updated_at; ?></p>
    <?php endif; ?>
    
    <div>
        <?php echo nl2br(htmlspecialchars($blog_post->content, ENT_QUOTES)); ?>
    </div>
    
    <p>
        <a href="read_posts.php">Back to All Posts</a> |
        <a href="update_post.php?id=<?php echo $blog_post->id; ?>">Edit</a> |
        <a href="delete_post.php?id=<?php echo $blog_post->id; ?>" onclick="return confirm('Are you sure?')">Delete</a>
    </p>
</body>
</html>

<?php
} else {
    echo "Post not found.";
}
?>


<?php
require_once 'database.php';
require_once 'blogpost.php';

$database = new Database();
$db = $database->getConnection();
$blog_post = new BlogPost($db);

$blog_post->id = isset($_GET['id']) ? $_GET['id'] : die('ERROR: missing ID.');

if ($_POST) {
    $blog_post->title = $_POST['title'];
    $blog_post->content = $_POST['content'];
    $blog_post->author = $_POST['author'];
    
    if($blog_post->update()) {
        echo "<div>Post was updated successfully.</div>";
    } else {
        echo "<div>Unable to update post.</div>";
    }
}

$blog_post->readOne();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Blog Post</title>
</head>
<body>
    <h1>Update Blog Post</h1>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $blog_post->id); ?>" method="post">
        <table>
            <tr>
                <td>Title</td>
                <td><input type="text" name="title" value="<?php echo htmlspecialchars($blog_post->title, ENT_QUOTES); ?>" required /></td>
            </tr>
            <tr>
                <td>Content</td>
                <td><textarea name="content" required><?php echo htmlspecialchars($blog_post->content, ENT_QUOTES); ?></textarea></td>
            </tr>
            <tr>
                <td>Author</td>
                <td><input type="text" name="author" value="<?php echo htmlspecialchars($blog_post->author, ENT_QUOTES); ?>" required /></td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input type="submit" value="Update Post" />
                    <a href="read_posts.php">Back to All Posts</a>
                </td>
            </tr>
        </table>
    </form>
</body>
</html>


<?php
require_once 'database.php';
require_once 'blogpost.php';

$database = new Database();
$db = $database->getConnection();
$blog_post = new BlogPost($db);

$blog_post->id = isset($_GET['id']) ? $_GET['id'] : die('ERROR: missing ID.');

if($blog_post->delete()) {
    header('Location: read_posts.php');
} else {
    echo "Unable to delete post.";
}
?>


sql
CREATE DATABASE IF NOT EXISTS blog_db;
USE blog_db;

CREATE TABLE IF NOT EXISTS blog_posts (
    id int(11) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    content text NOT NULL,
    author varchar(100) NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id)
);
?>