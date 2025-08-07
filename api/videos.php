<?php
// Set headers
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// DB connection
$conn = new mysqli("localhost", "root", "", "news_system"); // ← Change this to your DB

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Parse request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch all videos
        $result = $conn->query("SELECT * FROM videos ORDER BY id DESC");
        $videos = [];
        while ($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }
        echo json_encode($videos);
        break;

    case 'POST':
        // Add a new video
        $data = json_decode(file_get_contents("php://input"), true);
        $title = $conn->real_escape_string($data['title']);
        $description = $conn->real_escape_string($data['description']);
        $category = $conn->real_escape_string($data['category']);
        $duration = $conn->real_escape_string($data['duration']);
        $embed_link = $conn->real_escape_string($data['embed_link']);

        $sql = "INSERT INTO videos (title, description, category, duration, embed_link)
                VALUES ('$title', '$description', '$category', '$duration', '$embed_link')";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to insert video"]);
        }
        break;

    case 'PUT':
        // Update existing video
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int) $data['id'];
        $title = $conn->real_escape_string($data['title']);
        $description = $conn->real_escape_string($data['description']);
        $category = $conn->real_escape_string($data['category']);
        $duration = $conn->real_escape_string($data['duration']);
        $embed_link = $conn->real_escape_string($data['embed_link']);

        $sql = "UPDATE videos 
                SET title='$title', description='$description', category='$category', duration='$duration', embed_link='$embed_link' 
                WHERE id=$id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to update video"]);
        }
        break;

    case 'DELETE':
        // Delete video
        $data = json_decode(file_get_contents("php://input"), true);
        $id = (int) $data['id'];

        $sql = "DELETE FROM videos WHERE id=$id";
        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to delete video"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

$conn->close();
?>
