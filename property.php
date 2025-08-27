<?php

session_start();
include('api/db.php');
include('includes/functions.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get property ID from URL
$property_id = isset($_GET['id']) ? $_GET['id'] : '';
$ref = $_GET['ref'] ?? '';

if (empty($property_id)) {
    header('Location: index.php');
    exit;
}

$is_saved = false;
if (isset($_SESSION['user_id'])) {
    $check_sql = "SELECT 1 FROM saved_listings WHERE user_id = ? AND listing_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $_SESSION['user_id'], $property_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    $is_saved = $check_stmt->num_rows > 0;
    $check_stmt->close();
}

// Fetch property details
$sql = "SELECT * FROM rets_property_yu WHERE L_ListingID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $property_id);
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
    if (!is_array($photos)) $photos = [];
}

// Parse all data if available
$all_data = [];
if (!empty($property['L_alldata'])) {
    $all_data = json_decode($property['L_alldata'], true);
    if (!is_array($all_data)) $all_data = [];
}

// Get open houses for this property
$openhouse_sql = "SELECT * FROM rets_openhouse WHERE L_ListingID = ? ORDER BY OpenHouseDate ASC";
$openhouse_stmt = $conn->prepare($openhouse_sql);
$openhouse_stmt->bind_param("s", $property_id);
$openhouse_stmt->execute();
$openhouse_result = $openhouse_stmt->get_result();

$openhouses = [];
while ($row = $openhouse_result->fetch_assoc()) {
    $openhouses[] = $row;
}

$stmt->close();
$openhouse_stmt->close();
$nearby_sql = "SELECT * FROM rets_property_yu WHERE L_City = ? AND L_ListingID != ? LIMIT 3";
$nearby_stmt = $conn->prepare($nearby_sql);
$nearby_stmt->bind_param("ss", $property['L_City'], $property['L_ListingID']);
$nearby_stmt->execute();
$nearby_result = $nearby_stmt->get_result();

$nearby_listings = [];
while ($row = $nearby_result->fetch_assoc()) {
    $nearby_listings[] = $row;
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['L_Address']); ?> - California Homes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        .property-tags {
        margin-top: 20px;
        margin-bottom: 25px;
        }

        .tag-badge {
            display: inline-block;
            background-color: #e2e8f0;
            color: #333;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 20px;
            margin: 4px 6px 0 0;
            font-weight: 500;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: background-color 0.2s;
        }

        .tag-badge:hover {
            background-color: #cbd5e1;
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
        .photo-carousel {
            position: relative;
            width: 100%;
            max-width: 700px;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-photo {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
            user-select: none;
        }

        .carousel-btn {
            position: absolute;
            background: rgba(0,0,0,0.4);
            border: none;
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            padding: 10px 15px;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 50%;
            user-select: none;
            transition: background 0.3s;
        }

        #prev-btn {
            left: 10px;
        }

        #next-btn {
            right: 10px;
        }

        .carousel-btn:hover {
            background: rgba(0,0,0,0.7);
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
        @media (max-width: 768px) {
            .property-content {
                grid-template-columns: 1fr;
            }
            .property-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        <?php if ($ref === 'saved'): ?>
            <a href="saved_list.php" class="back-btn">← Back to Saved Listings</a>
        <?php else: ?>
            <a href="index.php" class="back-btn">← Back to Properties</a>
        <?php endif; ?>
        
        <div class="property-header">
            <h1 class="property-title"><?php echo htmlspecialchars($property['L_Address']); ?></h1>
            <div class="property-price">$<?php echo number_format($property['L_SystemPrice']); ?></div>
            <?php
            $address = urlencode($property['L_Address'] . ', ' . $property['L_City'] . ', ' . $property['L_State'] . ' ' . $property['L_Zip']);
            ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div style="margin: 1rem 0;">
                    <?php if ($is_saved): ?>
                        <form method="POST" action="unsave_listing.php">
                            <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($property_id); ?>">
                            <button type="submit" class="back-btn">Remove From Saved Listings</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="save_listing.php">
                            <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($property_id); ?>">
                            <button type="submit" class="back-btn">Save This Listing</button>
                        </form>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button id="sendAlertBtn" class="back-btn" style="margin-top: 10px;">Send Email Alert</button>
                        <div id="alertMessage" style="margin-top: 10px; display:none;" class="text-success">
                            Email sent!
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@califorsale.org'): ?>
                <form method="POST" action="admin_delete_listing.php" onsubmit="return confirm('Are you sure you want to delete this listing?');" style="margin-top: 10px;">
                    <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($property_id); ?>">
                    <button type="submit" class="back-btn" style="background-color: #dc3545; color: white;">Delete This Listing</button>
                </form>
            
                <button class="back-btn" style="background-color: #0d6efd; color: white; margin-top: 10px;" data-bs-toggle="modal" data-bs-target="#editListingModal">
                    Edit This Listing
                </button>
            <?php endif; ?>
            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $address; ?>" 
            target="_blank" class="back-btn" style="margin-top: 10px; display: inline-block;">
            🗺️ View on Google Maps
            </a>
            <?php
            
// Simulate having 30 tags
$tags = [
    "Newly Renovated", "Pet Friendly", "Granite Countertops", "Hardwood Floors", "Walk-In Closet",
    "Swimming Pool", "Energy Efficient", "Near Schools", "Modern Design", "Gated Community",
    "Finished Basement", "Stainless Steel Appliances", "Fireplace", "Rooftop Deck", "Furnished",
    "Corner Lot", "Skylights", "Custom Cabinets", "Solar Panels", "Large Backyard",
    "City View", "Cul-de-sac", "Fenced Yard", "Smart Home", "Home Office",
    "Two-Story", "Open Floor Plan", "Vaulted Ceilings", "Wine Cellar", "Marble Floors"
];

// Only show max 10 tags
$tags_to_display = array_slice($tags, 0, 10);
?>

            <div class="property-tags">
            <?php foreach ($tags_to_display as $tag): ?>
                <span class="tag-badge"><?php echo htmlspecialchars($tag); ?></span>
            <?php endforeach; ?>
            </div>
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
                <div class="photo-carousel">
                    <button class="carousel-btn" id="prev-btn" aria-label="Previous Photo">‹</button>
                    <img src="<?php echo htmlspecialchars($photos[0]); ?>" alt="Property Photo" class="carousel-photo" id="carousel-photo" />
                    <button class="carousel-btn" id="next-btn" aria-label="Next Photo">›</button>
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

    <?php if (!empty($nearby_listings)): ?>
    <div class="nearby-listings" style="margin-top: 30px;">
        <h3 class="section-title" style="margin-bottom: 15px;">Nearby Listings in <?php echo htmlspecialchars($property['L_City']); ?></h3>
        <?php foreach ($nearby_listings as $listing): ?>
        <div class="nearby-item" style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <a href="property.php?id=<?php echo urlencode($listing['L_ListingID']); ?>" 
               style="font-weight: bold; font-size: 1rem; color: #333; text-decoration: none;">
               <?php echo htmlspecialchars($listing['L_Address']); ?>
            </a>
            <div style="font-size: 0.9rem; color: #555; margin-top: 5px;">
                $<?php echo number_format($listing['L_SystemPrice']); ?> — 
                <?php echo $listing['L_Keyword2'] ?? '-' ?> Beds • 
                <?php echo $listing['LM_Dec_3'] ?? '-' ?> Baths
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

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
                                echo date('g:i A', strtotime($openhouse['OH_StartTime'])) . ' - ' . 
                                     date('g:i A', strtotime($openhouse['OH_EndTime']));
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
    
    <!-- Edit Listing Modal (for Admin) -->
    <div class="modal fade" id="editListingModal" tabindex="-1" aria-labelledby="editListingModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-3">
          <div class="modal-header">
            <h5 class="modal-title">Edit Listing</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
    
          <form id="editListingForm">
            <div class="modal-body">
                <input type="hidden" name="listing_id" value="<?php echo htmlspecialchars($property['L_ListingID']); ?>">
              <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" step="1" class="form-control" name="price" value="<?php echo htmlspecialchars(str_replace(',', '', $property['L_SystemPrice'])); ?>">
              </div>
    
              <div class="mb-3">
                <label for="beds" class="form-label">Beds</label>
                <input type="number" class="form-control" name="beds" value="<?php echo htmlspecialchars($property['L_Keyword2']); ?>">
              </div>
    
              <div class="mb-3">
                <label for="baths" class="form-label">Baths</label>
                <input type="number" step="0.1" class="form-control" name="baths" value="<?php echo htmlspecialchars($property['LM_Dec_3']); ?>">
              </div>
    
              <div class="mb-3">
                <label for="sqft" class="form-label">Square Feet</label>
                <input type="number" class="form-control" name="sqft" value="<?php echo htmlspecialchars($property['LM_Int2_3']); ?>">
              </div>
    
              <div class="mb-3">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea class="form-control" name="remarks"><?php echo htmlspecialchars($property['L_Remarks']); ?></textarea>
              </div>
    
              <div class="mb-3">
                <label for="agent_first" class="form-label">Agent First Name</label>
                <input type="text" class="form-control" name="agent_first" value="<?php echo htmlspecialchars($property['LA1_UserFirstName']); ?>">
              </div>
    
              <div class="mb-3">
                <label for="agent_last" class="form-label">Agent Last Name</label>
                <input type="text" class="form-control" name="agent_last" value="<?php echo htmlspecialchars($property['LA1_UserLastName']); ?>">
              </div>
    
              <div class="mb-3">
                <label for="office" class="form-label">Office Name</label>
                <input type="text" class="form-control" name="office" value="<?php echo htmlspecialchars($property['LO1_OrganizationName']); ?>">
              </div>
    
              <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <option value="active" <?php echo ($property['L_Status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                  <option value="pending" <?php echo ($property['L_Status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                </select>
              </div>
    
              <div id="editFeedback" class="text-center mt-2"></div>
            </div>
    
            <div class="modal-footer d-flex justify-content-between">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <?php if (!empty($photos) && count($photos) > 1): ?>
    <script>
        const photos = <?php echo json_encode($photos); ?>;
        let currentIndex = 0;

        const carouselPhoto = document.getElementById('carousel-photo');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');

        function showPhoto(index) {
            currentIndex = (index + photos.length) % photos.length;
            carouselPhoto.src = photos[currentIndex];
        }

        prevBtn.addEventListener('click', () => {
            showPhoto(currentIndex - 1);
        });

        nextBtn.addEventListener('click', () => {
            showPhoto(currentIndex + 1);
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') showPhoto(currentIndex - 1);
            else if (e.key === 'ArrowRight') showPhoto(currentIndex + 1);
        });
    </script>
    <?php endif; ?>

    <?php if (!empty($property['LMD_MP_Latitude']) && !empty($property['LMD_MP_Longitude'])): ?>
    <script>
        const map = L.map('property-map').setView([<?php echo $property['LMD_MP_Latitude']; ?>, <?php echo $property['LMD_MP_Longitude']; ?>], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        L.marker([<?php echo $property['LMD_MP_Latitude']; ?>, <?php echo $property['LMD_MP_Longitude']; ?>]).addTo(map)
            .bindPopup('<?php echo addslashes(htmlspecialchars($property['L_Address'])); ?>')
            .openPopup();
    </script>
    <?php endif; ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const alertBtn = document.getElementById("sendAlertBtn");
        const messageDiv = document.getElementById("alertMessage");
    
        if (alertBtn) {
            alertBtn.addEventListener("click", function () {
                fetch("send_alert.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "listing_id=<?php echo urlencode($property_id); ?>"
                })
                .then(response => response.json())
                .then(data => {
                    messageDiv.style.display = "block";
                    if (data.success) {
                        messageDiv.classList.remove("text-danger");
                        messageDiv.classList.add("text-success");
                        messageDiv.innerText = "Email alert sent!";
                    } else {
                        messageDiv.classList.remove("text-success");
                        messageDiv.classList.add("text-danger");
                        messageDiv.innerText = data.message || "Failed to send email.";
                    }
                })
                .catch(() => {
                    messageDiv.style.display = "block";
                    messageDiv.classList.remove("text-success");
                    messageDiv.classList.add("text-danger");
                    messageDiv.innerText = "Something went wrong.";
                });
            });
        }
    });
    </script>
</body>
</html>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("editListingForm");
  const feedback = document.getElementById("editFeedback");

  form?.addEventListener("submit", function (e) {
    e.preventDefault();
    feedback.textContent = "Saving...";

    const formData = new FormData(form);

    fetch("admin_update_listing.php", {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        feedback.className = "text-success";
        feedback.textContent = "Listing updated successfully. Reloading...";
        setTimeout(() => location.reload(), 1500);
      } else {
        feedback.className = "text-danger";
        feedback.textContent = data.message || "Failed to update listing.";
      }
    })
    .catch(() => {
      feedback.className = "text-danger";
      feedback.textContent = "An error occurred.";
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const editModal = document.getElementById('editListingModal');
  const editForm = document.getElementById('editListingForm');

  if (editModal && editForm) {
    editModal.addEventListener('hidden.bs.modal', function () {
      editForm.reset();

      const feedback = document.getElementById('editFeedback');
      if (feedback) {
        feedback.innerText = '';
        feedback.className = '';
      }
    });
  }
});
</script>
