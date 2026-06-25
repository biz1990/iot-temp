<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT-SRS Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; }
        
        /* Header */
        .header { background: #2c3e50; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.5rem; }
        .header-nav { display: flex; gap: 1rem; align-items: center; }
        .header-nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; }
        .header-nav a:hover { background: #34495e; }
        .user-info { background: #34495e; padding: 0.5rem 1rem; border-radius: 4px; }
        
        /* Main Content */
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 2rem; font-weight: bold; color: #2c3e50; }
        .stat-card.online .value { color: #27ae60; }
        .stat-card.alerts .value { color: #e74c3c; }
        
        /* Device Grid */
        .devices-section { margin-bottom: 2rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .section-header h2 { color: #2c3e50; }
        .filter-buttons { display: flex; gap: 0.5rem; }
        .filter-btn { padding: 0.5rem 1rem; border: 1px solid #bdc3c7; background: white; border-radius: 4px; cursor: pointer; }
        .filter-btn.active { background: #3498db; color: white; border-color: #3498db; }
        
        .devices-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .device-card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .device-card-header { padding: 1rem; background: #ecf0f1; display: flex; justify-content: space-between; align-items: center; }
        .device-card-header h3 { font-size: 1.1rem; color: #2c3e50; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .status-badge.online { background: #27ae60; color: white; }
        .status-badge.offline { background: #95a5a6; color: white; }
        .status-badge.alert { background: #e74c3c; color: white; }
        
        .device-card-body { padding: 1rem; }
        .sensor-readings { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .reading { flex: 1; text-align: center; padding: 0.75rem; background: #f8f9fa; border-radius: 4px; }
        .reading .label { font-size: 0.8rem; color: #7f8c8d; }
        .reading .value { font-size: 1.5rem; font-weight: bold; color: #2c3e50; }
        .reading .unit { font-size: 0.8rem; color: #7f8c8d; }
        
        .device-info { font-size: 0.9rem; color: #7f8c8d; margin-bottom: 0.5rem; }
        .device-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; }
        .btn-primary { background: #3498db; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn:hover { opacity: 0.9; }
        
        /* Alerts Panel */
        .alerts-panel { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .alerts-header { padding: 1rem; background: #ffeaa7; border-bottom: 1px solid #fdcb6e; }
        .alerts-header h3 { color: #d63031; display: flex; align-items: center; gap: 0.5rem; }
        .alerts-list { padding: 1rem; }
        .alert-item { padding: 1rem; border-left: 4px solid #e74c3c; background: #fff5f5; margin-bottom: 1rem; border-radius: 0 4px 4px 0; }
        .alert-item .alert-title { font-weight: bold; margin-bottom: 0.5rem; }
        .alert-item .alert-details { font-size: 0.9rem; color: #7f8c8d; }
        .alert-actions { margin-top: 0.75rem; display: flex; gap: 0.5rem; }
        
        /* Chart Section */
        .chart-section { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 2rem; }
        .chart-container { height: 300px; position: relative; }
        
        /* Login Form */
        .login-container { max-width: 400px; margin: 4rem auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .login-container h2 { text-align: center; margin-bottom: 1.5rem; color: #2c3e50; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #2c3e50; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #bdc3c7; border-radius: 4px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        .btn-login { width: 100%; padding: 0.75rem; background: #3498db; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
        .btn-login:hover { background: #2980b9; }
        .error-message { background: #ffeaa7; color: #d63031; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; display: none; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; max-width: 600px; margin: 2rem auto; padding: 2rem; border-radius: 8px; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .devices-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php
    session_start();
    $isLoggedIn = isset($_SESSION['user_id']);
    
    if (!$isLoggedIn && basename($_SERVER['PHP_SELF']) !== 'index.php') {
        header('Location: index.php');
        exit;
    }
    ?>
    
    <?php if ($isLoggedIn): ?>
    <div class="header">
        <h1>🌡️ IoT-SRS Dashboard</h1>
        <div class="header-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="devices.php">Devices</a>
            <a href="alerts.php">Alerts</a>
            <a href="export.php">Export</a>
            <div class="user-info">
                👤 <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['user_role']) ?>)
            </div>
            <a href="api/logout.php" onclick="return confirm('Logout?')">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3>Total Devices</h3>
                <div class="value" id="totalDevices">-</div>
            </div>
            <div class="stat-card online">
                <h3>Online Devices</h3>
                <div class="value" id="onlineDevices">-</div>
            </div>
            <div class="stat-card alerts">
                <h3>Active Alerts</h3>
                <div class="value" id="activeAlerts">-</div>
            </div>
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value" id="totalUsers">-</div>
            </div>
        </div>
        
        <!-- Active Alerts -->
        <div class="alerts-panel" id="alertsPanel" style="display:none;">
            <div class="alerts-header">
                <h3>⚠️ Active Alerts</h3>
            </div>
            <div class="alerts-list" id="alertsList"></div>
        </div>
        
        <!-- Devices -->
        <div class="devices-section">
            <div class="section-header">
                <h2>📱 Devices</h2>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="online">Online</button>
                    <button class="filter-btn" data-filter="offline">Offline</button>
                    <button class="filter-btn" data-filter="alerts">With Alerts</button>
                </div>
            </div>
            <div class="devices-grid" id="devicesGrid"></div>
        </div>
        
        <!-- Chart -->
        <div class="chart-section">
            <div class="section-header">
                <h2>📈 Real-time Chart</h2>
                <select id="chartDevice" style="padding: 0.5rem;">
                    <option value="">Select Device</option>
                </select>
            </div>
            <div class="chart-container">
                <canvas id="sensorChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let currentFilter = 'all';
        let sensorChart = null;
        
        // Load dashboard data
        async function loadDashboard() {
            try {
                const response = await fetch('api/dashboard.php?action=summary');
                const result = await response.json();
                
                if (result.success) {
                    updateStats(result.data.statistics);
                    renderDevices(result.data.devices);
                    updateChartDropdown(result.data.devices);
                }
            } catch (error) {
                console.error('Error loading dashboard:', error);
            }
        }
        
        // Update statistics
        function updateStats(stats) {
            document.getElementById('totalDevices').textContent = stats.TotalDevices || 0;
            document.getElementById('onlineDevices').textContent = stats.OnlineDevices || 0;
            document.getElementById('activeAlerts').textContent = stats.ActiveAlerts || 0;
            document.getElementById('totalUsers').textContent = stats.TotalUsers || 0;
        }
        
        // Render devices
        function renderDevices(devices) {
            const grid = document.getElementById('devicesGrid');
            let filtered = devices;
            
            if (currentFilter === 'online') {
                filtered = devices.filter(d => d.IsOnline == 1);
            } else if (currentFilter === 'offline') {
                filtered = devices.filter(d => d.IsOnline == 0);
            } else if (currentFilter === 'alerts') {
                filtered = devices.filter(d => d.ActiveAlertCount > 0);
            }
            
            grid.innerHTML = filtered.map(device => `
                <div class="device-card">
                    <div class="device-card-header">
                        <h3>${escapeHtml(device.DeviceName)}</h3>
                        <span class="status-badge ${device.ActiveAlertCount > 0 ? 'alert' : (device.IsOnline == 1 ? 'online' : 'offline')}">
                            ${device.ActiveAlertCount > 0 ? 'ALERT' : (device.IsOnline == 1 ? 'ONLINE' : 'OFFLINE')}
                        </span>
                    </div>
                    <div class="device-card-body">
                        <div class="device-info">📍 ${escapeHtml(device.Location || 'No location')}</div>
                        <div class="sensor-readings">
                            <div class="reading">
                                <div class="label">Temperature</div>
                                <div class="value">${device.Temperature !== null ? device.Temperature.toFixed(1) : '--'}</div>
                                <div class="unit">°C</div>
                            </div>
                            <div class="reading">
                                <div class="label">Humidity</div>
                                <div class="value">${device.Humidity !== null ? device.Humidity.toFixed(1) : '--'}</div>
                                <div class="unit">%</div>
                            </div>
                        </div>
                        <div class="device-info">Last seen: ${formatDate(device.LastSeen)}</div>
                        <div class="device-actions">
                            <button class="btn btn-primary" onclick="viewChart(${device.DeviceID})">View Chart</button>
                            <button class="btn btn-success" onclick="exportData(${device.DeviceID})">Export</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        // Update chart dropdown
        function updateChartDropdown(devices) {
            const select = document.getElementById('chartDevice');
            select.innerHTML = '<option value="">Select Device</option>' + 
                devices.map(d => `<option value="${d.DeviceID}">${escapeHtml(d.DeviceName)}</option>`).join('');
        }
        
        // Load alerts
        async function loadAlerts() {
            try {
                const response = await fetch('api/dashboard.php?action=alerts');
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    document.getElementById('alertsPanel').style.display = 'block';
                    document.getElementById('alertsList').innerHTML = result.data.map(alert => `
                        <div class="alert-item">
                            <div class="alert-title">
                                ⚠️ ${alert.MetricType} ${alert.AlertType} - ${escapeHtml(alert.DeviceName)}
                            </div>
                            <div class="alert-details">
                                Value: ${alert.CurrentValue} | Threshold: ${alert.ThresholdValue} | 
                                Triggered: ${formatDate(alert.TriggeredAt)}
                            </div>
                            <div class="alert-actions">
                                <button class="btn btn-primary" onclick="acknowledgeAlert(${alert.AlertID})">Acknowledge</button>
                                <button class="btn btn-success" onclick="resolveAlert(${alert.AlertID})">Resolve</button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    document.getElementById('alertsPanel').style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading alerts:', error);
            }
        }
        
        // View chart for device
        async function viewChart(deviceId) {
            try {
                const response = await fetch(`api/dashboard.php?action=chart&device_id=${deviceId}&hours=24`);
                const result = await response.json();
                
                if (result.success) {
                    const ctx = document.getElementById('sensorChart').getContext('2d');
                    const labels = result.data.readings.map(r => new Date(r.Timestamp).toLocaleTimeString());
                    const tempData = result.data.readings.map(r => r.Temperature);
                    const humidData = result.data.readings.map(r => r.Humidity);
                    
                    if (sensorChart) {
                        sensorChart.destroy();
                    }
                    
                    sensorChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels.reverse(),
                            datasets: [
                                {
                                    label: 'Temperature (°C)',
                                    data: tempData.reverse(),
                                    borderColor: '#e74c3c',
                                    tension: 0.4,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'Humidity (%)',
                                    data: humidData.reverse(),
                                    borderColor: '#3498db',
                                    tension: 0.4,
                                    yAxisID: 'y1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: { display: true, text: 'Temperature (°C)' }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    title: { display: true, text: 'Humidity (%)' }
                                }
                            }
                        }
                    });
                    
                    document.getElementById('chartDevice').value = deviceId;
                }
            } catch (error) {
                console.error('Error loading chart:', error);
            }
        }
        
        // Export data
        function exportData(deviceId) {
            window.open(`api/export.php?type=csv&device_id=${deviceId}`, '_blank');
        }
        
        // Acknowledge alert
        async function acknowledgeAlert(alertId) {
            if (!confirm('Acknowledge this alert?')) return;
            
            try {
                const response = await fetch('api/alerts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ alert_id: alertId, action: 'acknowledge' })
                });
                const result = await response.json();
                
                if (result.success) {
                    loadDashboard();
                    loadAlerts();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error acknowledging alert:', error);
            }
        }
        
        // Resolve alert
        async function resolveAlert(alertId) {
            if (!confirm('Resolve this alert?')) return;
            
            try {
                const response = await fetch('api/alerts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ alert_id: alertId, action: 'resolve' })
                });
                const result = await response.json();
                
                if (result.success) {
                    loadDashboard();
                    loadAlerts();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Error resolving alert:', error);
            }
        }
        
        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '--';
            return new Date(dateStr).toLocaleString();
        }
        
        // Event listeners
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentFilter = btn.dataset.filter;
                loadDashboard();
            });
        });
        
        document.getElementById('chartDevice').addEventListener('change', (e) => {
            if (e.target.value) {
                viewChart(e.target.value);
            }
        });
        
        // Initialize
        loadDashboard();
        loadAlerts();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            loadDashboard();
            loadAlerts();
        }, 30000);
    </script>
    
    <?php else: ?>
    <!-- Login Page -->
    <div class="login-container">
        <h2>🌡️ IoT-SRS Login</h2>
        <div class="error-message" id="errorMessage"></div>
        <form id="loginForm">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    document.getElementById('errorMessage').textContent = result.error;
                    document.getElementById('errorMessage').style.display = 'block';
                }
            } catch (error) {
                document.getElementById('errorMessage').textContent = 'Connection error';
                document.getElementById('errorMessage').style.display = 'block';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
