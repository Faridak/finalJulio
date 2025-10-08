<?php
require_once '../config/database.php';

$success = '';
$error = '';

// Handle merchant application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $businessType = $_POST['business_type'] ?? '';
    $businessDescription = trim($_POST['business_description'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $businessAddress = trim($_POST['business_address'] ?? '');
    $businessCity = trim($_POST['business_city'] ?? '');
    $businessState = trim($_POST['business_state'] ?? '');
    $businessPostalCode = trim($_POST['business_postal_code'] ?? '');
    $businessCountry = $_POST['business_country'] ?? 'USA';
    $taxId = trim($_POST['tax_id'] ?? '');
    $websiteUrl = trim($_POST['website_url'] ?? '');
    $estimatedMonthlySales = floatval($_POST['estimated_monthly_sales'] ?? 0);
    $productCategories = $_POST['product_categories'] ?? [];
    $businessLicenseNumber = trim($_POST['business_license_number'] ?? '');
    $yearsInBusiness = intval($_POST['years_in_business'] ?? 0);
    $previousExperience = trim($_POST['previous_experience'] ?? '');
    $marketingPlan = trim($_POST['marketing_plan'] ?? '');
    $agreementAccepted = isset($_POST['agreement_accepted']);
    
    if ($businessName && $businessType && $contactName && $contactEmail && $agreementAccepted) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO merchant_applications (
                    business_name, business_type, business_description, contact_name, 
                    contact_email, contact_phone, business_address, business_city, 
                    business_state, business_postal_code, business_country, tax_id, 
                    website_url, estimated_monthly_sales, product_categories, 
                    business_license_number, years_in_business, previous_ecommerce_experience, 
                    marketing_plan, agreement_accepted, agreement_accepted_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([
                $businessName, $businessType, $businessDescription, $contactName,
                $contactEmail, $contactPhone, $businessAddress, $businessCity,
                $businessState, $businessPostalCode, $businessCountry, $taxId,
                $websiteUrl, $estimatedMonthlySales, json_encode($productCategories),
                $businessLicenseNumber, $yearsInBusiness, $previousExperience,
                $marketingPlan, $agreementAccepted
            ])) {
                $success = 'Your merchant application has been submitted successfully! We will review it within 2-3 business days and contact you via email.';
            } else {
                $error = 'Failed to submit application. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields and accept the seller agreement.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                </div>
                
                <div class="flex items-center space-x-6">
                    <a href="../index.php" class="text-gray-600 hover:text-blue-600">Home</a>
                    <a href="../seller-guide.php" class="text-gray-600 hover:text-blue-600">Seller Guide</a>
                    <a href="login.php" class="text-gray-600 hover:text-blue-600">Merchant Login</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Become a VentDepot Seller</h1>
            <p class="text-xl text-gray-600">Join thousands of successful merchants and start selling today</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
                <div class="mt-4">
                    <a href="../index.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Return to Home
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <!-- Application Form -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Merchant Application</h2>
                
                <form method="POST" class="space-y-6">
                    <!-- Business Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="business_name" class="block text-sm font-medium text-gray-700 mb-2">Business Name *</label>
                                <input type="text" name="business_name" id="business_name" required
                                       value="<?= htmlspecialchars($_POST['business_name'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="business_type" class="block text-sm font-medium text-gray-700 mb-2">Business Type *</label>
                                <select name="business_type" id="business_type" required
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Business Type</option>
                                    <option value="individual" <?= ($_POST['business_type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option>
                                    <option value="sole_proprietorship" <?= ($_POST['business_type'] ?? '') === 'sole_proprietorship' ? 'selected' : '' ?>>Sole Proprietorship</option>
                                    <option value="partnership" <?= ($_POST['business_type'] ?? '') === 'partnership' ? 'selected' : '' ?>>Partnership</option>
                                    <option value="llc" <?= ($_POST['business_type'] ?? '') === 'llc' ? 'selected' : '' ?>>LLC</option>
                                    <option value="corporation" <?= ($_POST['business_type'] ?? '') === 'corporation' ? 'selected' : '' ?>>Corporation</option>
                                    <option value="other" <?= ($_POST['business_type'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="business_description" class="block text-sm font-medium text-gray-700 mb-2">Business Description</label>
                            <textarea name="business_description" id="business_description" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Describe your business and the products you plan to sell"><?= htmlspecialchars($_POST['business_description'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Contact Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="contact_name" class="block text-sm font-medium text-gray-700 mb-2">Contact Name *</label>
                                <input type="text" name="contact_name" id="contact_name" required
                                       value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <input type="email" name="contact_email" id="contact_email" required
                                       value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="contact_phone" id="contact_phone"
                                       value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="website_url" class="block text-sm font-medium text-gray-700 mb-2">Website URL</label>
                                <input type="url" name="website_url" id="website_url"
                                       value="<?= htmlspecialchars($_POST['website_url'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="https://yourwebsite.com">
                            </div>
                        </div>
                    </div>

                    <!-- Business Address -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Business Address</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="business_address" class="block text-sm font-medium text-gray-700 mb-2">Street Address</label>
                                <input type="text" name="business_address" id="business_address"
                                       value="<?= htmlspecialchars($_POST['business_address'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="business_city" class="block text-sm font-medium text-gray-700 mb-2">City</label>
                                    <input type="text" name="business_city" id="business_city"
                                           value="<?= htmlspecialchars($_POST['business_city'] ?? '') ?>"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="business_state" class="block text-sm font-medium text-gray-700 mb-2">State/Province</label>
                                    <input type="text" name="business_state" id="business_state"
                                           value="<?= htmlspecialchars($_POST['business_state'] ?? '') ?>"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="business_postal_code" class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                                    <input type="text" name="business_postal_code" id="business_postal_code"
                                           value="<?= htmlspecialchars($_POST['business_postal_code'] ?? '') ?>"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Details -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Business Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">Tax ID (EIN/SSN)</label>
                                <input type="text" name="tax_id" id="tax_id"
                                       value="<?= htmlspecialchars($_POST['tax_id'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="business_license_number" class="block text-sm font-medium text-gray-700 mb-2">Business License Number</label>
                                <input type="text" name="business_license_number" id="business_license_number"
                                       value="<?= htmlspecialchars($_POST['business_license_number'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="years_in_business" class="block text-sm font-medium text-gray-700 mb-2">Years in Business</label>
                                <input type="number" name="years_in_business" id="years_in_business" min="0"
                                       value="<?= htmlspecialchars($_POST['years_in_business'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="estimated_monthly_sales" class="block text-sm font-medium text-gray-700 mb-2">Estimated Monthly Sales ($)</label>
                                <input type="number" name="estimated_monthly_sales" id="estimated_monthly_sales" min="0" step="0.01"
                                       value="<?= htmlspecialchars($_POST['estimated_monthly_sales'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Product Categories -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Product Categories</h3>
                        <p class="text-sm text-gray-600 mb-4">Select the categories of products you plan to sell:</p>
                        
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <?php 
                            $categories = ['Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Books', 'Toys', 'Health & Beauty', 'Automotive', 'Food & Beverages', 'Art & Crafts', 'Jewelry', 'Other'];
                            foreach ($categories as $category): 
                            ?>
                                <div class="flex items-center">
                                    <input type="checkbox" name="product_categories[]" value="<?= $category ?>" 
                                           id="cat_<?= strtolower(str_replace(' ', '_', $category)) ?>"
                                           <?= in_array($category, $_POST['product_categories'] ?? []) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="cat_<?= strtolower(str_replace(' ', '_', $category)) ?>" class="ml-2 text-sm text-gray-700">
                                        <?= $category ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="previous_experience" class="block text-sm font-medium text-gray-700 mb-2">Previous E-commerce Experience</label>
                                <textarea name="previous_experience" id="previous_experience" rows="3"
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                          placeholder="Describe any previous experience selling online"><?= htmlspecialchars($_POST['previous_experience'] ?? '') ?></textarea>
                            </div>
                            
                            <div>
                                <label for="marketing_plan" class="block text-sm font-medium text-gray-700 mb-2">Marketing Plan</label>
                                <textarea name="marketing_plan" id="marketing_plan" rows="3"
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                          placeholder="How do you plan to market your products?"><?= htmlspecialchars($_POST['marketing_plan'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Agreement -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="flex items-start">
                            <input type="checkbox" name="agreement_accepted" id="agreement_accepted" required
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-1">
                            <label for="agreement_accepted" class="ml-2 text-sm text-gray-700">
                                I agree to the <a href="#" class="text-blue-600 hover:text-blue-800">VentDepot Seller Agreement</a> 
                                and <a href="#" class="text-blue-600 hover:text-blue-800">Terms of Service</a>. 
                                I understand that my application will be reviewed and I will be contacted via email with the decision.
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-center pt-6">
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 text-lg">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12 mt-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">VentDepot</h3>
                    <p class="text-gray-400">Your trusted online marketplace for quality products from verified merchants.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Customer Service</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="../contact.php" class="hover:text-white">Contact Us</a></li>
                        <li><a href="../shipping-info.php" class="hover:text-white">Shipping Info</a></li>
                        <li><a href="../returns.php" class="hover:text-white">Returns</a></li>
                        <li><a href="../faq.php" class="hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">For Merchants</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="register.php" class="hover:text-white">Become a Seller</a></li>
                        <li><a href="login.php" class="hover:text-white">Merchant Login</a></li>
                        <li><a href="../seller-guide.php" class="hover:text-white">Seller Guide</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Connect</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 VentDepot. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
