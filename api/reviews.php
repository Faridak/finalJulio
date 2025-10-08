<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/ProductReviews.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to vote on reviews']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $reviewsSystem = new ProductReviews($pdo);
    
    switch ($action) {
        case 'vote':
            $reviewId = intval($input['review_id'] ?? 0);
            $voteType = $input['vote_type'] ?? '';
            
            // Validate input
            if (!$reviewId || !in_array($voteType, ['helpful', 'unhelpful'])) {
                throw new Exception('Invalid vote data');
            }
            
            // Rate limiting
            if (!Security::checkRateLimit('review_vote', 10, 300)) { // 10 votes per 5 minutes
                throw new Exception('Too many votes. Please wait a moment.');
            }
            
            // Cast vote
            $result = $reviewsSystem->voteOnReview($reviewId, $_SESSION['user_id'], $voteType);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Vote recorded successfully']);
            } else {
                throw new Exception('Failed to record vote');
            }
            break;
            
        case 'submit':
            // Handle review submission via AJAX if needed
            $productId = intval($input['product_id'] ?? 0);
            $rating = intval($input['rating'] ?? 0);
            $title = Security::sanitizeInput($input['title'] ?? '', 'string');
            $reviewText = Security::sanitizeInput($input['review_text'] ?? '', 'string');
            
            // Validate input
            $rules = [
                'rating' => ['required' => true, 'type' => 'integer', 'min_value' => 1, 'max_value' => 5],
                'title' => ['required' => true, 'max_length' => 255],
                'review_text' => ['required' => true, 'max_length' => 2000]
            ];
            
            $data = compact('rating', 'title', 'review_text');
            $errors = Security::validateInput($data, $rules);
            
            if (!empty($errors)) {
                throw new Exception(implode(' ', $errors));
            }
            
            // Check if user can review
            $canReview = $reviewsSystem->canUserReviewProduct($_SESSION['user_id'], $productId);
            
            if (!$canReview['can_review']) {
                throw new Exception(match($canReview['reason']) {
                    'already_reviewed' => 'You have already reviewed this product.',
                    'no_purchase' => 'You can only review products you have purchased.',
                    default => 'Unable to submit review at this time.'
                });
            }
            
            // Rate limiting
            if (!Security::checkRateLimit('review_submit', 5, 3600)) { // 5 reviews per hour
                throw new Exception('Too many review submissions. Please wait.');
            }
            
            // Add review
            $reviewId = $reviewsSystem->addReview(
                $productId,
                $_SESSION['user_id'],
                $rating,
                $title,
                $reviewText,
                $canReview['order_id'] ?? null
            );
            
            if ($reviewId) {
                echo json_encode(['success' => true, 'message' => 'Review submitted successfully', 'review_id' => $reviewId]);
            } else {
                throw new Exception('Failed to submit review');
            }
            break;
            
        case 'delete':
            $reviewId = intval($input['review_id'] ?? 0);
            
            if (!$reviewId) {
                throw new Exception('Invalid review ID');
            }
            
            // Rate limiting
            if (!Security::checkRateLimit('review_delete', 3, 3600)) { // 3 deletions per hour
                throw new Exception('Too many deletion attempts. Please wait.');
            }
            
            $result = $reviewsSystem->deleteReview($reviewId, $_SESSION['user_id']);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
            } else {
                throw new Exception('Failed to delete review or review not found');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('review_api_error', [
        'action' => $action,
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? null
    ], 'warning');
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>