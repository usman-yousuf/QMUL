@extends('layout.app')
@section('title', 'Donate - ')
@section('content')


<header class="main-header prose">
	<h1>Donate to Queen Mary University of London Foundation</h1>
</header>

<div class="esitContent mt-4">
	<ul class="breadcrumb">
		<li @if ($currentStep === 1) class="active" @endif>Donation</li>
		<li @if ($currentStep === 2) class="active" @endif>Contact Details</li>
		<li @if ($currentStep === 3) class="active" @endif>Review and Pay</li>
		<li @if ($currentStep === 4) class="active" @endif>Confirmation</li>
	</ul>
</div>

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
	<strong>We are currently processing your donation, please do not redirect or move away from this page. Your donation reference will be displayed shortly.</strong></p>
</div>


{{-- test purpose --}}

	
    <!-- Step 1 Form -->
    @if ($currentStep === 1)
        <form id="donate-form-1" class="prose" action="{{ route('donation', ['form_step' => 1]) }}" method="post">
			@csrf
            <input type="hidden" name="form_step" value="1" />
            <!-- Form content -->
            <h2>Donation</h2>
			<div class="form-page">
				<fieldset>
					<legend class="th-s3">About your donation</legend>
					<label for="destinations">* Select a destination:</label>
					<select name="destinations" id="destinations" class="input-small no-sort required select" aria-required>
						<option disabled>Please select an option</option>
						@php
							$destinationOptions = session('destinationOptions', []);
						@endphp

						@foreach ($destinationOptions as $option)
							<option{{ $option['selected'] }} value="{{ $option['value'] }}|{{ $option['description'] }}">{{ $option['description'] }}</option>
						@endforeach
					</select>
					<legend class="th-s3 pt-4">Amount to donate</legend>
					<label for="amount">* Choose an amount to donate: £</label>
					<input name="amount" type="text" id="amount" class="input required" value="{{ isset($_SESSION['amount']) ? $_SESSION['amount'] : '' }}">
					<p class="info"><em>Please enter the full amount you wish to donate, for example 45.00</em></p>

					<hr />

					<legend class="th-s3 pt-4">Gift Aid</legend>
						<p>If you are a UK taxpayer, we can claim an additional 25p for every £1 you give through the Gift Aid scheme. If you would like us to do this, please confirm that you have read the statement below by ticking the &quot;I want to create a Gift Aid declaration&quot; box.</p>

						<p>Please Gift Aid any donations that I make in the future or have made in the past four years to the Queen Mary University of London Foundation. I am a UK taxpayer and understand that if I pay less Income Tax and/or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that tax year it is my responsibility to pay any difference.</p>

						<p>I have provided my name and home address via this online form and confirm that I will contact Queen Mary University of London if: </p>
						<ul>
							<li>I want to cancel this declaration</li>
							<li>I change my name or home address</li>
							<li>I no longer pay sufficient tax on my income and/or capital gains</li>
						</ul>

						<div class="checkbox">
							<input id="giftaid" class="checkbox__input" type="checkbox" name="giftaid" value="1" style="display: inline-block" <?php echo (isset($_SESSION['giftaid']) && $_SESSION['giftaid'] == '1') ? 'checked' : ''; ?>>
							<label class="checkbox__label" for="giftaid">I want to create a Gift Aid declaration&nbsp;</label>
						</div>

					<p>Please note that if you pay Income Tax at the higher or additional rate and want to receive the additional tax relief due to you, you must include all your Gift Aid donations on your Self-Assessment tax return or ask HM Revenue and Customs to adjust your tax code.</p>

				</fieldset>
			</div>
            <input class="action mb-4" type="submit" value="Continue" />
        </form>
		<script>
			$(document).ready(function () {
				$("#donate-form-1").validate({
					rules: {
						"amount": {
							required: true,
							min: 1
						}
					},
					messages: {
						"amount": {
							required: "Please specify an amount greater than or equal to £1 using only numbers."
						},
						errorPlacement: function(error, element) {
							error.insertBefore( element );
						}
					}
				});
			});
		</script>
    @endif
	
    <!-- Step 2 Form -->

    @if ($currentStep === 2)
        <form id="donate-form-2" class="prose" action="{{ route('donation', ['form_step' => 2]) }}" method="post">
			@csrf
            <input type="hidden" name="form_step" value="2" />
            <!-- Form content -->
            <h2>Contact Details</h2>

			<div class="form-page">

				@include('form-element.contact-details')

			</div>
            <input class="action my-4 mr-2" type="submit" value="Continue" />
			<input type="hidden" id="prevClicked" name="prevClicked" value="false" />
			<input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />

			{{-- <input class="action action--secondary my-4" type="button" value="Previous" onclick="window.location.href='donation?form_step=1&prev=true'" /> --}}
        </form>
		
		<script>
			$(document).ready(function () {
				$.validator.addMethod( "postcode", function( value, element ) {
					return this.optional( element ) || /^((([A-PR-UWYZ][0-9])|([A-PR-UWYZ][0-9][0-9])|([A-PR-UWYZ][A-HK-Y][0-9])|([A-PR-UWYZ][A-HK-Y][0-9][0-9])|([A-PR-UWYZ][0-9][A-HJKSTUW])|([A-PR-UWYZ][A-HK-Y][0-9][ABEHMNPRVWXY]))\s?([0-9][ABD-HJLNP-UW-Z]{2})|(GIR)\s?(0AA))$/i.test( value );
				}, "Please specify a valid postcode" );
				$("#donate-form-2").validate({
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
						},
						"mobile": {
							number: true
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
							equalTo: "Enter the same email addresse"
						},
						"contact_email": {
							required: "Choose an option"
						},
						"contact_post": {
							required: "Choose an option"
						}
					},
					errorPlacement: function(error, element) {
						error.insertBefore( element );
					}
				});
			});
		</script>
    @endif

    <!-- Step 3 Form -->

	@if ($currentStep === 3)
	
		<form id="donate-form-3" class="prose" action="{{ route('donation', ['form_step' => 3]) }}" method="post">
			@csrf
			<meta name="csrf-token" content="{{ csrf_token() }}">
			<script src="{{ asset('assets/js/rxp-js-1.5.1/dist/rxp-js.js') }}"></script>

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
						url: "/hpp-request-donate?amount={{ session('amount') }}",
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
								merchantRedirectForm.setAttribute("action", "donation-view?form_step=3");
	
								let csrfTokenEl = document.createElement("input");  // Create CSRF token field
								csrfTokenEl.setAttribute("type", "hidden");
								csrfTokenEl.setAttribute("name", "_token");
								csrfTokenEl.setAttribute("value", $('meta[name="csrf-token"]').attr('content')); // Get CSRF token value from meta tag
								merchantRedirectForm.appendChild(csrfTokenEl);
								
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


			<input type="hidden" name="form_step" value="3" />

			<h2>Review</h2>

			<div class="form-page">
				<fieldset>
					<legend class="th-s3">Your Donation</legend>

					<div class="tqRow">
						<b>Donation destination:</b> {{ $reviewData['storedDestination'] }}<br>
						<b>Amount donated:</b> &pound;{{ $reviewData['amount'] }}<br>
					</div>
				</fieldset>

				<fieldset>
					<legend class="th-s3 pt-4">Gift Aid</legend>

					<div class="tqRow">
						<b>I want to create a Gift Aid declaration:</b> {{ session('giftaid') == '1' ? 'Yes' : 'No' }}<br>
					</div>
				</fieldset>

				<fieldset>
					<legend class="th-s3 pt-4">Your details</legend>

					<div class="tqRow">
						<b>Title:</b> {{ $reviewData['title'] }}<br>
						<b>First name:</b> {{ $reviewData['firstname'] }}<br>
						<b>Surname:</b> {{ $reviewData['lastname'] }}<br>
						<b>Country:</b> {{ $reviewData['country'] }}<br>
						<b>Address:</b> {{ $reviewData['address'] }}<br>
						<b>Town:</b> {{ $reviewData['town'] }}<br>
						<b>County:</b> {{ $reviewData['county'] }}<br>
						<b>Postcode:</b> {{ $reviewData['postcode'] }}<br>
						<b>Email:</b> {{ $reviewData['email'] }}<br>
						<b>Daytime telephone:</b> {{ $reviewData['telephone_day'] }}<br>
						<b>Evening telephone:</b> {{ $reviewData['telephone_evening'] }}<br>
						<b>Mobile:</b> {{ $reviewData['mobile'] }}<br><br>
						{!! $reviewData['contact_email'] == '1' ? 'I do <b>not</b> want to be contacted by email' : 'I am <b>happy</b> for you to contact me by email' !!}<br>
						{!! $reviewData['contact_post'] == '1' ? 'I do <b>not</b> want to be contacted by telephone' : 'I am <b>happy</b> for you to contact me by telephone' !!}<br>
						
					</div>
					<div class="tqRow">
						<input class="action my-4 mr-2" type="button" id="payment-button" value="Pay and Submit" />

						<input type="hidden" id="prevClicked" name="prevClicked" value="false" />
						<input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />

						{{-- <input class="action action--secondary my-4" type="button" value="Previous" onclick="window.location.href='donation-view?form_step=2&prev=true'" /> --}}
					</div>
				</fieldset>

				<iframe src="" id="payment-iframe"></iframe>
			</div>
		</form>
    @endif

	<!-- Step 4 Form -->

	@if ($currentStep === 4)
		<script>
			$(document).ready(function() {
				$('#spinner').css('margin-top', '3rem').css('margin-bottom', '3rem').show();
			
				setTimeout(function() {
					$('#spinner').hide();
			
					$('#donate-form-4').css('display', 'block');
				}, 5000);
			});
		</script>
		<form id="donate-form-4" style="display: none;" class="prose" action="{{ route('donation') }}" method="post">
			@csrf
			<!-- Form content -->
			@if ($payment_result == '00' || session('amount') == '0')
				<h2>Confirmation</h2>

				<div class="form-page">
					<fieldset>
						<legend class="th-s3">Thank you</legend>

						<p>Your donation has been successfully processed.</p>

						@if($donation_id)
						<p>Your donation reference number is: <b>{{$donation_id}}</b></p>
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
