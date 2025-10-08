<?php
/**
 * Budget vs Actual Reporting
 * Provides detailed budget variance analysis
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
    <title>Budget vs Actual Analysis - VentDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Budget vs Actual Analysis</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshData">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-calendar"></i> Period
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-period="month">This Month</a></li>
                        <li><a class="dropdown-item" href="#" data-period="quarter">This Quarter</a></li>
                        <li><a class="dropdown-item" href="#" data-period="year">This Year</a></li>
                    </ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>

        <!-- Budget Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Budget</h5>
                        <p class="card-text display-6 text-primary" id="totalBudget">$2,450,000</p>
                        <small class="text-muted">Annual budget</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Actual Spend</h5>
                        <p class="card-text display-6 text-success" id="actualSpend">$1,875,000</p>
                        <small class="text-muted">Year to date</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Variance</h5>
                        <p class="card-text display-6 text-info" id="variance">$575,000</p>
                        <small class="text-muted">Under budget</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Variance %</h5>
                        <p class="card-text display-6 text-warning" id="variancePercent">23.5%</p>
                        <small class="text-muted">Under budget</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Variance Chart -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Budget Variance by Category</h5>
                        <div>
                            <span class="badge bg-primary">Year to Date</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="budgetVarianceChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Budget Analysis -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Detailed Budget Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Department</th>
                                        <th class="text-end">Budget</th>
                                        <th class="text-end">Actual</th>
                                        <th class="text-end">Variance</th>
                                        <th class="text-end">Variance %</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Product Development</td>
                                        <td>R&D</td>
                                        <td class="text-end">$450,000</td>
                                        <td class="text-end">$425,000</td>
                                        <td class="text-end text-success">-$25,000</td>
                                        <td class="text-end text-success">-5.6%</td>
                                        <td><span class="badge bg-success">Under Budget</span></td>
                                        <td><button class="btn btn-sm btn-outline-primary">Details</button></td>
                                    </tr>
                                    <tr>
                                        <td>Marketing</td>
                                        <td>Marketing</td>
                                        <td class="text-end">$600,000</td>
                                        <td class="text-end">$625,000</td>
                                        <td class="text-end text-danger">+$25,000</td>
                                        <td class="text-end text-danger">+4.2%</td>
                                        <td><span class="badge bg-warning">Over Budget</span></td>
                                        <td><button class="btn btn-sm btn-outline-primary">Details</button></td>
                                    </tr>
                                    <tr>
                                        <td>Salaries & Benefits</td>
                                        <td>HR</td>
                                        <td class="text-end">$950,000</td>
                                        <td class="text-end">$925,000</td>
                                        <td class="text-end text-success">-$25,000</td>
                                        <td class="text-end text-success">-2.6%</td>
                                        <td><span class="badge bg-success">Under Budget</span></td>
                                        <td><button class="btn btn-sm btn-outline-primary">Details</button></td>
                                    </tr>
                                    <tr>
                                        <td>Operations</td>
                                        <td>Operations</td>
                                        <td class="text-end">$300,000</td>
                                        <td class="text-end">$325,000</td>
                                        <td class="text-end text-danger">+$25,000</td>
                                        <td class="text-end text-danger">+8.3%</td>
                                        <td><span class="badge bg-warning">Over Budget</span></td>
                                        <td><button class="btn btn-sm btn-outline-primary">Details</button></td>
                                    </tr>
                                    <tr>
                                        <td>Facilities</td>
                                        <td>Admin</td>
                                        <td class="text-end">$150,000</td>
                                        <td class="text-end">$150,000</td>
                                        <td class="text-end">$0</td>
                                        <td class="text-end">0.0%</td>
                                        <td><span class="badge bg-secondary">On Budget</span></td>
                                        <td><button class="btn btn-sm btn-outline-primary">Details</button></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th>Total</th>
                                        <th></th>
                                        <th class="text-end">$2,450,000</th>
                                        <th class="text-end">$2,450,000</th>
                                        <th class="text-end">$0</th>
                                        <th class="text-end">0.0%</th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Recommendations -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Budget Recommendations</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#recommendationsModal">
                            <i class="bi bi-lightbulb"></i> View All
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-exclamation-triangle"></i> Marketing Over Budget</h6>
                                    <p>Marketing spend is 4.2% over budget. Consider reallocating funds from under-budget categories.</p>
                                    <button class="btn btn-sm btn-outline-warning">Adjust Budget</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle"></i> R&D Efficiency</h6>
                                    <p>Product Development is 5.6% under budget. Consider investing in additional innovation projects.</p>
                                    <button class="btn btn-sm btn-outline-success">Increase Allocation</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle"></i> Operations Monitoring</h6>
                                    <p>Operations spending is trending 8.3% over budget. Review operational efficiency.</p>
                                    <button class="btn btn-sm btn-outline-info">Review Expenses</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendations Modal -->
    <div class="modal fade" id="recommendationsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Budget Recommendations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Q3 Budget Optimization Plan</h6>
                    <p>Based on current spending patterns and business objectives, we recommend the following budget adjustments:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Reallocation Opportunities</h6>
                            <ul>
                                <li>Transfer $50,000 from Product Development to Marketing</li>
                                <li>Move $30,000 from Facilities to Operations for efficiency improvements</li>
                                <li>Reallocate $25,000 from Operations to R&D for new product initiatives</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Budget Monitoring Actions</h6>
                            <ul>
                                <li>Implement weekly marketing spend reviews</li>
                                <li>Conduct monthly operations efficiency assessments</li>
                                <li>Establish quarterly budget variance analysis meetings</li>
                            </ul>
                        </div>
                    </div>
                    
                    <h6>Forecast Impact</h6>
                    <p>These adjustments are projected to improve year-end budget performance by 3.2% while maintaining operational efficiency.</p>
                    
                    <div class="mt-3">
                        <button class="btn btn-primary">Approve Budget Adjustments</button>
                        <button class="btn btn-outline-secondary">Request Additional Review</button>
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
        initBudgetVarianceChart();
        
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
                alert(`Period changed to ${period}`);
            });
        });
    });

    function initBudgetVarianceChart() {
        const ctx = document.getElementById('budgetVarianceChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const categories = ['Product Development', 'Marketing', 'Salaries & Benefits', 'Operations', 'Facilities'];
        const budget = [450000, 600000, 950000, 300000, 150000];
        const actual = [425000, 625000, 925000, 325000, 150000];
        const variance = budget.map((b, i) => actual[i] - b);
        const variancePercent = budget.map((b, i) => ((actual[i] - b) / b * 100).toFixed(1));
        
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
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const index = context.dataIndex;
                                return `Variance: $${variance[index].toLocaleString()}\n(${variancePercent[index]}%)`;
                            }
                        }
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

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">© 2024 VentDepot Admin Panel</span>
                <span class="text-muted">Budget vs Actual Report</span>
            </div>
        </div>
    </footer>
</body>
</html>