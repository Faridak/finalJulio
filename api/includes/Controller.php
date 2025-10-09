<?php

class Controller {
    protected $db;

    public function __construct($database) {
        $this->db = $database->connect();
    }

    protected function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function sendErrorResponse($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }

    protected function validateRequiredFields($data, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }
        return true;
    }

    protected function getCurrentUserId() {
        // In a real implementation, you would get this from session or JWT token
        // For now, we'll return a placeholder
        return 1; // Assuming admin user ID is 1
    }

    protected function isAdmin() {
        // In a real implementation, you would check user role
        // For now, we'll assume the user is admin
        return true;
    }
}