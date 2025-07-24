<?php
include ('includes/db_local.php');  // Use local database settings
include ('includes/functions.php');
include ('includes/pagination.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$filter_sql = build_filter_query($_GET);
$sort_sql = build_sort_query($_GET);

$count_sql = "SELECT COUNT(*) as total FROM rets_property $filter_sql";
$count_result = $conn->query($count_sql);
$total = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// Get the latest created_at from the entire table
$latest_created_at = null;
$latest_result = $conn->query('SELECT MAX(created_at) as latest_created_at FROM rets_property');
if ($latest_result && ($row = $latest_result->fetch_assoc())) {
    $latest_created_at = $row['latest_created_at'];
}

$sql = "SELECT L_ListingID, L_Address, L_City, L_State, L_Zip, L_SystemPrice, L_Keyword2, LM_Dec_3, LM_Int2_3, L_Photos, L_Remarks, LMD_MP_Latitude as Latitude, LMD_MP_Longitude as Longitude, 
        LA1_UserFirstName, LA1_UserLastName, LO1_OrganizationName, L_Status, created_at
        FROM rets_property $filter_sql $sort_sql LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$listings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $photos = [];
        if (!empty($row['L_Photos'])) {
            $photos = json_decode($row['L_Photos'], true);
            if (!is_array($photos))
                $photos = [];
        }
        $firstPhoto = $photos[0] ?? 'https://via.placeholder.com/300x200?text=No+Photo';

        $listings[] = [
            'id' => $row['L_ListingID'] ?? '',
            'address' => ($row['L_Address'] ?? '') . ', ' . ($row['L_City'] ?? '') . ', ' . ($row['L_State'] ?? '') . ' ' . ($row['L_Zip'] ?? ''),
            'price' => isset($row['L_SystemPrice']) ? number_format($row['L_SystemPrice']) : 'N/A',
            'beds' => $row['L_Keyword2'] ?? '-',
            'baths' => $row['LM_Dec_3'] ?? '-',
            'sqft' => $row['LM_Int2_3'] ?? '-',
            'photo' => $firstPhoto,
            'lat' => $row['Latitude'] ?? null,
            'lng' => $row['Longitude'] ?? null,
            'remarks' => $row['L_Remarks'] ?? '',
            'agent_first' => $row['LA1_UserFirstName'] ?? '',
            'agent_last' => $row['LA1_UserLastName'] ?? '',
            'office' => $row['LO1_OrganizationName'] ?? '',
            'status' => $row['L_Status'] ?? '',
            'created_at' => $row['created_at'] ?? ''
        ];
    }
}

// Collect unique cities from the entire property table
$cities = [];
$city_result = $conn->query("SELECT DISTINCT L_City FROM rets_property WHERE L_City IS NOT NULL AND L_City != '' ORDER BY L_City ASC");
if ($city_result) {
    while ($row = $city_result->fetch_assoc()) {
        $cities[] = $row['L_City'];
    }
}
sort($cities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>California Homes</title>
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
            background:  rgba(118, 75, 162, 0.8), 
                        url('assets/pexels-davidmcbee-1546168.jpg') center/cover no-repeat;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            min-height: 80px;
        }

        header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.7) 0%, rgba(118, 75, 162, 0.7) 100%);
            z-index: 1;
        }

        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .logo svg {
            height: 48px;
            width: auto;
            display: inline-block;
            vertical-align: middle;
        }

        .logo svg * {
            fill: white !important;
        }

        .logo-img {
            height: 48px;
            width: auto;
            display: inline-block;
            vertical-align: middle;
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
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.85) 0%, rgba(118, 75, 162, 0.85) 50%), 
                        url('assets/pexels-binyaminmellish-186077.jpg') center/cover no-repeat;
            background-size: cover;
            background-position: center top;
            background-position-y: -180px;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: white;
            padding: 4rem 0;
            text-align: center;
            position: relative;
            min-height: 400px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.6) 0%, rgba(118, 75, 162, 0.6) 100%);
            z-index: 1;
        }

        .hero .container {
            position: relative;
            z-index: 2;
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

        /* Hero Search Bar */
        .hero-search {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .hero-search-form {
            display: flex;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50px;
            padding: 8px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
        }

        .hero-search-input {
            flex: 1;
            border: none;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            background: transparent;
            color: #333;
            outline: none;
            border-radius: 50px;
        }

        .hero-search-input::placeholder {
            color: #666;
            opacity: 0.8;
        }

        .hero-search-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 120px;
            justify-content: center;
        }

        .hero-search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .hero-search-icon {
            width: 20px;
            height: 20px;
        }

        @media (max-width: 768px) {
            .hero-search {
                max-width: 90%;
            }
            
            .hero-search-form {
                flex-direction: column;
                padding: 12px;
                border-radius: 16px;
                gap: 8px;
            }
            
            .hero-search-input {
                border-radius: 12px;
                padding: 1rem;
            }
            
            .hero-search-btn {
                border-radius: 12px;
                justify-content: center;
            }
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
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
            width: 100%;
        }
        .search-form .form-group {
            margin-bottom: 0;
        }
        .search-form .form-group.buttons {
            display: flex;
            flex-direction:row;
            gap: 1rem;
            align-items: end;
        }
        @media (max-width: 900px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
        @media (max-width: 600px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            .search-form .form-group.buttons {
                flex-direction: row;
                justify-content: flex-start;
            }
            
            .header-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .sort-section {
                justify-content: flex-start;
            }
            
            .layout-dropdown {
                align-self: center;
            }
            
            .layout-btn {
                width: fit-content;
                align-self: center;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-width: fit-content;
        }

        .form-group:first-child {
            flex: 2 1 auto; /* Address field gets more space but can shrink */
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
            width: 100%;
            min-width: 0;
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
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .sort-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .map-toggle-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .map-toggle-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .map-toggle-btn.active {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .layout-dropdown {
            position: relative;
            display: inline-block;
        }

        .layout-btn {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .layout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .layout-dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 120px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .layout-dropdown-content.show {
            display: block;
        }

        .layout-option {
            color: #333;
            padding: 0.75rem 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 0.9rem;
        }

        .layout-option:hover {
            background-color: #f8fafc;
        }

        .layout-option.active {
            background-color: #e0e7ff;
            color: #667eea;
            font-weight: 500;
        }

        .layout-option:first-child {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .layout-option:last-child {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .results-count {
            font-size: 1.1rem;
            color: #666;
        }

        /* Property Grid */
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .property-grid.hidden {
            display: none;
        }

        /* Property Table */
        .property-table {
            display: none;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-top: 1rem;
        }

        .property-table.visible {
            display: block;
        }

        .property-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .property-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .property-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .property-table tr:hover {
            background-color: #f8fafc;
        }

        .table-property-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }

        .table-property-price {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }

        .table-property-features {
            font-size: 0.8rem;
            color: #666;
        }

        .table-view-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }

        .table-view-btn:hover {
            transform: translateY(-1px);
        }

        .property-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .property-image {
            width: 100%;
            height: 250px;
            position: relative;
            overflow: hidden;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .property-content {
            padding: 1.5rem;
        }

        .property-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .property-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .property-location {
            color: #666;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .property-features {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .agent-info {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
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

        /* Map */
        #map {
            height: 500px;
            margin: 2rem auto;
            border-radius: 12px;
            overflow: hidden;
            display: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 1200px;
            width: calc(100% - 40px);
        }

        #map.visible {
            display: block;
        }

        /* Full viewport map when in map view */
        .map-view-active #map {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            margin: 0;
            border-radius: 0;
            z-index: 1000;
            max-width: none;
        }

        .map-view-active .pagination {
            margin-top: 0;
        }

        .map-view-active body {
            overflow: hidden;
        }

        /* Ensure map container doesn't inherit container constraints */
        .map-view-active #map * {
            box-sizing: border-box;
        }

        /* Reset any potential container padding/margin issues */
        .map-view-active #map {
            padding: 0 !important;
            box-sizing: border-box;
        }

        .map-close-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s;
            display: none;
            color: #333;
        }

        .map-view-active .map-close-btn {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-close-btn:hover {
            background: white;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
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

            .header-controls {
                align-self: stretch;
                justify-content: space-between;
                flex-direction: row;
            }

            .sort-section {
                align-self: stretch;
                justify-content: space-between;
            }

            .property-grid {
                grid-template-columns: 1fr;
            }

            .stats-content {
                flex-direction: column;
                text-align: center;
            }

            .property-table {
                overflow-x: auto;
            }

            .property-table table {
                min-width: 600px;
            }

            .table-property-image {
                width: 60px;
                height: 45px;
            }

            .layout-dropdown-content {
                right: auto;
                left: 0;
            }

            /* Optimize background images for mobile */
            header {
                background-size: cover !important;
                background-position: center !important;
                background-attachment: scroll;
                min-height: 100px;
            }

            .hero {
                background-size: cover !important;
                background-position: center !important;
                background-attachment: scroll;
                padding: 3rem 0;
                min-height: 350px;
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
            <div class="logo" style="display: flex; align-items: center; gap: 2px;">
                <img src="assets/white-logo.png" alt="California Homes Logo" style="height: 48px; width: auto; display: inline-block; vertical-align: middle;" />
                <div style="width:2px; height:32px; background:white; margin:0 10px; border-radius:2px;"></div>
                <span style="font-size: 1.5rem; font-weight: bold; color: white; margin-left: 0;">California Homes</span>
            </div>
                <nav>
                    <ul>
                        <li><a href="index.php">Homes for Sale</a></li>
                        <li><a href="openhouse.php">Open Houses</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Find Your Dream Home in California</h1>
            <p>Discover beautiful homes for sale across California's most desirable neighborhoods and communities.</p>
            
            <div class="hero-search">
                <form method="GET" class="hero-search-form" action="index.php">
                    <!-- Preserve existing filters -->
                    <?php if (isset($_GET['city']) && $_GET['city']): ?>
                        <input type="hidden" name="city" value="<?php echo htmlspecialchars($_GET['city']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['min']) && $_GET['min']): ?>
                        <input type="hidden" name="min" value="<?php echo htmlspecialchars($_GET['min']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['max']) && $_GET['max']): ?>
                        <input type="hidden" name="max" value="<?php echo htmlspecialchars($_GET['max']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['beds']) && $_GET['beds']): ?>
                        <input type="hidden" name="beds" value="<?php echo htmlspecialchars($_GET['beds']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['baths']) && $_GET['baths']): ?>
                        <input type="hidden" name="baths" value="<?php echo htmlspecialchars($_GET['baths']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['sort']) && $_GET['sort']): ?>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
                    <?php endif; ?>
                    
                    <input 
                        type="text" 
                        name="address" 
                        class="hero-search-input" 
                        placeholder="Enter an address, neighborhood, city or ZIP code..."
                        value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>"
                    >
                    <button type="submit" class="hero-search-btn">
                        <svg class="hero-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="search-section">
        <div class="container">
            <form method="GET" class="search-form">
                <div class="form-group">
                    <label for="city">City</label>
                    <select id="city" name="city">
                        <option value="">All Cities</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo htmlspecialchars(trim($city)); ?>" <?php if (isset($_GET['city']) && trim($_GET['city']) === trim($city)) echo 'selected'; ?>><?php echo htmlspecialchars(trim($city)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="min">Min Price</label>
                    <select id="min" name="min">
                        <?php
                        $min_price_options = [200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000];
                        foreach ($min_price_options as $price) {
                            $label = $price == 0 ? '$0' : ('$' . number_format($price / 1000, 0) . 'k');
                            echo '<option value="' . $price . '"' . (isset($_GET['min']) && $_GET['min'] == $price ? ' selected' : '') . '>' . $label . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="max">Max Price</label>
                    <select id="max" name="max">
                        <option value="" <?php echo (!isset($_GET['max']) || $_GET['max'] === '') ? 'selected' : ''; ?>>Not Limited</option>
                        <?php
                        $max_price_options = [300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000];
                        foreach ($max_price_options as $price) {
                            $label = '$' . number_format($price / 1000, 0) . 'k';
                            $selected = (isset($_GET['max']) && $_GET['max'] == $price) ? ' selected' : '';
                            echo "<option value=\"$price\"$selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="beds">Min Beds</label>
                    <select id="beds" name="beds">
                        <option value="">Any</option>
                        <?php
                        $bed_options = [1, 2, 3, 4, 5];
                        foreach ($bed_options as $bed) {
                            echo '<option value="' . $bed . '"' . (isset($_GET['beds']) && $_GET['beds'] == $bed ? ' selected' : '') . '>' . $bed . '</option>';
                        }
                        echo '<option value="6+"' . (isset($_GET['beds']) && $_GET['beds'] == '6+' ? ' selected' : '') . '>6+</option>';
                        ?>
                    </select>                </div>
                <div class="form-group">
                    <label for="baths">Min Baths</label>
                    <select id="baths" name="baths">
                        <option value="">Any</option>
                        <?php
                        $bath_options = [1, 2, 3, 4, 5];
                        foreach ($bath_options as $bath) {
                            echo '<option value="' . $bath . '"' . (isset($_GET['baths']) && $_GET['baths'] == $bath ? ' selected' : '') . '>' . $bath . '</option>';
                        }
                        echo '<option value="6+"' . (isset($_GET['baths']) && $_GET['baths'] == '6+' ? ' selected' : '') . '>6+</option>';
                        ?>
                    </select>                </div>

                <div class="form-group buttons">
                    <button type="submit" class="search-btn">Search</button>
                    <button type="button" class="search-btn" style="background: #e2e8f0; color: #333;" onclick="clearAllFilters()">Clear</button>
                </div>
            </form>
        </div>
    </section>

    <section class="results-section">
        <div class="container">
            <div class="results-header">
                <div class="results-count">
                    Showing <?php echo count($listings); ?> of <?php echo number_format($total); ?> properties
                    <?php if (!empty($_GET['address'])): ?>
                        <span style="color: #667eea; font-weight: 500;"> for "<?php echo htmlspecialchars($_GET['address']); ?>"</span>
                    <?php endif; ?>
                </div>
                <div class="header-controls">
                    <div class="sort-section">
                        <label for="sort-quick" style="margin-right: 0.5rem; font-weight: 500;">Sort by:</label>
                        <select id="sort-quick" onchange="updateSort(this.value)" style="padding: 0.5rem; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 0.9rem;">
                            <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <?php if (!empty($listings)): ?>
                    <div class="layout-dropdown">
                        <button class="layout-btn" onclick="toggleLayoutDropdown()">
                            Layout
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down-icon lucide-chevron-down"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="layout-dropdown-content" id="layoutDropdown">
                            <a class="layout-option active" onclick="setLayout('card')" data-layout="card">
                                Card View
                            </a>
                            <a class="layout-option" onclick="setLayout('table')" data-layout="table">
                                Table View
                            </a>
                            <a class="layout-option" onclick="setLayout('map')" data-layout="map">
                                Map View
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($listings)): ?>
            <div class="empty-state">
                <h3>No properties found</h3>
                <p>Try running the sync script to import properties from the Trestle API.</p>
                <a href="sync_properties.php" class="sync-btn">Sync Properties</a>
            </div>
            <?php else: ?>
            <div class="property-grid" id="cardView">
                <?php foreach ($listings as $home): ?>
                <a href="property.php?id=<?php echo htmlspecialchars($home['id']); ?>" class="property-card">
                    <div class="property-image">
                        <img src="<?php echo htmlspecialchars($home['photo']); ?>" alt="California Home Photo">
                        <?php if (!empty($home['status'])): ?>
                        <div class="property-badge" style="background: <?php echo strtolower($home['status']) === 'active' ? '#10b981' : (strtolower($home['status']) === 'pending' ? '#f59e0b' : '#ef4444'); ?>">
                            <?php echo htmlspecialchars($home['status']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="property-content">
                        <div class="property-price">$<?php echo $home['price']; ?></div>
                        <div class="property-title"><?php echo htmlspecialchars($home['address']); ?></div>
                        <div class="property-location">
                            📍 <?php echo htmlspecialchars($home['address']); ?>
                        </div>
                        <div class="property-features">
                            <div class="feature">🛏️ <?php echo $home['beds']; ?> beds</div>
                            <div class="feature">🚿 <?php echo $home['baths']; ?> baths</div>
                            <div class="feature">📐 <?php echo $home['sqft']; ?> sqft</div>
                        </div>
                        
                        <?php if (!empty($home['agent_first']) || !empty($home['agent_last'])): ?>
                        <div class="agent-info">
                            <strong>Agent:</strong> <?php echo htmlspecialchars($home['agent_first'] . ' ' . $home['agent_last']); ?>
                            <?php if (!empty($home['office'])): ?>
                            <br><strong>Office:</strong> <?php echo htmlspecialchars($home['office']); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="property-table" id="tableView">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Price</th>
                            <th>Address</th>
                            <th>Details</th>
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $home): ?>
                        <tr>
                            <td>
                                <img src="<?php echo htmlspecialchars($home['photo']); ?>" alt="Property Photo" class="table-property-image">
                            </td>
                            <td>
                                <div class="table-property-price">$<?php echo $home['price']; ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 500; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($home['address']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="table-property-features">
                                    🛏️ <?php echo $home['beds']; ?> beds<br>
                                    🚿 <?php echo $home['baths']; ?> baths<br>
                                    📐 <?php echo $home['sqft']; ?> sqft
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.8rem; color: #666;">
                                    <?php if (!empty($home['agent_first']) || !empty($home['agent_last'])): ?>
                                        <?php echo htmlspecialchars($home['agent_first'] . ' ' . $home['agent_last']); ?>
                                        <?php if (!empty($home['office'])): ?>
                                            <br><small><?php echo htmlspecialchars($home['office']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($home['status'])): ?>
                                <span style="background: <?php echo strtolower($home['status']) === 'active' ? '#10b981' : (strtolower($home['status']) === 'pending' ? '#f59e0b' : '#ef4444'); ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.7rem; font-weight: 500;">
                                    <?php echo htmlspecialchars($home['status']); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="property.php?id=<?php echo htmlspecialchars($home['id']); ?>" class="table-view-btn">
                                    View Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total > $limit): ?>
            <div class="pagination">
                <?php echo paginate($total, $limit, $page, '?' . http_build_query(array_merge($_GET, ['page' => null])) . '&'); ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <div id="map">
        <button class="map-close-btn" onclick="setLayout('card')" title="Close Map">✕</button>
    </div>

    <script async
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB0szWlIt9Vj26cM300wTcWxwL0ABHZ9HE
&loading=async&callback=initMap">
</script>
</body>
</html>


<script>
const listings = <?php echo json_encode($listings); ?>;

function initMap() {
    if (!listings.length) return;

    // Center map on California or the first property
    const center = { 
        lat: parseFloat(listings[0].lat) || 36.7783, 
        lng: parseFloat(listings[0].lng) || -119.4179 
    };

    map = new google.maps.Map(document.getElementById('map'), {
        zoom: 10,
        center: center,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });

    // Create bounds to fit all markers
    const bounds = new google.maps.LatLngBounds();
    let markersAdded = 0;

    listings.forEach(home => {
        if (home.lat && home.lng) {
            const position = { lat: parseFloat(home.lat), lng: parseFloat(home.lng) };
            
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: home.address,
                animation: google.maps.Animation.DROP
            });

            bounds.extend(position);
            markersAdded++;

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="max-width: 250px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                        <div style="margin-bottom: 10px;">
                            <img src="${home.photo}" alt="Property Photo" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px;">
                        </div>
                        <div style="margin-bottom: 8px;">
                            <strong style="font-size: 1.1em; color: #667eea;">$${home.price}</strong>
                        </div>
                        <div style="margin-bottom: 8px; font-size: 0.9em; color: #666;">
                            📍 ${home.address}
                        </div>
                        <div style="margin-bottom: 10px; font-size: 0.8em; color: #666;">
                            🛏️ ${home.beds} beds • 🚿 ${home.baths} baths • 📐 ${home.sqft} sqft
                        </div>
                        <div style="text-align: center;">
                            <a href="property.php?id=${home.id}" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.8em; display: inline-block;">View Details</a>
                        </div>
                    </div>
                `
            });

            marker.addListener('click', function() {
                infoWindow.open(map, marker);
            });
        }
    });

    // Fit map to show all markers if we have multiple properties
    if (markersAdded > 1) {
        map.fitBounds(bounds);
        
        // Ensure minimum zoom level
        const listener = google.maps.event.addListener(map, 'idle', function() {
            if (map.getZoom() > 15) map.setZoom(15);
            google.maps.event.removeListener(listener);
        });
    }
}

// Initialize map after page load - but don't show it initially
window.addEventListener('DOMContentLoaded', function() {
    // Map will be initialized when user clicks "View on Map" button
});

function clearAllFilters() {
    window.location.href = 'index.php';
}

function updateSort(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.set('page', '1'); // Reset to first page when sorting
    window.location.href = url.toString();
}

let map = null;
let isMapVisible = false;
let currentLayout = 'card'; // Default layout

function toggleLayoutDropdown() {
    const dropdown = document.getElementById('layoutDropdown');
    dropdown.classList.toggle('show');
}

function setLayout(layout) {
    const cardView = document.getElementById('cardView');
    const tableView = document.getElementById('tableView');
    const mapElement = document.getElementById('map');
    const dropdown = document.getElementById('layoutDropdown');
    const body = document.body;
    
    // Update active state in dropdown
    const options = dropdown.querySelectorAll('.layout-option');
    options.forEach(option => {
        option.classList.remove('active');
        if (option.dataset.layout === layout) {
            option.classList.add('active');
        }
    });
    
    // Hide all views first
    cardView.style.display = 'none';
    tableView.classList.remove('visible');
    mapElement.classList.remove('visible');
    body.classList.remove('map-view-active');
    
    // Show selected view
    if (layout === 'card') {
        cardView.style.display = 'grid';
        currentLayout = 'card';
    } else if (layout === 'table') {
        tableView.classList.add('visible');
        currentLayout = 'table';
    } else if (layout === 'map') {
        mapElement.classList.add('visible');
        body.classList.add('map-view-active');
        currentLayout = 'map';
        
        // Initialize map if not already done
        if (!map && listings.length > 0) {
            initMap();
        }
        
        // Trigger map resize after the view is shown
        setTimeout(() => {
            if (map) {
                google.maps.event.trigger(map, 'resize');
                // Re-fit bounds if we have markers
                if (listings.length > 0) {
                    const bounds = new google.maps.LatLngBounds();
                    listings.forEach(home => {
                        if (home.lat && home.lng) {
                            bounds.extend({ lat: parseFloat(home.lat), lng: parseFloat(home.lng) });
                        }
                    });
                    if (!bounds.isEmpty()) {
                        map.fitBounds(bounds);
                    }
                }
            }
        }, 100);
    }
    
    // Close dropdown
    dropdown.classList.remove('show');
    isMapVisible = (layout === 'map');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('layoutDropdown');
    const layoutBtn = document.querySelector('.layout-btn');
    
    if (dropdown && !dropdown.contains(event.target) && !layoutBtn.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Close full-screen map with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && currentLayout === 'map') {
        setLayout('card');
    }
});

function toggleMap() {
    // This function is kept for backward compatibility but now uses setLayout
    if (currentLayout === 'map') {
        setLayout('card');
    } else {
        setLayout('map');
    }
}
</script>