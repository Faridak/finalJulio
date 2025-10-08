<?php
/**
 * Risk Management Dashboard
 * Provides comprehensive financial risk indicators and compliance tracking
 */

require_once '../config/database.php';

// Require admin login
requireRole('admin');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Management Dashboard - VentDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Risk Management Dashboard</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshData">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar"></i> Period
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-period="30">Last 30 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-period="90">Last 90 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-period="365">Last Year</a></li>
                    </ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Risk Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Overall Risk Level</h5>
                        <p class="card-text display-6 text-success" id="overallRisk">Low</p>
                        <small class="text-muted">Based on 15 risk indicators</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Financial Risk</h5>
                        <p class="card-text display-6 text-success" id="financialRisk">Low</p>
                        <small class="text-muted">Liquidity, solvency, profitability</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Operational Risk</h5>
                        <p class="card-text display-6 text-warning" id="operationalRisk">Medium</p>
                        <small class="text-muted">Process, people, systems</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Compliance Risk</h5>
                        <p class="card-text display-6 text-success" id="complianceRisk">Low</p>
                        <small class="text-muted">Regulatory, legal, audit</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Risk Indicators -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Financial Risk Indicators</h5>
                        <div>
                            <span class="badge bg-primary">Current Period</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Indicator</th>
                                        <th>Current Value</th>
                                        <th>Industry Benchmark</th>
                                        <th>Status</th>
                                        <th>Trend</th>
                                        <th>Recommendation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Current Ratio</td>
                                        <td>2.1</td>
                                        <td>1.5-2.0</td>
                                        <td><span class="badge bg-success">Healthy</span></td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                        <td>Maintain current liquidity levels</td>
                                    </tr>
                                    <tr>
                                        <td>Quick Ratio</td>
                                        <td>1.4</td>
                                        <td>1.0-1.5</td>
                                        <td><span class="badge bg-success">Healthy</span></td>
                                        <td><i class="bi bi-arrow-right text-muted"></i></td>
                                        <td>Monitor cash flow closely</td>
                                    </tr>
                                    <tr>
                                        <td>Debt-to-Equity Ratio</td>
                                        <td>0.3</td>
                                        <td>0.5-1.0</td>
                                        <td><span class="badge bg-success">Healthy</span></td>
                                        <td><i class="bi bi-arrow-down text-success"></i></td>
                                        <td>Consider strategic debt for growth</td>
                                    </tr>
                                    <tr>
                                        <td>Interest Coverage</td>
                                        <td>15.2</td>
                                        <td>3.0+</td>
                                        <td><span class="badge bg-success">Healthy</span></td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                        <td>Strong interest coverage ratio</td>
                                    </tr>
                                    <tr>
                                        <td>Accounts Receivable Turnover</td>
                                        <td>8.5</td>
                                        <td>7.0-10.0</td>
                                        <td><span class="badge bg-success">Healthy</span></td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                        <td>Effective credit management</td>
                                    </tr>
                                    <tr>
                                        <td>Inventory Turnover</td>
                                        <td>6.2</td>
                                        <td>5.0-8.0</td>
                                        <td><span class="badge bg-success">Healthy</span></td>
                                        <td><i class="bi bi-arrow-right text-muted"></i></td>
                                        <td>Optimize inventory levels</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Heatmap -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Risk Heatmap</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="riskHeatmapChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Risk Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="riskDistributionChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compliance Tracking -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Compliance & Audit Tracking</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#complianceModal">
                            <i class="bi bi-clipboard-check"></i> View Details
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h6>GDPR Compliance</h6>
                                <p class="display-6 text-success">100%</p>
                                <p class="text-muted">All requirements met</p>
                            </div>
                            <div class="col-md-3">
                                <h6>SOX Compliance</h6>
                                <p class="display-6 text-success">100%</p>
                                <p class="text-muted">Audit ready</p>
                            </div>
                            <div class="col-md-3">
                                <h6>PCI DSS Compliance</h6>
                                <p class="display-6 text-success">100%</p>
                                <p class="text-muted">Valid certification</p>
                            </div>
                            <div class="col-md-3">
                                <h6>Tax Compliance</h6>
                                <p class="display-6 text-success">100%</p>
                                <p class="text-muted">All filings current</p>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>Upcoming Compliance Deadlines</h6>
                            <ul>
                                <li><strong>Annual Financial Audit:</strong> Due 2026-03-31</li>
                                <li><strong>GDPR Review:</strong> Due 2026-05-25</li>
                                <li><strong>Tax Filing (Q1):</strong> Due 2026-04-15</li>
                                <li><strong>SOX Documentation Update:</strong> Due 2026-06-30</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compliance Modal -->
    <div class="modal fade" id="complianceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compliance & Audit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Regulatory Compliance Status</h6>
                    <p>Our organization maintains full compliance with all applicable regulations and standards.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>GDPR Compliance</h6>
                            <ul>
                                <li>Data protection policies implemented</li>
                                <li>Privacy impact assessments completed</li>
                                <li>Data breach response procedures established</li>
                                <li>Regular staff training conducted</li>
                                <li>Third-party vendor compliance verified</li>
                            </ul>
                            
                            <h6>SOX Compliance</h6>
                            <ul>
                                <li>Internal controls documentation complete</li>
                                <li>Financial reporting processes validated</li>
                                <li>Audit trail maintained for all transactions</li>
                                <li>Segregation of duties implemented</li>
                                <li>Regular internal audits conducted</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>PCI DSS Compliance</h6>
                            <ul>
                                <li>Secure payment processing systems</li>
                                <li>Regular vulnerability assessments</li>
                                <li>Network security monitoring</li>
                                <li>Access control measures implemented</li>
                                <li>Annual compliance validation</li>
                            </ul>
                            
                            <h6>Tax Compliance</h6>
                            <ul>
                                <li>Federal tax filings current</li>
                                <li>State tax obligations met</li>
                                <li>Local tax requirements fulfilled</li>
                                <li>Transfer pricing documentation</li>
                                <li>Tax provision calculations accurate</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6>Audit Readiness</h6>
                    <p>All financial records are properly maintained and organized for audit purposes. Key audit areas include:</p>
                    <ul>
                        <li>Revenue recognition policies</li>
                        <li>Expense allocation procedures</li>
                        <li>Asset valuation methods</li>
                        <li>Liability assessment processes</li>
                        <li>Related party transaction documentation</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts
        initRiskHeatmapChart();
        initRiskDistributionChart();
        
        // Refresh data
        document.getElementById('refreshData').addEventListener('click', function() {
            location.reload();
        });
        
        // Period selection
        document.querySelectorAll('[data-period]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const period = this.getAttribute('data-period');
                // In a real implementation, this would fetch data for the selected period
                alert(`Period changed to last ${period} days`);
            });
        });
    });

    function initRiskHeatmapChart() {
        const ctx = document.getElementById('riskHeatmapChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const riskCategories = ['Financial', 'Operational', 'Compliance', 'Strategic', 'Reputational'];
        const riskPeriods = ['Q1 2025', 'Q2 2025', 'Q3 2025', 'Q4 2025', 'Q1 2026'];
        
        // Generate sample risk scores (1-5, where 5 is highest risk)
        const riskData = [];
        for (let i = 0; i < riskCategories.length; i++) {
            const categoryData = [];
            for (let j = 0; j < riskPeriods.length; j++) {
                // Generate realistic risk scores with some variation
                const baseScore = 2 + Math.floor(Math.random() * 2);
                const variation = Math.floor(Math.random() * 2) - 1;
                categoryData.push(Math.max(1, Math.min(5, baseScore + variation)));
            }
            riskData.push(categoryData);
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: riskPeriods,
                datasets: riskCategories.map((category, index) => ({
                    label: category,
                    data: riskData[index],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)'
                    ][index],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.1)',
                        'rgba(54, 162, 235, 0.1)',
                        'rgba(255, 205, 86, 0.1)',
                        'rgba(75, 192, 192, 0.1)',
                        'rgba(153, 102, 255, 0.1)'
                    ][index],
                    tension: 0.1
                }))
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                const labels = ['', 'Very Low', 'Low', 'Medium', 'High', 'Very High'];
                                return labels[value] || value;
                            }
                        }
                    }
                }
            }
        });
    }

    function initRiskDistributionChart() {
        const ctx = document.getElementById('riskDistributionChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const riskLevels = ['Very Low', 'Low', 'Medium', 'High', 'Very High'];
        const riskCounts = [8, 15, 7, 3, 1];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: riskLevels,
                datasets: [{
                    data: riskCounts,
                    backgroundColor: [
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)',
                        'rgb(255, 205, 86)',
                        'rgb(255, 159, 64)',
                        'rgb(255, 99, 132)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }
    </script>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">© 2024 VentDepot Admin Panel</span>
                <span class="text-muted">Risk Management Dashboard</span>
            </div>
        </div>
    </footer>
</body>
</html>