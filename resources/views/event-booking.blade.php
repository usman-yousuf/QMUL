@extends('layout.app')
@section('title', 'Book your events - ')
@section('content')
@if(isset($errorMessage) && isset($unavailableMessage))
    <header class="main-header prose">
        <h1>{{ $evt_res['eventName'] }}</h1>
        <div class="intro">
            <h2>{{ $errorMessage }}</h2>
            <p>{{ $unavailableMessage }}</p>
        </div>
    </header>
@else
    <header class="main-header prose">
        <h1>Book {{ $evt_res['eventName'] }}</h1>
        <div class="intro">
            <p>{{ $evt_res['webDescription'] }}</p>
        </div>
    </header>

    <time class="prose">
        <p><strong>Start:</strong> {{ date("j F Y,", strtotime($evt_res['startDate'])) }} {{ date("g:i a", strtotime($evt_res['startTime'])) }}<br />
        <strong>End:</strong> {{ date("j F Y,", strtotime($evt_res['endDate'])) }} {{ date("g:i a", strtotime($evt_res['endTime'])) }}</p>
    </time>

    <hr />

    <div class="esitContent mt-4">
        <ul class="breadcrumb">
            <li{{ ($form_step === 1) ? ' class="active"' : '' }}>Select tickets</li>
            <li{{ ($form_step === 2) ? ' class="active"' : '' }}>Attendee Details</li>
            <li{{ ($form_step === 3) ? ' class="active"' : '' }}>Contact Details</li>
            <li{{ ($form_step === 4) ? ' class="active"' : '' }}>Review</li>
            <li{{ ($form_step === 5) ? ' class="active"' : '' }}>Pay</li>
            <li{{ ($form_step === 6) ? ' class="active"' : '' }}>Confirmation</li>
        </ul><br><br>
    </div>
@endif

{{-- test purpose --}}

<style>

	.booking-warning{
		position: relative;
	}
	
	.cv-spinner {
		display: inline-flex;
		padding: 1rem 0 0 0;
	}
	
	.spinner {
	width: 40px;
	height: 40px;
	border: 4px #ddd solid;
	border-top: 4px #092657 solid;
	border-radius: 50%;
	animation: sp-anime 0.8s infinite linear;
	}
	@keyframes sp-anime {
	100% { 
	transform: rotate(360deg); 
	}
	}
	
	.booking-warning strong{
	padding: 0 0 0 2rem; 
	display: inline-flex;
	width: 80%;
	position: absolute;
	bottom: 0;
	}
	</style>
    <!-- Spinner -->
    <div id="spinner" class="booking-warning" style="display: none;">
        <p><span class="cv-spinner"><span class="spinner"></span></span>
        <strong>We are currently processing your booking, please do not redirect or move away from this page. Your booking reference will be displayed shortly.</strong></p>
    </div>


{{-- test purpose --}}

@if ($form_step == 1 && $evt_bookable && count($tableRows) > 0)

    <form id="event-form-1" action="{{ route('event.process', ['eventID' => $eventID]) }}" method="POST">
        @csrf
        <input type="hidden" name="step" value="2" />
        <input type="hidden" name="eventID" value="{{ $eventID }}" />
        <table class="table table-striped table-bordered" style="width: 100%">
            <thead>
                <tr>
                    <th style="text-align:left;">Type</th>
                    <th style="text-align:left;">Price</th>
                    <th style="text-align:left;">Number of tickets</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tableRows as $row)
                    <tr>
                        <td>{{ $row['attendeeType'] }}</td>
                        <td>
                            @if($row['cost'])
                                £ {{ number_format((float)$row['cost'], 2, '.', '') }}
                            @else
                                Free
                            @endif
                        </td>
                        <td>
                            @if ($row['remaining'] != '0')
                                @php
                                    if($row['maxAttendeesPerBooking'] <= $row['remaining']) {
                                        $ticketcount = $row['maxAttendeesPerBooking'];
                                    } else {
                                        $ticketcount = $row['remaining'];
                                    }
                                @endphp
                                <label for="{{ $row['attendeeTypeId'] }}_tickets" class="vh">Select number of {{ $row['attendeeType'] }} tickets</label>
                                <select id="{{ $row['attendeeTypeId'] }}_tickets" class="tickets no-sort select" name="{{ $row['attendeeTypeId'] }}-tickets">
                                    @for ($x = 0; $x <= $ticketcount; $x++)
                                        <option value="{{ $x }}">{{ $x }}</option>
                                    @endfor
                                </select>
                                <input type="hidden" name="ticketType[]" value="{{ $row['attendeeTypeId'] }}" />
                                <input type="hidden" name="{{ $row['attendeeTypeId'] }}DisplayTitle" value="{{ $row['attendeeType'] }}" />
                                <input type="hidden" name="{{ $row['attendeeTypeId'] }}Cost" value="{{ $row['cost'] }}" />
                            @else
                                <strong>Sold out</strong>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Add any additional form fields or inputs here -->

        <input type="submit" value="Continue" class="action my-4" />
    </form>

    <script>
        $(document).ready(function () {
            $.validator.addMethod('ticketMinCheck', function (value) {
                var hasMinTickets = false;
                $('.tickets').each(function() {
                    if (parseInt($(this).val()) > 0) {
                        hasMinTickets = true;
                    }
                });
                return hasMinTickets
            }, "Please select at least 1 ticket");
            $.validator.addMethod('ticketMaxCheck', function (value) {
                var selectedTickets = 0;
                $('.tickets').each(function() {
                    selectedTickets += parseInt($(this).val());
                });
                return (selectedTickets > {{ $maxAttendeesPerBooking }}) ? false : true;
            }, "You can only select up to {{ $maxAttendeesPerBooking }} attendees per booking");
            $("#event-form-1").validate({errorLabelContainer: '#invalid'});
            $.validator.addClassRules('tickets', {
                ticketMinCheck: true, ticketMaxCheck: true
            });
        });
    </script>

    <style>
        #invalid label.error:not(:last-child) {
            display: none !important;
        }
    </style>
@endif
@if ($form_step == 2)

	@if (isset($attendees_already_booked) && count($attendees_already_booked) > 0)
		<!-- jQuery Modal -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css" />

		<div id="dup-modal" class="dup-modal">
            <h2>Attention required:</h2>
            <p>The following attendee(s) have been removed as they are already booked onto this event:</p>
            <ul>
                @foreach ($attendees_already_booked as $attendee)
                    <li><b>{{ $attendee->fullName }}</b></li>
                @endforeach
            </ul>
            @if (count($_SESSION['booking']['id']) == 0)
                <a href="{{ $_SERVER['PHP_SELF'] }}?eventID={{ $_SESSION['eventID'] }}">Restart Booking</a>
            @else
                <a href="#" rel="modal:close">Close</a>
            @endif
        </div>

        <script>
            $(document).ready(function(){
                $('.dup-modal').modal({
                    escapeClose: false,
                    clickClose: false,
                    showClose: false
                });
            });
        </script>
	@endif
    @php
        $validationFields = '';
        $validationMessages = '';
    @endphp
	<form id="event-form-2" action="{{ route('event.process', ['eventID' => $eventID]) }}" method="POST" class="prose">
        @csrf
        {{-- <input type="hidden" name="step" value="{{ $prevClicked ? 2 : 3 }}" /> --}}
        <input type="hidden" id="step" name="step" value="3" />
        <input type="hidden" name="eventID" value="{{ $eventID }}" />
    
        <h2>Event attendees</h2>
        @if($ticketTypes)
            @php
                $attendeeFormNo = 0;
                $validationFields = '';
                $validationMessages = '';
            @endphp
            @foreach ($ticketTypes as $tickettype)
                @php
                    $ticketcount = Session::get($tickettype.'Tickets');
                @endphp
                @for ($i = 0; $i < $ticketcount; $i++)
                    @include('form-element.event-ticket')
                    @php
                        $validationFields .= '"attendeefirstname['.$attendeeFormNo.']": { required: true }, "attendeesurname['.$attendeeFormNo.']": { required: true }, ';
                        $validationMessages .= '"attendeefirstname['.$attendeeFormNo.']": { required: "Please enter the first name" }, "attendeesurname['.$attendeeFormNo.']": { required: "Please enter the last name" }, ';
                        $attendeeFormNo++;
                    @endphp
                @endfor
            @endforeach
        @endif
    
        <input type="submit" value="Continue" class="action my-4 mr-2" />
        <input type="hidden" id="prevClicked" name="prevClicked" value="false" />
        <input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; document.getElementById('step').value='2'; this.form.submit();" /> 
    </form>

    <script>
        $(document).ready(function () {
            $("#event-form-2").validate({
                rules: {
                    {!! $validationFields !!}
                },
                messages: {
                    {!! $validationMessages !!}
                },
                errorPlacement: function (error, element) {
                    error.insertBefore(element);
                }
            });
        });
    </script>

@endif
@if ($form_step == 3)
	<form id="event-form-3" action="{{ route('event.process', ['eventID' => $eventID]) }}" method="POST" class="prose">
		@csrf
		<input type="hidden" name="step" value="4" />
        <input type="hidden" name="eventID" value="{{ $eventID }}" />

		@include('form-element.contact-details')

		<input type="submit" value="Continue" class="action my-4 mr-2" />
        <input type="hidden" id="prevClicked" name="prevClicked" value="false" />
        <input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />
    </form>

	<script>
		$(document).ready(function () {

		});
	</script>

	<script>
		$(document).ready(function () {
			$.validator.addMethod("postcode", function (value, element) {
				return this.optional(element) || /^((([A-PR-UWYZ][0-9])|([A-PR-UWYZ][0-9][0-9])|([A-PR-UWYZ][A-HK-Y][0-9])|([A-PR-UWYZ][A-HK-Y][0-9][0-9])|([A-PR-UWYZ][0-9][A-HJKSTUW])|([A-PR-UWYZ][A-HK-Y][0-9][ABEHMNPRVWXY]))\s?([0-9][ABD-HJLNP-UW-Z]{2})|(GIR)\s?(0AA))$/i.test(value);
			}, "Please specify a valid postcode");
			$("#event-form-3").validate({
				rules: {
					"title": {
						required: true
					},
					"firstname": {
						required: true
					},
					"lastname": {
						required: true
					},
					"country": {
						required: true
					},
					"address": {
						required: true
					},
					"postcode": {
						required: true,
					},
					"town": {
						required: true
					},
					"email": {
						required: true,
						email: true
					},
					"email_confirm": {
						required: true,
						equalTo: '[name="email"]'
					},
					"contact_email": {
						required: true
					},
					"contact_post": {
						required: true
					}
				},
				messages: {
					"title": {
						required: "Select a title"
					},
					"firstname": {
						required: "Enter a first name"
					},
					"lastname": {
						required: "Enter a last name"
					},
					"country": {
						required: "Enter a country"
					},
					"address": {
						required: "Enter an address"
					},
					"postcode": {
						required: "Enter a postcode"
					},
					"town": {
						required: "Enter a town or city"
					},
					"email": {
						required: "Enter an email address"
					},
					"email_confirm": {
						required: "Enter an email address",
						equalTo: "Enter the same email address"
					},
					"contact_email": {
						required: "Choose an option"
					},
					"contact_post": {
						required: "Choose an option"
					}
				},
				errorPlacement: function (error, element) {
					error.insertBefore(element);
				}
			});
		});
	</script>
@endif
@if ($form_step == 4)
    <div class="prose">
        <h2>Review</h2>
        <fieldset>
            <legend class="th-s3">Your details</legend>
            <div class="tqRow">
                <b>Title:</b> {{ Session::get('title') }}<br>
                <b>First name:</b> {{ Session::get('firstname') }}<br>
                <b>Surname:</b> {{ Session::get('lastname') }}<br>
                <b>Country:</b> {{ Session::get('country') }}<br>
                <b>Address:</b> {{ Session::get('address') }}<br>
                <b>Town:</b> {{ Session::get('town') }}<br>
                <b>County:</b> {{ Session::get('county') }}<br>
                <b>Postcode:</b> {{ Session::get('postcode') }}<br>
                <b>Email:</b> {{ Session::get('email') }}<br>
                <b>Daytime telephone:</b> {{ Session::get('telephone_day') }}<br>
                <b>Evening telephone:</b> {{ Session::get('telephone_evening') }}<br>
                <b>Mobile:</b> {{ Session::get('mobile') }}<br><br>
                {!! (Session::get('contact_email') == '1') ? 'I do <b>not</b> want to be contacted by email' : 'I am <b>happy</b> for you to contact me by email' !!}<br>
                {!! (Session::get('contact_post') == '1') ? 'I do <b>not</b> want to be contacted by telephone' : 'I am <b>happy</b> for you to contact me by telephone' !!}<br>

                @php
                    $attendee_count = count(Session::get('booking.id'));
                @endphp
                @for ($i = 0; $i < $attendee_count; $i++)
                    <br><br><b>{{ Session::get('booking.type')[$i] }} Attendee {{ $i + 1 }}</b><br><br>
                    <b>Name:</b> {{ Session::get('booking.title')[$i] }} {{ Session::get('booking.attendeefirstname')[$i] }} {{ Session::get('booking.attendeesurname')[$i] }}<br>
                    <b>Dietary requirements:</b> {{ Session::get('booking.attendeedietary')[$i] }}
                    @if (isset(Session::get('booking.attendeespecialrequirements')[$i]) && strlen(Session::get('booking.attendeespecialrequirements')[$i]) > 0)
                        <br><b>Special dietary needs:</b> {{ Session::get('booking.attendeespecialrequirements')[$i] }}
                    @endif
                @endfor
            </div>
        </fieldset>
    </div>

    <form id="event-form-4" action="{{ route('event.process', ['eventID' => $eventID]) }}" method="POST">
        @csrf

        <input type="hidden" name="eventID" value="{{ $eventID }}" />
        <input type="hidden" name="step" value="{{ (Session::get('totalCost') != '0') ? '5' : '6' }}" />
        <input type="hidden" name="submitted" value="true" />
        <fieldset>
            <input type="submit" value="Proceed{{ (Session::get('totalCost') != '0') ? ' to Payment' : '' }}" class="action my-4 mr-2" />
            <input type="hidden" id="prevClicked" name="prevClicked" value="false" />
            <input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />
        </fieldset>
    </form>
@endif

@if ($form_step == 5)
    <form id="event-form-5" action="{{ route('event.process', ['eventID' => $eventID]) }}" method="POST" class="prose">
        @csrf
        <input type="hidden" name="eventID" value="{{ $eventID }}" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script src="{{ asset('assets/js/rxp-js-1.5.1/dist/rxp-js.js')}}"></script>
        <script>
            @if (env('status') == 'DEVELOPMENT')
                RealexHpp.setHppUrl('{{ env('HPP_DEV_SERVICE_URL') }}');
            @else
                RealexHpp.setHppUrl('{{ env('HPP_LIVE_SERVICE_URL') }}');
            @endif
            @php
                if (strpos(session('totalCost'), '.') === false) {
                    $totalCost = session('totalCost') . '.00';
                } else {
                    $totalCost = session('totalCost') ?: '';
                }
            @endphp
            $(document).ready(function () {
                $.ajax({
                    url: "/hpp-request?amount={{ $totalCost }}",
                    dataType: 'json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(jsonFromRequestEndpoint) {
                        var parsedData = JSON.parse(jsonFromRequestEndpoint);
                        RealexHpp.embedded.init("payment-button", "payment-iframe", function (answer, close) {
                            $.busyLoadFull("show", { text: "LOADING ..." });
                            close();

                            let merchantRedirectForm = document.createElement("form");
                            merchantRedirectForm.setAttribute("method", "POST");
                            merchantRedirectForm.setAttribute("action", "process?step=6");

                            let csrfTokenEl = document.createElement("input");  // Create CSRF token field
                            csrfTokenEl.setAttribute("type", "hidden");
                            csrfTokenEl.setAttribute("name", "_token");
                            csrfTokenEl.setAttribute("value", $('meta[name="csrf-token"]').attr('content')); // Get CSRF token value from meta tag
                            merchantRedirectForm.appendChild(csrfTokenEl);
                            
                            let eventIdEl = document.createElement("input");  // Create EventID field
                            eventIdEl.setAttribute("type", "hidden");
                            eventIdEl.setAttribute("name", "eventID");
                            eventIdEl.setAttribute("value", $('input[name="eventID"]').val()); // Grab the value from the HTML form
                            merchantRedirectForm.appendChild(eventIdEl); // Append EventID field to the form


                            let hiddenResponseEl = document.createElement("input");
                            hiddenResponseEl.setAttribute("type", "hidden");
                            hiddenResponseEl.setAttribute("name", "hppResponse");
                            hiddenResponseEl.setAttribute("value", answer);
                            merchantRedirectForm.appendChild(hiddenResponseEl);
                            document.body.appendChild(merchantRedirectForm);

                            merchantRedirectForm.submit();
                        }, parsedData); // parsed data is passed here

                        if (window.addEventListener) {
                            window.addEventListener('message', receiveMessage, false);
                        } else {
                            window.attachEvent('message', receiveMessage);
                        }
                    }
                });
                $('.form-page #payment-button').click(function () {
                    $(this).hide();
                    $('.form-page #payment-iframe').css('display', 'block !important').css('height', '700px').css('width', '100%');
                });
            });
        </script>

        <h2>Payment</h2>
        <p><strong>Please complete your booking by selecting the "Pay Now" button below</strong></p>
        <p>Once pressed you will see the payment screen below. Do disable any pop-up blockers that you may have.</p>
        <hr />
        <p>We have recently added 3D Secure to all payments. If you have any issues, please contact us with as much information as possible.</p>
        <div class="form-page">
            <fieldset>
                <legend>Pay for Booking</legend>

                <p>The amount due is: <b>£{{ $totalCost }}</b></p>

                <input type="button" id="payment-button" value="Pay Now" class="action my-4" />

            </fieldset>
            <iframe src="" id="payment-iframe"></iframe>
        </div>
    </form>
@endif
@if ($form_step == 6)
        <script>
            $(document).ready(function() {
                $('#spinner').css('margin-top', '3rem').css('margin-bottom', '3rem').show();
            
                setTimeout(function() {
                    $('#spinner').hide();
            
                    $('#event-form-6').css('display', 'block');
                }, 5000);
            });
        </script>
        <form id="event-form-6" style="display: none; action="{{ route('event.process', ['eventID' => $eventID]) }}" method="POST" class="prose">
            @csrf
            @if ($payment_result == '00' || session('totalCost') == '0')
            
                <h2>Confirmation</h2>

                <div class="form-page">
                    <fieldset>
                        <legend>Thank you</legend>

                        <p>Your event booking is now complete.</p>

                        @if (session('bookingConfirmId'))
                            <p>Your booking reference number is: <b>{{ session('bookingConfirmId') }}</b></p>
                        @endif
                    </fieldset>
                </div>
                @php
                    session()->flush();
                @endphp
            @else
                <div class="form-page">
                    <fieldset>
                        <h2>Payment Error</h2>
                            <p><strong>We're sorry, on this occasion your payment was unsuccessful.</strong></p>
                            <blockquote>{{ session('paymentError') }}</blockquote>
                            <p>Please try again later.</p>
                    </fieldset>
                </div>

            @endif
        </form>
@endif


@endsection
