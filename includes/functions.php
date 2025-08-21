<?php
function build_filter_query($params) {
    $conditions = [];
    if (!empty($params['min'])) $conditions[] = "L_SystemPrice >= " . intval($params['min']);
    if (!empty($params['max'])) $conditions[] = "L_SystemPrice <= " . intval($params['max']);
    if (!empty($params['beds'])) $conditions[] = "L_Keyword2 >= " . intval($params['beds']);
    if (!empty($params['baths'])) $conditions[] = "LM_Dec_3 >= " . intval($params['baths']);
    if (!empty($params['city'])) $conditions[] = "L_City LIKE '%" . addslashes($params['city']) . "%'";
    if (!empty($params['address'])) {
        $address_escaped = addslashes($params['address']);
        $conditions[] = "(L_City LIKE '%" . $address_escaped . "%' OR L_Zip LIKE '%" . $address_escaped . "%' OR L_Address LIKE '%" . $address_escaped . "%')";
    }
    return $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
}
function build_saved_filter_query($params) {
    $conditions = [];
    if (!empty($params['min'])) $conditions[] = "L_SystemPrice >= " . intval($params['min']);
    if (!empty($params['max'])) $conditions[] = "L_SystemPrice <= " . intval($params['max']);
    if (!empty($params['beds'])) $conditions[] = "L_Keyword2 >= " . intval($params['beds']);
    if (!empty($params['baths'])) $conditions[] = "LM_Dec_3 >= " . intval($params['baths']);
    if (!empty($params['city'])) $conditions[] = "L_City LIKE '%" . addslashes($params['city']) . "%'";
    if (!empty($params['address'])) {
        $address_escaped = addslashes($params['address']);
        $conditions[] = "(L_City LIKE '%" . $address_escaped . "%' OR L_Zip LIKE '%" . $address_escaped . "%' OR L_Address LIKE '%" . $address_escaped . "%')";
    }
    return $conditions ? "AND " . implode(" AND ", $conditions) : "";
}
?>
