<?php
// insert_rebuild_huiting.php : finished DEBUG version, all echos are commented

$wp_load = '/home/boxgra6/idx-cali.com/wp-load.php';
if (file_exists($wp_load)) {
    require_once $wp_load;
    //echo "<pre>DEBUG 1: wp-load loaded</pre>";
} else {
    die('no-load (wp-load.php not found)');
}

global $wpdb;
$token_db = new wpdb('boxgra6_sd3','Real_estate123$','boxgra6_sd3','localhost');
//echo "<pre>DEBUG 2: token_db connected</pre>";

$table1 = "rets_property";

// fetch token
//echo "<pre>DEBUG 3: fetching access_token ...</pre>";
$access_token = $token_db->get_var(
    $token_db->prepare(
        "SELECT access_token FROM token_store_huiting
         WHERE token_type=%s AND expires_at>%d
         ORDER BY expires_at DESC LIMIT 1",
        'trestle', time()
    )
);
//echo "<pre>DEBUG 3 result: ".($access_token?substr($access_token,0,25).'…':'[EMPTY]')."</pre>";
if (!$access_token) die('Error: no valid access_token found.');

// helper to build URL
function trestle_url(string $orderby, string $filter, array $extra = []): string {
    $params = [
        '$orderby' => $orderby,
        '$filter'  => $filter
    ] + $extra;
    return 'https://api-trestle.corelogic.com/trestle/odata/Property?' .
           http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

// first request: get total count
$count_url = trestle_url(
    "ListingContractDate desc,ListingKey desc",
    "PropertyType eq 'Residential' and MlsStatus eq 'Active'",
    ['$top'=>1,'$count'=>'true']
);
//echo "<pre>DEBUG 4: count URL = ".htmlspecialchars($count_url)."</pre>";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $count_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$access_token}"],
]);
$count_json = curl_exec($curl);
if ($count_json === false) {
    //echo "<pre>DEBUG curl_errno=".curl_errno($curl)." curl_error=".curl_error($curl)."</pre>";
}
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//echo "<pre>DEBUG 4 HTTP={$http_code}</pre>";
curl_close($curl);

$count_obj = json_decode($count_json);
//echo "<pre>DEBUG 4 resp=".(strlen($count_json)>80?substr($count_json,0,80).'...':$count_json)."</pre>";
if (empty($count_obj->{'@odata.count'})) die('<pre>DEBUG 4 ERROR: @odata.count missing</pre>');
$total = (int)$count_obj->{'@odata.count'};
//echo "<pre>DEBUG 4 total={$total}</pre>";

// pagination
$offset     = 200;
$skip_saved = (int)get_option('insert_property_offset');
//echo "<pre>DEBUG 5: current offset={$skip_saved}</pre>";
if ($skip_saved >= $total) $skip_saved = 0;
//echo "<pre>DEBUG 5 using skip={$skip_saved}</pre>";

// second request: get page data
$page_url = trestle_url(
    "ListingContractDate desc,ListingKey desc",
    "PropertyType eq 'Residential' and MlsStatus eq 'Active'",
    ['$skip'=>$skip_saved,'$top'=>$offset,
     '$expand'=>"Media(\$orderby=Order)",'$count'=>'true']
);
//echo "<pre>DEBUG 6: page URL = ".htmlspecialchars($page_url)."</pre>";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $page_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$access_token}"],
]);
$page_json = curl_exec($curl);
if ($page_json === false) {
    //echo "<pre>DEBUG curl_errno=".curl_errno($curl)." curl_error=".curl_error($curl)."</pre>";
}
$http_code2 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
//echo "<pre>DEBUG 6 HTTP={$http_code2}</pre>";
curl_close($curl);

$page = json_decode($page_json);
//echo "<pre>DEBUG 6 resp=".(strlen($page_json)>80?substr($page_json,0,80).'...':$page_json)."</pre>";
if (empty($page->value)) die('<pre>DEBUG 6 ERROR: page->value empty</pre>');
//echo "<pre>DEBUG 6 records=".count($page->value)."</pre>";

// insert loop
$Inserted = $noInserted = 0;
foreach ($page->value as $row) {
    // check if exists
    $exists = $token_db->get_var(
        $token_db->prepare(
            "SELECT 1 FROM {$table1} WHERE L_ListingID=%s LIMIT 1",
            $row->ListingKey
        )
    );
    if ($exists) {
        $noInserted++;
        //echo "<pre>DEBUG: Skipped ListingKey={$row->ListingKey}</pre>";
        continue;
    }

    $media_urls = [];
    if (!empty($row->Media)) foreach ($row->Media as $m) $media_urls[] = $m->MediaURL;
    $images = json_encode($media_urls);
    $levels = $row->Levels ?? '';

    $data1 = [
        'L_ListingID'         => $row->ListingKey,
        'L_DisplayId'         => $row->ListingKey,
        'L_Address'           => $row->UnparsedAddress,
        'L_Zip'               => $row->PostalCode,
        'LM_char10_70'        => $row->SubdivisionName,
        'L_AddressStreet'     => $row->StreetName,
        'L_City'              => $row->City,
        'L_State'             => $row->StateOrProvince,
        'L_Class'             => $row->PropertyType,
        'L_Type_'             => $row->PropertySubType,
        'L_Keyword2'          => $row->BedroomsTotal,
        'LM_Dec_3'            => $row->BathroomsTotalInteger,
        'L_Keyword1'          => $row->LotSizeArea,
        'L_Keyword5'          => $row->GarageSpaces,
        'L_Keyword7'          => $levels,
        'L_SystemPrice'       => $row->ListPrice,
        'LM_Int2_3'           => $row->LivingArea,
        'L_ListingDate'       => $row->ModificationTimestamp,
        'ListingContractDate' => $row->ListingContractDate,
        'LMD_MP_Latitude'     => $row->Latitude,
        'LMD_MP_Longitude'    => $row->Longitude,
        'LA1_UserFirstName'   => $row->ListAgentFirstName,
        'LA1_UserLastName'    => $row->ListAgentLastName,
        'L_Status'            => $row->MlsStatus,
        'LO1_OrganizationName'=> $row->ListOfficeName,
        'L_Remarks'           => $row->PublicRemarks,
        'L_Photos'            => $images,
        'PhotoTime'           => $row->PhotosChangeTimestamp,
        'PhotoCount'          => $row->PhotosCount,
        'L_alldata'           => json_encode($row)
    ];
    $ok = $token_db->insert($table1, $data1);
    if ($ok) {
        $Inserted++;
        //echo "<pre>DEBUG: Inserted ListingKey={$row->ListingKey}</pre>";
    } else {
        $noInserted++;
        //echo "<pre>DEBUG: Failed Insert ListingKey={$row->ListingKey}</pre>";
    }
}

$new_offset = ($skip_saved + $offset >= $total) ? 0 : $skip_saved + $offset;
update_option('insert_property_offset', $new_offset);
//echo "<pre>DEBUG done: Inserted={$Inserted} Skipped={$noInserted} new_offset={$new_offset}</pre>";

// helper for checking existence
function check_property_insert_exist($listid){
    global $token_db,$table1;
    return $token_db->get_var(
        $token_db->prepare(
            "SELECT 1 FROM {$table1} WHERE L_ListingID=%s LIMIT 1",$listid
        )
    );
}
?>