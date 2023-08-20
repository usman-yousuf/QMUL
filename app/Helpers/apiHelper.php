<?php

function callAPI($method, $request, $token, $data) {
    $curl = curl_init();
    
    $status = env('status') == 'LIVE' ? 'LIVE' : 'DEV';
    $url = env($status.'_API_URL') . $request . '/';
    $apiKey = env($status.'_API_KEY');
    
    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
            break;
        default:
            if ($data) {
                $url = sprintf("%s?%s", $url, http_build_query($data));
            }
    }
    
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'accept: text/plain',
        'Content-Type: application/json-patch+json',
        'X-API-Key: '.$apiKey,
        'Authorization: Bearer '.$token
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    
    $result = curl_exec($curl);
    if (!$result) {die("Connection Failure");}
    curl_close($curl);
    return $result;
}

function getCRMAccessToken() {
    try {
        $login_creds = new \stdClass();
        $status = env('status') == 'LIVE' ? 'LIVE' : 'DEV';
        
        $login_creds->userName = env($status.'_API_USER');
        $login_creds->password = env($status.'_API_PASS');
        
        return json_decode(callAPI('POST', 'account/signin', '', json_encode($login_creds)), true);
    } catch (Exception $e) {
        echo 'Unable to get CRM API access token. Please contact an administrator.';
        return;
    }
}
?>
