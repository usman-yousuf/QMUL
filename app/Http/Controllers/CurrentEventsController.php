<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\apiHelper;


class CurrentEventsController extends Controller
{
    public function index()
    {
        // Acquire/refresh CRM API access token
        $accessToken_data = getCRMAccessToken();
        if ($accessToken_data && isset($accessToken_data['accessToken'])) {
            session(['accessToken' => $accessToken_data['accessToken']]);
        } else {
            return 'Unable to connect to CRM. Please contact an administrator.';
        }

        // Fetch events data
        $noofpages = ["PageSize" => '1000'];
        $response = json_decode(callAPI('GET', 'events', session('accessToken'), $noofpages), true);
        $events = $response['data'];

        return view('current-events', compact('events'));
    }
}
