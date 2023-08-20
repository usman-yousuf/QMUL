<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpEcomConfig;
use GlobalPayments\Api\Services\HostedService;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use App\Helpers\apiHelper;
use App\Helpers\emailHelper;

class EventController extends Controller
{
    
    public function eventProcess(Request $request)
    {
        
        session_start();
        date_default_timezone_set('Europe/London');
        
        $sourceCode = 'WEBC';
        $noofpages = array("PageSize" => '1000');

        $alumniLogger = Log::channel('daily');
    
        $form_step = $request->input('step', 1);
        $prevClicked = $request->input('prevClicked', 'false') === 'true';
        
        if ($prevClicked) {

            $form_step = max(1, $form_step - 2); 

            if ($form_step === 1) {
                $evt_bookable = false;
                $errorMessage = null;
                $unavailableMessage = null;
                $eventID = $request->route('eventID');
                $evt_tickets = [];
                $accessToken = session('accessToken');
                $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                session(['evt_res' => $evt_res]);
    
                if (isset($evt_res) && isset($evt_res['eventId'])) {
                    session(['eventName' => $evt_res['eventName']]);
                    $maxAttendeesPerBooking = ((int)$evt_res['ticketsLeft'] < (int)$evt_res['maxAttendeesPerBooking']) ? (int)$evt_res['ticketsLeft'] : (int)$evt_res['maxAttendeesPerBooking'];
                    session(['maxAttendeesPerBooking' => $maxAttendeesPerBooking]);
                    session(['displayDietaryRequirements' => $evt_res['displayDietaryRequirements']]);
                    session(['eventSourceCode' => $evt_res['webSourceCode']]);
    
                    if (($evt_res['publishToWeb'] == true) || (env('show_unpublished_events') == 'TRUE')) {
                        if (isset($evt_res['publishOnWebUntil']) && \DateTime::createFromFormat('Y-m-d\TH:i:s', $evt_res['publishOnWebUntil']) !== false) {
                            $publishUntil = \DateTime::createFromFormat('Y-m-d\TH:i:s', $evt_res['publishOnWebUntil']);
                            $dateNow = new \DateTime('now');
                            if (($publishUntil > $dateNow) || (env('show_unpublished_events') == 'TRUE')) {
                                $evt_bookable = true;
                                $evt_att_response = json_decode(callAPI('GET', 'events/attendeeType', $accessToken, ['eventId' => $eventID, 'IncludeCosts' => true]), true);
                                session(['evt_tickets' => $evt_att_response['data']]);
                                $evt_tickets = session('evt_tickets');
                                
                            } else {
                                $errorMessage = "We're sorry...!";
                                $unavailableMessage = "This event is no longer available for booking.";
                            }
                        }
                    } else {
                        $errorMessage = "We're sorry...!";
                        $unavailableMessage = "This event is no longer available for booking.";
                    }
                } else {
                    $errorMessage = "We're sorry...!";
                    $unavailableMessage = "This event is no longer available for booking.";
                }
                
                $alumniLogger->info('Started session '.Session::get('token'));
               
                $tableRows = [];
                $evt_res = session('evt_res', []); 
                $evt_tickets = session('evt_tickets', []);
                $accessToken = session('accessToken');
                $maxAttendeesPerBooking = session('maxAttendeesPerBooking', 0);
                foreach ($evt_tickets as $item) {
                    $row = [];
                    $row['attendeeType'] = $item['attendeeType'];
                    $row['cost'] = 0;
        
                    try {
                        $costsResponse = json_decode(callAPI('GET', 'events/attendeeType/' . $item['attendeeTypeId'], $accessToken, ['IncludeCosts' => "true"]), true);
                        if (isset($costsResponse['costs']) && count($costsResponse['costs']) > 0) {
                            $costsArr = $costsResponse['costs'];
                            $row['cost'] = $costsArr[0]['value'];
                        } else {
                            $row['cost'] = 0;
                        }
        
                    } catch (ApiException $e) {
                        $alumniLogger->error('Session ' . $accessToken . ' - Unable to get attendee costs: ' . $e->getMessage());
                    }
        
                    $row['remaining'] = $item['remaining'];
                    $row['maxAttendeesPerBooking'] = $maxAttendeesPerBooking;
                    $row['attendeeTypeId'] = $item['attendeeTypeId'];
        
                    $tableRows[] = $row;
                }
        
                return view('event-booking', compact('evt_res', 'evt_bookable', 'tableRows', 'form_step', 'eventID', 'maxAttendeesPerBooking', 'accessToken','errorMessage','unavailableMessage'));
    
            } 
            if ($form_step === 2) {
                $eventID = $request->input('eventID') ?: $request->eventID;
                $ticketTypes = session('ticketTypes');
                $accessToken = session('accessToken');
                $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                
                $total_attendees = session('total_attendees');
                $total_cost = session('total_cost');
                
                return view('event-booking', compact('evt_res', 'form_step', 'eventID', 'total_attendees', 'total_cost', 'ticketTypes'));
            } 
            if ($form_step === 3) {

                $eventID = $request->input('eventID') ?: $request->eventID;
                
                $accessToken = session('accessToken');
                $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
        
                $attendee_title_arr = [];
                $title = request()->input('title');
                
                $title_other = request()->input('title_other');
                $titleArray = collect($title)->toArray();
                if ($titleArray) {
                    for ($i = 0; $i < count($titleArray); $i++) {
                        if ($titleArray[$i] == 'Other') {
                            array_push($attendee_title_arr, $title_other[$i]);
                        } else {
                            array_push($attendee_title_arr, $titleArray[$i]);
                        }
                    }
                }
    
    
                // Duplicate attendees check (endpoint only accepts a single record at a time, so it may be slower with 10+ attendees)
                $attendee_count = Session::get('attendee_count');
                $attendees_already_booked = [];
                $event_booking_ids = [];
    
                for ($i = 0; $i < $attendee_count; $i++) {
                    $firstName = Session::get('booking.attendeefirstname')[$i] ?? null;
                    $keyname = Session::get('booking.attendeesurname')[$i] ?? null;
                    $evt_att_response = json_decode(callAPI('GET', 'eventBooking/attendee', session('accessToken'), ['eventId' => $eventID, 'FirstName' => $firstName, 'Keyname' => $keyname]), true);

                    // Add to duplicates array
                    if (count($evt_att_response['data']) > 0) {
                        $att_dup = new \stdClass();
                        $att_dup->fullName = $evt_att_response['data'][0]['title'] . ' ' . $evt_att_response['data'][0]['firstName'] . ' ' . $evt_att_response['data'][0]['keyname'];
                        $att_dup->serialNumber = $evt_att_response['data'][0]['serialNumber'];
                        $att_dup->ticketType = $evt_att_response['data'][0]['attendeeId'];
                        $att_dup->idx = $i;
                        $attendees_already_booked[] = $att_dup;
    
                        // Add booking ID to array
                        if (!in_array($evt_att_response['data'][0]['bookingId'], $event_booking_ids)) {
                            $event_booking_ids[] = $evt_att_response['data'][0]['bookingId'];
                        }
                    }
                }
            
                // Allow main attendees (bookers) to register for additional events
                foreach ($event_booking_ids as $event_booking_id) {
    
                    $evt_bkg_response = json_decode(callAPI('GET', 'eventBooking/' . $event_booking_id, $accessToken, []), true);
                    if (count($evt_bkg_response) > 0) {
                        if (in_array($evt_bkg_response['mainAttendeeSerialNumber'], array_column($attendees_already_booked, 'serialNumber'))) {
                            $attendees_already_booked = array_filter($attendees_already_booked, function ($att) use ($evt_bkg_response) {
                                return $att->serialNumber != $evt_bkg_response['mainAttendeeSerialNumber'];
                            });
                        }
                    }
                }
            
                // Remove duplicate attendees and recalculate number of tickets and costs
                foreach ($attendees_already_booked as $attendee) {
                    $att_idx = $attendee->idx;
                    $att_ticket_type = $attendee->ticketType;
                    Session::put($att_ticket_type . 'Tickets', (int)Session::get($att_ticket_type . 'Tickets') - 1);
                    Session::put('totalAttendees', (int)Session::get('totalAttendees') - 1);
                    Session::put('totalCost', (int)Session::get('totalCost') - (int)Session::get($att_ticket_type . 'TicketsCost'));
    
                    $titles = Session::get('booking.title');
                    array_splice($titles, $att_idx, 1);
                    Session::put('booking.title', $titles);
    
                    $ids = Session::get('booking.id');
                    array_splice($ids, $att_idx, 1);
                    Session::put('booking.id', $ids);
    
                    $types = Session::get('booking.type');
                    array_splice($types, $att_idx, 1);
                    Session::put('booking.type', $types);
    
                    $costs = Session::get('booking.cost');
                    array_splice($costs, $att_idx, 1);
                    Session::put('booking.cost', $costs);
    
                    $firstnames = Session::get('booking.attendeefirstname');
                    array_splice($firstnames, $att_idx, 1);
                    Session::put('booking.attendeefirstname', $firstnames);
    
                    $surnames = Session::get('booking.attendeesurname');
                    array_splice($surnames, $att_idx, 1);
                    Session::put('booking.attendeesurname', $surnames);
    
                    $dietaries = Session::get('booking.attendeedietary');
                    array_splice($dietaries, $att_idx, 1);
                    Session::put('booking.attendeedietary', $dietaries);
    
                    $requirements = Session::get('booking.attendeespecialrequirements');
                    array_splice($requirements, $att_idx, 1);
                    Session::put('booking.attendeespecialrequirements', $requirements);
    
                }
                
                if (count($attendees_already_booked) > 0) {
                    $form_step = 2;
                }
                
                return view('event-booking', compact('evt_res', 'form_step', 'eventID'));
    
            } 
            if ($form_step == 4) {
            
                $eventID = $request->input('eventID') ?: $request->eventID;$eventID = $request->input('eventID') ?: $request->eventID;
                $request->session()->put('eventID', $eventID);
                $accessToken = session('accessToken');
                $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
               
                if (session('firstattendeeisbooker') == '1') {
                    session()->put('title', session('booking.title')[0]);
                    session()->put('firstname', session('booking.attendeefirstname')[0]);
                    session()->put('lastname', session('booking.attendeesurname')[0]);
                } else {
                    if (request()->has('title')) {
                        if (request('title') == 'Other' && (request()->has('title_other') && strlen(request('title_other')) > 0)) {
                            session()->put('title', request('title_other'));
                        } else {
                            session()->put('title', request('title', ''));
                        }
                    }
                    session()->put('firstname', request('firstname', ''));
                    session()->put('lastname', request('lastname', ''));
                }
                
                session()->put('country', request('country', ''));
                session()->put('address', request('address', ''));
                session()->put('town', request('town', ''));
                session()->put('county', request('county', ''));
                session()->put('postcode', request('postcode', ''));
                session()->put('email', request('email', ''));
                session()->put('telephone_day', request('telephone_day', ''));
                session()->put('telephone_evening', request('telephone_evening', ''));
                session()->put('mobile', request('mobile', ''));
                session()->put('contact_post', request()->has('contact_post') ? request('contact_post') : '0');
                session()->put('contact_email', request()->has('contact_email') ? request('contact_email') : '0');
    
                return view('event-booking', compact('evt_res', 'form_step', 'eventID'));
                
            }
        }else {
            
            if(!$prevClicked){
                session_unset();
		        $_SESSION['token'] = uniqid();
                
                $_SESSION['qmul_order_id'] = $_SESSION['token'];
            }
            // Acquire/refresh CRM API access token
            if (Session::has('accessTokenExpiry')) {
                $tokenExpiry = Carbon::parse(Session::get('accessTokenExpiry'));
                $dateNow = Carbon::now();
                if ($tokenExpiry < $dateNow) {
                    Session::forget('accessToken');
                }
            }
            if (!Session::has('accessToken')) {
                $accessToken_data = getCRMAccessToken(); 
                if ($accessToken_data && isset($accessToken_data['accessToken'])) {
                    Session::put('accessToken', $accessToken_data['accessToken']);
                    Session::put('accessTokenExpiry', $accessToken_data['expiry']);
                } else {
                    exit('Unable to connect to CRM. Please contact an administrator.');
                }
            }
            switch ($form_step) {
                case 1:
                    // dd('test 1');
                    // dd($request->eventID);    

                    $evt_bookable = false;
                    $errorMessage = null;
                    $unavailableMessage = null;
                    $ticketTypes = session('ticketTypes');
                    $eventID = $request->eventID;
                    $evt_tickets = [];
                    $accessToken = session('accessToken');
                    $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                    session(['evt_res' => $evt_res]);

                    if (isset($evt_res) && isset($evt_res['eventId'])) {
                        session(['eventName' => $evt_res['eventName']]);
                        $maxAttendeesPerBooking = ((int)$evt_res['ticketsLeft'] < (int)$evt_res['maxAttendeesPerBooking']) ? (int)$evt_res['ticketsLeft'] : (int)$evt_res['maxAttendeesPerBooking'];
                        session(['maxAttendeesPerBooking' => $maxAttendeesPerBooking]);
                        session(['displayDietaryRequirements' => $evt_res['displayDietaryRequirements']]);
                        session(['eventSourceCode' => $evt_res['webSourceCode']]);

                        if (($evt_res['publishToWeb'] == true) || (env('show_unpublished_events') == 'TRUE')) {
                            if (isset($evt_res['publishOnWebUntil']) && \DateTime::createFromFormat('Y-m-d\TH:i:s', $evt_res['publishOnWebUntil']) !== false) {
                                $publishUntil = \DateTime::createFromFormat('Y-m-d\TH:i:s', $evt_res['publishOnWebUntil']);
                                $dateNow = new \DateTime('now');
                                if (($publishUntil > $dateNow) || (env('show_unpublished_events') == 'TRUE')) {
                                    $evt_bookable = true;
                                    $evt_att_response = json_decode(callAPI('GET', 'events/attendeeType', $accessToken, ['eventId' => $eventID, 'IncludeCosts' => true]), true);
                                    session(['evt_tickets' => $evt_att_response['data']]);
                                    $evt_tickets = session('evt_tickets');
                                    
                                } else {
                                    $errorMessage = "We're sorry...!";
                                    $unavailableMessage = "This event is no longer available for booking.";
                                }
                            }
                        } else {
                            $errorMessage = "We're sorry...!";
                            $unavailableMessage = "This event is no longer available for booking.";
                        }
                    } else {
                        $errorMessage = "We're sorry...!";
                        $unavailableMessage = "This event is no longer available for booking.";
                    }
                    $alumniLogger->info('Started session '.Session::get('token'));
                
                    $tableRows = [];
                    $evt_res = session('evt_res', []); 
                    $evt_tickets = session('evt_tickets', []);
                    $accessToken = session('accessToken');
                    $maxAttendeesPerBooking = session('maxAttendeesPerBooking', 0);
                    foreach ($evt_tickets as $item) {
                        $row = [];
                        $row['attendeeType'] = $item['attendeeType'];
                        $row['cost'] = 0;
            
                        try {
                            $costsResponse = json_decode(callAPI('GET', 'events/attendeeType/' . $item['attendeeTypeId'], $accessToken, ['IncludeCosts' => "true"]), true);
                            if (isset($costsResponse['costs']) && count($costsResponse['costs']) > 0) {
                                $costsArr = $costsResponse['costs'];
                                $row['cost'] = $costsArr[0]['value'];
                            } else {
                                $row['cost'] = 0;
                            }
            
                        } catch (ApiException $e) {
                            $alumniLogger->error('Session ' . $accessToken . ' - Unable to get attendee costs: ' . $e->getMessage());
                        }
            
                        $row['remaining'] = $item['remaining'];
                        $row['maxAttendeesPerBooking'] = $maxAttendeesPerBooking;
                        $row['attendeeTypeId'] = $item['attendeeTypeId'];
            
                        $tableRows[] = $row;
                    }
            
                    // $form_step = 1;
                    return view('event-booking', compact('ticketTypes','evt_res', 'evt_bookable', 'tableRows', 'form_step', 'eventID', 'maxAttendeesPerBooking', 'accessToken','errorMessage','unavailableMessage'));
                    break;
                case 2:
                        // dd('test 2');
                        $eventID = $request->input('eventID') ?: $request->eventID;
                        $ticketTypes = $request->input('ticketType');
                        session()->put('ticketTypes', $ticketTypes);
                        $ticketTypes = session('ticketTypes');
                        // dd($ticketTypes);
                        $accessToken = session('accessToken');
                        $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                        
                        $total_attendees = 0;
                        $total_cost = 0;
                        
                        foreach ($ticketTypes as $ticketType) {
                            Session::put($ticketType.'TicketsDisplayTitle', $request->input($ticketType.'DisplayTitle') ?: '');
                            Session::put($ticketType.'Tickets', $request->input($ticketType.'-tickets') ?: '');
                            Session::put($ticketType.'TicketsCost', $request->input($ticketType.'Cost') ?: '');
                            $total_attendees += (int)$request->input($ticketType.'-tickets');
                            $total_cost += (int)$request->input($ticketType.'Cost') * (int)$request->input($ticketType.'-tickets');
                        }
                        
                        Session::put('totalAttendees', $total_attendees);
                        Session::put('totalCost', $total_cost);
                    
                        // $form_step = 2;
                        // Pass the necessary data to the view
                        return view('event-booking', compact('evt_res', 'form_step', 'eventID', 'total_attendees', 'total_cost', 'ticketTypes'));
            
                        break;
            
                case 3:
                        // dd('test 3');
    
                        $eventID = $request->input('eventID') ?: $request->eventID;
                
                        $accessToken = session('accessToken');
                        $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                
                        $attendee_title_arr = [];
                        $title = request()->input('title');
                        
                        $title_other = request()->input('title_other');
                        $titleArray = collect($title)->toArray();
                        if ($titleArray) {
                            for ($i = 0; $i < count($titleArray); $i++) {
                                if ($titleArray[$i] == 'Other') {
                                    array_push($attendee_title_arr, $title_other[$i]);
                                } else {
                                    array_push($attendee_title_arr, $titleArray[$i]);
                                }
                            }
                        }
    
                        $bookingData = [
                            'title' => $attendee_title_arr,
                            'id' => $request->input('bookingid') ?: '',
                            'type' => $request->input('bookingtype') ?: '',
                            'cost' => $request->input('bookingcost') ?: '',
                            'attendeefirstname' => $request->input('attendeefirstname') ?: '',
                            'attendeesurname' => $request->input('attendeesurname') ?: '',
                            'attendeedietary' => $request->input('attendeedietary') ?: '',
                            'attendeespecialrequirements' => $request->input('attendeespecialrequirements') ?: '',
                        ];
    
                        Session::put('bookingData', $bookingData);
                        Session::put('booking', $bookingData);
                        Session::put('booking.title', $attendee_title_arr);
    
                        Session::put('firstattendeeisbooker', $request->input('firstattendeeisbooker') ?: '0');
    
                        if (Session::get('firstattendeeisbooker') == '1') {
                            Session::put('title', Session::get('booking.title')[0]);
                            Session::put('firstname', Session::get('booking.attendeefirstname')[0]);
                            Session::put('lastname', Session::get('booking.attendeesurname')[0]);
                        }
    
                        // Duplicate attendees check (endpoint only accepts a single record at a time, so it may be slower with 10+ attendees)
                        $attendee_count = count($request->input('bookingid'));
                        Session::put('attendee_count', $attendee_count);
                        $attendees_already_booked = [];
                        $event_booking_ids = [];
    
                        for ($i = 0; $i < $attendee_count; $i++) {
                            $evt_att_response = json_decode(callAPI('GET', 'eventBooking/attendee', session('accessToken'), ['eventId' => $eventID, 'FirstName' => Session::get('booking.attendeefirstname')[$i], 'Keyname' => Session::get('booking.attendeesurname')[$i]]), true);
                            Session::put('evt_att_response', $evt_att_response);
                            // Add to duplicates array
                            if (count($evt_att_response['data']) > 0) {
                                $att_dup = new \stdClass();
                                $att_dup->fullName = $evt_att_response['data'][0]['title'] . ' ' . $evt_att_response['data'][0]['firstName'] . ' ' . $evt_att_response['data'][0]['keyname'];
                                $att_dup->serialNumber = $evt_att_response['data'][0]['serialNumber'];
                                $att_dup->ticketType = $evt_att_response['data'][0]['attendeeId'];
                                $att_dup->idx = $i;
                                $attendees_already_booked[] = $att_dup;
    
                                // Add booking ID to array
                                if (!in_array($evt_att_response['data'][0]['bookingId'], $event_booking_ids)) {
                                    $event_booking_ids[] = $evt_att_response['data'][0]['bookingId'];
                                }
                            }
                        }
                    
                        // Allow main attendees (bookers) to register for additional events
                        foreach ($event_booking_ids as $event_booking_id) {
    
                            $evt_bkg_response = json_decode(callAPI('GET', 'eventBooking/' . $event_booking_id, $accessToken, []), true);
                            if (count($evt_bkg_response) > 0) {
                                if (in_array($evt_bkg_response['mainAttendeeSerialNumber'], array_column($attendees_already_booked, 'serialNumber'))) {
                                    $attendees_already_booked = array_filter($attendees_already_booked, function ($att) use ($evt_bkg_response) {
                                        return $att->serialNumber != $evt_bkg_response['mainAttendeeSerialNumber'];
                                    });
                                }
                            }
                        }
                    
                        // Remove duplicate attendees and recalculate number of tickets and costs
                        foreach ($attendees_already_booked as $attendee) {
                            $att_idx = $attendee->idx;
                            $att_ticket_type = $attendee->ticketType;
                            Session::put($att_ticket_type . 'Tickets', (int)Session::get($att_ticket_type . 'Tickets') - 1);
                            Session::put('totalAttendees', (int)Session::get('totalAttendees') - 1);
                            Session::put('totalCost', (int)Session::get('totalCost') - (int)Session::get($att_ticket_type . 'TicketsCost'));
    
                            $titles = Session::get('booking.title');
                            array_splice($titles, $att_idx, 1);
                            Session::put('booking.title', $titles);
    
                            $ids = Session::get('booking.id');
                            array_splice($ids, $att_idx, 1);
                            Session::put('booking.id', $ids);
    
                            $types = Session::get('booking.type');
                            array_splice($types, $att_idx, 1);
                            Session::put('booking.type', $types);
    
                            $costs = Session::get('booking.cost');
                            array_splice($costs, $att_idx, 1);
                            Session::put('booking.cost', $costs);
    
                            $firstnames = Session::get('booking.attendeefirstname');
                            array_splice($firstnames, $att_idx, 1);
                            Session::put('booking.attendeefirstname', $firstnames);
    
                            $surnames = Session::get('booking.attendeesurname');
                            array_splice($surnames, $att_idx, 1);
                            Session::put('booking.attendeesurname', $surnames);
    
                            $dietaries = Session::get('booking.attendeedietary');
                            array_splice($dietaries, $att_idx, 1);
                            Session::put('booking.attendeedietary', $dietaries);
    
                            $requirements = Session::get('booking.attendeespecialrequirements');
                            array_splice($requirements, $att_idx, 1);
                            Session::put('booking.attendeespecialrequirements', $requirements);
    
                        }
                        
                        if (count($attendees_already_booked) > 0) {
                            $form_step = 2;
                        }
                        
                        // $form_step = 3;
                        return view('event-booking', compact('evt_res', 'form_step', 'eventID'));
    
                        break;
                    
                case 4:
                        // dd('test 4');
    
                        $eventID = $request->input('eventID') ?: $request->eventID;$eventID = $request->input('eventID') ?: $request->eventID;
                        $request->session()->put('eventID', $eventID);
                        $accessToken = session('accessToken');
                        $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                    
                        if (session('firstattendeeisbooker') == '1') {
                            session()->put('title', session('booking.title')[0]);
                            session()->put('firstname', session('booking.attendeefirstname')[0]);
                            session()->put('lastname', session('booking.attendeesurname')[0]);
                        } else {
                            if (request()->has('title')) {
                                if (request('title') == 'Other' && (request()->has('title_other') && strlen(request('title_other')) > 0)) {
                                    session()->put('title', request('title_other'));
                                } else {
                                    session()->put('title', request('title', ''));
                                }
                            }
                            session()->put('firstname', request('firstname', ''));
                            session()->put('lastname', request('lastname', ''));
                        }
                        
                        session()->put('country', request('country', ''));
                        session()->put('address', request('address', ''));
                        session()->put('town', request('town', ''));
                        session()->put('county', request('county', ''));
                        session()->put('postcode', request('postcode', ''));
                        session()->put('email', request('email', ''));
                        session()->put('telephone_day', request('telephone_day', ''));
                        session()->put('telephone_evening', request('telephone_evening', ''));
                        session()->put('mobile', request('mobile', ''));
                        session()->put('contact_post', request()->has('contact_post') ? request('contact_post') : '0');
                        session()->put('contact_email', request()->has('contact_email') ? request('contact_email') : '0');
    
                        // $form_step = 4;
                        return view('event-booking', compact('evt_res', 'form_step', 'eventID'));
    
                        break;
                        
                case 5:
                        // dd('test 5');
                        $eventID = $request->input('eventID') ?: $request->eventID;
                        $accessToken = session('accessToken');
                        $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                        
                        $totalCost = $request->input('submitted');
                        
                        // $form_step = 5;
                        return view('event-booking', compact('evt_res', 'form_step', 'eventID', 'totalCost'));
    
                        break;
    
                    
                    
                    if (Session::has('eventID')) {
                        $eventID = Session::get('eventID');
                    }
            
                    if (!isset($eventID)) {
                        $output = view('layout.footer')->render();
                        return response('Unable to continue - no event ID specified.' . $output, 500);
                    }

                case 6:
                        // dd($request->all());
                        $eventID = $request->input('eventID') ?: $request->eventID;
                        $accessToken = session('accessToken');
                        $evt_res = json_decode(callAPI('GET', 'events/'.$eventID, $accessToken, $data = ''), true);
                        
                        $payment_result = '';
                        
                        if ($request->has('hppResponse') && session('totalCost') != '0') {
                            $config = new GpEcomConfig();
                            if (env('status') == 'DEVELOPMENT') {
                                $config->merchantId = env('HPP_DEV_MERCHANT_ID');
                                $config->accountId = env('HPP_DEV_EVENT_ACCOUNT_ID');
                                $config->sharedSecret = env('HPP_DEV_EVENT_SHARED_SECRET');
                                $config->serviceUrl = env('HPP_DEV_SERVICE_URL');
                            } else {
                                $config->merchantId = env('HPP_LIVE_MERCHANT_ID');
                                $config->accountId = env('HPP_LIVE_EVENT_ACCOUNT_ID');
                                $config->sharedSecret = env('HPP_LIVE_EVENT_SHARED_SECRET');
                                $config->serviceUrl = env('HPP_LIVE_SERVICE_URL');
                            }
                            
                            $service = new HostedService($config);
                            
                            $responseJson = $request->input('hppResponse');
                            
                            $alumniLogger = Log::channel('daily');
                    
                            try {
                                // Create response object from the response JSON
                                $hppResponse = $service->parseResponse($responseJson, true);
                                                
                                $order_id = $hppResponse->orderId; // GTI5Yxb0SumL_TkDMCAxQA
                                $payment_result = $hppResponse->responseCode; // 00
                                $payment_message = $hppResponse->responseMessage; // [ test system ] Authorised
                                $payment_values = $hppResponse->responseValues; // get values accessible by key
                                $payment_authCode = $payment_values['AUTHCODE']; // 12345
                    
                                $alumniLogger->info('Session ' . session('token') . ' - Elavon payment result: ' . $payment_result);
                                $alumniLogger->info('Session ' . session('token') . ' - Elavon payment message: ' . $payment_message);
                            
                                Session::put('paymentError', $payment_message);
                            
                            } catch (ApiException $e) {
                                $alumniLogger->error('Session ' . session('token') . ' - Caught Elavon payment validation exception: ' . $e->getMessage());
                            }
                        }
    
                        if ($payment_result == '00' || session('totalCost') == '0') {
                            // Save form data via ThankQ API call
                    
                            // Use 'ContactPublic' to create a new contact
                            $contactType = 'Individual';
                    
                                    
                            $user_reg_data_arr = array(
                                "title" => session('title'),
                                "firstName" => session('firstname'),
                                "keyname" => session('lastname'),
                                "contactType" => $contactType,
                                "primaryCategory" => "Alumni",
                                "sourceCode" => session('eventSourceCode'),
                                "addressType" => "Residential",
                                "country" => session('country'),
                                "address" => session('address'),
                                "town" => session('town'),
                                //"county" => session('county'),
                                "postcode" => session('postcode'),
                                "emailAddress" => session('email'),
                                //"dayTelephone" => session('telephone_day'),
                                //"eveningTelephone" => session('telephone_evening'),
                                //"mobileNumber" => session('mobile'),
                                "doNotPhone" => filter_var(session('contact_post'), FILTER_VALIDATE_BOOLEAN),
                                "doNotEmail" => filter_var(session('contact_email'), FILTER_VALIDATE_BOOLEAN)
                                );
    
                            $user_reg_data_arr = array_merge($user_reg_data_arr, session('county') != '' ? ['county' => session('county')] : []);
                            $user_reg_data_arr = array_merge($user_reg_data_arr, session('telephone_day') != '' ? ['dayTelephone' => session('telephone_day')] : []);
                            $user_reg_data_arr = array_merge($user_reg_data_arr, session('telephone_evening') != '' ? ['eveningTelephone' => session('telephone_evening')] : []);
                            $user_reg_data_arr = array_merge($user_reg_data_arr, session('mobile') != '' ? ['mobileNumber' => session('mobile')] : []);
    
                            $custom_fields_arr = [];
    
                            if (Session('contact_post') == 1) {
                                $phoneReason = new \stdClass();
                                $phoneReason->customFieldName = "NOPHONEDETAILS";
                                $phoneReason->customFieldValue = "Opt Out - Phone Only";
    
                                $user_reg_data_arr['doNotPhoneSourceCode'] = 'WEBC';
                            
                                // } else {
                                //     $phoneReason->customFieldValue = "";
                                //     $user_reg_data_arr['doNotPhoneSourceCode'] = "";
        
                                // }
                                $custom_fields_arr[] = $phoneReason;
                            }
    
                            if (Session('contact_email') == 1) {
                                $emailReason = new \stdClass();
                                $emailReason->customFieldName = "NOEMAILDETAILS";
                                $emailReason->customFieldValue = "Email Consent Withdrawn";
    
                                $user_reg_data_arr['doNotEmailSourceCode'] = 'WEBC';
                                
                                // } else {
                                //     $emailReason->customFieldValue = "";
                                //     $user_reg_data_arr['doNotEmailSourceCode'] = "";

                                // }
                                $custom_fields_arr[] = $emailReason;
                            }
    
                            $user_reg_data_arr['customFields'] = $custom_fields_arr;
                            $user_reg_data = json_encode($user_reg_data_arr);
                            // Session(['user_reg_data' => $user_reg_data]);
    
                            try {
                                // Log ContactPublic POST data
                                $alumniLogger->info('Session ' . session('token') . ' - Contacts POST data: ' . var_export($user_reg_data, true));
                        
                                $user_reg_response = json_decode(callAPI('POST', 'contacts', session('accessToken'), $user_reg_data), true);
                        
                                // Log ContactPublic POST response
                                $alumniLogger->info('Session ' . session('token') . ' - Contacts POST response: ' . var_export($user_reg_response, true));
                        
                                /*if ($user_reg_response['Status'] === 'Success') {
                                    $user_reg_values = $user_reg_response['Values'];
                                }*/
                                // if (env('status') === 'DEVELOPMENT') {
                                //     echo '<br><br><b>New contact response:</b>';
                                //     dump($user_reg_response);
                                // }
                                // dd($user_reg_response);
                            } catch (Exception $e) {
                                $alumniLogger->error('Session ' . session('token') . ' - Contacts POST: Unable to create contact record');
                            }
                            // dd(session('firstattendeeisbooker') == '0');
                            if (session('firstattendeeisbooker') == '0') {
                                // Main attendee
                                $main_att_reg_data = json_encode([
                                    "title" => session('booking')['title'][0],
                                    "firstName" => session('booking')['attendeefirstname'][0],
                                    "keyname" => session('booking')['attendeesurname'][0],
                                    "contactType" => $contactType,
                                    "primaryCategory" => "Alumni",
                                    "sourceCode" => session('eventSourceCode'),
                                ]);
                        
                                try {
                                    $main_att_reg_response = json_decode(callAPI('POST', 'contacts', session('accessToken'), $main_att_reg_data), true);
                        
                                    // Log ContactPublic POST response
                                    $alumniLogger->info('Session ' . session('token') . ' - Contacts POST response: ' . var_export($main_att_reg_response, true));
                        
                                    // if (env('status') === 'DEVELOPMENT') {
                                    //     echo '<br><br><b>New contact response:</b>';
                                    //     dump($main_att_reg_response);
                                    // }
                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - Contacts POST: Unable to create contact record');
                                }
                            }
                                
                            
                            // Proceed upon creation of contact (and existence of serial number)
                            if ($user_reg_response && isset($user_reg_response['serialNumber'])) {
                                $serialNo = $user_reg_response['serialNumber'];
                                session(['contactSerialNumber' => $serialNo]);
                                $mainAttSerialNo = (session('firstattendeeisbooker') == '0') ? $main_att_reg_response['serialNumber'] : $serialNo;
                                
                                // Creation of booking reference
                                $booking_date = new \DateTime('now');
                                $booking_data = [
                                    "eventId" => session('eventID'),
                                    "bookingDate" => $booking_date->format('Y-m-d\TH:i:s'),
                                    "bookingStatus" => "Booked",
                                    "bookingStage" => "Confirmed",
                                    "serialNumber" => $serialNo,
                                    "sourceCode" => session('eventSourceCode'),
                                    "paymentType" => "Credit Card",
                                    "paymentDueDate" => $booking_date->format('Y-m-d\TH:i:s'),
                                    "currency" => "GBP",
                                    "mainAttendeeSerialNumber" => $mainAttSerialNo,
                                    "badgename" => session('booking')['title'][0] . ' ' . session('booking')['attendeefirstname'][0] . ' ' . session('booking')['attendeesurname'][0],
                                    "dietaryrequirements" => session('booking')['attendeedietary'][0],
                                    "specialneeds" => session('booking')['attendeespecialrequirements'][0],
                                    "attendeeId" => session('booking')['id'][0],
                                    "attendeeType" => session('booking')['type'][0],
                                    "attendanceStatus" => "Attending",
                                    "attended" => "Pending"
                                ];
    
                                $booking_count = count(session('booking')['id']);
                                $attendees = [];
                                // dd($booking_data);
    
                                for ($i = 1; $i < $booking_count; $i++) {
                                    $att_serial_no = null;
                                    
                                    $att_reg_data = json_encode([
                                        "title" => session('booking')['title'][$i],
                                        "firstName" => session('booking')['attendeefirstname'][$i],
                                        "keyname" => session('booking')['attendeesurname'][$i],
                                        "contactType" => $contactType,
                                        "primaryCategory" => "Alumni",
                                        "sourceCode" => session('eventSourceCode')
                                    ]);
                                    
                                    try {
                                        $att_reg_response = json_decode(callAPI('POST', 'contacts', session('accessToken'), $att_reg_data), true);
                                        $att_serial_no = $att_reg_response['serialNumber'];
                                        
                                        // Log ContactPublic POST response
                                        $alumniLogger->info('Session ' . session('token') . ' - Contacts POST response: ' . var_export($att_reg_response, true));
                                        // if (env('status') == 'DEVELOPMENT') {
                                        //     echo '<br><br><b>New contact response:</b>';
                                        //     dump($att_reg_response);
                                        // }
                                    } catch (Exception $e) {
                                        $alumniLogger->error('Session ' . session('token') . ' - Contacts POST: Unable to create contact record');
                                    }
                                    
                                    // Create event attendee record
                                    $ticket_data_arr = [
                                        "bookingStatus" => "Booked",
                                        "attendeeId" => session('booking')['id'][$i],
                                        "attendeeType" => session('booking')['type'][$i],
                                        "attendanceStatus" => "Attending",
                                        "attended" => "Pending",
                                        "serialNumber" => $att_serial_no,
                                        "eventId" => session('eventID'),
                                        "title" => session('booking')['title'][$i],
                                        "firstName" => session('booking')['attendeefirstname'][$i],
                                        "keyname" => session('booking')['attendeesurname'][$i],
                                        "badgename" => session('booking')['title'][$i] . ' ' . session('booking')['attendeefirstname'][$i] . ' ' . session('booking')['attendeesurname'][$i],
                                        "dietaryrequirements" => session('booking')['attendeedietary'][$i],
                                        "specialneeds" => session('booking')['attendeespecialrequirements'][$i]
                                    ];
                                    
                                    if (strcasecmp(session('firstname'), session('booking')['attendeefirstname'][$i]) == 0 && strcasecmp(session('lastname'), session('booking')['attendeesurname'][$i]) == 0) {
                                        $ticket_data_arr['serialNumber'] = $serialNo;
                                    }
                                    
                                    array_push($attendees, $ticket_data_arr);
                                }
                                
                                $booking_data['additionalAttendees'] = $attendees;
                                $booking_data_json = json_encode($booking_data);
                                
                                try {
                                    // Log EventBookingInVerificationPublic POST data
                                    $alumniLogger->info('Session ' . session('token') . ' - EventBooking POST data: ' . var_export($booking_data, true));
                                    
                                    $booking_response = json_decode(callAPI('POST', 'eventBooking', session('accessToken'), $booking_data_json), true);
                                    if (isset($booking_response['bookingId'])) {
                                        session(['bookingConfirmId' => $booking_response['bookingId']]);
                                        // Our custom-generated order ID
                                        // $_SESSION['qmul_order_id'] = 'EVENT_'.$bookingId;
                                    }
                                    
                                    // Log EventBookingInVerificationPublic POST response
                                    $alumniLogger->info('Session ' . session('token') . ' - EventBooking POST response: ' . var_export($booking_response, true));
                                    
                                    // if (env('status') == 'DEVELOPMENT') {
                                    //     echo '<br><br><b>New booking response:</b>';
                                    //     dump($booking_response);
                                    // }
                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - EventBooking POST: Unable to create booking');
                                }
                            }
                            if ($payment_result == '00' && session('totalCost') != '0') {
                                $paymentRef = $order_id;
                            
                                $paymentBatchId = '';
                                // Try to find an existing payment batch for this event
                                try {
                                    $existing_batch_response = json_decode(callAPI('GET', 'payments/batch', session('accessToken'), ['batchDescription' => "Event Batch - " . session('eventID')]), true);
                                    $existing_batch = $existing_batch_response['data'];
                                    if (count($existing_batch) > 0 && isset($existing_batch[0]['paymentBatchId'])) {
                                        $paymentBatchId = $existing_batch[0]['paymentBatchId'];
                                    }
                            
                                    $alumniLogger->info('Session ' . session('token') . ' - Payment Batch GET response: ' . var_export($existing_batch, true));
                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - Payment Batch GET: Unable to retrieve batch');
                                }
                                
                                // If no payment batch exists for this event, create new batch
                                if ($paymentBatchId == '') {
                                    try {
                                        $batch_data = json_encode([
                                            "batchType" => "Event Batch",
                                            "isApproved" => false,
                                            "batchDescription" => "Event Batch - " . session('eventID'),
                                            "defaultSourceCode" => session('eventSourceCode'),
                                        ]);
                            
                                        // Log Payment Batch POST data
                                        $alumniLogger->info('Session ' . session('token') . ' - Payment Batch POST data: ' . var_export($batch_data, true));
                            
                                        $batch_response = json_decode(callAPI('POST', 'payments/batch', session('accessToken'), $batch_data), true);
                                        if (isset($batch_response['paymentBatchId'])) {
                                            $paymentBatchId = $batch_response['paymentBatchId'];
                                        }
                            
                                        // Log Payment Batch POST response
                                        $alumniLogger->info('Session ' . session('token') . ' - Payment Batch POST response: ' . var_export($batch_response, true));
                            
                                        // if (constant('status') == 'DEVELOPMENT') {
                                        //     echo '<br><br><b>Payment Batch response:</b>';
                                        //     dump($batch_response);
                                        // }
                            
                                    } catch (Exception $e) {
                                        $alumniLogger->error('Session ' . session('token') . ' - Payment Batch POST: Unable to submit payment batch details');
                                    }
                                }
                            
                                // Process event payment
                                if (session('bookingConfirmId') && $paymentBatchId != '') {
                                    $payment_data = json_encode([
                                        "bookingId" => session('bookingConfirmId'),
                                        "paymentRef" => $paymentRef,
                                        "paymentBatchId" => $paymentBatchId,
                                        "paymentMethod" => "Credit Card",
                                        "incomeStream" => "Annual Fund",
                                        "amount" => session('totalCost'),
                                        "serialNumber" => session('contactSerialNumber'),
                                        "receiptSerialNumber" => session('contactSerialNumber'),
                                        "externalRef" => session('bookingConfirmId'),
                                        "externalRefType" => "EventBooking"
                                    ]);
                            
                                    try {
                                        // Log Payments POST data
                                        $alumniLogger->info('Session ' . session('token') . ' - Payments POST data: ' . var_export($payment_data, true));
                            
                                        $payment_response = json_decode(callAPI('POST', 'payments', session('accessToken'), $payment_data), true);
                            
                                        // Log Payments POST
                                        $alumniLogger->info('Session ' . session('token') . ' - Payments POST response: ' . var_export($payment_response, true));
                            
                                        // if (env('status') == 'DEVELOPMENT') {
                                        //     echo '<br><br><b>Payments response:</b>';
                                        //     dump($payment_response);
                                        // }
                            
                                    } catch (Exception $e) {
                                        $alumniLogger->error('Session ' . session('token') . ' - Payments POST: Unable to record payment outcome');
                                    }
                                }
                            }
    
                            // Send confirmation email
                            if (session('bookingConfirmId')) {
                                try {
                                    $mailSubject = 'Your Event Booking Confirmation:' . session('eventName');

                                    $mailBody  = '<p>Dear ' . session('title') . ' ' . session('lastname') . '</p>';
                                    $mailBody .= '<p>Thank you for your booking to join us at ' . session('eventName') . ' on ' . session('eventStart') . '.</p>';
                                    $mailBody .= '<p>Please find the details of your booking as follows:</p>';
                                    $mailBody .= '<ul><li><strong>Booking reference:</strong> ' . session('bookingConfirmId') . '</li>';

                                    if (session('totalCost') != 0) {
                                        $mailBody .= '<li><strong>Total booking cost:</strong> &pound;' . session('totalCost') . '</li></ul>';
                                    } else {
                                        $mailBody .= '<li><strong>Total booking cost:</strong> Free</li></ul>';
                                    }
    
                                    $mailBody .= '<h2>Full booking details</h2> <hr />';
                                    $attendee_count = count(session('booking')['id']);
                                    for ($i = 0; $i < $attendee_count; $i++) {
                                        $mailBody .= '<p><strong>Attendee '.($i+1).'</strong><br><br>';
                                        $mailBody .= '<strong>Name:</strong> ' . session('booking')['title'][$i] . ' ' . session('booking')['attendeefirstname'][$i] . ' ' . session('booking')['attendeesurname'][$i] . '<br>';
                                        $mailBody .= '<strong>Dietary requirements:</strong>  ' . session('booking')['attendeedietary'][$i];
                                        if (isset(session('booking')['attendeespecialrequirements'][$i]) && strlen(session('booking')['attendeespecialrequirements'][$i]) > 0) {
                                            $mailBody .= '<br><strong>Special requirements:</strong>' . session('booking')['attendeespecialrequirements'][$i];
                                        }
                                        $mailBody .= '</p>';
                                    }
                                    $mailBody .= '<hr/><p>If you have any questions regarding the event or if you would like to amend your booking, please contact the Alumni Engagement Team at <a href="mailto:alumni@qmul.ac.uk">alumni@qmul.ac.uk</a>.</p>';

                                    $mailBody .= '<p>Kind regards,</p>';
                                    $mailBody .= '<p>Department of Development and Alumni Engagement Directorate<br>Queen Mary University of London<br>Mile End Road<br>London<br>E1 4NS</p>';
                                    $mailBody .= '<p>Email: <a href="mailto:alumni@qmul.ac.uk">alumni@qmul.ac.uk</a><br /><a href="http://www.qmul.ac.uk/alumni">www.qmul.ac.uk/alumni</a></p>';
                                    $mailBody  .= '<p><img src="https://qmul.alumni-live.hexdev.uk/media/qmullogo.jpg" alt="" /></p>';
                                    $mailBody  .= '<p><small>The Department of Development and Alumni Engagement (DDAE) supports Queen Mary University of London by engaging with alumni, students, and supporters of the university. To carry out our work, it is necessary for us to process and store personal information according to the principles of the General Data Protection Regulation (GDPR). We process data in pursuit of our legitimate interests in a range of activities, including communicating with alumni, supporters and potential supporters; providing information on events, benefits and services; and furthering Queen Mary`s educational and charitable mission (which includes fundraising and securing the support of volunteers). We may contact you by telephone, email, post, or social media, and you have the right to amend your preferences or opt out entirely at any time. All data is held securely; is not sold; is not shared with unaffiliated third parties; and is not disclosed to external organisations other than those contracted by Queen Mary to assist in its routine activities. You can read our full Privacy Notice at <a href="http://www.qmul.ac.uk/alumni/privacynotice">qmul.ac.uk/alumni/privacynotice</a>. If you would like to update your details, or tell us if you no longer wish to hear from us, please email alumni@qmul.ac.uk.</small></p>';

                                    sendEmail('donotreply@qmul.ac.uk', session('email'), 'alumni@qmul.ac.uk', $mailSubject, $mailBody);
                    
                                    // Record communication in CRM
                                    $comms_date = new \DateTime('now');
                                    $comms_data = json_encode([
                                        "subject" => $mailSubject,
                                        "date" => $comms_date->format('Y-m-d\TH:i:s'),
                                        "recipient" => session('email'),
                                        "sender" => config('mail.from.address'),
                                        "bodyHTML" => $mailBody,
                                        "inOrOut" => "Out",
                                    ]);
                    
                                    // Log communications data
                                    $alumniLogger->info('Session ' . session('token') . ' - Communication Logging POST data: ' . var_export($comms_data, true));
                    
                                    $comms_response = json_decode(callAPI('POST', 'communications/emailLogging', session('accessToken'), $comms_data), true);
                    
                                    // Session::flush();
                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - Unable to send event booking email confirmation: ' . $e);
                                }
                            }
    
                        } else {
                            // Payment failed
                        }
                        
                        return view('event-booking', compact('evt_res', 'form_step', 'eventID', 'payment_result'));
                                
                        break;
                    
                    default:
                        // Invalid step, handle accordingly
                    break;
    
            }

        }

    }

    
}
