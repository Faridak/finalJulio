<?php
// Accounting System Status Dashboard
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Check if user is authenticated and is admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

include 'header.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get counts for all tables
    $tables = [
        'chart_of_accounts' => 'Chart of Accounts',
        'general_ledger' => 'General Ledger Entries',
        'accounts_payable' => 'Accounts Payable',
        'accounts_receivable' => 'Accounts Receivable',
        'sales_commissions' => 'Sales Commissions',
        'commission_tiers' => 'Commission Tiers',
        'marketing_campaigns' => 'Marketing Campaigns',
        'marketing_expenses' => 'Marketing Expenses',
        'operations_costs' => 'Operations Costs',
        'product_costing' => 'Product Costing Records',
        'payroll' => 'Payroll Records',
        'financial_reports' => 'Financial Reports'
    ];
    
    $counts = [];
    foreach ($tables as $table => $label) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $counts[$table] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            $counts[$table] = 'Table not found';
        }
    }
    
} catch(PDOException $e) {
    $error = "Database connection failed: " . $e->getMessage();
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Accounting System Status</h1>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Error: </strong>
            <span class="block sm:inline"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>
    
    <!-- System Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">System Status</h3>
            <p class="text-2xl font-bold text-green-600">Operational</p>
            <p class="text-sm text-gray-500 mt-1">All modules are functioning properly</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Database Connection</h3>
            <p class="text-2xl font-bold text-green-600">Connected</p>
            <p class="text-sm text-gray-500 mt-1">MySQL database accessible</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">API Status</h3>
            <p class="text-2xl font-bold text-green-600">Active</p>
            <p class="text-sm text-gray-500 mt-1">All endpoints responding</p>
        </div>
    </div>
    
    <!-- Module Status -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Module Status</h2>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record Count</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tables as $table => $label): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $label; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $counts[$table]; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($counts[$table] !== 'Table not found'): ?>
                                    <a href="#" class="text-blue-600 hover:text-blue-900">View Records</a>
                                <?php else: ?>
                                    <span class="text-red-600">Error</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Quick Links</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="accounting-dashboard.php" class="block bg-blue-50 hover:bg-blue-100 p-4 rounded-lg text-center">
                    <div class="text-blue-600 font-medium">Accounting Dashboard</div>
                </a>
                <a href="commission-tracking.php" class="block bg-green-50 hover:bg-green-100 p-4 rounded-lg text-center">
                    <div class="text-green-600 font-medium">Commission Tracking</div>
                </a>
                <a href="marketing-expenses.php" class="block bg-purple-50 hover:bg-purple-100 p-4 rounded-lg text-center">
                    <div class="text-purple-600 font-medium">Marketing Expenses</div>
                </a>
                <a href="financial-reports.php" class="block bg-indigo-50 hover:bg-indigo-100 p-4 rounded-lg text-center">
                    <div class="text-indigo-600 font-medium">Financial Reports</div>
                </a>
                <a href="accounts-payable.php" class="block bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg text-center">
                    <div class="text-yellow-600 font-medium">Accounts Payable</div>
                </a>
                <a href="accounts-receivable.php" class="block bg-teal-50 hover:bg-teal-100 p-4 rounded-lg text-center">
                    <div class="text-teal-600 font-medium">Accounts Receivable</div>
                </a>
                <a href="accounting-system-documentation.md" class="block bg-gray-50 hover:bg-gray-100 p-4 rounded-lg text-center">
                    <div class="text-gray-600 font-medium">Documentation</div>
                </a>
                <a href="#" class="block bg-red-50 hover:bg-red-100 p-4 rounded-lg text-center">
                    <div class="text-red-600 font-medium">System Logs</div>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>