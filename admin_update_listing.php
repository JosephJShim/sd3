<?php
session_start();
header('Content-Type: application/json');
include('api/db.php');

$response = ['success' => false, 'message' => 'Unauthorized access.'];

if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@califorsale.org') {
    echo json_encode($response);
    exit;
}

$listing_id = $_POST['listing_id'] ?? '';
if (!$listing_id) {
    $response['message'] = "Missing listing ID.";
    echo json_encode($response);
    exit;
}

$fields = [
    'L_SystemPrice'     => $_POST['price'] ?? null,
    'L_Keyword2'        => $_POST['beds'] ?? null,
    'LM_Dec_3'          => $_POST['baths'] ?? null,
    'LM_Int2_3'         => $_POST['sqft'] ?? null,
    'L_Remarks'         => $_POST['remarks'] ?? null,
    'LA1_UserFirstName' => $_POST['agent_first'] ?? null,
    'LA1_UserLastName'  => $_POST['agent_last'] ?? null,
    'LO1_OrganizationName' => $_POST['office'] ?? null,
    'L_Status'          => $_POST['status'] ?? null
];

if (empty($fields)) {
    $response['message'] = "No fields provided to update.";
    echo json_encode($response);
    exit;
}

$set_clauses = [];
$params = [];
$types = '';

foreach ($fields as $column => $value) {
    $set_clauses[] = "$column = ?";
    $params[] = $value;
    $types .= 's';
}

$params[] = $listing_id;
$types .= 's';

$sql = "UPDATE rets_property_yu SET " . implode(', ', $set_clauses) . " WHERE L_ListingID = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $response['message'] = "Prepare failed: " . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = "Listing updated successfully.";
} else {
    $response['message'] = "Update failed: " . $stmt->error;
}

$stmt->close();
echo json_encode($response);