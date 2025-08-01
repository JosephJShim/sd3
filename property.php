<?php

include('api/db.php');
include('includes/functions.php');

// include ('includes/db_local.php');
// include ('includes/functions.php');


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get property ID from URL
$property_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($property_id)) {
    header('Location: index.php');
    exit;
}

// Fetch property details

$sql = "SELECT * FROM rets_property_yu WHERE L_ListingID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $property_id);

// $sql = 'SELECT * FROM rets_property WHERE L_ListingID = ?';
// $stmt = $conn->prepare($sql);
// $stmt->bind_param('s', $property_id);

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$property = $result->fetch_assoc();

// Parse photos
$photos = [];
if (!empty($property['L_Photos'])) {
    $photos = json_decode($property['L_Photos'], true);

//     if (!is_array($photos)) $photos = [];
// }

// // Parse all data if available
// $all_data = [];
// if (!empty($property['L_alldata'])) {
//     $all_data = json_decode($property['L_alldata'], true);
//     if (!is_array($all_data)) $all_data = [];
// }

// // Get open houses for this property
// $openhouse_sql = "SELECT * FROM rets_openhouse WHERE L_ListingID = ? ORDER BY OpenHouseDate ASC";
// $openhouse_stmt = $conn->prepare($openhouse_sql);
// $openhouse_stmt->bind_param("s", $property_id);
// $openhouse_stmt->execute();
// $openhouse_result = $openhouse_stmt->get_result();

// $openhouses = [];
// while ($row = $openhouse_result->fetch_assoc()) {
//     $openhouses[] = $row;
// }


    if (!is_array($photos))
        $photos = [];
}

// Parse all data if available
$all_data = [];
if (!empty($property['L_alldata'])) {
    $all_data = json_decode($property['L_alldata'], true);
    if (!is_array($all_data))
        $all_data = [];
}

// Get open houses for this property
$openhouse_sql = 'SELECT * FROM rets_openhouse WHERE L_ListingID = ? ORDER BY OpenHouseDate ASC';
$openhouse_stmt = $conn->prepare($openhouse_sql);
$openhouse_stmt->bind_param('s', $property_id);
$openhouse_stmt->execute();
$openhouse_result = $openhouse_stmt->get_result();

$openhouses = [];
while ($row = $openhouse_result->fetch_assoc()) {
    $openhouses[] = $row;
}

$stmt->close();
$openhouse_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['L_Address']); ?> - California Homes</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
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
            padding: 20px 20px 0 20px;
        }
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
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .logo img {
            height: 48px;
            width: auto;
            display: inline-block;
            vertical-align: middle;
        }
        .logo-divider {
            width:2px;
            height:32px;
            background:white;
            margin:0 10px;
            border-radius:2px;
        }
        .logo-text {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            margin-left: 0;
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
        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }
        .property-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .property-title {
            font-size: 2em;
            margin-bottom: 10px;
            color: #333;
        }
        .property-price {
            font-size: 1.5em;
            color: #007bff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .property-address {
            font-size: 1.2em;
            color: #666;
            margin-bottom: 15px;
        }
        .property-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .property-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .main-content {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .sidebar {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .photo-gallery {
            margin-bottom: 30px;
        }
        .main-photo {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .photo-thumbnails {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }
        .photo-thumbnail {
            width: 100px;
            height: 75px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .photo-thumbnail:hover {
            opacity: 0.8;
        }
        .section-title {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .agent-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .agent-name {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .agent-office {
            color: #666;
            margin-bottom: 10px;
        }
        .openhouse-item {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .openhouse-date {
            font-weight: bold;
            color: #007bff;
        }
        .openhouse-time {
            color: #666;
        }
        .property-map {
            height: 300px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .property-details {
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
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
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-sold {
            background: #f8d7da;
            color: #721c24;
        }

        .property-header-row {
            display: flex;
            align-items: stretch;
            gap: 2rem;
            margin-bottom: 20px;
        }
        .property-header {
            flex: 1 1 0;
        }
        #map {
            min-width: 300px;
            max-width: 600px;
            width: 100%;
            height: 200px;
            border-radius: 12px;
        }
        @media (max-width: 900px) {
            .property-header-row {
                flex-direction: column;
                gap: 1rem;
            }
            #map {
                width: 100%;
                min-width: 0;
                height: 200px;
                margin-left: 0;
            }
        }
      
        @media (max-width: 768px) {
            .property-content {
                grid-template-columns: 1fr;
            }
            .property-stats {
                grid-template-columns: repeat(2, 1fr);
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
                    <div class="logo-divider"></div>
                    <span class="logo-text">California Homes</span>
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
    <div class="container">
        <a href="index.php" class="back-btn">← Back to Properties</a>
        
        <div class="property-header-row">
            <div class="property-header">
                <h1 class="property-title"><?php echo htmlspecialchars($property['L_Address']); ?></h1>
                <div class="property-price">$<?php echo number_format($property['L_SystemPrice']); ?></div>
                <div class="property-address">
                    <?php echo htmlspecialchars($property['L_Address']); ?>, 
                    <?php echo htmlspecialchars($property['L_City']); ?>, 
                    <?php echo htmlspecialchars($property['L_State']); ?> 
                    <?php echo htmlspecialchars($property['L_Zip']); ?>
                </div>
                <?php if (!empty($property['L_Status'])): ?>
                <span class="status-badge status-<?php echo strtolower($property['L_Status']); ?>">
                    <?php echo htmlspecialchars($property['L_Status']); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php
            // Use the correct keys for your lat/lng fields!
            $lat = !empty($property['lat']) ? $property['lat'] : (!empty($property['LMD_MP_Latitude']) ? $property['LMD_MP_Latitude'] : null);
            $lng = !empty($property['lng']) ? $property['lng'] : (!empty($property['LMD_MP_Longitude']) ? $property['LMD_MP_Longitude'] : null);
            ?>
            <?php if (!empty($lat) && !empty($lng)): ?>
                <div id="map"></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($lat) && !empty($lng)): ?>
        <script>
            function initMap() {
                const center = {
                    lat: parseFloat('<?php echo $lat; ?>'),
                    lng: parseFloat('<?php echo $lng; ?>')
                };
                const map = new google.maps.Map(document.getElementById('map'), {
                    zoom: 14,
                    center: center
                });
                new google.maps.Marker({
                    position: center,
                    map: map,
                    title: "<?php echo htmlspecialchars($property['L_Address']); ?>"
                });
            }
        </script>
        <script async
            src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB0szWlIt9Vj26cM300wTcWxwL0ABHZ9HE&callback=initMap">
        </script>
        <?php endif; ?>

        <div class="property-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo $property['L_Keyword2'] ?? '-'; ?></div>
                <div class="stat-label">Bedrooms</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $property['LM_Dec_3'] ?? '-'; ?></div>
                <div class="stat-label">Bathrooms</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($property['LM_Int2_3'] ?? 0); ?></div>
                <div class="stat-label">Square Feet</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $property['L_Keyword5'] ?? '-'; ?></div>
                <div class="stat-label">Garage Spaces</div>
            </div>
        </div>

        <div class="property-content">
            <div class="main-content">
                <?php if (!empty($photos)): ?>
                <div class="photo-gallery">
                    <img src="<?php echo htmlspecialchars($photos[0]); ?>" alt="Main Photo" class="main-photo" id="main-photo">
                    <?php if (count($photos) > 1): ?>
                    <div class="photo-thumbnails">
                        <?php foreach ($photos as $index => $photo): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="Photo <?php echo $index + 1; ?>" 
                             class="photo-thumbnail" onclick="changeMainPhoto('<?php echo htmlspecialchars($photo); ?>')">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="property-details">
                    <h2 class="section-title">Property Details</h2>
                    <div class="detail-row">
                        <span class="detail-label">Property Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['L_Type_'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Property Class:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['L_Class'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Listing Date:</span>
                        <span class="detail-value"><?php echo $property['L_ListingDate'] ? date('M j, Y', strtotime($property['L_ListingDate'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contract Date:</span>
                        <span class="detail-value"><?php echo $property['ListingContractDate'] ? date('M j, Y', strtotime($property['ListingContractDate'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Lot Size:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['L_Keyword7'] ?? 'N/A'); ?></span>
                    </div>
                </div>


                <?php if (!empty($property['L_Remarks'])): ?>
                <div class="property-description">
                    <h2 class="section-title">Description</h2>
                    <p><?php echo nl2br(htmlspecialchars($property['L_Remarks'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($property['LMD_MP_Latitude']) && !empty($property['LMD_MP_Longitude'])): ?>
                <div id="property-map" class="property-map"></div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <div class="agent-card">
                    <h3 class="section-title">Listing Agent</h3>
                    <?php if (!empty($property['LA1_UserFirstName']) || !empty($property['LA1_UserLastName'])): ?>
                    <div class="agent-name">
                        <?php echo htmlspecialchars($property['LA1_UserFirstName'] . ' ' . $property['LA1_UserLastName']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($property['LO1_OrganizationName'])): ?>
                    <div class="agent-office">
                        <?php echo htmlspecialchars($property['LO1_OrganizationName']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($openhouses)): ?>
                <div class="openhouses">
                    <h3 class="section-title">Open Houses</h3>
                    <?php foreach ($openhouses as $openhouse): ?>
                    <div class="openhouse-item">
                        <div class="openhouse-date">
                            <?php echo date('l, F j, Y', strtotime($openhouse['OpenHouseDate'])); ?>
                        </div>
                        <div class="openhouse-time">
                            <?php
                            if ($openhouse['OH_StartTime'] && $openhouse['OH_EndTime']) {
                                echo date('g:i A', strtotime($openhouse['OH_StartTime'])) . ' - '
                                    . date('g:i A', strtotime($openhouse['OH_EndTime']));
                            }
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="property-info">
                    <h3 class="section-title">Property Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Listing ID:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($property['L_DisplayId'] ?? $property['L_ListingID']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Last Updated:</span>
                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($property['updated_at'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Photos:</span>
                        <span class="detail-value"><?php echo count($photos); ?> photos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html> 