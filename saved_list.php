<?php
session_start();
include('api/db.php');
include('includes/functions.php');
include('includes/pagination.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$filter_sql = build_saved_filter_query($_GET);

// Build ORDER BY clause based on sort parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order_by = '';
switch ($sort) {
    case 'price_low':
        $order_by = 'ORDER BY L_SystemPrice ASC, created_at DESC';
        break;
    case 'price_high':
        $order_by = 'ORDER BY L_SystemPrice DESC, created_at DESC';
        break;
    case 'newest':
    default:
        $order_by = 'ORDER BY created_at DESC';
        break;
}


$query = "SELECT listing_id FROM saved_listings WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$saved_ids = [];
while ($row = $result->fetch_assoc()) {
    $saved_ids[] = $row['listing_id'];
}
$stmt->close();

$count_query = "SELECT COUNT(*) AS total FROM saved_listings WHERE user_id = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = $count_result ? $count_result->fetch_assoc()['total'] : 0;

$listings = [];
if (count($saved_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($saved_ids), '?'));
    $types = str_repeat('s', count($saved_ids));
    
    $sql = "SELECT L_ListingID, L_Address, L_City, L_State, L_Zip, L_SystemPrice, L_Keyword2, LM_Dec_3, LM_Int2_3, L_Photos, L_Remarks, LMD_MP_Latitude as Latitude, LMD_MP_Longitude as Longitude, 
            LA1_UserFirstName, LA1_UserLastName, LO1_OrganizationName, L_Status, created_at
            FROM rets_property_yu WHERE L_ListingID IN ($placeholders) $filter_sql $order_by LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$saved_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $photos = [];
        if (!empty($row['L_Photos'])) {
            $photos = json_decode($row['L_Photos'], true);

            if (!is_array($photos)) $photos = [];
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
    $stmt->close();
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        
        .heart-button:hover span {
            opacity: 0.8;
            transform: scale(1.05);
            transition: all 0.2s ease-in-out;
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
            position: sticky;
            top: 0;
            z-index: 1000;
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

        /* Search Autocomplete Dropdown */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 8px;
            right: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            z-index: 99999;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }

        .search-suggestions.show {
            display: block !important;
        }

        .search-suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 1rem;
            color: #333;
            transition: background-color 0.2s;
        }

        .search-suggestion-item:last-child {
            border-bottom: none;
            border-radius: 0 0 12px 12px;
        }

        .search-suggestion-item:first-child {
            border-radius: 12px 12px 0 0;
        }

        .search-suggestion-item:hover,
        .search-suggestion-item.highlighted {
            background-color: #f8fafc;
            color: #667eea;
        }

        .search-suggestion-item.highlighted {
            background-color: #e0e7ff;
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
            z-index: 1;
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
            flex-grow: 1;
            flex-basis: 0;
            width: 100%;
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

        /* Comparison Styles */
        .comparison-controls {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s;
        }

        .comparison-controls.visible {
            display: flex;
        }

        .comparison-count {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .compare-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .compare-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .compare-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .clear-comparison-btn {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .clear-comparison-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .property-checkbox {
            position: absolute;
            top: 1rem;
            left: 1rem;
            width: 20px;
            height: 20px;
            cursor: pointer;
            z-index: 10;
        }

        .property-card {
            position: relative;
        }

        .property-card.selected {
            border: 3px solid #667eea;
            transform: translateY(-2px);
        }

        .table-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .property-table tr.selected {
            background-color: #e0e7ff;
            border-left: 4px solid #667eea;
        }

        /* Comparison Modal */
        .comparison-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            overflow-y: auto;
        }

        .comparison-modal.visible {
            display: block;
        }

        .comparison-content {
            background: white;
            margin: 2rem auto;
            max-width: 1200px;
            border-radius: 12px;
            position: relative;
            min-height: calc(100vh - 4rem);
        }

        .comparison-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .comparison-header h2 {
            margin: 0;
            font-size: 1.8rem;
        }

        .modal-close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.3s;
        }

        .modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        .comparison-property {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .comparison-property-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .comparison-property-content {
            padding: 1.5rem;
        }

        .comparison-property-price {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .comparison-property-address {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .comparison-details {
            display: grid;
            gap: 1rem;
        }

        .comparison-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .comparison-detail-row:last-child {
            border-bottom: none;
        }

        .comparison-detail-label {
            font-weight: 500;
            color: #666;
        }

        .comparison-detail-value {
            font-weight: 600;
            color: #333;
        }

        .comparison-view-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
            transition: transform 0.2s;
            width: 100%;
            text-align: center;
        }

        .comparison-view-btn:hover {
            transform: translateY(-2px);
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

            /* Comparison mobile optimizations */
            .comparison-controls {
                bottom: 10px;
                right: 10px;
                left: 10px;
                padding: 0.75rem 1rem;
                border-radius: 25px;
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            .comparison-controls .comparison-count {
                font-size: 0.8rem;
            }

            .comparison-controls .compare-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }

            .comparison-content {
                margin: 1rem;
                min-height: calc(100vh - 2rem);
            }

            .comparison-header {
                padding: 1.5rem 1rem;
            }

            .comparison-header h2 {
                font-size: 1.4rem;
            }

            .comparison-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1.5rem 1rem;
            }

            .comparison-property-content {
                padding: 1rem;
            }

            .comparison-property-price {
                font-size: 1.5rem;
            }

            .comparison-property-address {
                font-size: 1rem;
            }

            .comparison-detail-row {
                padding: 0.5rem 0;
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
            <h1>Your Saved Listings</h1>
            
            <div class="hero-search">
                <form method="GET" class="hero-search-form" id="heroSearchForm" action="saved_list.php">
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
                    
                    <div style="position: relative; display: flex; width: 100%;">
                        <input 
                            type="text" 
                            name="address" 
                            id="heroSearchInput"
                            class="hero-search-input" 
                            placeholder="Enter a city or ZIP code..."
                            value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>"
                            style="padding-right: 40px;"
                            autocomplete="off"
                        >
                        <?php if (isset($_GET['address']) && !empty($_GET['address'])): ?>
                        <button type="button" id="clearHeroSearch" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; font-size: 18px;">&times;</button>
                        <?php endif; ?>
                        <div id="heroSearchSuggestions" class="search-suggestions"></div>
                    </div>
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
            <form method="GET" class="search-form" id="searchForm">
                <!-- Hidden address field to maintain synchronization -->
                <input type="hidden" id="address" name="address" value="<?php echo isset($_GET['address']) ? htmlspecialchars($_GET['address']) : ''; ?>">
                <div class="form-group">
                    <label for="min">Min Price</label>
                    <select id="min" name="min">
                        <option value="">Any</option>
                        <?php
                        $min_price_options = [200000, 300000, 400000, 500000, 600000, 700000, 800000, 900000, 1000000];
                        foreach ($min_price_options as $price) {
                            $label = '$' . number_format($price / 1000, 0) . 'k';
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

                <!-- Include sort field for filtering -->
                <input type="hidden" id="sortField" name="sort" value="<?php echo isset($_GET['sort']) ? htmlspecialchars($_GET['sort']) : ''; ?>">

                <div class="form-group buttons">
                    <button type="submit" class="search-btn">Apply</button>
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
                    <?php 
                    $active_filters = [];
                    
                    // Address filter
                    if (!empty($_GET['address'])) {
                        $active_filters[] = 'in "' . htmlspecialchars($_GET['address']) . '"';
                    }
                    
                    // Price filters
                    if (!empty($_GET['min']) || !empty($_GET['max'])) {
                        $price_range = '';
                        if (!empty($_GET['min'])) {
                            $price_range .= '$' . number_format($_GET['min'] / 1000, 0) . 'k';
                        }
                        if (!empty($_GET['min']) && !empty($_GET['max'])) {
                            $price_range .= ' - ';
                        } else if (empty($_GET['min'])) {
                            $price_range .= 'up to ';
                        }
                        if (!empty($_GET['max'])) {
                            $price_range .= '$' . number_format($_GET['max'] / 1000, 0) . 'k';
                        } else if (!empty($_GET['min'])) {
                            $price_range .= '+';
                        }
                        $active_filters[] = $price_range . ' price range';
                    }
                    
                    // Beds filter
                    if (!empty($_GET['beds'])) {
                        $beds_text = $_GET['beds'] === '6+' ? '6+ beds' : $_GET['beds'] . '+ beds';
                        $active_filters[] = $beds_text;
                    }
                    
                    // Baths filter
                    if (!empty($_GET['baths'])) {
                        $baths_text = $_GET['baths'] === '6+' ? '6+ baths' : $_GET['baths'] . '+ baths';
                        $active_filters[] = $baths_text;
                    }
                    
                    // Display active filters
                    if (!empty($active_filters)) {
                        echo '<span style="color: #667eea; font-weight: 500;"> with ' . implode(', ', $active_filters) . '</span>';
                    }
                    ?>
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
                <h3>No properties saved yet</h3>
            </div>
            <?php else: ?>
            <div class="property-grid" id="cardView">
                <?php foreach ($listings as $home): ?>
                <div class="property-card" data-property-id="<?php echo htmlspecialchars($home['id']); ?>">
                    <input type="checkbox" class="property-checkbox" data-property-id="<?php echo htmlspecialchars($home['id']); ?>" onclick="event.stopPropagation(); toggleComparison(this);">
                    <a href="property.php?id=<?php echo htmlspecialchars($home['id']); ?>&ref=saved" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: inherit; display: block;">
                        <div class="property-image">
                            <img src="<?php echo htmlspecialchars($home['photo']); ?>" alt="California Home Photo">
                            <?php if (!empty($home['status'])): ?>
                            <div class="property-badge" style="background: <?php echo strtolower($home['status']) === 'active' ? '#10b981' : (strtolower($home['status']) === 'pending' ? '#f59e0b' : '#ef4444'); ?>">
                                <?php echo htmlspecialchars($home['status']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="heart-button"
                                        data-listing-id="<?php echo htmlspecialchars($home['id']); ?>"
                                        data-saved="<?php echo in_array($home['id'], $saved_ids) ? '1' : '0'; ?>"
                                        style="position: absolute; top: 5px; right: 100px; background: none; border: none; cursor: pointer; font-size: 2rem;">
                                    <span style="color: <?php echo in_array($home['id'], $saved_ids) ? 'red' : 'black'; ?>">
                                        ❤
                                    </span>
                                </button>
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
                </div>
                <?php endforeach; ?>
            </div>

            <div class="property-table" id="tableView">
                <table>
                    <thead>
                        <tr>
                            <th>Compare</th>
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
                        <tr data-property-id="<?php echo htmlspecialchars($home['id']); ?>">
                            <td>
                                <input type="checkbox" class="table-checkbox" data-property-id="<?php echo htmlspecialchars($home['id']); ?>" onclick="toggleComparison(this);">
                            </td>
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
                                <a href="property.php?id=<?php echo htmlspecialchars($home['id']); ?>&ref=saved" target="_blank" rel="noopener noreferrer" class="table-view-btn">
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

    <!-- Comparison Controls -->
    <div class="comparison-controls" id="comparisonControls">
        <div class="comparison-count" id="comparisonCount">0 properties selected</div>
        <button class="compare-btn" id="compareBtn" onclick="showComparison()" disabled>Compare</button>
        <button class="clear-comparison-btn" onclick="clearComparison()" title="Clear Selection">✕</button>
    </div>

    <!-- Comparison Modal -->
    <div class="comparison-modal" id="comparisonModal">
        <div class="comparison-content">
            <div class="comparison-header">
                <h2>Property Comparison</h2>
                <button class="modal-close-btn" onclick="hideComparison()">✕</button>
            </div>
            <div class="comparison-grid" id="comparisonGrid">
                <!-- Comparison content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
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

    <script async
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB0szWlIt9Vj26cM300wTcWxwL0ABHZ9HE
&loading=async&callback=initMap">
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.heart-button').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const listingId = this.dataset.listingId;
            const isSaved = this.dataset.saved === '1';
            const heartSpan = this.querySelector('span');

            const targetFile = isSaved ? 'unsave_listing.php' : 'save_listing.php';

            fetch(targetFile, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'listing_id=' + encodeURIComponent(listingId)
            })
            .then(res => res.text())
            .then(() => {
                if (isSaved) {
                    heartSpan.style.color = 'black';
                    this.dataset.saved = '0';
                } else {
                    heartSpan.style.color = 'red';
                    this.dataset.saved = '1';
                }
                location.reload();
            })
            .catch(err => console.error('Save toggle failed:', err));
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
const listings = <?php echo json_encode($listings); ?>;

// Global variables for map
let map = null;
let isMapVisible = false;
let currentLayout ='card'
let sharedInfoWindow = null;
let selectedMarker = null;

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

    sharedInfoWindow = new google.maps.InfoWindow();

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

            marker.addListener('click', function () {
                if (selectedMarker && selectedMarker !== marker) {
                    selectedMarker.setAnimation(null);
                }
                selectedMarker = marker;
                marker.setAnimation(google.maps.Animation.BOUNCE);
                setTimeout(() => marker.setAnimation(null), 700);
            

                const content = `
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
                            <a href="property.php?id=${home.id}&ref=saved" target="_blank" rel="noopener noreferrer" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.8em; display: inline-block;">View Details</a>
                        </div>
                    </div>
                `;
            
                sharedInfoWindow.setContent(content);
                sharedInfoWindow.open(map, marker);

            // marker.addListener('click', function() {
            //     infoWindow.open(map, marker);
            // });
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

    // Close the InfoWindow when clicking on the map background
    map.addListener('click', () => {
        if (sharedInfoWindow) sharedInfoWindow.close();
    });
}

// Initialize map after page load - but don't show it initially
window.addEventListener('DOMContentLoaded', function() {
    // Map will be initialized when user clicks "View on Map" button
});

function clearAllFilters() {
    // Clear all form inputs first
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        const inputs = searchForm.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.type === 'hidden' && input.name === 'sort') {
                input.value = 'newest'; // Reset to default sort
            } else if (input.type !== 'hidden') {
                input.value = '';
            }
        });
    }
    
    // Clear hero search input
    const heroInput = document.getElementById('heroSearchInput');
    if (heroInput) heroInput.value = '';
    
    // Clear sort dropdown
    const sortDropdown = document.getElementById('sort-quick');
    if (sortDropdown) sortDropdown.value = 'newest';
    
    // Redirect to clean page
    window.location.href = 'saved_list.php';
}

function updateSort(sortValue) {
    // Update the sort field in search form
    const sortField = document.getElementById('sortField');
    if (sortField) {
        sortField.value = sortValue;
    }
    
    // Submit the main search form to apply sort with current filters
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        // Set page to 1 when sorting
        let pageInput = searchForm.querySelector('input[name="page"]');
        if (!pageInput) {
            pageInput = document.createElement('input');
            pageInput.type = 'hidden';
            pageInput.name = 'page';
            searchForm.appendChild(pageInput);
        }
        pageInput.value = '1';
        
        searchForm.submit();
    } else {
        // Fallback to original behavior
        const url = new URL(window.location);
        url.searchParams.set('sort', sortValue);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }
}

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

// Comparison functionality
let selectedProperties = [];

function toggleComparison(checkbox) {
    const propertyId = checkbox.dataset.propertyId;
    const property = listings.find(p => p.id === propertyId);
    
    if (!property) return;
    
    if (checkbox.checked) {
        // Check if we already have 2 properties selected
        if (selectedProperties.length >= 2) {
            checkbox.checked = false;
            alert('You can only compare up to 2 properties at a time.');
            return;
        }
        
        // Add property to selection
        selectedProperties.push(property);
        
        // Add visual indicator
        const propertyCard = document.querySelector(`.property-card[data-property-id="${propertyId}"]`);
        const propertyRow = document.querySelector(`.property-table tr[data-property-id="${propertyId}"]`);
        
        if (propertyCard) propertyCard.classList.add('selected');
        if (propertyRow) propertyRow.classList.add('selected');
        
    } else {
        // Remove property from selection
        selectedProperties = selectedProperties.filter(p => p.id !== propertyId);
        
        // Remove visual indicator
        const propertyCard = document.querySelector(`.property-card[data-property-id="${propertyId}"]`);
        const propertyRow = document.querySelector(`.property-table tr[data-property-id="${propertyId}"]`);
        
        if (propertyCard) propertyCard.classList.remove('selected');
        if (propertyRow) propertyRow.classList.remove('selected');
    }
    
    updateComparisonControls();
}

function updateComparisonControls() {
    const comparisonControls = document.getElementById('comparisonControls');
    const comparisonCount = document.getElementById('comparisonCount');
    const compareBtn = document.getElementById('compareBtn');
    
    const count = selectedProperties.length;
    
    if (count > 0) {
        comparisonControls.classList.add('visible');
        comparisonCount.textContent = `${count} ${count === 1 ? 'property' : 'properties'} selected`;
        compareBtn.disabled = count < 2;
    } else {
        comparisonControls.classList.remove('visible');
    }
}

function clearComparison() {
    selectedProperties = [];
    
    // Uncheck all checkboxes
    document.querySelectorAll('.property-checkbox, .table-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Remove visual indicators
    document.querySelectorAll('.property-card.selected').forEach(card => {
        card.classList.remove('selected');
    });
    
    document.querySelectorAll('.property-table tr.selected').forEach(row => {
        row.classList.remove('selected');
    });
    
    updateComparisonControls();
}

function showComparison() {
    if (selectedProperties.length !== 2) {
        alert('Please select exactly 2 properties to compare.');
        return;
    }
    
    const modal = document.getElementById('comparisonModal');
    const grid = document.getElementById('comparisonGrid');
    
    // Generate comparison content
    grid.innerHTML = selectedProperties.map(property => `
        <div class="comparison-property">
            <img src="${property.photo}" alt="Property Photo" class="comparison-property-image">
            <div class="comparison-property-content">
                <div class="comparison-property-price">$${property.price}</div>
                <div class="comparison-property-address">${property.address}</div>
                
                <div class="comparison-details">
                    <div class="comparison-detail-row">
                        <span class="comparison-detail-label">Bedrooms</span>
                        <span class="comparison-detail-value">${property.beds}</span>
                    </div>
                    <div class="comparison-detail-row">
                        <span class="comparison-detail-label">Bathrooms</span>
                        <span class="comparison-detail-value">${property.baths}</span>
                    </div>
                    <div class="comparison-detail-row">
                        <span class="comparison-detail-label">Square Feet</span>
                        <span class="comparison-detail-value">${property.sqft}</span>
                    </div>
                    <div class="comparison-detail-row">
                        <span class="comparison-detail-label">Status</span>
                        <span class="comparison-detail-value">${property.status || 'N/A'}</span>
                    </div>
                    ${property.agent_first || property.agent_last ? `
                    <div class="comparison-detail-row">
                        <span class="comparison-detail-label">Agent</span>
                        <span class="comparison-detail-value">${property.agent_first} ${property.agent_last}</span>
                    </div>
                    ` : ''}
                    ${property.office ? `
                    <div class="comparison-detail-row">
                        <span class="comparison-detail-label">Office</span>
                        <span class="comparison-detail-value">${property.office}</span>
                    </div>
                    ` : ''}
                </div>
                
                <a href="property.php?id=${property.id}&ref=saved" target="_blank" rel="noopener noreferrer" class="comparison-view-btn">View Full Details</a>
            </div>
        </div>
    `).join('');
    
    modal.classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function hideComparison() {
    const modal = document.getElementById('comparisonModal');
    modal.classList.remove('visible');
    document.body.style.overflow = '';
}

// Close comparison modal with ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        if (document.getElementById('comparisonModal').classList.contains('visible')) {
            hideComparison();
        } else if (currentLayout === 'map') {
            setLayout('card');
        }
    }
});

// Close comparison modal when clicking outside
document.getElementById('comparisonModal').addEventListener('click', function(event) {
    if (event.target === this) {
        hideComparison();
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
          setTimeout(() => window.location.href = "index.php", 1000);
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

<script>
  // Clear search functionality
  const clearButton = document.getElementById('clearSearch');
  if (clearButton) {
    clearButton.addEventListener('click', function() {
      // Get current URL without address parameter
      const url = new URL(window.location);
      url.searchParams.delete('address');
      
      // Redirect to show all listings
      window.location.href = url.toString();
    });
  }

  // Search State Management - Synchronize all search components
  const cities = <?php echo json_encode($cities); ?>;
  
  // Search inputs and suggestion containers
  const heroSearchInput = document.getElementById('heroSearchInput');
  const heroSearchSuggestions = document.getElementById('heroSearchSuggestions');
  const searchFormInput = document.getElementById('address');
  const searchInput = document.getElementById('searchInput'); // Legacy support
  const searchSuggestions = document.getElementById('searchSuggestions'); // Legacy support
  
  let currentHighlight = -1;
  let currentSuggestionsContainer = null;

  // Initialize search synchronization
  function initSearchSync() {
    // Synchronize hero search with main search form
    if (heroSearchInput && searchFormInput) {
      heroSearchInput.addEventListener('input', function() {
        searchFormInput.value = this.value;
        handleSearchInput(this, heroSearchSuggestions);
      });
      
      searchFormInput.addEventListener('input', function() {
        heroSearchInput.value = this.value;
        handleSearchInput(this, null); // No suggestions for main form input
      });
    }

    // Legacy support for existing searchInput
    if (searchInput && heroSearchInput) {
      searchInput.addEventListener('input', function() {
        heroSearchInput.value = this.value;
        if (searchFormInput) searchFormInput.value = this.value;
        handleSearchInput(this, searchSuggestions);
      });
    }

    // Setup autocomplete for all search inputs
    setupAutocomplete();
    
    // Setup clear functionality
    setupClearButtons();
    
    // Setup form submission sync
    setupFormSync();
  }

  function handleSearchInput(inputElement, suggestionsContainer) {
    const value = inputElement.value.trim().toLowerCase();
    
    if (value.length === 0) {
      hideSuggestions(suggestionsContainer);
      return;
    }

    const matches = cities.filter(city => 
      city.toLowerCase().includes(value)
    ).slice(0, 8); // Limit to 8 suggestions

    if (matches.length > 0 && suggestionsContainer) {
      showSuggestions(matches, value, suggestionsContainer);
    } else {
      hideSuggestions(suggestionsContainer);
    }
  }

  function setupAutocomplete() {
    const inputs = [heroSearchInput, searchFormInput, searchInput].filter(input => input);
    
    inputs.forEach(input => {
      const suggestionsContainer = input.id === 'heroSearchInput' ? heroSearchSuggestions : 
                                   input.id === 'searchInput' ? searchSuggestions : null;
      
      if (!suggestionsContainer) return;
      
      input.addEventListener('keydown', function(e) {
        const suggestions = suggestionsContainer.querySelectorAll('.search-suggestion-item');
        
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          currentHighlight = Math.min(currentHighlight + 1, suggestions.length - 1);
          updateHighlight(suggestions);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          currentHighlight = Math.max(currentHighlight - 1, -1);
          updateHighlight(suggestions);
        } else if (e.key === 'Enter') {
          if (currentHighlight >= 0 && suggestions[currentHighlight]) {
            e.preventDefault();
            selectSuggestion(suggestions[currentHighlight].textContent);
          }
        } else if (e.key === 'Escape') {
          hideSuggestions(suggestionsContainer);
        }
      });
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
      [heroSearchSuggestions, searchSuggestions].forEach(container => {
        if (container) {
          const relatedInput = container.id === 'heroSearchSuggestions' ? heroSearchInput : searchInput;
          if (relatedInput && !relatedInput.contains(e.target) && !container.contains(e.target)) {
            hideSuggestions(container);
          }
        }
      });
    });
  }

  function setupClearButtons() {
    const clearHeroBtn = document.getElementById('clearHeroSearch');
    const clearBtn = document.getElementById('clearSearch');
    
    [clearHeroBtn, clearBtn].forEach(btn => {
      if (btn) {
        btn.addEventListener('click', function() {
          // Clear all search inputs
          if (heroSearchInput) heroSearchInput.value = '';
          if (searchFormInput) searchFormInput.value = '';
          if (searchInput) searchInput.value = '';
          
          // Get current URL without address parameter
          const url = new URL(window.location);
          url.searchParams.delete('address');
          
          // Redirect to show all listings
          window.location.href = url.toString();
        });
      }
    });
  }

  function setupFormSync() {
    // When hero search form is submitted, sync with main form first
    const heroForm = document.getElementById('heroSearchForm');
    const mainForm = document.getElementById('searchForm');
    const sortDropdown = document.getElementById('sort-quick');
    const sortField = document.getElementById('sortField');
    
    if (heroForm && mainForm) {
      heroForm.addEventListener('submit', function(e) {
        // Sync address value to main form before hero form submits
        if (searchFormInput) {
          searchFormInput.value = heroSearchInput.value;
        }
      });
    }
    
    // Sync sort dropdown with hidden sort field
    if (sortDropdown && sortField) {
      sortDropdown.addEventListener('change', function() {
        sortField.value = this.value;
      });
      
      // Initialize sort field with dropdown value
      sortField.value = sortDropdown.value;
    }
    
    // Add form change listeners to sync all inputs
    if (mainForm) {
      const formInputs = mainForm.querySelectorAll('input, select');
      formInputs.forEach(input => {
        if (input.name !== 'address') { // Address already handled above
          input.addEventListener('change', function() {
            // This ensures all form fields stay in sync
            // Future enhancement: could add real-time filtering here
          });
        }
      });
    }
  }

  function showSuggestions(matches, searchTerm, container) {
    if (!container) return;
    
    container.innerHTML = '';
    currentHighlight = -1;
    currentSuggestionsContainer = container;

    matches.forEach(city => {
      const item = document.createElement('div');
      item.className = 'search-suggestion-item';
      
      // Highlight matching text
      const regex = new RegExp(`(${searchTerm})`, 'gi');
      const highlightedText = city.replace(regex, '<strong>$1</strong>');
      item.innerHTML = highlightedText;
      
      item.addEventListener('click', function() {
        selectSuggestion(city);
      });
      
      container.appendChild(item);
    });

    container.classList.add('show');
  }

  function hideSuggestions(container) {
    if (container) {
      container.classList.remove('show');
    } else {
      // Hide all suggestion containers
      [heroSearchSuggestions, searchSuggestions].forEach(cont => {
        if (cont) cont.classList.remove('show');
      });
    }
    currentHighlight = -1;
    currentSuggestionsContainer = null;
  }

  function updateHighlight(suggestions) {
    suggestions.forEach((item, index) => {
      if (index === currentHighlight) {
        item.classList.add('highlighted');
      } else {
        item.classList.remove('highlighted');
      }
    });
  }

  function selectSuggestion(city) {
    // Update all search inputs with selected city
    if (heroSearchInput) heroSearchInput.value = city;
    if (searchFormInput) searchFormInput.value = city;
    if (searchInput) searchInput.value = city;
    
    // Hide all suggestions
    hideSuggestions();
    
    // Optionally trigger search immediately
    // heroSearchInput.closest('form').submit();
  }

  // Initialize search synchronization when DOM is loaded
  initSearchSync();
</script>