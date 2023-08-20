<?php

namespace App\Http\Controllers;

use GlobalPayments\Api\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpEcomConfig;
use GlobalPayments\Api\Services\HostedService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Helpers\apiHelper;
use App\Helpers\emailHelper;

class DonationController extends Controller
{

    public function processForm(Request $request)
    {
        session_start();
        date_default_timezone_set('Europe/London');
        
        $sourceCode = 'WEBC';
        $noofpages = array("PageSize" => '1000');
        // Laravel Logger
        $alumniLogger = Log::channel('daily');

        $currentStep = $request->input('form_step', 1);
        $formPrev = $request->input('form_prev', false);

        $prevClicked = $request->input('prevClicked', 'false') === 'true';
        
        if ($prevClicked) {
            $currentStep = max(1, $currentStep - 1);
            return view('donate', ['currentStep' => $currentStep]);
        }
        
        // Initialise form
        if ($currentStep === 1 && !$formPrev) {
            session_unset();
		    $_SESSION['token'] = uniqid();
        }

        // Acquire/refresh CRM API access token
        if (isset($_SESSION['accessTokenExpiry'])) {
            $tokenExpiry = new \DateTime($_SESSION['accessTokenExpiry']);
            $dateNow = new \DateTime("now");
            if ($tokenExpiry < $dateNow) {
                unset($_SESSION['accessToken']);
            }
        }
        if (!isset($_SESSION['accessToken'])) {
            $accessToken_data = getCRMAccessToken();
            if ($accessToken_data && isset($accessToken_data['accessToken'])) {
                $_SESSION['accessToken'] = $accessToken_data['accessToken'];
                $_SESSION['accessTokenExpiry'] = $accessToken_data['expiry'];
            } else {
                exit('Unable to connect to CRM. Please contact an administrator.');
            }
        }
        if ($request->isMethod('get')) {
            switch ($currentStep) {
                case 1:
                    if ($currentStep === 1 && !$formPrev) {
                        $alumniLogger->info('Started session ' . $_SESSION['token']);
                    }

                    // Get destinations
                    try {
                        $destinations_data = json_decode(callAPI('GET', 'destinationcodes', $_SESSION['accessToken'], $noofpages), true);
                        $destinations = $destinations_data['data'];
                        
                        usort($destinations, function($a, $b) { return strcmp($a['webDescription'], $b['webDescription']); });
                    } catch (Exception $e) {
                        echo 'Unable to get destinations. Please contact an administrator.';
                    }
                    // dd($destinations);
                    $campaign = htmlspecialchars(request()->input("campaign"));
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
                        // dd($destinationOptions);
                    session(['destinationOptions' => $destinationOptions]);
                    
                    return view('donate', compact('destinationOptions', 'currentStep'));

                break;

                default:
                    // Invalid step, handle accordingly
                break;
            }
        } elseif ($request->isMethod('post')) {
            switch ($currentStep) {
                case 1:
                    // Process Step 1 form data and store in session or database
                    $selectedOption = $request->input('destinations');
                    list($selectedValue, $selectedDescription) = explode('|', $selectedOption);
                    session(['selectedValue' => $selectedValue, 'selectedDescription' => $selectedDescription]);
                   
                    $amount = $request->input('amount', '');
                    if (strpos($amount, '.') === false) {
                        $amount .= '.00';
                    }
                    session(['amount' => $amount]);

                    session(['giftaid' => $request->input('giftaid', '0')]);
                        
                    
                    // Update the current step to 2
                    $currentStep = 2;
                    break;

                case 2:
                    
                    // Process Step 2 form data and store in session or database
                    if ($request->has('title')) {
                        if ($request->input('title') == 'Other' && $request->has('title_other') && strlen($request->input('title_other')) > 0) {
                            $title = $request->input('title_other');
                        } else {
                            $title = $request->input('title');
                        }
                    }

                    $storedDestination = session('selectedDescription');
                    $amount = session('amount');
                    $firstname = $request->input('firstname', '');
                    $lastname = $request->input('lastname', '');
                    $country = $request->input('country', '');
                    $address = $request->input('address', '');
                    $town = $request->input('town', '');
                    $county = $request->input('county', '');
                    $postcode = $request->input('postcode', '');
                    $email = $request->input('email', '');
                    $email_confirm = $request->input('email_confirm', '');
                    $telephone_day = $request->input('telephone_day', '');
                    $telephone_evening = $request->input('telephone_evening', '');
                    $mobile = $request->input('mobile', '');
                    $contact_post = $request->input('contact_post', '0');
                    $contact_email = $request->input('contact_email', '0');
                    
                    // Store the values in an array
                    $sessionData = [
                        'storedDestination'  =>  $storedDestination,
                        'amount' => $amount,
                        'title' => $title,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'country' => $country,
                        'address' => $address,
                        'town' => $town,
                        'county' => $county,
                        'postcode' => $postcode,
                        'email' => $email,
                        'email_confirm' => $email_confirm,
                        'telephone_day' => $telephone_day,
                        'telephone_evening' => $telephone_evening,
                        'mobile' => $mobile,
                        'contact_post' => $contact_post,
                        'contact_email' => $contact_email,
                    ];
                    
                    // Save the session data into a variable
                    session($sessionData); 
                    $firstname = session('firstname');
                    $lastname = session('lastname');
                    
                    
                    // Our custom-generated order ID
                    $_SESSION['qmul_order_id'] = 'DONATION_'.$storedDestination.'_'.$firstname.'_'.$lastname;
                    
                    $reviewData = $sessionData;

                    // Update the current step to 3
                    $currentStep = 3;
                    return view('donate', ['reviewData' => $reviewData, 'currentStep' => $currentStep]);

                    break;

                case 3:
                    // Process Step 3 form data and store in session 
                    $payment_result = '';
                    if ($request->has('hppResponse')) {
                        
                        $config = new GpEcomConfig();
            
                        if (env('status') == 'DEVELOPMENT') {
                            $config->merchantId = env('HPP_DEV_MERCHANT_ID');
                            $config->accountId = env('HPP_DEV_DONATE_ACCOUNT_ID');
                            $config->sharedSecret = env('HPP_DEV_DONATE_SHARED_SECRET');
                            $config->serviceUrl = env('HPP_DEV_SERVICE_URL');
                        } else {
                            $config->merchantId = env('HPP_LIVE_MERCHANT_ID');
                            $config->accountId = env('HPP_LIVE_DONATE_ACCOUNT_ID');
                            $config->sharedSecret = env('HPP_LIVE_DONATE_SHARED_SECRET');
                            $config->serviceUrl = env('HPP_LIVE_SERVICE_URL');
                        }
            
                        $service = new HostedService($config);
            
                        $responseJson = $request->input('hppResponse');
            
                        $alumniLogger = Log::channel('daily');
            
                        try {
                            // Create response object from the response JSON
                            $hppResponse = $service->parseResponse($responseJson, true);
            
                            $order_id = $hppResponse->orderId;
                            $payment_result = $hppResponse->responseCode;
                            $payment_message = $hppResponse->responseMessage;
                            $payment_values = $hppResponse->responseValues;
                            $payment_authCode = $payment_values['AUTHCODE'];
            
                            $alumniLogger->info('Session ' . $request->session()->get('token') . ' - Elavon payment result: ' . $payment_result);
                            $alumniLogger->info('Session ' . $request->session()->get('token') . ' - Elavon payment message: ' . $payment_message);
                        
                            Session::put('paymentError', $payment_message);

                        } catch (ApiException $e) {
                            $alumniLogger->error('Session ' . $request->session()->get('token') . ' - Caught Elavon payment validation exception: ' . $e->getMessage());
                        }
                    }
            

                    $donation_id = '';
                    // Save form data via ThankQ API calls
                    if ($payment_result == '00') {

                        $request->session()->put('balancePaid', '1');

                        $contactType = 'Individual';

                        $user_reg_data_arr = [
                            "title" => session('title'),
                            "firstName" => session('firstname'),
                            "keyname" => session('lastname'),
                            "contactType" => $contactType,
                            "primaryCategory" => "Alumni",
                            "sourceCode" => $sourceCode,
                            "addressType" => "Residential",
                            "country" => session('country'),
                            "address" => session('address'),
                            "town" => session('town'),
                            "postcode" => session('postcode'),
                            "emailAddress" => session('email'),
                            "doNotPhone" => filter_var(session('contact_post'), FILTER_VALIDATE_BOOLEAN),
                            "doNotEmail" => filter_var(session('contact_email'), FILTER_VALIDATE_BOOLEAN),
                        ];

                        if (session('county') != '') {
                            $user_reg_data_arr['county'] = session('county');
                        }
                        if (session('telephone_day') != '') {
                            $user_reg_data_arr['dayTelephone'] = session('telephone_day');
                        }
                        if (session('telephone_evening') != '') {
                            $user_reg_data_arr['eveningTelephone'] = session('telephone_evening');
                        }
                        if (session('mobile') != '') {
                            $user_reg_data_arr['mobileNumber'] = session('mobile');
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
                            //Log ContactPublic POST data
                            $alumniLogger->info('Session ' . session('token') . ' - ContactPublic POST data: ' . var_export($user_reg_data, true));
                            
                            
                            $user_reg_response = json_decode(callAPI('POST', 'contacts', $_SESSION['accessToken'], $user_reg_data), true);
                
                            //Log ContactPublic POST response
                            $alumniLogger->info('Session ' . session('token') . ' - ContactPublic POST response: ' . var_export($user_reg_response, true));
                
                            /*if ($user_reg_response['Status'] === 'Success') {
                                $user_reg_values = $user_reg_response['Values'];
                            }*/
                
                            // if (env('status') == 'DEVELOPMENT') {
                            //     echo '<br><br><b>New contact response:</b>';
                            //     dump($user_reg_response);
                            // }
                
                        } catch (Exception $e) {
                            $alumniLogger->error('Session ' . session('token') . ' - ContactPublic POST: Unable to create contact record');
                        }
                        
                        // Proceed upon creation of contact (and existence of serial number)
                        if ($user_reg_response && isset($user_reg_response['serialNumber'])) {
                            $serialNo = $user_reg_response['serialNumber'];

                            if (session('giftaid') == '1') {
                                try {
                                    // Set user GiftAid declaration (note: this method sets declaration and effective from date automatically)
                                    $giftaid_data = json_encode([
                                        "serialNumber" => $serialNo,
                                        "declarationType" => 'Web'
                                    ]);

                                    //Log GiftAidDeclarationPublic POST data
                                    $alumniLogger->info('Session ' . session('token') . ' - GiftAidDeclarations POST data: ' . var_export($giftaid_data, true));

                                    $giftaid_response = json_decode(callAPI('POST', 'giftaiddeclarations', $_SESSION['accessToken'], $giftaid_data), true);

                                    //Log GiftAidDeclarationPublic POST response
                                    $alumniLogger->info('Session ' . session('token') . ' - GiftAidDeclaration POST response: ' . var_export($giftaid_response, true));

                                    // if (env('status') == 'DEVELOPMENT') {
                                    //     echo '<br><br><b>GiftAid response:</b>';
                                    //     dump($giftaid_response);
                                    // }

                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - GiftAidDeclaration POST: Unable to set GiftAid declaration');
                                }
                            }

                            $paymentBatchId = '';
                            // Try to find an existing payment batch for this month
                            try {
                                $existing_batch_response = json_decode(callAPI('GET', 'payments/batch', $_SESSION['accessToken'], ['batchDescription' => "Web Donation Batch - ".date('F Y')]), true);
                                $existing_batch = $existing_batch_response['data'];
                                if (count($existing_batch) > 0 && isset($existing_batch[0]['paymentBatchId'])) {
                                    $paymentBatchId = $existing_batch[0]['paymentBatchId'];
                                }

                                $alumniLogger->info('Session ' . session('token') . ' - Payment Batch GET response: ' . var_export($existing_batch, true));
                            } catch (Exception $e) {
                                $alumniLogger->error('Session ' . session('token') . ' - Payment Batch GET: Unable to retrieve batch');
                            }
                            // If no payment batch exists for this month, create new batch
                            if ($paymentBatchId == '') {

                                try {
                                    $batch_data = json_encode([
                                        "batchType" => "Donation Batch",
                                        "isApproved" => false,
                                        "batchDescription" => "Web Donation Batch - ".date('F Y'),
                                        "defaultSourceCode" => $sourceCode,
                                        "defaultDestinationCode" => session('destinations'),
                                    ]);

                                    //Log Payment Batch POST data
                                    $alumniLogger->info('Session ' . session('token') . ' - Payment Batch POST data: ' . var_export($batch_data, true));

                                    $batch_response = json_decode(callAPI('POST', 'payments/batch', $_SESSION['accessToken'], $batch_data), true);
                                    if (isset($batch_response['paymentBatchId'])) {
                                        $paymentBatchId = $batch_response['paymentBatchId'];
                                    }

                                    //Log Payment Batch POST response
                                    $alumniLogger->info('Session ' . session('token') . ' - Payment Batch POST response: ' . var_export($batch_response, true));

                                    // if (env('status') == 'DEVELOPMENT') {
                                    //     echo '<br><br><b>Payment Batch response:</b>';
                                    //     dump($batch_response);
                                    // }

                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - Payment Batch POST: Unable to submit payment batch details');
                                }
                            }

                            // Process Donation
                            if ($paymentBatchId != '') {

                                try {
                                    $donate_date = new \DateTime('now');
                                    $tax_claimable = true; //($_SESSION['giftaid'] == 1) ? true : false;

                                    // Submit details for donation
                                    $donation_data = json_encode([
                                        "serialNumber" => $serialNo,
                                        "paymentMethod" => "Credit Card",
                                        "paymentRef" => $order_id,
                                        "incomeStream" => "Annual Fund",
                                        "incomeType" => "DONATION",
                                        "paymentBatchId" => $paymentBatchId,
                                        "amount" => session('amount'),
                                        "receiptSerialNumber" => $serialNo,
                                        "date" => $donate_date->format('Y-m-d\TH:i:s'),
                                        "sourceCode" => $sourceCode,
                                        "destinationCode" => session('destinations'),
                                        "receiptRequired" => false,
                                        "taxClaimable" => $tax_claimable
                                    ]);

                                    //Log DonationPublic POST data
                                    $alumniLogger->info('Session ' . session('token') . ' - Payments POST data: ' . var_export($donation_data, true));

                                    $donation_response = json_decode(callAPI('POST', 'payments', $_SESSION['accessToken'], $donation_data), true);
                                    
                                    if (isset($donation_response['paymentId'])) {
                                        $donation_id = $donation_response['paymentId'];
                                        //Send confirmation email
                                        try {
                                            $mailSubject = 'Thank you for your donation';
                                            $mailBody = '<p>Dear '.session('firstname').',</p>';
                                            $mailBody  .= '<p>Please accept our sincere thanks for your donation of &pound;' .session('amount'). ' to '.session('selectedDescription'). ' at Queen Mary University of London.</p>';
                                            $mailBody  .= '<p>Your generosity will make a real difference and your support is greatly appreciated by all of us here at Queen Mary. Our community has a strong tradition of coming together to bring about positive change. If you are also interested in giving your time to support Queen Mary and its students, please read about all the ways <a href="https://www.qmul.ac.uk/alumni/volunteering/">you can volunteer</a>.</p>';
                                            $mailBody  .= '<p>Thank you again for your kindness and if you have any questions about your donation or would like further information, please don`t hesitate to contact the Development and Alumni Engagement Team at <a href="mailto:annualfund@qmul.ac.uk">annualfund@qmul.ac.uk</a>.</p>';
                                            $mailBody  .= '<p>Yours sincerely</p>';
                                            $mailBody  .= '<p><strong>Gemma Marenghi</strong><br>';
                                            $mailBody  .= 'Head of Alumni Engagement</p>';
                                            $mailBody  .= '<p>Directorate of Development & Alumni Engagement<br>';
                                            $mailBody  .= '<strong>Queen Mary University of London</strong><br>';
                                            $mailBody  .= 'Department W (1st Floor), 327 Mile End Road, London E1 4NS, UK</p>';

                                            $mailBody  .= 'Email: <a href="mailto:annualfund@qmul.ac.uk">annualfund@qmul.ac.uk</a><br>';
								            $mailBody  .= 'URL: <a href="http://www.qmul.ac.uk/alumni/giving">www.qmul.ac.uk/alumni/giving</a></p>';
                                            $mailBody  .= '<p><img src="https://qmul.alumni-live.hexdev.uk/media/qmullogo.jpg" alt="" /></p>';
                                            $mailBody  .= '<p><small>The Department of Development and Alumni Engagement (DDAE) supports Queen Mary University of London by engaging with alumni, students, and supporters of the university. To carry out our work, it is necessary for us to process and store personal information according to the principles of the General Data Protection Regulation (GDPR). We process data in pursuit of our legitimate interests in a range of activities, including communicating with alumni, supporters and potential supporters; providing information on events, benefits and services; and furthering Queen Mary`s educational and charitable mission (which includes fundraising and securing the support of volunteers). We may contact you by telephone, email, post, or social media, and you have the right to amend your preferences or opt out entirely at any time. All data is held securely; is not sold; is not shared with unaffiliated third-parties; and is not disclosed to external organisations other than those contracted by Queen Mary to assist in its routine activities. You can read our full Privacy Notice at qmul.ac.uk/alumni/privacynotice. If you would like to update your details, or tell us if you no longer wish to hear from us, please email alumni@qmul.ac.uk.</small></p>';

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
            
                                            //Log communications data
                                            $alumniLogger->info('Session ' . session('token') . ' - Communication Logging POST data: ' . var_export($comms_data, true));
            
                                            $comms_response = json_decode(callAPI('POST', 'communications/emailLogging', $_SESSION['accessToken'], $comms_data), true);
                                        } catch (Exception $e) {
                                            $alumniLogger->error('Session ' . session('token') . ' - Unable to send donation email confirmation: '.$e);
                                        }

                                    }
                                        //Log DonationPublic POST response
                                        //Log DonationPublic POST response
                                    $alumniLogger->info('Session ' . session('token') . ' - Payments POST response: ' . var_export($donation_response, true));

                                    // if (env('status') == 'DEVELOPMENT') {
                                    //     echo '<br><br><b>Payments response:</b>';
                                    //     dump($donation_response);
                                    // }
                                

                                } catch (Exception $e) {
                                    $alumniLogger->error('Session ' . session('token') . ' - Payments POST: Unable to submit donation details');
                                }       
                            }
                        }

                    }
                    // dd('testend');

                    $currentStep = 4;
                    return view('donate', ['payment_result' => $payment_result, 'currentStep' => $currentStep, 'donation_id' => $donation_id]);

                    break;

                    default:
                    // Invalid step, handle accordingly
                    break;
            }
            return view('donate', ['currentStep' => $currentStep]);
        }
    }

}