<?php
function build_filter_query($params) {
    $conditions = [];
    if (!empty($params['min'])) $conditions[] = "L_SystemPrice >= " . intval($params['min']);
    if (!empty($params['max'])) $conditions[] = "L_SystemPrice <= " . intval($params['max']);
    if (!empty($params['beds'])) $conditions[] = "L_Keyword2 >= " . intval($params['beds']);
    if (!empty($params['baths'])) $conditions[] = "LM_Dec_3 >= " . intval($params['baths']);
    if (!empty($params['city'])) $conditions[] = "L_City LIKE '%" . addslashes($params['city']) . "%'";
    
    // Address search - search across multiple fields
    if (!empty($params['address'])) {
        $address = addslashes(trim($params['address']));
        $address_conditions = [
            "L_Address LIKE '%" . $address . "%'",
            "L_City LIKE '%" . $address . "%'",
            "L_State LIKE '%" . $address . "%'",
            "L_Zip LIKE '%" . $address . "%'",
            "CONCAT(L_Address, ', ', L_City, ', ', L_State, ' ', L_Zip) LIKE '%" . $address . "%'"
        ];
        $conditions[] = "(" . implode(" OR ", $address_conditions) . ")";
    }
    
    return $conditions ? "WHERE " . implode(" AND ", $conditions) : "";
}

function build_sort_query($params) {
    $sort = isset($params['sort']) ? $params['sort'] : 'newest';
    
    switch ($sort) {
        case 'price_low':
            return "ORDER BY L_SystemPrice ASC";
        case 'price_high':
            return "ORDER BY L_SystemPrice DESC";
        case 'newest':
        default:
            return "ORDER BY created_at DESC";
    }
}
?>
