<?php
// Credit Management Dashboard
require_once '../config/database.php';

// Require admin login
requireRole('admin');

include 'header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Credit Management</h1>
        <button onclick="openSetCreditLimitModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Set Credit Limit
        </button>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <nav class="flex space-x-8">
            <button onclick="switchTab('overview')" class="tab-button active" id="overview-tab">Overview</button>
            <button onclick="switchTab('applications')" class="tab-button" id="applications-tab">Credit Applications</button>
            <button onclick="switchTab('collections')" class="tab-button" id="collections-tab">Collections</button>
            <button onclick="switchTab('reports')" class="tab-button" id="reports-tab">Reports</button>
        </nav>
    </div>

    <!-- Overview Tab -->
    <div id="overview-tab-content" class="tab-content">
        <!-- Credit Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Total Customers</h3>
                <p class="text-3xl font-bold text-gray-800" id="totalCustomers">0</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Active Credit Lines</h3>
                <p class="text-3xl font-bold text-blue-600" id="activeCreditLines">0</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Total Credit Limit</h3>
                <p class="text-3xl font-bold text-green-600" id="totalCreditLimit">$0.00</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Collections</h3>
                <p class="text-3xl font-bold text-red-600" id="collectionsCount">0</p>
            </div>
        </div>

        <!-- Credit Applications Summary -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Recent Credit Applications</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="applicationsTable">
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading applications...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Collections Summary -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Collections Overview</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="collectionsSummaryTable">
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">Loading collections...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Credit Applications Tab -->
    <div id="applications-tab-content" class="tab-content hidden">
        <!-- Filter and Search -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                    <select id="applicationStatusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="under_review">Under Review</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Customer</label>
                    <input type="text" id="applicationCustomerFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search customer...">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Date Range</label>
                    <div class="flex space-x-2">
                        <input type="date" id="applicationStartDateFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <input type="date" id="applicationEndDateFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex items-end">
                    <button onclick="filterApplications()" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">Filter</button>
                </div>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Credit Applications</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="allApplicationsTable">
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">Loading applications...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Collections Tab -->
    <div id="collections-tab-content" class="tab-content hidden">
        <!-- Filter and Search -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                    <select id="collectionStatusFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="new">New</option>
                        <option value="in_progress">In Progress</option>
                        <option value="escalated">Escalated</option>
                        <option value="resolved">Resolved</option>
                        <option value="written_off">Written Off</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Customer</label>
                    <input type="text" id="collectionCustomerFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search customer...">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Date Range</label>
                    <div class="flex space-x-2">
                        <input type="date" id="collectionStartDateFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <input type="date" id="collectionEndDateFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex items-end">
                    <button onclick="filterCollections()" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">Filter</button>
                </div>
            </div>
        </div>

        <!-- Collections Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Collections</h2>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="collectionsTable">
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">Loading collections...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Tab -->
    <div id="reports-tab-content" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Aging Report -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Aging Report</h2>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Customer</label>
                        <select id="agingCustomerSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a customer</option>
                        </select>
                    </div>
                    <button onclick="generateAgingReport()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md mb-6">Generate Report</button>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="agingReportTable">
                                <tr>
                                    <td colspan="2" class="px-6 py-4 text-center text-gray-500">Select a customer and generate report</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Risk Scoring -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Credit Risk Scoring</h2>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Customer</label>
                        <select id="riskCustomerSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a customer</option>
                        </select>
                    </div>
                    <button onclick="calculateRiskScore()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md mb-6">Calculate Risk Score</button>
                    <div id="riskScoreResult" class="hidden">
                        <div class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Overall Score: <span id="overallScore" class="font-bold"></span></h3>
                            <h3 class="text-lg font-medium text-gray-900">Risk Category: <span id="riskCategory" class="font-bold"></span></h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factor</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="riskFactorsTable">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Set Credit Limit Modal -->
<div id="setCreditLimitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Set Customer Credit Limit</h3>
        </div>
        <div class="p-6">
            <form id="setCreditLimitForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="customerSelect">Customer</label>
                    <select id="customerSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select a customer</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="creditLimit">Credit Limit</label>
                        <input type="number" id="creditLimit" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="creditScore">Credit Score</label>
                        <input type="number" id="creditScore" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="riskLevel">Risk Level</label>
                    <select id="riskLevel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeSetCreditLimitModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">Set Credit Limit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Process Credit Application Modal -->
<div id="processApplicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Process Credit Application</h3>
        </div>
        <div class="p-6">
            <form id="processApplicationForm">
                <input type="hidden" id="applicationId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Customer</label>
                    <p id="processCustomerName" class="text-gray-900"></p>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Requested Limit</label>
                        <p id="processRequestedLimit" class="text-gray-900"></p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Application Date</label>
                        <p id="processApplicationDate" class="text-gray-900"></p>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="applicationDecision">Decision</label>
                    <select id="applicationDecision" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select decision</option>
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                        <option value="under_review">Under Review</option>
                    </select>
                </div>
                <div class="mb-4 hidden" id="approvedLimitSection">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="approvedLimit">Approved Limit</label>
                    <input type="number" id="approvedLimit" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="reviewNotes">Review Notes</label>
                    <textarea id="reviewNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeProcessApplicationModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-white bg-green-600 rounded-md hover:bg-green-700">Process Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadOverviewData();
    loadCustomers();
    loadApplications();
    loadCollections();
    
    // Set default dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('applicationStartDateFilter').valueAsDate = firstDay;
    document.getElementById('applicationEndDateFilter').valueAsDate = today;
    document.getElementById('collectionStartDateFilter').valueAsDate = firstDay;
    document.getElementById('collectionEndDateFilter').valueAsDate = today;
    
    // Handle form submissions
    document.getElementById('setCreditLimitForm').addEventListener('submit', function(e) {
        e.preventDefault();
        setCustomerCreditLimit();
    });
    
    document.getElementById('processApplicationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        processCreditApplication();
    });
    
    // Handle decision change for application processing
    document.getElementById('applicationDecision').addEventListener('change', function() {
        if (this.value === 'approved') {
            document.getElementById('approvedLimitSection').classList.remove('hidden');
        } else {
            document.getElementById('approvedLimitSection').classList.add('hidden');
        }
    });
});

// Tab switching
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab-content').classList.remove('hidden');
    
    // Add active class to selected tab button
    document.getElementById(tabName + '-tab').classList.add('active');
}

// Load overview data
function loadOverviewData() {
    // In a real implementation, this would fetch data from the API
    // For now, we'll use sample data
    document.getElementById('totalCustomers').textContent = '1,247';
    document.getElementById('activeCreditLines').textContent = '389';
    document.getElementById('totalCreditLimit').textContent = '$2,487,500.00';
    document.getElementById('collectionsCount').textContent = '24';
    
    // Load applications for overview
    loadOverviewApplications();
    
    // Load collections summary
    loadCollectionsSummary();
}

// Load applications for overview
function loadOverviewApplications() {
    fetch('api/credit-api.php?action=get_credit_applications&status=pending')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('applicationsTable');
            tbody.innerHTML = '';
            
            if (data.success && data.data.length > 0) {
                // Show only the first 5 applications
                const applications = data.data.slice(0, 5);
                applications.forEach(application => {
                    const row = document.createElement('tr');
                    const requestedAmount = parseFloat(application.requested_credit_limit).toFixed(2);
                    const applicationDate = new Date(application.application_date).toLocaleDateString();
                    
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Customer ${application.customer_id}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${requestedAmount}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                ${application.application_status.charAt(0).toUpperCase() + application.application_status.slice(1)}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${applicationDate}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="openProcessApplicationModal(${application.id}, 'Customer ${application.customer_id}', ${application.requested_credit_limit}, '${application.application_date}')" class="text-blue-600 hover:text-blue-900">Process</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No pending applications</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading applications:', error);
            document.getElementById('applicationsTable').innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Error loading applications</td></tr>';
        });
}

// Load collections summary
function loadCollectionsSummary() {
    // In a real implementation, this would fetch data from the API
    // For now, we'll use sample data
    const tbody = document.getElementById('collectionsSummaryTable');
    tbody.innerHTML = `
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">New</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">8</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$12,450.00</td>
        </tr>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">In Progress</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">12</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$28,750.00</td>
        </tr>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Escalated</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">4</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$15,200.00</td>
        </tr>
    `;
}

// Load customers for dropdowns
function loadCustomers() {
    // In a real implementation, this would fetch actual customers from the API
    // For now, we'll use sample data
    const customers = [
        {id: 1, name: 'John Smith'},
        {id: 2, name: 'Sarah Johnson'},
        {id: 3, name: 'Mike Davis'},
        {id: 4, name: 'Emily Wilson'},
        {id: 5, name: 'David Brown'}
    ];
    
    const customerSelect = document.getElementById('customerSelect');
    const agingCustomerSelect = document.getElementById('agingCustomerSelect');
    const riskCustomerSelect = document.getElementById('riskCustomerSelect');
    
    customers.forEach(customer => {
        const option = document.createElement('option');
        option.value = customer.id;
        option.textContent = customer.name;
        
        customerSelect.appendChild(option.cloneNode(true));
        agingCustomerSelect.appendChild(option.cloneNode(true));
        riskCustomerSelect.appendChild(option.cloneNode(true));
    });
}

// Load all applications
function loadApplications() {
    fetch('api/credit-api.php?action=get_credit_applications')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('allApplicationsTable');
            tbody.innerHTML = '';
            
            if (data.success && data.data.length > 0) {
                data.data.forEach(application => {
                    const row = document.createElement('tr');
                    const requestedAmount = parseFloat(application.requested_credit_limit).toFixed(2);
                    const approvedAmount = parseFloat(application.approved_credit_limit || 0).toFixed(2);
                    const applicationDate = new Date(application.application_date).toLocaleDateString();
                    const statusClass = getStatusClass(application.application_status);
                    
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Customer ${application.customer_id}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${requestedAmount}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${approvedAmount}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                ${application.application_status.charAt(0).toUpperCase() + application.application_status.slice(1)}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${applicationDate}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="openProcessApplicationModal(${application.id}, 'Customer ${application.customer_id}', ${application.requested_credit_limit}, '${application.application_date}')" class="text-blue-600 hover:text-blue-900">Process</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No applications found</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading applications:', error);
            document.getElementById('allApplicationsTable').innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Error loading applications</td></tr>';
        });
}

// Load collections
function loadCollections() {
    // In a real implementation, this would fetch data from the API
    // For now, we'll use sample data
    const tbody = document.getElementById('collectionsTable');
    tbody.innerHTML = `
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Customer 1</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#INV-001</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$1,250.00</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-08-15</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">45</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                    Escalated
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                <button class="text-green-600 hover:text-green-900">Update</button>
            </td>
        </tr>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Customer 2</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#INV-002</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$850.00</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-08-20</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">30</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                    In Progress
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                <button class="text-green-600 hover:text-green-900">Update</button>
            </td>
        </tr>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Customer 3</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#INV-003</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$2,100.00</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-08-10</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">60</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                    Escalated
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                <button class="text-green-600 hover:text-green-900">Update</button>
            </td>
        </tr>
    `;
}

// Filter applications
function filterApplications() {
    // In a real implementation, this would filter the data on the server side
    // For now, we'll just reload all data
    loadApplications();
}

// Filter collections
function filterCollections() {
    // In a real implementation, this would filter the data on the server side
    // For now, we'll just reload all data
    loadCollections();
}

// Get status class for styling
function getStatusClass(status) {
    switch(status) {
        case 'approved':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        case 'under_review':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Open set credit limit modal
function openSetCreditLimitModal() {
    document.getElementById('setCreditLimitModal').classList.remove('hidden');
    document.getElementById('setCreditLimitModal').classList.add('flex');
    document.getElementById('setCreditLimitForm').reset();
}

// Close set credit limit modal
function closeSetCreditLimitModal() {
    document.getElementById('setCreditLimitModal').classList.add('hidden');
    document.getElementById('setCreditLimitModal').classList.remove('flex');
}

// Set customer credit limit
function setCustomerCreditLimit() {
    const formData = {
        customer_id: document.getElementById('customerSelect').value,
        credit_limit: document.getElementById('creditLimit').value,
        credit_score: document.getElementById('creditScore').value,
        risk_level: document.getElementById('riskLevel').value
    };
    
    fetch('api/credit-api.php?action=set_customer_credit_limit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Credit limit set successfully!');
            closeSetCreditLimitModal();
            loadOverviewData();
        } else {
            alert('Error setting credit limit: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error setting credit limit:', error);
        alert('Error setting credit limit. Please try again.');
    });
}

// Open process application modal
function openProcessApplicationModal(applicationId, customerName, requestedLimit, applicationDate) {
    document.getElementById('applicationId').value = applicationId;
    document.getElementById('processCustomerName').textContent = customerName;
    document.getElementById('processRequestedLimit').textContent = '$' + parseFloat(requestedLimit).toFixed(2);
    document.getElementById('processApplicationDate').textContent = new Date(applicationDate).toLocaleDateString();
    
    document.getElementById('processApplicationModal').classList.remove('hidden');
    document.getElementById('processApplicationModal').classList.add('flex');
    document.getElementById('processApplicationForm').reset();
    document.getElementById('approvedLimit').value = requestedLimit;
}

// Close process application modal
function closeProcessApplicationModal() {
    document.getElementById('processApplicationModal').classList.add('hidden');
    document.getElementById('processApplicationModal').classList.remove('flex');
}

// Process credit application
function processCreditApplication() {
    const formData = {
        application_id: document.getElementById('applicationId').value,
        decision: document.getElementById('applicationDecision').value,
        approved_limit: document.getElementById('approvedLimit').value,
        notes: document.getElementById('reviewNotes').value
    };
    
    fetch('api/credit-api.php?action=process_credit_application', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Credit application processed successfully!');
            closeProcessApplicationModal();
            loadOverviewData();
            loadApplications();
        } else {
            alert('Error processing credit application: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error processing credit application:', error);
        alert('Error processing credit application. Please try again.');
    });
}

// Generate aging report
function generateAgingReport() {
    const customerId = document.getElementById('agingCustomerSelect').value;
    
    if (!customerId) {
        alert('Please select a customer');
        return;
    }
    
    fetch(`api/credit-api.php?action=generate_aging_report&customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('agingReportTable');
                tbody.innerHTML = `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Current</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(data.data.current || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">1-30 Days</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(data.data.days_1_30 || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">31-60 Days</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(data.data.days_31_60 || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">61-90 Days</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(data.data.days_61_90 || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">91-120 Days</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(data.data.days_91_120 || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Over 120 Days</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(data.data.days_over_120 || 0).toFixed(2)}</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Total Outstanding</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">$${parseFloat(data.data.total_outstanding || 0).toFixed(2)}</td>
                    </tr>
                `;
            } else {
                alert('Error generating aging report: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error generating aging report:', error);
            alert('Error generating aging report. Please try again.');
        });
}

// Calculate risk score
function calculateRiskScore() {
    const customerId = document.getElementById('riskCustomerSelect').value;
    
    if (!customerId) {
        alert('Please select a customer');
        return;
    }
    
    fetch(`api/credit-api.php?action=calculate_credit_risk_score&customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('overallScore').textContent = data.score;
                document.getElementById('riskCategory').textContent = data.category.charAt(0).toUpperCase() + data.category.slice(1);
                
                const tbody = document.getElementById('riskFactorsTable');
                tbody.innerHTML = `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Payment History</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.factors.payment_history}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Credit Utilization</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.factors.credit_utilization}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Length of Credit</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.factors.length_of_credit}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">New Credit</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.factors.new_credit}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Credit Mix</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${data.factors.credit_mix}</td>
                    </tr>
                `;
                
                document.getElementById('riskScoreResult').classList.remove('hidden');
            } else {
                alert('Error calculating risk score: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error calculating risk score:', error);
            alert('Error calculating risk score. Please try again.');
        });
}
</script>

<?php include 'footer.php'; ?>