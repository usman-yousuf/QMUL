<?php

namespace App\Http\Controllers;

use GlobalPayments\Api\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpEcomConfig;
use GlobalPayments\Api\Services\HostedService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Helpers\apiHelper;
use App\Helpers\emailHelper;


class PledgeController extends Controller
{
    // logic for donation pledge
    public function pledgeDonation(Request $request)
    {
        // Form-specific vars
        $sourceCode = 'WEBC';
        $noofpages = array("PageSize" => '1000');

        $alumniLogger = Log::channel('daily');

        $form_step = (int) $request->input('step', 1);
        $form_prev = $request->has('prev');

        $prevClicked = $request->input('prevClicked', 'false') === 'true';
        
        if ($prevClicked) {
            $form_step = max(1, $form_step - 2);
            if($form_step === 1){
                
                $accessToken = session('accessToken');
                
                 // Get destinations
             try {
                $destinations_data = json_decode(callAPI('GET', 'destinationcodes', $accessToken, $noofpages), true);
                $destinations = $destinations_data['data'];
                usort($destinations, function($a, $b) { return strcmp($a['webDescription'], $b['webDescription']); });
            } catch (Exception $e) {
                echo 'Unable to get destinations. Please contact an administrator.';
            }
            
            // $campaign = htmlspecialchars(request()->input("campaign"));
            $destinationOptions = collect($destinations)
                ->filter(function ($item) {
                    return $item['webPublish'] == "true";
                })
                ->map(function ($item) {
                    $destination_selected = ($item['destinationCode'] == session('destinations')) ? ' selected' : '';

                    return [
                        'value' => $item['destinationCode'],
                        'description' => $item['webDescription'],
                        'selected' => $destination_selected
                    ];
                })
                ->toArray();
                session(['destinationOptions' => $destinationOptions]);
            
            return view('pledge', compact('destinationOptions', 'form_step'));

            }

            if($form_step === 2) {

                $startDate = Session::get('start_date');
                $giftaid = Session::get('giftaid');

                return view('pledge', compact('form_step'));
            }

            if($form_step === 3){
                
                Session::get('title');
                Session::get('firstname');
                Session::get('lastname');
                Session::get('country');
                Session::get('address');
                Session::get('town');
                Session::get('county');
                Session::get('postcode');
                Session::get('email');
                Session::get('email_confirm');
                Session::get('telephone_day');
                Session::get('telephone_evening');
                Session::get('mobile');
                Session::get('contact_post');
                Session::get('contact_email');
                
                return view('pledge', compact('form_step'));

            }
        }
        // Initialise form
        if ($form_step === 1 && !$form_prev) {
            session_unset();
		    $_SESSION['token'] = uniqid();
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

        // Get and display form data
        if ($form_step === 1) {

            if (!$form_prev) {
              $alumniLogger->info('Started session ' . $_SESSION['token']);
            }
            $accessToken = session('accessToken');
             // Get destinations
             try {
                $destinations_data = json_decode(callAPI('GET', 'destinationcodes', $accessToken, $noofpages), true);
                $destinations = $destinations_data['data'];
                usort($destinations, function($a, $b) { return strcmp($a['webDescription'], $b['webDescription']); });
            } catch (Exception $e) {
                echo 'Unable to get destinations. Please contact an administrator.';
            }
            
            // $campaign = htmlspecialchars(request()->input("campaign"));
            $destinationOptions = collect($destinations)
                ->filter(function ($item) {
                    return $item['webPublish'] == "true";
                })
                ->map(function ($item) {
                    $destination_selected = ($item['destinationCode'] == session('destinations')) ? ' selected' : '';

                    return [
                        'value' => $item['destinationCode'],
                        'description' => $item['webDescription'],
                        'selected' => $destination_selected
                    ];
                })
                ->toArray();

                session(['destinationOptions' => $destinationOptions]);
            
            return view('pledge', compact('destinationOptions', 'form_step'));

        } elseif ($form_step === 2 && !$form_prev) {
            $selectedOption = $request->input('destinations');
            list($selectedValue, $selectedDescription) = explode('|', $selectedOption);
            session(['selectedValue' => $selectedValue, 'selectedDescription' => $selectedDescription]);

            // $request->session()->put('destinations', $request->input('destinations', ''));
            $request->session()->put('frequency', $request->input('frequency', ''));
            $request->session()->put('amount', $request->input('amount', ''));

            $start_date_day = $request->input('start_date_day', '');
            $start_date_month = $request->input('start_date_month', '');
            $start_date_year = $request->input('start_date_year', '');

            $donation_start = new \DateTime($start_date_year . '/' . $start_date_month . '/' . $start_date_day);
            $request->session()->put('start_date', $donation_start->format('Y-m-d\TH:i:s'));
            $startDate = Session::get('start_date');
            $request->session()->put('giftaid', $request->input('giftaid', '0'));


            return view('pledge', compact('form_step'));

        } elseif ($form_step === 3 && !$form_prev) {

            if ($request->input('title') === 'Other' && $request->filled('title_other')) {
                $request->session()->put('title', $request->input('title_other'));
            } else {
                $request->session()->put('title', $request->input('title'));
            }
    
            $request->session()->put('firstname', $request->input('firstname'));
            $request->session()->put('lastname', $request->input('lastname'));
            $request->session()->put('country', $request->input('country'));
            $request->session()->put('address', $request->input('address'));
            $request->session()->put('town', $request->input('town'));
            $request->session()->put('county', $request->input('county'));
            $request->session()->put('postcode', $request->input('postcode'));
            $request->session()->put('email', $request->input('email'));
            $request->session()->put('email_confirm', $request->input('email_confirm'));
            $request->session()->put('telephone_day', $request->input('telephone_day'));
            $request->session()->put('telephone_evening', $request->input('telephone_evening'));
            $request->session()->put('mobile', $request->input('mobile'));
            $request->session()->put('contact_post', $request->input('contact_post', '0'));
            $request->session()->put('contact_email', $request->input('contact_email', '0'));
        
            return view('pledge', compact('form_step'));

        } elseif ( $form_step === 4 && !$form_prev) {

            // dd('test');  

            $request->session()->put('dd_acc_name', $request->input('dd_acc_name'));
            $request->session()->put('dd_acc_number', $request->input('dd_acc_number'));
            $request->session()->put('dd_acc_sortcode', $request->input('dd_acc_sortcode'));
            
            $frequency = session('frequency', ''); 
            $amount = session('amount', '');
            $accessToken = session('accessToken');
            try {
                $destinations_data = json_decode(callAPI('GET', 'destinationcodes', $accessToken, $data = ''), true);
                $destinations = $destinations_data['data'];
                usort($destinations, function($a, $b) { return strcmp($a['webDescription'], $b['webDescription']); });
            } catch (Exception $e) {
                echo 'Unable to get destinations. Please contact an administrator.';
            }
            $destinationName = '';
            foreach($destinations as $item) {
                if ($item['destinationCode'] == session('selectedValue')) {
                    $destinationName = $item['webDescription'];
                }
            }

            return view('pledge', compact('form_step', 'destinationName', 'amount', 'frequency'));

        } elseif ( $form_step === 5 && !$form_prev) {

            // dd($request->all());
            // dd('test 1');
            // Save form data via ThankQ API calls
            if ($request->has('submitted') && $request->has('_token')) {
              
            //   dd('test 2');  // Use 'ContactPublic' to create a new contact
                $contactType = 'Individual';
                $user_reg_data_arr = [
                    "title" => $request->session()->get('title'),
                    "firstName" => $request->session()->get('firstname'),
                    "keyname" => $request->session()->get('lastname'),
                    "contactType" => $contactType,
                    "primaryCategory" => "Alumni",
                    "sourceCode" => $request->sourceCode,
                    "addressType" => "Residential",
                    "country" => $request->session()->get('country'),
                    "address" => $request->session()->get('address'),
                    "town" => $request->session()->get('town'),
                    // "county" => $request->session()->get('county'),
                    "postcode" => $request->session()->get('postcode'),
                    "emailAddress" => $request->session()->get('email'),
                    // "dayTelephone" => $request->session()->get('telephone_day'),
                    // "eveningTelephone" => $request->session()->get('telephone_evening'),
                    // "mobileNumber" => $request->session()->get('mobile'),
                    "doNotPhone" => filter_var($request->session()->get('contact_post'), FILTER_VALIDATE_BOOLEAN),
                    "doNotEmail" => filter_var($request->session()->get('contact_email'), FILTER_VALIDATE_BOOLEAN),
                ];

                // Add conditional elements to the user registration data array
                if ($request->session()->get('county') != '') {
                    $user_reg_data_arr['county'] = $request->session()->get('county');
                }

                if ($request->session()->get('telephone_day') != '') {
                    $user_reg_data_arr['dayTelephone'] = $request->session()->get('telephone_day');
                }

                if ($request->session()->get('telephone_evening') != '') {
                    $user_reg_data_arr['eveningTelephone'] = $request->session()->get('telephone_evening');
                }

                if ($request->session()->get('mobile') != '') {
                    $user_reg_data_arr['mobileNumber'] = $request->session()->get('mobile');
                }

                $custom_fields_arr = [];

                $phoneReason = new \stdClass();
                $phoneReason->customFieldName = "NOPHONEDETAILS";

                if (Session('contact_post') == 1) {
                    $phoneReason->customFieldValue = "Opt Out - Phone Only";

                    $user_reg_data_arr['doNotPhoneSourceCode'] = 'WEBC';
                
                } else {
                        $phoneReason->customFieldValue = "";
                        $user_reg_data_arr['doNotPhoneSourceCode'] = "";

                }
                $custom_fields_arr[] = $phoneReason;
                

                $emailReason = new \stdClass();
                $emailReason->customFieldName = "NOEMAILDETAILS";

                if (Session('contact_email') == 1) {
                    $emailReason->customFieldValue = "Email Consent Withdrawn";

                    $user_reg_data_arr['doNotEmailSourceCode'] = 'WEBC';
                    
                } else {
                        $emailReason->customFieldValue = "";
                        $user_reg_data_arr['doNotEmailSourceCode'] = "";

                }
                $custom_fields_arr[] = $emailReason;

                $user_reg_data_arr['customFields'] = $custom_fields_arr;
                $user_reg_data = json_encode($user_reg_data_arr);

                try {
                    $accessToken = session('accessToken');
                    $user_reg_response = callAPI('POST', 'contacts', $accessToken, $user_reg_data);
                    $user_reg_response = json_decode($user_reg_response, true);
        
                    /*if ($user_reg_response['Status'] === 'Success') {
                        $user_reg_values = $user_reg_response['Values'];
                    }*/
        
                    if (env('status') === 'DEVELOPMENT') {
                        echo '<br><br><b>New contact 1 response:</b>';
                        dump($user_reg_response);
                    }
                } catch (Exception $e) {
                    echo 'Unable to create contact. Please contact an administrator.';
                }
                
                $ddpledge_id = null;
            
                if ($user_reg_response && isset($user_reg_response['serialNumber'])) {
                    $serialNo = $user_reg_response['serialNumber'];
                
                    if (session('giftaid') === '1') {
                        try {
                            // Set user GiftAid declaration (note: this method sets declaration and effective from date automatically)
                            $giftaid_data = json_encode([
                                "serialNumber" => $serialNo,
                                "declarationType" => 'Web'
                            ]);
            
                            // Log GiftAidDeclarationPublic POST data
                            Log::info('Session ' . session('token') . ' - GiftAidDeclarations POST data: ' . var_export($giftaid_data, true));
            
                            $accessToken = session('accessToken');
                            $giftaid_response = callAPI('POST', 'giftaiddeclarations', $accessToken, $giftaid_data);
                            $giftaid_response = json_decode($giftaid_response, true);
            
                            // Log GiftAidDeclarationPublic POST response
                            Log::info('Session ' . session('token') . ' - GiftAidDeclaration POST response: ' . var_export($giftaid_response, true));
            
                            if (env('status') === 'DEVELOPMENT') {
                                echo '<br><br><b>GiftAid response:</b>';
                                dump($giftaid_response);
                            }
                        } catch (Exception $e) {
                            Log::error('Session ' . session('token') . ' - GiftAidDeclaration POST: Unable to set GiftAid declaration');
                        }
                    }

                    try {
                        $dd_date = new \DateTime('now');
                        $tax_claimable = true; // (session('giftaid') == 1) ? true : false;
            
                        $ddpledge_data = json_encode([
                            "serialNumber" => $serialNo,
                            "incomeType" => "DONATION",
                            "pledgeStatus" => "Active",
                            "pledgeType" => "Open Ended",
                            "paymentMethod" => "Direct Debit",
                            "instalmentValue" => session('amount'),
                            "startDate" => session('start_date'),
                            "paymentDay" => session('start_date_day'),
                            "paymentFrequency" => session('frequency'),
                            "sortCode" => session('dd_acc_sortcode'),
                            "accountNumber" => session('dd_acc_number'),
                            // "receiptSerialNumber" => $serialNo,
                            "accountName" => session('dd_acc_name'),
                            "accountVerified" => $dd_date->format('Y-m-d\TH:i:s'),
                            "accountVerifiedBy" => "QMUL",
                            "DDIDateReceived" => $dd_date->format('Y-m-d\TH:i:s'),
                            "DDIMethod" => "Internet",
                            "DDIStatus" => "New - Awaiting Lodgement",
                            "sourceCode" => $sourceCode,
                            "taxClaimable" => $tax_claimable,
                            "destinations" => [
                                [
                                    "destinationCode" => session('destinations'),
                                    "amount" => session('amount')
                                ]
                            ]
                        ]);

                        // Replace the callAPI function with your actual API call implementation
                        $accessToken = session('accessToken');
                        $ddpledge_response = callAPI('POST', 'pledges', $accessToken, $ddpledge_data);
                        $ddpledge_response = json_decode($ddpledge_response, true);
                        $ddpledge_id = $ddpledge_response['pledgeId'];

                        Session::put('ddpledge_id', $ddpledge_id);

                        if (isset($ddpledge_response['pledgeId'])) {
                            $ddpledge_id = $ddpledge_response['pledgeId'];
                        }

                        if (env('status') === 'DEVELOPMENT') {
                            echo '<br><br><b>Direct Debit pledge response:</b>';
                            dump($ddpledge_response);
                        }
                    } catch (Exception $e) {
                        echo 'Unable to submit Direct Debit details for pledge. Please contact an administrator.';
                    }
                    $alumniLogger->info('Session '.session('token').' reached Pledge form confirmation page');
                }
                // dd('test end');
                // return view('pledge', compact('form_step', 'ddpledge_id'));
            }
            // dd('test end');
            return view('pledge', compact('form_step', 'ddpledge_id'));
        }

        
    }
}
