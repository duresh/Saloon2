<?php
ob_start();
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/dashboard.php');
    exit();
}

// Include database connection
require_once '../../includes/dbcon.php';
require_once '../../includes/helpers.php';

$date_range = isset($_GET['range']) ? $_GET['range'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set date range based on selection
if ($date_range == 'custom') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
} else {
    switch ($date_range) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case 'month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d');
            break;
        case 'year':
            $start_date = date('Y-01-01');
            $end_date = date('Y-m-d');
            break;
    }
}

include 'header/header-admin.php';
?>

<!-- SweetAlert2 for error messages -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Page Header -->
<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-chart-bar me-2" style="color: #6f42c1;"></i> Reports & Analytics</h1>
            <p class="lead mb-0">Comprehensive business insights and performance metrics</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Date Range Filter -->
<div class="filter-card">
    <div class="filter-header">
        <h5><i class="fas fa-calendar-alt me-2"></i> Date Range</h5>
    </div>
    <div class="filter-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label fw-bold">Quick Select</label>
                <select class="form-select" id="rangeSelect">
                    <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $date_range == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $date_range == 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="year" <?php echo $date_range == 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <div class="col-md-3 mb-3">
                <label class="form-label fw-bold">Start Date</label>
                <input type="date" class="form-control" id="startDate" value="<?php echo $start_date; ?>">
            </div>
            
            <div class="col-md-3 mb-3">
                <label class="form-label fw-bold">End Date</label>
                <input type="date" class="form-control" id="endDate" value="<?php echo $end_date; ?>">
            </div>
            
            <div class="col-md-3 mb-3 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" id="applyFiltersBtn">
                    <i class="fas fa-filter me-2"></i>Apply Filter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Indicator -->
<div id="loadingIndicator" class="text-center py-4" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2">Loading report data...</p>
</div>

<!-- Key Metrics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value" id="totalAppointments">--</div>
            <div class="stat-label">Total Appointments</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value" id="completedAppointments">--</div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value" id="cancelledAppointments">--</div>
            <div class="stat-label">Cancelled</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
            <i class="fas fa-rupee-sign"></i>
        </div>
        <div class="stat-details">
            <div class="stat-value" id="totalRevenue">Rs: --</div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row mt-4">
    <div class="col-lg-8 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i> Daily Appointments Trend</h5>
            </div>
            <div class="chart-container">
                <canvas id="dailyChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i> Appointment Status</h5>
            </div>
            <div class="chart-container">
                <canvas id="statusChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row mt-4">
    <div class="col-lg-6 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i> Popular Services</h5>
            </div>
            <div class="chart-container">
                <canvas id="popularServicesChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-donut me-2"></i> Revenue by Category</h5>
            </div>
            <div class="chart-container">
                <canvas id="categoryRevenueChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 3 -->
<div class="row mt-4">
    <div class="col-lg-12 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i> Monthly Trends</h5>
            </div>
            <div class="chart-container">
                <canvas id="monthlyTrendsChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 4 -->
<div class="row mt-4">
    <div class="col-lg-6 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-clock me-2"></i> Peak Hours Analysis</h5>
            </div>
            <div class="chart-container">
                <canvas id="hourlyChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="chart-card">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i> Customer Retention</h5>
            </div>
            <div class="chart-container">
                <canvas id="retentionChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Staff Performance Table -->
<div class="table-card mt-4">
    <div class="table-header">
        <h5><i class="fas fa-trophy me-2 text-warning"></i> Staff Performance</h5>
    </div>
    <div class="table-responsive">
        <table class="performance-table" id="staffPerformanceTable">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Specialization</th>
                    <th>Appointments</th>
                    <th>Completed</th>
                    <th>Cancelled</th>
                    <th>Rating</th>
                    <th>Revenue</th>
                 </tr>
            </thead>
            <tbody>
                <tr><td colspan="7" class="text-center py-4">Loading data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Category Distribution Table -->
<div class="table-card mt-4">
    <div class="table-header">
        <h5><i class="fas fa-tags me-2"></i> Category Performance</h5>
    </div>
    <div class="table-responsive">
        <table class="performance-table" id="categoryTable">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Total Bookings</th>
                    <th>Total Revenue</th>
                    <th>Unique Customers</th>
                    <th>Average per Booking</th>
                </tr>
            </thead>
            <tbody>
                <td><td colspan="5" class="text-center py-4">Loading data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Global chart instances
let dailyChart = null;
let statusChart = null;
let popularServicesChart = null;
let categoryRevenueChart = null;
let monthlyTrendsChart = null;
let hourlyChart = null;
let retentionChart = null;

const chartColors = {
    primary: '#6f42c1',
    success: '#28a745',
    danger: '#dc3545',
    warning: '#ffc107',
    info: '#17a2b8'
};

// Initialize when document is ready
$(document).ready(function() {
    // Set initial disabled state for date inputs
    if ($('#rangeSelect').val() !== 'custom') {
        $('#startDate, #endDate').prop('disabled', true);
    }
    
    // Load initial data
    loadReportData();
    
    // Apply filters button click
    $('#applyFiltersBtn').click(function() {
        loadReportData();
    });
    
    // Range select change
    $('#rangeSelect').change(function() {
        const val = $(this).val();
        if (val === 'custom') {
            $('#startDate, #endDate').prop('disabled', false);
        } else {
            $('#startDate, #endDate').prop('disabled', true);
            loadReportData();
        }
    });
});

function loadReportData() {
    const range = $('#rangeSelect').val();
    let startDate = $('#startDate').val();
    let endDate = $('#endDate').val();
    
    // If not custom range, don't send dates (let server handle it)
    if (range !== 'custom') {
        startDate = '';
        endDate = '';
    }
    
    // Show loading indicator
    $('#loadingIndicator').show();
    $('.stats-grid, .chart-card, .table-card').css('opacity', '0.5');
    $('.chart-card canvas').css('pointer-events', 'none');
    
    // Prepare form data
    const formData = new FormData();
    formData.append('range', range);
    if (startDate) formData.append('start_date', startDate);
    if (endDate) formData.append('end_date', endDate);
    
    $.ajax({
        url: 'ajax/get-report-data.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 30000
    })
    .done(function(response) {
        $('#loadingIndicator').hide();
        $('.stats-grid, .chart-card, .table-card').css('opacity', '1');
        $('.chart-card canvas').css('pointer-events', 'auto');
        
        if (response.success) {
            // Update metrics
            updateMetrics(response.metrics);
            
            // Update Daily Chart
            if (response.daily_data && response.daily_data.length > 0) {
                updateDailyChart(response.daily_data);
            } else {
                showNoDataMessage('dailyChart', 'No appointment data for this period');
            }
            
            // Update Status Chart
            if (response.status_data) {
                updateStatusChart(response.status_data);
            } else {
                showNoDataMessage('statusChart', 'No status data available');
            }
            
            // Update Popular Services Chart
            if (response.popular_services && response.popular_services.length > 0) {
                updatePopularServicesChart(response.popular_services);
            } else {
                showNoDataMessage('popularServicesChart', 'No service booking data');
            }
            
            // Update Category Revenue Chart
            if (response.category_revenue && response.category_revenue.length > 0) {
                updateCategoryRevenueChart(response.category_revenue);
            } else {
                showNoDataMessage('categoryRevenueChart', 'No category revenue data');
            }
            
            // Update Monthly Trends Chart
            if (response.monthly_trends && response.monthly_trends.length > 0) {
                updateMonthlyTrendsChart(response.monthly_trends);
            } else {
                showNoDataMessage('monthlyTrendsChart', 'No monthly trend data');
            }
            
            // Update Hourly Chart
            if (response.hourly_data && response.hourly_data.length > 0) {
                updateHourlyChart(response.hourly_data);
            } else {
                showNoDataMessage('hourlyChart', 'No hourly booking data');
            }
            
            // Update Retention Chart
            if (response.retention_data && response.retention_data.length > 0) {
                updateRetentionChart(response.retention_data);
            } else {
                showNoDataMessage('retentionChart', 'No customer retention data');
            }
            
            // Update Staff Performance Table
            if (response.staff_performance && response.staff_performance.length > 0) {
                updateStaffPerformanceTable(response.staff_performance);
            } else {
                $('#staffPerformanceTable tbody').html('<tr><td colspan="7" class="text-center py-4 text-muted">No staff performance data available</td></tr>');
            }
            
            // Update Category Table
            if (response.category_performance && response.category_performance.length > 0) {
                updateCategoryTable(response.category_performance);
            } else {
                $('#categoryTable tbody').html('<tr><td colspan="5" class="text-center py-4 text-muted">No category performance data available</td></tr>');
            }
        } else {
            showError(response.message || 'Failed to load report data');
        }
    })
    .fail(function(xhr, status, error) {
        $('#loadingIndicator').hide();
        $('.stats-grid, .chart-card, .table-card').css('opacity', '1');
        $('.chart-card canvas').css('pointer-events', 'auto');
        
        console.error('AJAX Error:', status, error);
        console.error('Response:', xhr.responseText);
        
        let errorMessage = 'Failed to load report data. ';
        if (status === 'timeout') {
            errorMessage += 'Request timed out. Please try again.';
        } else if (status === 'parsererror') {
            errorMessage += 'Invalid response from server. Please check the console for details.';
        } else if (xhr.status === 404) {
            errorMessage += 'API endpoint not found. Please check the file path.';
        } else if (xhr.status === 500) {
            errorMessage += 'Server error. Please try again later.';
        } else {
            errorMessage += 'Please check your connection and try again.';
        }
        
        showError(errorMessage);
        
        // Show empty states
        showNoDataMessage('dailyChart', 'Unable to load data');
        showNoDataMessage('statusChart', 'Unable to load data');
        showNoDataMessage('popularServicesChart', 'Unable to load data');
        showNoDataMessage('categoryRevenueChart', 'Unable to load data');
        showNoDataMessage('monthlyTrendsChart', 'Unable to load data');
        showNoDataMessage('hourlyChart', 'Unable to load data');
        showNoDataMessage('retentionChart', 'Unable to load data');
        
        $('#staffPerformanceTable tbody').html('<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load staff data</td></tr>');
        $('#categoryTable tbody').html('<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load category data</td></tr>');
    });
}

function updateMetrics(metrics) {
    if (!metrics) {
        $('#totalAppointments').text('0');
        $('#completedAppointments').text('0');
        $('#cancelledAppointments').text('0');
        $('#totalRevenue').text('Rs: 0.00');
        return;
    }
    
    $('#totalAppointments').text(formatNumber(parseInt(metrics.total_appointments) || 0));
    $('#completedAppointments').text(formatNumber(parseInt(metrics.completed_count) || 0));
    $('#cancelledAppointments').text(formatNumber(parseInt(metrics.cancelled_count) || 0));
    $('#totalRevenue').text('Rs: ' + formatNumber(parseFloat(metrics.total_revenue) || 0, 2));
}

function updateDailyChart(data) {
    const canvas = document.getElementById('dailyChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    if (dailyChart) {
        dailyChart.destroy();
        dailyChart = null;
    }
    
    const labels = data.map(item => item.date);
    const totals = data.map(item => parseInt(item.total) || 0);
    const completed = data.map(item => parseInt(item.completed) || 0);
    const cancelled = data.map(item => parseInt(item.cancelled) || 0);
    
    dailyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { 
                    label: 'Total', 
                    data: totals, 
                    borderColor: chartColors.primary, 
                    backgroundColor: 'rgba(111,66,193,0.1)', 
                    tension: 0.4, 
                    fill: true,
                    pointBackgroundColor: chartColors.primary,
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                { 
                    label: 'Completed', 
                    data: completed, 
                    borderColor: chartColors.success, 
                    backgroundColor: 'rgba(40,167,69,0.1)', 
                    tension: 0.4, 
                    fill: true,
                    pointBackgroundColor: chartColors.success,
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                { 
                    label: 'Cancelled', 
                    data: cancelled, 
                    borderColor: chartColors.danger, 
                    backgroundColor: 'rgba(220,53,69,0.05)', 
                    tension: 0.4, 
                    fill: true,
                    pointBackgroundColor: chartColors.danger,
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            interaction: { mode: 'index', intersect: false },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { stepSize: 1 },
                    title: { display: true, text: 'Number of Appointments' }
                },
                x: { title: { display: true, text: 'Date' } }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });
}

function updateStatusChart(data) {
    const canvas = document.getElementById('statusChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (statusChart) {
        statusChart.destroy();
        statusChart = null;
    }
    
    const values = [
        parseInt(data.pending_count) || 0,
        parseInt(data.confirmed_count) || 0,
        parseInt(data.completed_count) || 0,
        parseInt(data.cancelled_count) || 0
    ];
    
    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: { 
            labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'], 
            datasets: [{ 
                data: values, 
                backgroundColor: [chartColors.warning, chartColors.info, chartColors.success, chartColors.danger], 
                borderWidth: 0,
                hoverOffset: 10
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw} (${((ctx.raw / values.reduce((a,b)=>a+b,0)) * 100).toFixed(1)}%)` } }
            } 
        }
    });
}

function updatePopularServicesChart(data) {
    const canvas = document.getElementById('popularServicesChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (popularServicesChart) {
        popularServicesChart.destroy();
        popularServicesChart = null;
    }
    
    const labels = data.slice(0, 8).map(item => item.name);
    const bookings = data.slice(0, 8).map(item => parseInt(item.booking_count) || 0);
    
    popularServicesChart = new Chart(ctx, {
        type: 'bar',
        data: { 
            labels: labels, 
            datasets: [{ 
                label: 'Bookings', 
                data: bookings, 
                backgroundColor: chartColors.primary, 
                borderRadius: 8,
                barPercentage: 0.7
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            scales: { 
                y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Number of Bookings' } },
                x: { ticks: { autoSkip: true, maxRotation: 45, minRotation: 45 } }
            },
            plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} bookings` } } }
        }
    });
}

function updateCategoryRevenueChart(data) {
    const canvas = document.getElementById('categoryRevenueChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (categoryRevenueChart) {
        categoryRevenueChart.destroy();
        categoryRevenueChart = null;
    }
    
    const labels = data.map(item => item.category);
    const revenues = data.map(item => parseFloat(item.total_revenue) || 0);
    const revenueColors = ['#6f42c1', '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#fd7e14', '#20c997', '#6c757d'];
    
    categoryRevenueChart = new Chart(ctx, {
        type: 'pie',
        data: { 
            labels: labels, 
            datasets: [{ 
                data: revenues, 
                backgroundColor: revenueColors.slice(0, labels.length), 
                borderWidth: 0,
                hoverOffset: 10
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            plugins: { 
                legend: { position: 'bottom' },
                tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatCurrency(ctx.raw)}` } }
            } 
        }
    });
}

function updateMonthlyTrendsChart(data) {
    const canvas = document.getElementById('monthlyTrendsChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (monthlyTrendsChart) {
        monthlyTrendsChart.destroy();
        monthlyTrendsChart = null;
    }
    
    const labels = data.map(item => item.month);
    const appointments = data.map(item => parseInt(item.completed_appointments) || 0);
    const revenues = data.map(item => parseFloat(item.revenue) || 0);
    
    monthlyTrendsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { 
                    label: 'Appointments', 
                    data: appointments, 
                    borderColor: chartColors.primary, 
                    backgroundColor: 'rgba(111,66,193,0.1)', 
                    tension: 0.3, 
                    fill: true, 
                    yAxisID: 'y',
                    pointBackgroundColor: chartColors.primary,
                    pointRadius: 4
                },
                { 
                    label: 'Revenue (Rs)', 
                    data: revenues, 
                    borderColor: chartColors.success, 
                    backgroundColor: 'rgba(40,167,69,0.1)', 
                    tension: 0.3, 
                    fill: true, 
                    yAxisID: 'y1',
                    pointBackgroundColor: chartColors.success,
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true, 
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { 
                y: { beginAtZero: true, title: { display: true, text: 'Appointments' }, grid: { drawOnChartArea: true } },
                y1: { position: 'right', beginAtZero: true, title: { display: true, text: 'Revenue (Rs)' }, grid: { drawOnChartArea: false } }
            },
            plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.dataset.label === 'Revenue (Rs)' ? formatCurrency(ctx.raw) : ctx.raw}` } } }
        }
    });
}

function updateHourlyChart(data) {
    const canvas = document.getElementById('hourlyChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (hourlyChart) {
        hourlyChart.destroy();
        hourlyChart = null;
    }
    
    const labels = data.map(item => {
        const hour = parseInt(item.hour);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}${ampm}`;
    });
    const bookings = data.map(item => parseInt(item.bookings) || 0);
    
    hourlyChart = new Chart(ctx, {
        type: 'bar',
        data: { 
            labels: labels, 
            datasets: [{ 
                label: 'Bookings', 
                data: bookings, 
                backgroundColor: chartColors.warning, 
                borderRadius: 6,
                barPercentage: 0.8
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            scales: { 
                y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Number of Bookings' } },
                x: { ticks: { autoSkip: true, maxRotation: 45 } }
            },
            plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} bookings` } } }
        }
    });
}

function updateRetentionChart(data) {
    const canvas = document.getElementById('retentionChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (retentionChart) {
        retentionChart.destroy();
        retentionChart = null;
    }
    
    const labels = data.map(item => item.customer_type);
    const counts = data.map(item => parseInt(item.customer_count) || 0);
    const retentionColors = ['#6f42c1', '#28a745', '#ffc107', '#17a2b8'];
    
    retentionChart = new Chart(ctx, {
        type: 'bar',
        data: { 
            labels: labels, 
            datasets: [{ 
                label: 'Number of Customers', 
                data: counts, 
                backgroundColor: retentionColors.slice(0, labels.length), 
                borderRadius: 8,
                barPercentage: 0.6
            }] 
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false, 
            scales: { 
                y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Customers' } }
            },
            plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} customers` } } }
        }
    });
}

function updateStaffPerformanceTable(data) {
    const tbody = $('#staffPerformanceTable tbody');
    let html = '';
    
    if (!data || data.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No staff performance data available</td></tr>');
        return;
    }
    
    data.forEach(staff => {
        const avgRating = parseFloat(staff.avg_rating) || 0;
        let ratingStars = '';
        const fullStars = Math.floor(avgRating);
        const hasHalfStar = avgRating % 1 >= 0.5;
        
        for (let i = 1; i <= 5; i++) {
            if (i <= fullStars) {
                ratingStars += '<i class="fas fa-star text-warning"></i>';
            } else if (i === fullStars + 1 && hasHalfStar) {
                ratingStars += '<i class="fas fa-star-half-alt text-warning"></i>';
            } else {
                ratingStars += '<i class="far fa-star text-muted"></i>';
            }
        }
        
        html += `<tr>
            <td><strong>${escapeHtml(staff.staff_name)}</strong></td>
            <td>${escapeHtml(staff.specialization || 'General')}</td>
            <td>${parseInt(staff.total_appointments) || 0}</td>
            <td class="text-success">${parseInt(staff.completed_appointments) || 0}</td>
            <td class="text-danger">${parseInt(staff.cancelled_appointments) || 0}</td>
            <td><div class="rating-stars">${ratingStars} <span class="ms-1 text-muted">(${avgRating.toFixed(1)})</span></div></td>
            <td class="text-primary fw-bold">${formatCurrency(parseFloat(staff.total_revenue) || 0)}</td>
        </tr>`;
    });
    
    tbody.html(html);
}

function updateCategoryTable(data) {
    const tbody = $('#categoryTable tbody');
    let html = '';
    
    if (!data || data.length === 0) {
        tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">No category performance data available</td></tr>');
        return;
    }
    
    data.forEach(cat => {
        const totalBookings = parseInt(cat.total_bookings) || 0;
        const totalRevenue = parseFloat(cat.total_revenue) || 0;
        const avgPerBooking = totalBookings > 0 ? totalRevenue / totalBookings : 0;
        
        html += `<tr>
            <td><strong>${escapeHtml(cat.category)}</strong></td>
            <td>${formatNumber(totalBookings)}</td>
            <td class="text-primary fw-bold">${formatCurrency(totalRevenue)}</td>
            <td>${formatNumber(parseInt(cat.unique_customers) || 0)}</td>
            <td class="text-success">${formatCurrency(avgPerBooking)}</td>
        </tr>`;
    });
    
    tbody.html(html);
}

function showNoDataMessage(canvasId, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    
    // Instead of replacing canvas, clear the chart and show message
    const chartInstance = getChartInstance(canvasId);
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    // Clear canvas and show text
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.font = '14px Arial';
    ctx.fillStyle = '#999';
    ctx.textAlign = 'center';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2);
}

function getChartInstance(canvasId) {
    switch(canvasId) {
        case 'dailyChart': return dailyChart;
        case 'statusChart': return statusChart;
        case 'popularServicesChart': return popularServicesChart;
        case 'categoryRevenueChart': return categoryRevenueChart;
        case 'monthlyTrendsChart': return monthlyTrendsChart;
        case 'hourlyChart': return hourlyChart;
        case 'retentionChart': return retentionChart;
        default: return null;
    }
}

function formatNumber(num, decimals = 0) {
    if (isNaN(num)) return '0';
    return num.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function formatCurrency(amount) {
    return 'Rs: ' + formatNumber(amount, 2);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    Swal.fire({ 
        icon: 'error', 
        title: 'Error', 
        text: message, 
        confirmButtonColor: '#6f42c1',
        confirmButtonText: 'OK'
    });
}

function showSuccess(message) {
    Swal.fire({ 
        icon: 'success', 
        title: 'Success', 
        text: message, 
        confirmButtonColor: '#6f42c1',
        timer: 2000,
        showConfirmButton: false
    });
}

function refreshPage() { 
    location.reload(); 
}
</script>

<style>
/* Additional styles for reports page */
.filter-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.filter-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.filter-header h5 {
    margin: 0;
    color: #2c3e50;
}

.chart-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    height: 100%;
}

.chart-card .card-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.chart-card .card-header h5 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.1rem;
}

.chart-container {
    position: relative;
    min-height: 300px;
}

.table-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.table-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.table-header h5 {
    margin: 0;
    color: #2c3e50;
}

.performance-table {
    width: 100%;
    border-collapse: collapse;
}

.performance-table th {
    background: #f8f9fa;
    padding: 12px;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.performance-table td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

.performance-table tr:hover {
    background: #f8f9fa;
}

.rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 12px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-details {
    flex: 1;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1.2;
}

.stat-label {
    color: #6c757d;
    font-size: 13px;
    margin-top: 5px;
}

.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }
.text-primary { color: #6f42c1 !important; }
.text-muted { color: #6c757d !important; }
.fw-bold { font-weight: 600 !important; }

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 20px;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 20px;
    }
}
</style>
</body>
</html>
<?php ob_end_flush(); ?>