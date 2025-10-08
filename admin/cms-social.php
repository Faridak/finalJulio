<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$postId = intval($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $platform = trim($_POST['platform'] ?? 'facebook');
        $status = trim($_POST['status'] ?? 'draft');
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        $imageId = intval($_POST['image_id'] ?? 0);
        
        // Validate required fields
        if (empty($content)) {
            $error = 'Post content is required.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO social_posts 
                        (title, content, platform, status, scheduled_at, image_id, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title, $content, $platform, $status, $scheduledAt ?: null, 
                        $imageId ?: null, $_SESSION['user_id']
                    ]);
                    $success = 'Social post created successfully!';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE social_posts 
                        SET title = ?, content = ?, platform = ?, status = ?, 
                            scheduled_at = ?, image_id = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $content, $platform, $status, $scheduledAt ?: null, 
                        $imageId ?: null, $postId
                    ]);
                    $success = 'Social post updated successfully!';
                }
            } catch (Exception $e) {
                $error = 'Error saving social post: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM social_posts WHERE id = ?");
            $stmt->execute([$postId]);
            $success = 'Social post deleted successfully!';
        } catch (Exception $e) {
            $error = 'Error deleting social post: ' . $e->getMessage();
        }
    } elseif ($action === 'publish') {
        try {
            $stmt = $pdo->prepare("
                UPDATE social_posts 
                SET status = 'published', published_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$postId]);
            $success = 'Social post published successfully!';
        } catch (Exception $e) {
            $error = 'Error publishing social post: ' . $e->getMessage();
        }
    }
}

// Get post for edit
$post = null;
if ($action === 'edit' && $postId) {
    $stmt = $pdo->prepare("SELECT * FROM social_posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        $error = 'Social post not found.';
        $action = 'list';
    }
}

// Get all posts for listing
$posts = [];
if ($action === 'list') {
    $stmt = $pdo->query("
        SELECT sp.*, u.email as author_email, ia.filename as image_filename
        FROM social_posts sp
        LEFT JOIN users u ON sp.created_by = u.id
        LEFT JOIN image_assets ia ON sp.image_id = ia.id
        ORDER BY sp.created_at DESC
        LIMIT 50
    ");
    $posts = $stmt->fetchAll();
}

// Get images for dropdown
$stmt = $pdo->query("SELECT id, filename, title FROM image_assets WHERE is_active = 1 ORDER BY created_at DESC");
$images = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php if ($action === 'add'): ?>
                        Create Social Post
                    <?php elseif ($action === 'edit'): ?>
                        Edit Social Post
                    <?php else: ?>
                        Social Media Management
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 mt-2">
                    <?php if ($action === 'add'): ?>
                        Create a new social media post
                    <?php elseif ($action === 'edit'): ?>
                        Update social media post details
                    <?php else: ?>
                        Manage all social media posts
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex space-x-3">
                <?php if ($action === 'list'): ?>
                    <a href="cms-social.php?action=add" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Create Post
                    </a>
                <?php endif; ?>
                <a href="cms-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to CMS
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Posts List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Social Media Posts</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($posts)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        No social posts found. <a href="cms-social.php?action=add" class="text-blue-600 hover:text-blue-800">Create your first post</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($posts as $p): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($p['title'] ?? substr($p['content'], 0, 50) . '...') ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars(substr($p['content'], 0, 100)) ?>...
                                            </div>
                                            <div class="text-xs text-gray-400 mt-1">
                                                By <?= htmlspecialchars($p['author_email'] ?? 'Unknown') ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $platformIcons = [
                                                'facebook' => 'fab fa-facebook text-blue-600',
                                                'twitter' => 'fab fa-twitter text-blue-400',
                                                'instagram' => 'fab fa-instagram text-pink-500',
                                                'linkedin' => 'fab fa-linkedin text-blue-700'
                                            ];
                                            $iconClass = $platformIcons[$p['platform']] ?? 'fas fa-share-alt';
                                            ?>
                                            <div class="flex items-center">
                                                <i class="<?= $iconClass ?> mr-2"></i>
                                                <span class="capitalize"><?= htmlspecialchars($p['platform']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $statusClasses = [
                                                'draft' => 'bg-gray-100 text-gray-800',
                                                'scheduled' => 'bg-yellow-100 text-yellow-800',
                                                'published' => 'bg-green-100 text-green-800',
                                                'failed' => 'bg-red-100 text-red-800'
                                            ];
                                            $statusClass = $statusClasses[$p['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                                <?= ucfirst($p['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?php if ($p['scheduled_at']): ?>
                                                <?= date('M j, Y g:i A', strtotime($p['scheduled_at'])) ?>
                                            <?php elseif ($p['published_at']): ?>
                                                <?= date('M j, Y g:i A', strtotime($p['published_at'])) ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Not scheduled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm font-medium">
                                            <?php if ($p['status'] === 'draft'): ?>
                                                <a href="cms-social.php?action=publish&id=<?= $p['id'] ?>" 
                                                   onclick="return confirm('Publish this post now?')" 
                                                   class="text-green-600 hover:text-green-900 mr-3">
                                                    <i class="fas fa-paper-plane"></i> Publish
                                                </a>
                                            <?php endif; ?>
                                            <a href="cms-social.php?action=edit&id=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="cms-social.php?action=delete&id=<?= $p['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this post?')" 
                                               class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Add/Edit Form -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <?php if ($action === 'add'): ?>
                            Create Social Post
                        <?php else: ?>
                            Edit Social Post
                        <?php endif; ?>
                    </h2>
                </div>
                <form method="POST" class="p-6">
                    <?= Security::getCSRFInput() ?>
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($post['title'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                            <textarea name="content" rows="4" maxlength="280"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
                            <p class="text-sm text-gray-500 mt-1">Max 280 characters</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                            <select name="platform" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="facebook" <?= (isset($post['platform']) && $post['platform'] === 'facebook') ? 'selected' : '' ?>>Facebook</option>
                                <option value="twitter" <?= (isset($post['platform']) && $post['platform'] === 'twitter') ? 'selected' : '' ?>>Twitter</option>
                                <option value="instagram" <?= (isset($post['platform']) && $post['platform'] === 'instagram') ? 'selected' : '' ?>>Instagram</option>
                                <option value="linkedin" <?= (isset($post['platform']) && $post['platform'] === 'linkedin') ? 'selected' : '' ?>>LinkedIn</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="draft" <?= (isset($post['status']) && $post['status'] === 'draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="scheduled" <?= (isset($post['status']) && $post['status'] === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                                <option value="published" <?= (isset($post['status']) && $post['status'] === 'published') ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled At</label>
                            <input type="datetime-local" name="scheduled_at" value="<?= $post['scheduled_at'] ?? '' ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                            <select name="image_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select an image</option>
                                <?php foreach ($images as $image): ?>
                                    <option value="<?= $image['id'] ?>" <?= (isset($post['image_id']) && $post['image_id'] == $image['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($image['title'] ?? $image['filename']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="cms-social.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <?php if ($action === 'add'): ?>
                                Create Post
                            <?php else: ?>
                                Update Post
                            <?php endif; ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 VentDepot Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>