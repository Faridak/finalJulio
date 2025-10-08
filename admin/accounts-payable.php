<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Payable - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php
    // Accounts Payable Management
    require_once '../config/database.php';

    // Require admin login
    requireRole('admin');

    // Check if user is authenticated and is admin
    if (!isLoggedIn() || getUserRole() !== 'admin') {
        header('Location: ../login.php');
        exit;
    }

    include 'header.php';
    ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Accounts Payable</h1>
            <button onclick="openAddPayableModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add Payable
            </button>
        </div>

        <!-- Filter and Search -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                    <select id="statusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Vendor</label>
                    <input type="text" id="vendorFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search vendor...">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Date Range</label>
                    <div class="flex space-x-2">
                        <input type="date" id="startDateFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <input type="date" id="endDateFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex items-end">
                    <button onclick="filterPayables()" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">Filter</button>
                </div>
            </div>
        </div>

        <!-- Payables Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Total Payables</h3>
                <p class="text-3xl font-bold text-gray-800" id="totalPayables">$0.00</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Pending</h3>
                <p class="text-3xl font-bold text-yellow-600" id="pendingPayables">$0.00</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Overdue</h3>
                <p class="text-3xl font-bold text-red-600" id="overduePayables">$0.00</p>
            </div>
        </div>

        <!-- Payables Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Payable Invoices</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="payablesTable">
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">Loading payables...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Payable Modal -->
    <div id="addPayableModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Add Account Payable</h3>
            </div>
            <div class="p-6">
                <form id="addPayableForm">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="vendor_name">Vendor Name</label>
                        <input type="text" id="vendor_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="invoice_number">Invoice Number</label>
                            <input type="text" id="invoice_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="amount">Amount</label>
                            <input type="number" id="amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="invoice_date">Invoice Date</label>
                            <input type="date" id="invoice_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="due_date">Due Date</label>
                            <input type="date" id="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
                        <textarea id="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddPayableModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">Add Payable</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pay Invoice Modal -->
    <div id="payInvoiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Pay Invoice</h3>
            </div>
            <div class="p-6">
                <form id="payInvoiceForm">
                    <input type="hidden" id="payable_id">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Vendor</label>
                        <p id="pay_vendor_name" class="text-gray-900"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Invoice #</label>
                            <p id="pay_invoice_number" class="text-gray-900"></p>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Due Date</label>
                            <p id="pay_due_date" class="text-gray-900"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Total Amount</label>
                            <p id="pay_total_amount" class="text-gray-900 font-medium"></p>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Amount Paid</label>
                            <p id="pay_paid_amount" class="text-gray-900"></p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="payment_amount">Payment Amount</label>
                        <input type="number" id="payment_amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePayInvoiceModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-green-600 rounded-md hover:bg-green-700">Process Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        loadPayables();
        loadPayablesSummary();
        
        // Set default dates
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('startDateFilter').valueAsDate = firstDay;
        document.getElementById('endDateFilter').valueAsDate = today;
        
        // Handle form submissions
        document.getElementById('addPayableForm').addEventListener('submit', function(e) {
            e.preventDefault();
            addAccountPayable();
        });
        
        document.getElementById('payInvoiceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            payAccountPayable();
        });
    });

    // Load payables
    function loadPayables() {
        fetch('api/accounting-api.php?action=get_accounts_payable')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('payablesTable');
                tbody.innerHTML = '';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(payable => {
                        const row = document.createElement('tr');
                        const statusClass = getStatusClass(payable.status);
                        const amount = parseFloat(payable.amount).toFixed(2);
                        const paidAmount = parseFloat(payable.paid_amount).toFixed(2);
                        const balance = (parseFloat(payable.amount) - parseFloat(payable.paid_amount)).toFixed(2);
                        
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${payable.vendor_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${payable.invoice_number}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${payable.invoice_date}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${payable.due_date}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$${amount}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$${paidAmount}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                    ${payable.status.charAt(0).toUpperCase() + payable.status.slice(1)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                ${payable.status !== 'paid' ? 
                                    `<button onclick="openPayInvoiceModal(${payable.id}, '${payable.vendor_name}', '${payable.invoice_number}', '${payable.due_date}', ${payable.amount}, ${payable.paid_amount})" class="text-green-600 hover:text-green-900 mr-3">Pay</button>` : 
                                    ''
                                }
                                <button class="text-blue-600 hover:text-blue-900">View</button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">No payables found</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading payables:', error);
                document.getElementById('payablesTable').innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Error loading payables</td></tr>';
            });
    }

    // Load payables summary
    function loadPayablesSummary() {
        fetch('api/accounting-api.php?action=get_accounts_payable')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let total = 0;
                    let pending = 0;
                    let overdue = 0;
                    
                    data.data.forEach(payable => {
                        const amount = parseFloat(payable.amount);
                        const paidAmount = parseFloat(payable.paid_amount);
                        const balance = amount - paidAmount;
                        
                        if (payable.status !== 'paid' && payable.status !== 'cancelled') {
                            total += balance;
                            
                            if (payable.status === 'pending') {
                                pending += balance;
                            } else if (payable.status === 'overdue') {
                                overdue += balance;
                            }
                        }
                    });
                    
                    document.getElementById('totalPayables').textContent = '$' + total.toFixed(2);
                    document.getElementById('pendingPayables').textContent = '$' + pending.toFixed(2);
                    document.getElementById('overduePayables').textContent = '$' + overdue.toFixed(2);
                }
            })
            .catch(error => {
                console.error('Error loading payables summary:', error);
            });
    }

    // Get status class for styling
    function getStatusClass(status) {
        switch(status) {
            case 'paid':
                return 'bg-green-100 text-green-800';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            case 'overdue':
                return 'bg-red-100 text-red-800';
            case 'cancelled':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }

    // Filter payables
    function filterPayables() {
        // In a real implementation, this would filter the data on the server side
        // For now, we'll just reload all data
        loadPayables();
    }

    // Open add payable modal
    function openAddPayableModal() {
        // Set default dates
        const today = new Date();
        document.getElementById('invoice_date').valueAsDate = today;
        document.getElementById('due_date').valueAsDate = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days from now
        
        document.getElementById('addPayableModal').classList.remove('hidden');
        document.getElementById('addPayableModal').classList.add('flex');
    }

    // Close add payable modal
    function closeAddPayableModal() {
        document.getElementById('addPayableModal').classList.add('hidden');
        document.getElementById('addPayableModal').classList.remove('flex');
        document.getElementById('addPayableForm').reset();
    }

    // Add account payable
    function addAccountPayable() {
        const formData = {
            vendor_name: document.getElementById('vendor_name').value,
            invoice_number: document.getElementById('invoice_number').value,
            invoice_date: document.getElementById('invoice_date').value,
            due_date: document.getElementById('due_date').value,
            amount: document.getElementById('amount').value,
            description: document.getElementById('description').value
        };
        
        fetch('api/accounting-api.php?action=add_account_payable', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Account payable added successfully!');
                closeAddPayableModal();
                loadPayables();
                loadPayablesSummary();
            } else {
                alert('Error adding account payable: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error adding account payable:', error);
            alert('Error adding account payable. Please try again.');
        });
    }

    // Open pay invoice modal
    function openPayInvoiceModal(payableId, vendorName, invoiceNumber, dueDate, totalAmount, paidAmount) {
        document.getElementById('payable_id').value = payableId;
        document.getElementById('pay_vendor_name').textContent = vendorName;
        document.getElementById('pay_invoice_number').textContent = invoiceNumber;
        document.getElementById('pay_due_date').textContent = dueDate;
        document.getElementById('pay_total_amount').textContent = '$' + parseFloat(totalAmount).toFixed(2);
        document.getElementById('pay_paid_amount').textContent = '$' + parseFloat(paidAmount).toFixed(2);
        document.getElementById('payment_amount').value = (totalAmount - paidAmount).toFixed(2);
        
        document.getElementById('payInvoiceModal').classList.remove('hidden');
        document.getElementById('payInvoiceModal').classList.add('flex');
    }

    // Close pay invoice modal
    function closePayInvoiceModal() {
        document.getElementById('payInvoiceModal').classList.add('hidden');
        document.getElementById('payInvoiceModal').classList.remove('flex');
        document.getElementById('payInvoiceForm').reset();
    }

    // Pay account payable
    function payAccountPayable() {
        const formData = {
            payable_id: document.getElementById('payable_id').value,
            amount: document.getElementById('payment_amount').value
        };
        
        fetch('api/accounting-api.php?action=pay_account_payable', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment processed successfully!');
                closePayInvoiceModal();
                loadPayables();
                loadPayablesSummary();
            } else {
                alert('Error processing payment: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error processing payment:', error);
            alert('Error processing payment. Please try again.');
        });
    }
    </script>

    <!-- Admin Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 VentDepot Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>