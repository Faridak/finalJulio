<?php
/**
 * Growth Metrics Dashboard
 * Tracks key growth indicators including ARR, MRR, Churn Rate, and NPS
 */

require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Removed header include to prevent HTML structure conflicts
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Growth Metrics Dashboard - VentDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Growth Metrics Dashboard</h1>
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

        <!-- Key Growth Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Annual Recurring Revenue (ARR)</h5>
                        <p class="card-text display-6 text-primary" id="arrValue">$14.4M</p>
                        <small class="text-muted">↑ 15% YoY growth</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Monthly Recurring Revenue (MRR)</h5>
                        <p class="card-text display-6 text-success" id="mrrValue">$1.2M</p>
                        <small class="text-muted">↑ 12% MoM growth</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Net Revenue Retention</h5>
                        <p class="card-text display-6 text-info" id="nrrValue">118%</p>
                        <small class="text-muted">↑ 3% from last month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Net Promoter Score (NPS)</h5>
                        <p class="card-text display-6 text-warning" id="npsValue">68</p>
                        <small class="text-muted">↑ 5 points from last quarter</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Growth Trends Chart -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Revenue Growth Trends</h5>
                        <div>
                            <span class="badge bg-primary">Last 12 Months</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueGrowthChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Metrics -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Growth Metrics</h5>
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
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Total Customers</td>
                                        <td>12,500</td>
                                        <td>11,200</td>
                                        <td class="text-success">+11.6%</td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                    </tr>
                                    <tr>
                                        <td>New Customers</td>
                                        <td>1,250</td>
                                        <td>1,100</td>
                                        <td class="text-success">+13.6%</td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                    </tr>
                                    <tr>
                                        <td>Churn Rate</td>
                                        <td>2.1%</td>
                                        <td>2.8%</td>
                                        <td class="text-success">-0.7%</td>
                                        <td><i class="bi bi-arrow-down text-success"></i></td>
                                    </tr>
                                    <tr>
                                        <td>Customer Retention</td>
                                        <td>97.9%</td>
                                        <td>97.2%</td>
                                        <td class="text-success">+0.7%</td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                    </tr>
                                    <tr>
                                        <td>Average Revenue per User (ARPU)</td>
                                        <td>$115.20</td>
                                        <td>$108.50</td>
                                        <td class="text-success">+6.2%</td>
                                        <td><i class="bi bi-arrow-up text-success"></i></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Churn Analysis</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="churnAnalysisChart" height="150"></canvas>
                        <div class="mt-3">
                            <h6>Key Churn Drivers</h6>
                            <ul>
                                <li>Product complexity (35%)</li>
                                <li>Price sensitivity (25%)</li>
                                <li>Customer support issues (20%)</li>
                                <li>Competitor offerings (15%)</li>
                                <li>Other reasons (5%)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Market Position -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Market Position & Competitive Analysis</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#marketAnalysisModal">
                            <i class="bi bi-bar-chart"></i> Detailed Analysis
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h6>Market Share</h6>
                                <p class="display-6 text-primary">12.5%</p>
                                <p class="text-muted">↑ 2.1% from last year</p>
                            </div>
                            <div class="col-md-3">
                                <h6>Market Growth Rate</h6>
                                <p class="display-6 text-success">8.7%</p>
                                <p class="text-muted">Industry average: 6.2%</p>
                            </div>
                            <div class="col-md-3">
                                <h6>Competitive Position</h6>
                                <p class="display-6 text-info">2nd</p>
                                <p class="text-muted">Among 15 major players</p>
                            </div>
                            <div class="col-md-3">
                                <h6>Growth Efficiency</h6>
                                <p class="display-6 text-warning">1.72x</p>
                                <p class="text-muted">Revenue growth vs spend</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Market Analysis Modal -->
    <div class="modal fade" id="marketAnalysisModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detailed Market Analysis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Market Position Summary</h6>
                    <p>Our company maintains a strong position in the market with 12.5% market share and consistent growth above industry averages.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Competitive Advantages</h6>
                            <ul>
                                <li>Superior product quality and features</li>
                                <li>Strong customer service and support</li>
                                <li>Innovative technology platform</li>
                                <li>Competitive pricing strategy</li>
                                <li>Effective marketing and brand recognition</li>
                            </ul>
                            
                            <h6>Growth Opportunities</h6>
                            <ul>
                                <li>Expand into emerging markets</li>
                                <li>Develop new product lines</li>
                                <li>Enhance customer retention programs</li>
                                <li>Increase average order value</li>
                                <li>Improve operational efficiency</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Market Challenges</h6>
                            <ul>
                                <li>Increasing competition from new entrants</li>
                                <li>Economic uncertainty affecting spending</li>
                                <li>Rising customer acquisition costs</li>
                                <li>Regulatory changes in key markets</li>
                                <li>Supply chain disruptions</li>
                            </ul>
                            
                            <h6>Strategic Recommendations</h6>
                            <ol>
                                <li>Invest in product differentiation to maintain competitive advantage</li>
                                <li>Optimize customer acquisition channels to reduce CAC</li>
                                <li>Expand into high-growth geographic markets</li>
                                <li>Enhance customer success programs to improve retention</li>
                                <li>Develop strategic partnerships to accelerate growth</li>
                            </ol>
                        </div>
                    </div>
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
        initRevenueGrowthChart();
        initChurnAnalysisChart();
        
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

    function initRevenueGrowthChart() {
        const ctx = document.getElementById('revenueGrowthChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const arr = [12.5, 12.8, 13.0, 13.2, 13.5, 13.7, 13.9, 14.1, 14.2, 14.3, 14.4, 14.4];
        const mrr = [1.04, 1.07, 1.08, 1.10, 1.13, 1.14, 1.16, 1.17, 1.18, 1.19, 1.20, 1.20];
        const nrr = [112, 113, 114, 115, 115, 116, 116, 117, 117, 118, 118, 118];
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'ARR ($M)',
                        data: arr,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        yAxisID: 'y',
                        tension: 0.1
                    },
                    {
                        label: 'MRR ($M)',
                        data: mrr,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        yAxisID: 'y',
                        tension: 0.1
                    },
                    {
                        label: 'NRR (%)',
                        data: nrr,
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
                            text: 'Revenue ($M)'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    }

    function initChurnAnalysisChart() {
        const ctx = document.getElementById('churnAnalysisChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const churnReasons = ['Product Complexity', 'Price Sensitivity', 'Support Issues', 'Competitors', 'Other'];
        const churnData = [35, 25, 20, 15, 5];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: churnReasons,
                datasets: [{
                    data: churnData,
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)'
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
                <span class="text-muted">Growth Metrics Dashboard</span>
            </div>
        </div>
    </footer>
</body>
</html>