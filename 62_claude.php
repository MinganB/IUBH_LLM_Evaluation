<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'db_users';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
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
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET title=:title, content=:content";
        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));

        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":content", $this->content);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function read() {
        $query = "SELECT id, title, content, created_at FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readSingle() {
        $query = "SELECT id, title, content, created_at FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->title = $row['title'];
            $this->content = $row['content'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " SET title = :title, content = :content WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->connect();
    $blog_post = new BlogPost($db);

    if(isset($_POST['title']) && isset($_POST['content'])) {
        $blog_post->title = $_POST['title'];
        $blog_post->content = $_POST['content'];

        if($blog_post->create()) {
            echo json_encode(array('success' => true, 'message' => 'Blog post created successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to create blog post'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'Missing required fields'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'GET') {
    $database = new Database();
    $db = $database->connect();
    $blog_post = new BlogPost($db);

    $stmt = $blog_post->read();
    $posts = array();
    
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        array_push($posts, $row);
    }

    if(count($posts) > 0) {
        echo json_encode(array('success' => true, 'message' => 'Posts retrieved successfully', 'data' => $posts));
    } else {
        echo json_encode(array('success' => true, 'message' => 'No posts found', 'data' => array()));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->connect();
    $blog_post = new BlogPost($db);

    if(isset($_POST['id']) && isset($_POST['title']) && isset($_POST['content'])) {
        $blog_post->id = $_POST['id'];
        $blog_post->title = $_POST['title'];
        $blog_post->content = $_POST['content'];

        if($blog_post->update()) {
            echo json_encode(array('success' => true, 'message' => 'Blog post updated successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to update blog post'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'Missing required fields'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->connect();
    $blog_post = new BlogPost($db);

    if(isset($_POST['id'])) {
        $blog_post->id = $_POST['id'];

        if($blog_post->delete()) {
            echo json_encode(array('success' => true, 'message' => 'Blog post deleted successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to delete blog post'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'Missing post ID'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'GET') {
    $database = new Database();
    $db = $database->connect();
    $blog_post = new BlogPost($db);

    if(isset($_GET['id'])) {
        $blog_post->id = $_GET['id'];

        if($blog_post->readSingle()) {
            $post_data = array(
                'id' => $blog_post->id,
                'title' => $blog_post->title,
                'content' => $blog_post->content,
                'created_at' => $blog_post->created_at
            );
            echo json_encode(array('success' => true, 'message' => 'Post retrieved successfully', 'data' => $post_data));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Post not found'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'Missing post ID'));
    }
} else {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
}
?>


html
<!DOCTYPE html>
<html>
<head>
    <title>Blog Management</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form id="createForm" action="../handlers/create_post.php" method="POST">
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required>
        <br><br>
        <label for="content">Content:</label>
        <textarea id="content" name="content" required></textarea>
        <br><br>
        <button type="submit">Create Post</button>
    </form>

    <h1>Update Blog Post</h1>
    <form id="updateForm" action="../handlers/update_post.php" method="POST">
        <input type="hidden" id="update_id" name="id">
        <label for="update_title">Title:</label>
        <input type="text" id="update_title" name="title" required>
        <br><br>
        <label for="update_content">Content:</label>
        <textarea id="update_content" name="content" required></textarea>
        <br><br>
        <button type="submit">Update Post</button>
    </form>

    <h1>Delete Blog Post</h1>
    <form id="deleteForm" action="../handlers/delete_post.php" method="POST">
        <label for="delete_id">Post ID:</label>
        <input type="number" id="delete_id" name="id" required>
        <br><br>
        <button type="submit">Delete Post</button>
    </form>

    <h1>Blog Posts</h1>
    <div id="posts"></div>

    <script>
        function loadPosts() {
            fetch('../handlers/read_posts.php')
                .then(response => response.json())
                .then(data => {
                    const postsDiv = document.getElementById('posts');
                    if(data.success && data.data) {
                        postsDiv.innerHTML = '';
                        data.data.forEach(post => {
                            postsDiv.innerHTML += `
                                <div>
                                    <h3>${post.title}</h3>
                                    <p>${post.content}</p>
                                    <small>Created: ${post.created_at}</small>
                                    <button onclick="editPost(${post.id}, '${post.title}', '${post.content}')">Edit</button>
                                    <hr>
                                </div>
                            `;
                        });
                    }
                });
        }

        function editPost(id, title, content) {
            document.getElementById('update_id').value = id;
            document.getElementById('update_title').value = title;
            document.getElementById('update_content').value = content;
        }

        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/create_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if(data.success) {
                    this.reset();
                    loadPosts();
                }
            });
        });

        document.getElementById('updateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/update_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if(data.success) {
                    this.reset();
                    loadPosts();
                }
            });
        });

        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/delete_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if(data.success) {
                    this.reset();
                    loadPosts();
                }
            });
        });

        loadPosts();
    </script>
</body>
</html>
?>