<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

class NewsAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch($method) {
            case 'GET':
                if (isset($_GET['action'])) {
                    switch($_GET['action']) {
                        case 'all':
                            $this->getAllNews();
                            break;
                        case 'stats':
                            $this->getStats();
                            break;
                        case 'recent':
                            $this->getRecentActivity();
                            break;
                        default:
                            $this->getAllNews();
                    }
                } else {
                    $this->getAllNews();
                }
                break;
                
            case 'POST':
                $this->createNews();
                break;
                
            case 'PUT':
                $this->updateNews();
                break;
                
            case 'DELETE':
                $this->deleteNews();
                break;
                
            default:
                $this->sendResponse(405, ['error' => 'Method not allowed']);
        }
    }
    
    private function getAllNews() {
        try {
            $query = "SELECT * FROM news_articles WHERE status = 'active' ORDER BY news_datetime DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $news = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $news[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'link' => $row['article_link'],
                    'datetime' => $row['news_datetime'],
                    'image' => $row['image_path'],
                    'createdAt' => $row['created_at']
                ];
            }
            
            $this->sendResponse(200, $news);
        } catch(PDOException $e) {
            $this->sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function getStats() {
        try {
            // Get total news count
            $query = "SELECT COUNT(*) as total FROM news_articles WHERE status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get today's news count
            $query = "SELECT COUNT(*) as today FROM news_articles WHERE DATE(created_at) = CURDATE() AND status = 'active'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $today = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
            
            $this->sendResponse(200, [
                'total' => (int)$total,
                'today' => (int)$today,
                'status' => 'active'
            ]);
        } catch(PDOException $e) {
            $this->sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function getRecentActivity() {
        try {
            $query = "SELECT title, created_at FROM news_articles WHERE status = 'active' ORDER BY created_at DESC LIMIT 3";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $activities = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $activities[] = [
                    'title' => $row['title'],
                    'createdAt' => $row['created_at']
                ];
            }
            
            $this->sendResponse(200, $activities);
        } catch(PDOException $e) {
            $this->sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function createNews() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['title']) || !isset($input['description']) || !isset($input['link']) || !isset($input['datetime'])) {
                $this->sendResponse(400, ['error' => 'Missing required fields']);
                return;
            }
            
            $query = "INSERT INTO news_articles (title, description, article_link, news_datetime, image_path, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $this->conn->prepare($query);
            
            $result = $stmt->execute([
                $input['title'],
                $input['description'],
                $input['link'],
                $input['datetime'],
                $input['image'] ?? null
            ]);
            
            if ($result) {
                $this->sendResponse(201, [
                    'message' => 'News article created successfully',
                    'id' => $this->conn->lastInsertId()
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to create news article']);
            }
        } catch(PDOException $e) {
            $this->sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function updateNews() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                $this->sendResponse(400, ['error' => 'Missing news ID']);
                return;
            }
            
            // Check if the news item exists
            $checkQuery = "SELECT id FROM news_articles WHERE id = ? AND status = 'active'";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$input['id']]);
            
            if ($checkStmt->rowCount() == 0) {
                $this->sendResponse(404, ['error' => 'News article not found']);
                return;
            }
            
            $query = "UPDATE news_articles SET title = ?, description = ?, article_link = ?, news_datetime = ?, image_path = ?, updated_at = NOW() WHERE id = ? AND status = 'active'";
            $stmt = $this->conn->prepare($query);
            
            $result = $stmt->execute([
                $input['title'],
                $input['description'],
                $input['link'],
                $input['datetime'],
                $input['image'] ?? null,
                $input['id']
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->sendResponse(200, ['message' => 'News article updated successfully']);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to update news article']);
            }
        } catch(PDOException $e) {
            $this->sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function deleteNews() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                $this->sendResponse(400, ['error' => 'Missing news ID']);
                return;
            }
            
            // Check if the news item exists
            $checkQuery = "SELECT id FROM news_articles WHERE id = ? AND status = 'active'";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$input['id']]);
            
            if ($checkStmt->rowCount() == 0) {
                $this->sendResponse(404, ['error' => 'News article not found']);
                return;
            }
            
            // Soft delete - update status to inactive
            $query = "UPDATE news_articles SET status = 'inactive', updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            $result = $stmt->execute([$input['id']]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->sendResponse(200, ['message' => 'News article deleted successfully']);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to delete news article']);
            }
        } catch(PDOException $e) {
            $this->sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data);
        exit();
    }
}

// Handle any PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Initialize and handle the request
    $api = new NewsAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>