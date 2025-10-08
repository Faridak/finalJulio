<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$applicationId = intval($_GET['id'] ?? 0);

if (!$applicationId) {
    header('Location: merchants.php');
    exit;
}

// Get merchant application details
$applicationQuery = "
    SELECT ma.*, u.email as reviewer_email
    FROM merchant_applications ma
    LEFT JOIN users u ON ma.reviewed_by = u.id
    WHERE ma.id = ?
";
$stmt = $pdo->prepare($applicationQuery);
$stmt->execute([$applicationId]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: merchants.php');
    exit;
}

// Get associated user account if exists
$userAccount = null;
if ($application['status'] === 'approved') {
    $userQuery = "SELECT * FROM users WHERE email = ?";
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute([$application['contact_email']]);
    $userAccount = $stmt->fetch();
}

// Get application documents if any
$documentsQuery = "SELECT * FROM merchant_application_documents WHERE application_id = ?";
$stmt = $pdo->prepare($documentsQuery);
$stmt->execute([$applicationId]);
$documents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Application Details - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Merchant Application Details</h1>
                <p class="text-gray-600 mt-2"><?= htmlspecialchars($application['business_name']) ?></p>
            </div>
            <button onclick="window.close()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>

        <!-- Application Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Application Status</h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2
                        <?php
                        switch($application['status']) {
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'under_review': echo 'bg-blue-100 text-blue-800'; break;
                            case 'approved': echo 'bg-green-100 text-green-800'; break;
                            case 'rejected': echo 'bg-red-100 text-red-800'; break;
                            case 'requires_info': echo 'bg-purple-100 text-purple-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?= ucwords(str_replace('_', ' ', $application['status'])) ?>
                    </span>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">Applied on</div>
                    <div class="text-lg font-semibold text-gray-900"><?= date('M j, Y', strtotime($application['created_at'])) ?></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Business Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Business Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Business Name</label>
                        <p class="text-gray-900 font-medium"><?= htmlspecialchars($application['business_name']) ?></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500">Business Type</label>
                        <p class="text-gray-900"><?= ucwords(str_replace('_', ' ', $application['business_type'])) ?></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500">Business Description</label>
                        <p class="text-gray-900"><?= htmlspecialchars($application['business_description']) ?></p>
                    </div>
                    
                    <?php if ($application['business_address']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Business Address</label>
                        <p class="text-gray-900 whitespace-pre-line"><?= htmlspecialchars($application['business_address']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['website_url']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Website</label>
                        <p class="text-gray-900">
                            <a href="<?= htmlspecialchars($application['website_url']) ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800">
                                <?= htmlspecialchars($application['website_url']) ?>
                                <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['years_in_business']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Years in Business</label>
                        <p class="text-gray-900"><?= $application['years_in_business'] ?> years</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['estimated_monthly_sales']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Estimated Monthly Sales</label>
                        <p class="text-gray-900">$<?= number_format($application['estimated_monthly_sales'], 2) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Contact Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Contact Name</label>
                        <p class="text-gray-900 font-medium"><?= htmlspecialchars($application['contact_name']) ?></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500">Email Address</label>
                        <p class="text-gray-900">
                            <a href="mailto:<?= htmlspecialchars($application['contact_email']) ?>" 
                               class="text-blue-600 hover:text-blue-800">
                                <?= htmlspecialchars($application['contact_email']) ?>
                            </a>
                        </p>
                    </div>
                    
                    <?php if ($application['contact_phone']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Phone Number</label>
                        <p class="text-gray-900">
                            <a href="tel:<?= htmlspecialchars($application['contact_phone']) ?>" 
                               class="text-blue-600 hover:text-blue-800">
                                <?= htmlspecialchars($application['contact_phone']) ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($application['tax_id']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Tax ID / EIN</label>
                        <p class="text-gray-900 font-mono"><?= htmlspecialchars($application['tax_id']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- User Account Status -->
                <?php if ($application['status'] === 'approved'): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">User Account Status</h3>
                        <?php if ($userAccount): ?>
                            <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div>
                                    <div class="text-sm font-medium text-green-800">Account Created</div>
                                    <div class="text-sm text-green-600">User ID: <?= $userAccount['id'] ?></div>
                                </div>
                                <a href="user-details.php?id=<?= $userAccount['id'] ?>" 
                                   class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-user"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <div class="text-sm font-medium text-yellow-800">Account Not Created</div>
                                <div class="text-sm text-yellow-600">User account needs to be created manually</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Review Information -->
        <?php if ($application['reviewed_at']): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Review Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Reviewed By</label>
                        <p class="text-gray-900"><?= htmlspecialchars($application['reviewer_email'] ?? 'System') ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Review Date</label>
                        <p class="text-gray-900"><?= date('M j, Y g:i A', strtotime($application['reviewed_at'])) ?></p>
                    </div>
                    
                    <?php if ($application['review_notes']): ?>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-gray-500">Review Notes</label>
                            <p class="text-gray-900 mt-1 p-3 bg-gray-50 rounded-lg"><?= htmlspecialchars($application['review_notes']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($application['rejection_reason']): ?>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-gray-500">Rejection Reason</label>
                            <p class="text-gray-900 mt-1 p-3 bg-red-50 border border-red-200 rounded-lg"><?= htmlspecialchars($application['rejection_reason']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Documents -->
        <?php if (!empty($documents)): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Uploaded Documents</h2>
                <div class="space-y-3">
                    <?php foreach ($documents as $document): ?>
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-file text-blue-600 mr-3"></i>
                                <div>
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($document['document_name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= ucwords(str_replace('_', ' ', $document['document_type'])) ?></div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500"><?= date('M j, Y', strtotime($document['uploaded_at'])) ?></span>
                                <a href="<?= htmlspecialchars($document['file_path']) ?>" target="_blank" 
                                   class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Actions</h2>
            <div class="flex space-x-4">
                <a href="merchants.php?search=<?= htmlspecialchars($application['business_name']) ?>" target="_blank" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    <i class="fas fa-external-link-alt mr-2"></i>View in Applications
                </a>
                
                <?php if ($application['contact_email']): ?>
                <a href="mailto:<?= htmlspecialchars($application['contact_email']) ?>?subject=Merchant Application - <?= htmlspecialchars($application['business_name']) ?>" 
                   class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-envelope mr-2"></i>Email Applicant
                </a>
                <?php endif; ?>
                
                <?php if ($userAccount): ?>
                <a href="merchant-store.php?id=<?= $userAccount['id'] ?>" 
                   class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                    <i class="fas fa-store mr-2"></i>View Store
                </a>
                
                <a href="merchant-sales.php?id=<?= $userAccount['id'] ?>" 
                   class="bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700">
                    <i class="fas fa-chart-line mr-2"></i>Track Sales
                </a>
                <?php endif; ?>
                
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i>Print Details
                </button>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .shadow-md { box-shadow: none !important; }
        }
    </style>
</body>
</html>
