<?php

session_start();
include('api/db.php');
include('includes/functions.php');
include('includes/pagination.php');



// include('includes/db_local.php');
// include('includes/functions.php');
// include('includes/pagination.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter query
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($_GET['date_from'])) {
    $where_conditions[] = "OpenHouseDate >= ?";
    $params[] = $_GET['date_from'];
    $param_types .= "s";
}

if (!empty($_GET['date_to'])) {
    $where_conditions[] = "OpenHouseDate <= ?";
    $params[] = $_GET['date_to'];
    $param_types .= "s";
}

if (!empty($_GET['listing_id'])) {
    $where_conditions[] = "L_ListingID LIKE ?";
    $params[] = '%' . $_GET['listing_id'] . '%';
    $param_types .= "s";
}

$filter_sql = "";
if (!empty($where_conditions)) {
    $filter_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count

$count_sql = "SELECT COUNT(*) as total FROM rets_openhouse_yu $filter_sql";

// $count_sql = "SELECT COUNT(*) as total FROM rets_openhouse $filter_sql";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}

$total = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// Get open house data
$sql = "SELECT L_ListingID, L_DisplayId, OpenHouseDate, OH_StartTime, OH_EndTime, 
        OH_StartDate, OH_EndDate, updated_date, created_at, all_data

        FROM rets_openhouse_yu $filter_sql 

        ORDER BY OpenHouseDate ASC, OH_StartTime ASC 
        LIMIT $limit OFFSET $offset";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$openhouses = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $all_data = json_decode($row['all_data'], true);
        
        $openhouses[] = [
            'listing_id' => $row['L_ListingID'],
            'display_id' => $row['L_DisplayId'],
            'date' => $row['OpenHouseDate'],
            'start_time' => $row['OH_StartTime'],
            'end_time' => $row['OH_EndTime'],
            'start_date' => $row['OH_StartDate'],
            'end_date' => $row['OH_EndDate'],
            'updated_date' => $row['updated_date'],
            'created_at' => $row['created_at'],
            'all_data' => $all_data,
            'remarks' => $all_data['OpenHouseRemarks'] ?? '',
            'status' => $all_data['OpenHouseStatus'] ?? '',
            'attended_by' => $all_data['OpenHouseAttendedBy'] ?? '',
            'refreshments' => $all_data['Refreshments'] ?? '',
            'showing_agent_first' => $all_data['ShowingAgentFirstName'] ?? '',
            'showing_agent_last' => $all_data['ShowingAgentLastName'] ?? '',
            'livestream_url' => $all_data['LivestreamOpenHouseURL'] ?? ''
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>California Homes - Open Houses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8fafc;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        nav a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        nav a:hover {
            opacity: 0.8;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 300;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Search Form */
        .search-section {
            background: white;
            padding: 2rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            margin-top: -2rem;
            position: relative;
            z-index: 10;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .search-btn:hover {
            transform: translateY(-2px);
        }

        /* Stats Section */
        .stats {
            background: white;
            padding: 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            margin-top: 2rem;

        }

        .stats-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        /* Results Section */
        .results-section {
            padding: 2rem 0;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .results-count {
            font-size: 1.1rem;
            color: #666;
        }

        /* Open House Cards */
        .openhouse-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .openhouse-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .openhouse-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .openhouse-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }

        .openhouse-date {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .openhouse-time {
            font-size: 1rem;
            opacity: 0.9;
        }

        .openhouse-content {
            padding: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            font-weight: bold;
            color: #666;
        }

        .detail-value {
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            background: white;
            color: #666;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .page-btn:hover,
        .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 0;
            color: #666;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .empty-state p {
            margin-bottom: 2rem;
        }

        .sync-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }

        .sync-btn:hover {
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            nav ul {
                gap: 1rem;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .openhouse-grid {
                grid-template-columns: 1fr;
            }

            .stats-content {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .hero {
                padding: 2rem 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">

            <a href="index.php" style="text-decoration: none;">
            <div class="logo" style="display: flex; align-items: center; gap: 2px;">
                <img src="assets/white-logo.png" alt="California Homes Logo" style="height: 48px; width: auto; display: inline-block; vertical-align: middle;" />
                <div style="width:2px; height:32px; background:white; margin:0 10px; border-radius:2px;"></div>
                <span style="font-size: 1.5rem; font-weight: bold; color: white; margin-left: 0;">California Homes</span>
            </div>
            </a>           
            <nav>
                    <ul>
                    <li><a href="index.php">Homes for Sale</a></li>
                    <li><a href="openhouse.php">Open Houses</a></li>
                    </ul>
                </nav>
            </div>
        </div>
        
        <?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Open Houses</h1>
            <p>Discover upcoming open houses across California's most desirable neighborhoods and communities.</p>
        </div>
    </section>

    <section class="search-section">
        <div class="container">
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label for="listing_id">Listing ID</label>
                    <input type="text" id="listing_id" name="listing_id" placeholder="Search by listing ID" value="<?php echo $_GET['listing_id'] ?? ''; ?>">
                </div>
                <div class="form-group">


                    <button type="submit" class="search-btn">Search</button>

                </div>
            </form>
        </div>
    </section>

    <section class="stats">
        <div class="container">
            <div class="stats-content">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($total); ?></div>
                    <div class="stat-label">Total Open Houses</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($openhouses); ?></div>
                    <div class="stat-label">Showing</div>
                </div>
                <?php if (!empty($openhouses)): ?>
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('M j', strtotime($openhouses[0]['date'])); ?> - <?php echo date('M j', strtotime($openhouses[count($openhouses)-1]['date'])); ?></div>
                    <div class="stat-label">Date Range</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="results-section">
        <div class="container">
            <div class="results-header">
                <div class="results-count">Showing <?php echo count($openhouses); ?> of <?php echo number_format($total); ?> California open houses</div>
            </div>

            <?php if (empty($openhouses)): ?>
            <div class="empty-state">
                <h3>No open houses found</h3>
                <p>Try running the sync script to import open houses from the Trestle API.</p>
                <a href="sync_openhouse.php" class="sync-btn">Sync Open Houses</a>
            </div>
            <?php else: ?>
            <div class="openhouse-grid">
                <?php foreach($openhouses as $oh): ?>
                <div class="openhouse-card">
                    <div class="openhouse-header">
                        <div class="openhouse-date">
                            <?php echo date('l, F j, Y', strtotime($oh['date'])); ?>
                        </div>
                        <div class="openhouse-time">
                            <?php 
                            if ($oh['start_time'] && $oh['end_time']) {
                                echo date('g:i A', strtotime($oh['start_time'])) . ' - ' . date('g:i A', strtotime($oh['end_time']));
                            }
                            ?>
                        </div>
                        <?php if (!empty($oh['status'])): ?>
                        <div class="status-badge status-<?php echo strtolower($oh['status']); ?>">
                            <?php echo htmlspecialchars($oh['status']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="openhouse-content">
                        <div class="detail-row">
                            <span class="detail-label">Listing ID:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($oh['listing_id']); ?></span>
                        </div>
                        
                        <?php if (!empty($oh['showing_agent_first']) || !empty($oh['showing_agent_last'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Showing Agent:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($oh['showing_agent_first'] . ' ' . $oh['showing_agent_last']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($oh['attended_by'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Attended By:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($oh['attended_by']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($oh['refreshments'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Refreshments:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($oh['refreshments']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($oh['remarks'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Remarks:</span>
                            <span class="detail-value"><?php echo htmlspecialchars(substr($oh['remarks'], 0, 200)); ?><?php echo strlen($oh['remarks']) > 200 ? '...' : ''; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($oh['livestream_url'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Livestream:</span>
                            <span class="detail-value">
                                <a href="<?php echo htmlspecialchars($oh['livestream_url']); ?>" target="_blank" style="color: #667eea; text-decoration: none;">Watch Live</a>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <span class="detail-label">Added:</span>
                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($oh['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total > $limit): ?>
            <div class="pagination">
                <?php echo paginate($total, $limit, $page, '?'.http_build_query(array_merge($_GET, ['page' => null])) . '&'); ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-4">
          <div class="modal-header">
            <h5 class="modal-title">Create Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="registerModalBody">
            Loading form...
          </div>
        </div>
      </div>
    </div>
    
    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content p-3">
          <div class="modal-header">
            <h5 class="modal-title" id="loginModalLabel">Login to Your Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Content from login_form.php will be loaded here -->
            <div id="login-form-container">Loading...</div>
          </div>
        </div>
      </div>
    </div>

     <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
          <div class="modal-header">
            <h5 class="modal-title" id="forgotPasswordModalLabel">Reset Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="forgotPasswordForm">
            <div class="modal-body">
              <div class="mb-3">
                <label for="resetEmail" class="form-label">Enter your email address</label>
                <input type="email" class="form-control" id="resetEmail" name="email" required>
              </div>
              <div id="resetFeedback" class="mt-2 text-center"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
              <button type="submit" class="btn btn-primary">Send Reset Link</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<script>
function toggleDashboard() {
    const menu = document.getElementById('userDashboard');
    const isOpen = menu.style.right === '0px';
    menu.style.right = isOpen ? '-320px' : '0px';
}

document.addEventListener('click', function(event) {
    const dashboard = document.getElementById('userDashboard');
    const icon = document.getElementById('menuIcon');
    if (!dashboard.contains(event.target) && !icon.contains(event.target)) {
        dashboard.style.right = '-320px';
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const regModal = document.getElementById('registerModal');
  regModal.addEventListener('show.bs.modal', function () {
    fetch('register_form.php')
      .then(res => res.text())
      .then(html => {
        document.getElementById('registerModalBody').innerHTML = html;

        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          const formData = new FormData(form);

          fetch('register.php', {
            method: 'POST',
            body: formData
          })
          .then(res => res.json())
          .then(data => {
            const msg = document.getElementById('reg-message');
            if (data.success) {
              msg.classList.remove('text-danger');
              msg.classList.add('text-success');
              msg.textContent = "Success! Reloading...";
              setTimeout(() => location.reload(), 1000);
            } else {
              msg.textContent = data.message;
            }
          });
        });
      });
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const loginModal = document.getElementById('loginModal');
  loginModal.addEventListener('show.bs.modal', function () {
    fetch('login_form.php')
      .then(res => res.text())
      .then(html => {
        document.getElementById('login-form-container').innerHTML = html;
        attachLoginSubmit();
      });
  });

  function attachLoginSubmit() {
    const form = document.getElementById('loginForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const formData = new FormData(form);

      fetch('login.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        const msg = document.getElementById('login-message');
        if (data.success) {
          msg.classList.remove('text-danger');
          msg.classList.add('text-success');
          msg.textContent = "Login successful. Redirecting...";
          setTimeout(() => window.location.href = "openhouse.php", 1000);
        } else {
          msg.classList.remove('text-success');
          msg.classList.add('text-danger');
          msg.textContent = data.message;
        }
      });
    });
  }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("forgotPasswordForm");
  const feedback = document.getElementById("resetFeedback");

  form.addEventListener("submit", function (e) {
    e.preventDefault(); 
    feedback.innerText = "Sending...";

    const formData = new FormData(form);

    fetch("forgot_password.php", {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        feedback.className = "text-success text-center mt-2";
        feedback.innerText = "Reset link sent successfully.";
      } else {
        feedback.className = "text-danger text-center mt-2";
        feedback.innerText = data.message || "Failed to send reset link.";
      }
    })
    .catch(() => {
      feedback.className = "text-danger text-center mt-2";
      feedback.innerText = "Something went wrong.";
    });
  });
});
</script>