<?php
/**
 * C-Level Executive Dashboard
 * Provides comprehensive financial reporting for C-Suite executives
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
    <title>C-Level Executive Dashboard - VentDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">C-Level Executive Dashboard</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDashboard">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar"></i> Period
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-period="7">Last 7 Days</a></li>
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

        <!-- Key Financial Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Cash Runway</h5>
                        <p class="card-text display-6" id="cashRunway">90 days</p>
                        <small>Burn Rate: $50,000/month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Monthly Revenue</h5>
                        <p class="card-text display-6" id="monthlyRevenue">$1.2M</p>
                        <small class="text-white">↑ 12% from last month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Customer CAC</h5>
                        <p class="card-text display-6" id="customerCAC">$85</p>
                        <small class="text-white">↓ 5% from last month</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h5 class="card-title">Churn Rate</h5>
                        <p class="card-text display-6" id="churnRate">2.1%</p>
                        <small class="text-dark">↑ 0.3% from last month</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cash Flow Forecasting -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">90-Day Cash Flow Forecast</h5>
                        <div>
                            <span class="badge bg-success">Confidence: 85%</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="cashFlowChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Performance -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Budget vs Actual (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="budgetVarianceChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Unit Economics</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>CAC:</span>
                            <strong id="unitEconomicsCAC">$85</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>LTV:</span>
                            <strong id="unitEconomicsLTV">$425</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>LTV/CAC:</span>
                            <strong id="unitEconomicsRatio">5.0x</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Payback Period:</span>
                            <strong id="paybackPeriod">2.1 months</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Gross Margin:</span>
                            <strong id="grossMargin">68%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Growth Metrics -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Growth Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="arrValue">$14.4M</h3>
                                    <p class="text-muted">ARR</p>
                                    <span class="badge bg-success">↑ 15%</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="mrrValue">$1.2M</h3>
                                    <p class="text-muted">MRR</p>
                                    <span class="badge bg-success">↑ 12%</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="npsValue">68</h3>
                                    <p class="text-muted">NPS</p>
                                    <span class="badge bg-success">↑ 5</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="marketShareValue">12%</h3>
                                    <p class="text-muted">Market Share</p>
                                    <span class="badge bg-success">↑ 2%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Management -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Financial Risk Indicators</h5>
                        <span class="badge bg-success">Low Risk</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="currentRatio">2.1</h3>
                                    <p class="text-muted">Current Ratio</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="quickRatio">1.4</h3>
                                    <p class="text-muted">Quick Ratio</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="debtEquityRatio">0.3</h3>
                                    <p class="text-muted">Debt/Equity</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 id="interestCoverage">15.2</h3>
                                    <p class="text-muted">Interest Coverage</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts
        initCashFlowChart();
        initBudgetVarianceChart();
        
        // Refresh dashboard
        document.getElementById('refreshDashboard').addEventListener('click', function() {
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

    function initCashFlowChart() {
        const ctx = document.getElementById('cashFlowChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const dates = [];
        const inflows = [];
        const outflows = [];
        const netCash = [];
        
        // Generate sample data for the next 90 days
        const today = new Date();
        for (let i = 0; i < 13; i++) { // 13 weeks
            const date = new Date(today);
            date.setDate(today.getDate() + (i * 7));
            dates.push(date.toISOString().split('T')[0]);
            inflows.push(100000 + Math.random() * 50000);
            outflows.push(80000 + Math.random() * 30000);
            netCash.push(inflows[i] - outflows[i]);
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
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    },
                    {
                        label: 'Cash Outflows',
                        data: outflows,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1
                    },
                    {
                        label: 'Net Cash Flow',
                        data: netCash,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
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
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    function initBudgetVarianceChart() {
        const ctx = document.getElementById('budgetVarianceChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const categories = ['Revenue', 'Marketing', 'R&D', 'Operations', 'Salaries'];
        const budget = [1200000, 200000, 150000, 180000, 400000];
        const actual = [1250000, 220000, 140000, 190000, 380000];
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: categories,
                datasets: [
                    {
                        label: 'Budget',
                        data: budget,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgb(54, 162, 235)',
                        borderWidth: 1
                    },
                    {
                        label: 'Actual',
                        data: actual,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1
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
                    }
                }
            }
        });
    }
    </script>

    <!-- Admin Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="text-center">
                <p>&copy; 2024 VentDepot Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>