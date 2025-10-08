<?php
// Contact Seller Modal Component
// Include this in product.php or any page where users can contact sellers

// This assumes $product array with merchant_id is available
if (!isset($product) || !$product) {
    return;
}

$merchantId = $product['merchant_id'];
$productId = $product['id'];
?>

<!-- Contact Seller Button -->
<?php if (isLoggedIn() && $_SESSION['user_id'] != $merchantId): ?>
    <button onclick="openContactModal()" 
            class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition-colors mt-4">
        <i class="fas fa-envelope mr-2"></i>
        Contact Seller
    </button>
<?php elseif (!isLoggedIn()): ?>
    <a href="login.php" 
       class="w-full bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition-colors mt-4 inline-block text-center">
        <i class="fas fa-sign-in-alt mr-2"></i>
        Login to Contact Seller
    </a>
<?php endif; ?>

<!-- Contact Seller Modal -->
<div id="contactModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Contact Seller</h3>
                <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <div class="flex items-center space-x-3">
                    <img src="<?= htmlspecialchars($product['image_url'] ?: 'https://via.placeholder.com/60x60') ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         class="w-12 h-12 object-cover rounded">
                    <div>
                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></h4>
                        <p class="text-sm text-blue-600 font-semibold">$<?= number_format($product['price'], 2) ?></p>
                    </div>
                </div>
            </div>
            
            <form id="contactForm" class="space-y-4">
                <?= Security::getCSRFInput() ?>
                <input type="hidden" name="action" value="start_conversation">
                <input type="hidden" name="seller_id" value="<?= $merchantId ?>">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject" id="subject" required
                           value="Question about <?= htmlspecialchars($product['name']) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="initial_message" class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea name="initial_message" id="initial_message" rows="4" required
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                              placeholder="Hi, I'm interested in this product. Could you tell me more about..."></textarea>
                </div>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="closeContactModal()" 
                            class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openContactModal() {
    document.getElementById('contactModal').classList.remove('hidden');
}

function closeContactModal() {
    document.getElementById('contactModal').classList.add('hidden');
}

// Handle contact form submission
document.getElementById('contactForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const button = this.querySelector('button[type="submit"]');
    const originalText = button.textContent;
    
    button.disabled = true;
    button.textContent = 'Sending...';
    
    fetch('messages.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeContactModal();
            // Redirect to messages page
            window.location.href = 'messages.php?conversation=' + data.conversation_id;
        } else {
            alert('Error: ' + (data.message || 'Failed to send message'));
            button.disabled = false;
            button.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error sending message');
        button.disabled = false;
        button.textContent = originalText;
    });
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeContactModal();
    }
});

// Close modal on outside click
document.getElementById('contactModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeContactModal();
    }
});
</script>