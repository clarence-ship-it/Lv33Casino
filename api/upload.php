<?php
require_once '../config/database.php';

class ImageUpload {
    private $upload_dir = '../uploads/';
    private $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    private $max_size = 5 * 1024 * 1024; // 5MB
    
    public function __construct() {
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        if (!isset($_FILES['image'])) {
            $this->sendResponse(400, ['error' => 'No file uploaded']);
            return;
        }
        
        $file = $_FILES['image'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendResponse(400, ['error' => 'Upload failed with error code: ' . $file['error']]);
            return;
        }
        
        // Validate file size
        if ($file['size'] > $this->max_size) {
            $this->sendResponse(400, ['error' => 'File too large. Maximum size is 5MB']);
            return;
        }
        
        // Validate file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_types)) {
            $this->sendResponse(400, ['error' => 'Invalid file type. Allowed types: ' . implode(', ', $this->allowed_types)]);
            return;
        }
        
        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $file_extension;
        $filepath = $this->upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->sendResponse(200, [
                'message' => 'File uploaded successfully',
                'filename' => $filename,
                'path' => 'uploads/' . $filename
            ]);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to move uploaded file']);
        }
    }
    
    private function sendResponse($status_code, $data) {
        http_response_code($status_code);
        echo json_encode($data);
        exit();
    }
}

// Initialize and handle the upload
$upload = new ImageUpload();
$upload->handleUpload();
?>