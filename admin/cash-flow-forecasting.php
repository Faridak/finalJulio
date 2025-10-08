<?php
/**
 * Cash Flow Forecasting Report
 * Provides detailed cash flow forecasting with predictive analytics
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
    <title>Cash Flow Forecasting - VentDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Cash Flow Forecasting</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshData">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar"></i> Period
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-period="30">Next 30 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-period="60">Next 60 Days</a></li>
                        <li><a class="dropdown-item" href="#" data-period="90">Next 90 Days</a></li>
                    </ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Current Cash Balance</h5>
                        <p class="card-text display-6 text-primary" id="currentCashBalance">$1,250,000</p>
                        <small class="text-muted">As of today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">90-Day Runway</h5>
                        <p class="card-text display-6 text-success" id="runwayDays">87 days</p>
                        <small class="text-muted">Based on current burn rate</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Burn Rate</h5>
                        <p class="card-text display-6 text-warning" id="burnRate">$48,000</p>
                        <small class="text-muted">Per month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Confidence Level</h5>
                        <p class="card-text display-6 text-info" id="confidenceLevel">82%</p>
                        <small class="text-muted">Predictive model accuracy</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Flow Forecast Chart -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">90-Day Cash Flow Forecast</h5>
                        <div>
                            <span class="badge bg-primary">Daily Forecast</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="cashFlowForecastChart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Flow Components -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Expected Cash Inflows</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Product Sales</td>
                                        <td>$45,000</td>
                                        <td>2025-09-20</td>
                                        <td><span class="badge bg-success">95%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Subscription Revenue</td>
                                        <td>$25,000</td>
                                        <td>2025-09-22</td>
                                        <td><span class="badge bg-success">98%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Accounts Receivable</td>
                                        <td>$18,500</td>
                                        <td>2025-09-25</td>
                                        <td><span class="badge bg-warning">75%</span></td>
                                    </tr>
                                    <tr>
                                        <td>Investment Income</td>
                                        <td>$5,000</td>
                                        <td>2025-09-30</td>
                                        <td><span class="badge bg-info">60%</span></td>
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
                        <h5 class="card-title mb-0">Expected Cash Outflows</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Payroll</td>
                                        <td>$85,000</td>
                                        <td>2025-09-25</td>
                                        <td><span class="badge bg-danger">High</span></td>
                                    </tr>
                                    <tr>
                                        <td>Inventory Purchase</td>
                                        <td>$42,000</td>
                                        <td>2025-09-20</td>
                                        <td><span class="badge bg-warning">Medium</span></td>
                                    </tr>
                                    <tr>
                                        <td>Marketing</td>
                                        <td>$18,000</td>
                                        <td>2025-09-22</td>
                                        <td><span class="badge bg-warning">Medium</span></td>
                                    </tr>
                                    <tr>
                                        <td>Office Rent</td>
                                        <td>$12,000</td>
                                        <td>2025-09-30</td>
                                        <td><span class="badge bg-danger">High</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forecasting Model Details -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Forecasting Model Details</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modelDetailsModal">
                            <i class="bi bi-info-circle"></i> Model Information
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Model Performance</h6>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 82%">82% Accuracy</div>
                                </div>
                                <p class="text-muted">Based on historical data from the past 12 months</p>
                            </div>
                            <div class="col-md-4">
                                <h6>Key Factors</h6>
                                <ul>
                                    <li>Historical sales trends</li>
                                    <li>Seasonal patterns</li>
                                    <li>Marketing campaign impact</li>
                                    <li>Economic indicators</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Model Updates</h6>
                                <p>Last updated: 2025-09-14</p>
                                <p>Next scheduled update: 2025-09-21</p>
                                <button class="btn btn-sm btn-outline-secondary">Force Model Update</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Model Details Modal -->
    <div class="modal fade" id="modelDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Forecasting Model Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Model Overview</h6>
                    <p>Our cash flow forecasting model uses a combination of time series analysis and machine learning algorithms to predict future cash flows with high accuracy.</p>
                    
                    <h6>Methodology</h6>
                    <ul>
                        <li><strong>Time Series Analysis:</strong> ARIMA models for trend identification</li>
                        <li><strong>Machine Learning:</strong> Random Forest algorithms for pattern recognition</li>
                        <li><strong>External Factors:</strong> Economic indicators, market trends, and seasonal adjustments</li>
                        <li><strong>Validation:</strong> Continuous backtesting against actual results</li>
                    </ul>
                    
                    <h6>Accuracy Metrics</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Mean Absolute Percentage Error (MAPE):</strong> 12%</p>
                            <p><strong>Root Mean Square Error (RMSE):</strong> $8,500</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>R-Squared:</strong> 0.85</p>
                            <p><strong>Confidence Interval:</strong> ±15%</p>
                        </div>
                    </div>
                    
                    <h6>Model Limitations</h6>
                    <p>The model may not account for:</p>
                    <ul>
                        <li>Unexpected market disruptions</li>
                        <li>Sudden changes in customer behavior</li>
                        <li>Major economic events not captured in historical data</li>
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
        initCashFlowForecastChart();
        
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
                alert(`Period changed to next ${period} days`);
            });
        });
    });

    function initCashFlowForecastChart() {
        const ctx = document.getElementById('cashFlowForecastChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const dates = [];
        const inflows = [];
        const outflows = [];
        const netCash = [];
        const balance = [];
        
        // Generate sample data for the next 90 days
        const today = new Date();
        let currentBalance = 1250000; // Starting balance
        
        for (let i = 0; i < 13; i++) { // 13 weeks
            const date = new Date(today);
            date.setDate(today.getDate() + (i * 7));
            dates.push(date.toISOString().split('T')[0]);
            
            const weekInflow = 100000 + Math.random() * 50000;
            const weekOutflow = 80000 + Math.random() * 30000;
            
            inflows.push(weekInflow);
            outflows.push(weekOutflow);
            netCash.push(weekInflow - weekOutflow);
            
            currentBalance += (weekInflow - weekOutflow);
            balance.push(currentBalance);
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Cash Inflows',
                        data: inflows,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Cash Outflows',
                        data: outflows,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Net Cash Flow',
                        data: netCash,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Cash Balance',
                        data: balance,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.1)',
                        tension: 0.1,
                        fill: false,
                        yAxisID: 'y1'
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
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
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
                <span class="text-muted">Cash Flow Forecasting Report</span>
            </div>
        </div>
    </footer>
</body>
</html>