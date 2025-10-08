<?php
require_once '../config/database.php';
require_once '../config/db-connection-pool.php';
require_once '../classes/TaxManager.php';

// Check if user is authenticated
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

try {
    $pdo = getOptimizedDBConnection();
    $taxManager = new TaxManager($pdo);
} catch(Exception $e) {
    $error = "Database connection failed: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Tax Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="bi bi-calculator"></i> Tax Calculator
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#exemptions">
                                <i class="bi bi-shield-check"></i> Tax Exemptions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#reverse-charge">
                                <i class="bi bi-arrow-repeat"></i> Reverse Charge VAT
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#audit-trail">
                                <i class="bi bi-journal-text"></i> Tax Audit Trail
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#reports">
                                <i class="bi bi-bar-chart"></i> Tax Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Advanced Tax Management</h1>
                </div>

                <!-- Tax Calculator Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calculator"></i> Tax Calculator
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="tax-calculator-form">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="customer_id" class="form-label">Customer ID</label>
                                                <input type="number" class="form-control" id="customer_id" name="customer_id" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="country_id" class="form-label">Country</label>
                                                <select class="form-control" id="country_id" name="country_id" required>
                                                    <option value="">Select Country</option>
                                                    <?php
                                                    try {
                                                        $stmt = $pdo->query("SELECT id, name FROM countries ORDER BY name");
                                                        while ($country = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='{$country['id']}'>{$country['name']}</option>";
                                                        }
                                                    } catch(Exception $e) {
                                                        echo "<option value=''>Error loading countries</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="state_id" class="form-label">State/Province</label>
                                                <select class="form-control" id="state_id" name="state_id">
                                                    <option value="">Select State/Province</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="product_category" class="form-label">Product Category</label>
                                                <input type="text" class="form-control" id="product_category" name="product_category" placeholder="e.g., Electronics, Clothing">
                                            </div>
                                            <div class="mb-3">
                                                <label for="amount" class="form-label">Amount ($)</label>
                                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="transaction_id" class="form-label">Transaction ID (Optional)</label>
                                                <input type="number" class="form-control" id="transaction_id" name="transaction_id">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-calculator"></i> Calculate Tax
                                    </button>
                                </form>
                                
                                <div id="tax-result" class="mt-4" style="display: none;">
                                    <h5>Tax Calculation Result</h5>
                                    <div class="alert alert-info">
                                        <p><strong>Tax Amount:</strong> $<span id="tax-amount">0.00</span></p>
                                        <p><strong>Tax Rate:</strong> <span id="tax-rate">0</span>%</p>
                                        <p><strong>Tax Type:</strong> <span id="tax-type">-</span></p>
                                        <p><strong>Notes:</strong> <span id="tax-notes">-</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax Exemptions Section -->
                <div class="row mb-4" id="exemptions">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-shield-check"></i> Tax Exemptions
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="exemption-form">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="ex_customer_id" class="form-label">Customer ID</label>
                                                <input type="number" class="form-control" id="ex_customer_id" name="customer_id" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="exemption_type" class="form-label">Exemption Type</label>
                                                <select class="form-control" id="exemption_type" name="exemption_type" required>
                                                    <option value="">Select Type</option>
                                                    <option value="b2b">B2B Sales</option>
                                                    <option value="non_profit">Non-Profit Organization</option>
                                                    <option value="government">Government Entity</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="certificate_number" class="form-label">Certificate Number</label>
                                                <input type="text" class="form-control" id="certificate_number" name="certificate_number">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="exemption_rate" class="form-label">Exemption Rate (%)</label>
                                                <input type="number" class="form-control" id="exemption_rate" name="exemption_rate" step="0.01" min="0" max="100" value="0">
                                            </div>
                                            <div class="mb-3">
                                                <label for="effective_date" class="form-label">Effective Date</label>
                                                <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="exemption_notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="exemption_notes" name="notes" rows="2"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Add Exemption
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reverse Charge VAT Section -->
                <div class="row mb-4" id="reverse-charge">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-arrow-repeat"></i> Reverse Charge VAT Rules
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="reverse-charge-form">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="seller_country" class="form-label">Seller Country</label>
                                                <select class="form-control" id="seller_country" name="seller_country_id" required>
                                                    <option value="">Select Seller Country</option>
                                                    <?php
                                                    try {
                                                        $stmt = $pdo->query("SELECT id, name FROM countries ORDER BY name");
                                                        while ($country = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='{$country['id']}'>{$country['name']}</option>";
                                                        }
                                                    } catch(Exception $e) {
                                                        echo "<option value=''>Error loading countries</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="product_category_rule" class="form-label">Product Category (Optional)</label>
                                                <input type="text" class="form-control" id="product_category_rule" name="product_category" placeholder="e.g., Electronics">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="buyer_country" class="form-label">Buyer Country</label>
                                                <select class="form-control" id="buyer_country" name="buyer_country_id" required>
                                                    <option value="">Select Buyer Country</option>
                                                    <?php
                                                    try {
                                                        $stmt = $pdo->query("SELECT id, name FROM countries ORDER BY name");
                                                        while ($country = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='{$country['id']}'>{$country['name']}</option>";
                                                        }
                                                    } catch(Exception $e) {
                                                        echo "<option value=''>Error loading countries</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="effective_date_rule" class="form-label">Effective Date</label>
                                                <input type="date" class="form-control" id="effective_date_rule" name="effective_date" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="expiry_date_rule" class="form-label">Expiry Date (Optional)</label>
                                        <input type="date" class="form-control" id="expiry_date_rule" name="expiry_date">
                                    </div>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-plus-circle"></i> Add Reverse Charge Rule
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax Audit Trail Section -->
                <div class="row mb-4" id="audit-trail">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-journal-text"></i> Tax Audit Trail
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="audit-transaction-id" placeholder="Enter Transaction ID">
                                            <select class="form-select" id="audit-transaction-type">
                                                <option value="order">Order</option>
                                                <option value="invoice">Invoice</option>
                                                <option value="refund">Refund</option>
                                            </select>
                                            <button class="btn btn-outline-secondary" type="button" id="load-audit-trail">
                                                <i class="bi bi-search"></i> Load
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="audit-trail-table">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Tax Type</th>
                                                <th>Tax Rate</th>
                                                <th>Tax Amount</th>
                                                <th>Exemption</th>
                                                <th>Reverse Charge</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="7" class="text-center">Enter a transaction ID to load audit trail</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax Reports Section -->
                <div class="row mb-4" id="reports">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-bar-chart"></i> Tax Reports
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="report-start-date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="report-start-date" value="<?= date('Y-m-01') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="report-end-date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="report-end-date" value="<?= date('Y-m-t') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div>
                                            <button class="btn btn-primary" id="generate-tax-report">
                                                <i class="bi bi-file-earmark-bar-chart"></i> Tax Report
                                            </button>
                                            <button class="btn btn-secondary" id="generate-exemption-report">
                                                <i class="bi bi-file-earmark-text"></i> Exemption Report
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive" id="report-container" style="display: none;">
                                    <table class="table table-striped" id="report-table">
                                        <thead id="report-thead">
                                        </thead>
                                        <tbody id="report-tbody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle country/state selection
            $('#country_id').change(function() {
                var countryId = $(this).val();
                if (countryId) {
                    $.get('api/location-api.php?action=get_states&country_id=' + countryId, function(data) {
                        if (data.success) {
                            var options = '<option value="">Select State/Province</option>';
                            $.each(data.data, function(index, state) {
                                options += '<option value="' + state.id + '">' + state.name + '</option>';
                            });
                            $('#state_id').html(options);
                        }
                    });
                } else {
                    $('#state_id').html('<option value="">Select State/Province</option>');
                }
            });

            // Tax Calculator Form Submission
            $('#tax-calculator-form').submit(function(e) {
                e.preventDefault();
                
                var formData = {
                    customer_id: $('#customer_id').val(),
                    country_id: $('#country_id').val(),
                    state_id: $('#state_id').val(),
                    product_category: $('#product_category').val(),
                    amount: $('#amount').val(),
                    transaction_id: $('#transaction_id').val(),
                    transaction_type: 'order'
                };
                
                $.ajax({
                    url: 'api/tax-api.php?action=calculate_tax',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    success: function(response) {
                        if (response.success) {
                            $('#tax-amount').text(response.data.tax_amount.toFixed(2));
                            $('#tax-rate').text(response.data.tax_rate);
                            $('#tax-type').text(response.data.tax_type);
                            $('#tax-notes').text(response.data.notes);
                            $('#tax-result').show();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while calculating tax');
                    }
                });
            });

            // Tax Exemption Form Submission
            $('#exemption-form').submit(function(e) {
                e.preventDefault();
                
                var formData = {
                    customer_id: $('#ex_customer_id').val(),
                    exemption_type: $('#exemption_type').val(),
                    certificate_number: $('#certificate_number').val(),
                    exemption_rate: $('#exemption_rate').val(),
                    effective_date: $('#effective_date').val(),
                    expiry_date: $('#expiry_date').val(),
                    notes: $('#exemption_notes').val()
                };
                
                $.ajax({
                    url: 'api/tax-api.php?action=add_tax_exemption',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    success: function(response) {
                        if (response.success) {
                            alert('Tax exemption added successfully!');
                            $('#exemption-form')[0].reset();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while adding tax exemption');
                    }
                });
            });

            // Reverse Charge VAT Form Submission
            $('#reverse-charge-form').submit(function(e) {
                e.preventDefault();
                
                var formData = {
                    seller_country_id: $('#seller_country').val(),
                    buyer_country_id: $('#buyer_country').val(),
                    product_category: $('#product_category_rule').val(),
                    effective_date: $('#effective_date_rule').val(),
                    expiry_date: $('#expiry_date_rule').val()
                };
                
                $.ajax({
                    url: 'api/tax-api.php?action=add_reverse_charge_rule',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    success: function(response) {
                        if (response.success) {
                            alert('Reverse charge VAT rule added successfully!');
                            $('#reverse-charge-form')[0].reset();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while adding reverse charge VAT rule');
                    }
                });
            });

            // Load Audit Trail
            $('#load-audit-trail').click(function() {
                var transactionId = $('#audit-transaction-id').val();
                var transactionType = $('#audit-transaction-type').val();
                
                if (!transactionId) {
                    alert('Please enter a transaction ID');
                    return;
                }
                
                $.get('api/tax-api.php?action=get_tax_audit_trail&transaction_id=' + transactionId + '&transaction_type=' + transactionType, function(response) {
                    if (response.success) {
                        var tbody = '';
                        if (response.data.length > 0) {
                            $.each(response.data, function(index, record) {
                                tbody += '<tr>';
                                tbody += '<td>' + record.calculated_at + '</td>';
                                tbody += '<td>' + record.tax_type + '</td>';
                                tbody += '<td>' + parseFloat(record.tax_rate_applied).toFixed(2) + '%</td>';
                                tbody += '<td>$' + parseFloat(record.tax_amount_calculated).toFixed(2) + '</td>';
                                tbody += '<td>' + (record.exemption_applied ? 'Yes' : 'No') + '</td>';
                                tbody += '<td>' + (record.reverse_charge_applied ? 'Yes' : 'No') + '</td>';
                                tbody += '<td>' + (record.notes || '-') + '</td>';
                                tbody += '</tr>';
                            });
                        } else {
                            tbody = '<tr><td colspan="7" class="text-center">No audit trail records found</td></tr>';
                        }
                        $('#audit-trail-table tbody').html(tbody);
                    } else {
                        alert('Error: ' + response.message);
                    }
                });
            });

            // Generate Tax Report
            $('#generate-tax-report').click(function() {
                var startDate = $('#report-start-date').val();
                var endDate = $('#report-end-date').val();
                
                $.get('api/tax-api.php?action=get_tax_report&start_date=' + startDate + '&end_date=' + endDate, function(response) {
                    if (response.success) {
                        var thead = '<tr><th>Tax Type</th><th>Transaction Count</th><th>Total Tax Collected</th><th>Average Tax Rate</th></tr>';
                        var tbody = '';
                        
                        if (response.data.length > 0) {
                            $.each(response.data, function(index, record) {
                                tbody += '<tr>';
                                tbody += '<td>' + record.tax_type + '</td>';
                                tbody += '<td>' + record.transaction_count + '</td>';
                                tbody += '<td>$' + parseFloat(record.total_tax_collected).toFixed(2) + '</td>';
                                tbody += '<td>' + parseFloat(record.average_tax_rate).toFixed(2) + '%</td>';
                                tbody += '</tr>';
                            });
                        } else {
                            tbody = '<tr><td colspan="4" class="text-center">No data found for the selected period</td></tr>';
                        }
                        
                        $('#report-thead').html(thead);
                        $('#report-tbody').html(tbody);
                        $('#report-container').show();
                    } else {
                        alert('Error: ' + response.message);
                    }
                });
            });

            // Generate Exemption Report
            $('#generate-exemption-report').click(function() {
                var startDate = $('#report-start-date').val();
                var endDate = $('#report-end-date').val();
                
                $.get('api/tax-api.php?action=get_exemption_report&start_date=' + startDate + '&end_date=' + endDate, function(response) {
                    if (response.success) {
                        var thead = '<tr><th>Exemption Type</th><th>Exemption Count</th><th>Unique Customers</th></tr>';
                        var tbody = '';
                        
                        if (response.data.length > 0) {
                            $.each(response.data, function(index, record) {
                                tbody += '<tr>';
                                tbody += '<td>' + record.exemption_type + '</td>';
                                tbody += '<td>' + record.exemption_count + '</td>';
                                tbody += '<td>' + record.unique_customers + '</td>';
                                tbody += '</tr>';
                            });
                        } else {
                            tbody = '<tr><td colspan="3" class="text-center">No data found for the selected period</td></tr>';
                        }
                        
                        $('#report-thead').html(thead);
                        $('#report-tbody').html(tbody);
                        $('#report-container').show();
                    } else {
                        alert('Error: ' + response.message);
                    }
                });
            });
        });
    </script>
</body>
</html>