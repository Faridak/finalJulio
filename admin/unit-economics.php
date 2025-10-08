<?php
/**
 * Unit Economics Dashboard
 * Provides insights into customer acquisition cost, lifetime value, and payback period
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
    <title>Unit Economics Dashboard - VentDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Unit Economics Dashboard</h1>
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

        <!-- Key Unit Economics Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Customer Acquisition Cost (CAC)</h5>
                        <p class="card-text display-6 text-primary" id="cacValue">$85</p>
                        <small class="text-muted">↓ 5% from last period</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Customer Lifetime Value (LTV)</h5>
                        <p class="card-text display-6 text-success" id="ltvValue">$425</p>
                        <small class="text-muted">↑ 3% from last period</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">LTV/CAC Ratio</h5>
                        <p class="card-text display-6 text-info" id="ltvCacRatio">5.0x</p>
                        <small class="text-muted">Healthy ratio (>3.0)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Payback Period</h5>
                        <p class="card-text display-6 text-warning" id="paybackPeriod">2.1 months</p>
                        <small class="text-muted">↓ 0.3 months from last period</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Economics Trend Chart -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Unit Economics Trends</h5>
                        <div>
                            <span class="badge bg-primary">Last 12 Months</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="unitEconomicsTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Metrics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Metric</th>
                                        <th>Current</th>
                                        <th>Previous</th>
                                        <th>Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>New Customers</td>
                                        <td>1,250</td>
                                        <td>1,180</td>
                                        <td class="text-success">+5.9%</td>
                                    </tr>
                                    <tr>
                                        <td>Customer Retention</td>
                                        <td>87.5%</td>
                                        <td>85.2%</td>
                                        <td class="text-success">+2.3%</td>
                                    </tr>
                                    <tr>
                                        <td>Churn Rate</td>
                                        <td>12.5%</td>
                                        <td>14.8%</td>
                                        <td class="text-success">-2.3%</td>
                                    </tr>
                                    <tr>
                                        <td>Average Order Value</td>
                                        <td>$125.50</td>
                                        <td>$118.75</td>
                                        <td class="text-success">+5.7%</td>
                                    </tr>
                                    <tr>
                                        <td>Purchase Frequency</td>
                                        <td>2.3x/year</td>
                                        <td>2.1x/year</td>
                                        <td class="text-success">+9.5%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Financial Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Metric</th>
                                        <th>Current</th>
                                        <th>Previous</th>
                                        <th>Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Gross Margin</td>
                                        <td>68.2%</td>
                                        <td>66.8%</td>
                                        <td class="text-success">+1.4%</td>
                                    </tr>
                                    <tr>
                                        <td>Marketing Spend</td>
                                        <td>$106,250</td>
                                        <td>$112,000</td>
                                        <td class="text-success">-5.1%</td>
                                    </tr>
                                    <tr>
                                        <td>Customer Support Cost</td>
                                        <td>$15,625</td>
                                        <td>$16,250</td>
                                        <td class="text-success">-3.9%</td>
                                    </tr>
                                    <tr>
                                        <td>Refund Rate</td>
                                        <td>2.1%</td>
                                        <td>2.8%</td>
                                        <td class="text-success">-0.7%</td>
                                    </tr>
                                    <tr>
                                        <td>Customer Service Tickets</td>
                                        <td>850</td>
                                        <td>920</td>
                                        <td class="text-success">-7.6%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Economics Analysis -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Unit Economics Analysis</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#analysisModal">
                            <i class="bi bi-graph-up"></i> Detailed Analysis
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Performance Assessment</h6>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 85%">Excellent</div>
                                </div>
                                <p class="text-muted">LTV/CAC ratio of 5.0x indicates strong unit economics</p>
                            </div>
                            <div class="col-md-4">
                                <h6>Key Drivers</h6>
                                <ul>
                                    <li>Improved customer retention (+2.3%)</li>
                                    <li>Higher average order value (+5.7%)</li>
                                    <li>Reduced marketing costs (-5.1%)</li>
                                    <li>Lower churn rate (-2.3%)</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Opportunities</h6>
                                <ul>
                                    <li>Increase purchase frequency (target: 2.5x/year)</li>
                                    <li>Reduce customer acquisition cost (target: $80)</li>
                                    <li>Improve gross margin (target: 70%)</li>
                                    <li>Further reduce churn rate (target: 10%)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analysis Modal -->
    <div class="modal fade" id="analysisModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detailed Unit Economics Analysis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Unit Economics Breakdown</h6>
                    <p>Our unit economics model shows strong performance with healthy LTV/CAC ratios and improving customer metrics.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Customer Acquisition Cost Calculation</h6>
                            <ul>
                                <li>Marketing Spend: $106,250</li>
                                <li>New Customers Acquired: 1,250</li>
                                <li><strong>CAC = $85 per customer</strong></li>
                            </ul>
                            
                            <h6>Customer Lifetime Value Calculation</h6>
                            <ul>
                                <li>Average Order Value: $125.50</li>
                                <li>Purchase Frequency: 2.3x/year</li>
                                <li>Gross Margin: 68.2%</li>
                                <li>Customer Lifespan: 2.17 years</li>
                                <li><strong>LTV = $425 per customer</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Payback Period Calculation</h6>
                            <ul>
                                <li>CAC: $85</li>
                                <li>Monthly Revenue per Customer: $24.12</li>
                                <li>Monthly Gross Profit per Customer: $16.45</li>
                                <li><strong>Payback Period = 2.1 months</strong></li>
                            </ul>
                            
                            <h6>Key Performance Indicators</h6>
                            <ul>
                                <li>LTV/CAC Ratio: 5.0x (Target: >3.0)</li>
                                <li>Gross Margin: 68.2% (Target: >65%)</li>
                                <li>Customer Retention: 87.5% (Target: >85%)</li>
                                <li>Churn Rate: 12.5% (Target: <15%)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6>Recommendations</h6>
                    <ol>
                        <li>Continue investing in high-performing marketing channels to maintain low CAC</li>
                        <li>Implement customer loyalty programs to increase purchase frequency</li>
                        <li>Focus on customer success initiatives to further reduce churn</li>
                        <li>Optimize product offerings to improve gross margins</li>
                    </ol>
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
        initUnitEconomicsTrendChart();
        
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

    function initUnitEconomicsTrendChart() {
        const ctx = document.getElementById('unitEconomicsTrendChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const cac = [92, 90, 88, 87, 86, 85, 84, 85, 85, 84, 83, 85];
        const ltv = [390, 395, 400, 405, 410, 415, 420, 422, 425, 423, 424, 425];
        const ratio = [4.2, 4.4, 4.5, 4.7, 4.8, 4.9, 5.0, 5.0, 5.0, 5.0, 5.1, 5.0];
        const payback = [2.4, 2.4, 2.3, 2.3, 2.2, 2.2, 2.1, 2.1, 2.1, 2.1, 2.1, 2.1];
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'CAC ($)',
                        data: cac,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        yAxisID: 'y',
                        tension: 0.1
                    },
                    {
                        label: 'LTV ($)',
                        data: ltv,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        yAxisID: 'y',
                        tension: 0.1
                    },
                    {
                        label: 'LTV/CAC Ratio',
                        data: ratio,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.1
                    },
                    {
                        label: 'Payback Period (months)',
                        data: payback,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.1
                    }
                ]
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
                        title: {
                            display: true,
                            text: 'Dollar Amount ($)'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Ratio / Months'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
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
                <span class="text-muted">Unit Economics Dashboard</span>
            </div>
        </div>
    </footer>
</body>
</html>