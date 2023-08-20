@extends('layout.app')
@section('title', 'Pledge donation - ')
@section('content')
    <header class="main-header prose">
        <h1>Pledge a regular donation</h1>
    </header>
    <div class="esitContent mt-4">
        <ul class="breadcrumb">
            <li class="{{ ($form_step === 1) ? 'active' : '' }}">Donation</li>
            <li class="{{ ($form_step === 2) ? 'active' : '' }}">Contact Details</li>
            <li class="{{ ($form_step === 3) ? 'active' : '' }}">Direct Debit</li>
            <li class="{{ ($form_step === 4) ? 'active' : '' }}">Review</li>
            <li class="{{ ($form_step === 5) ? 'active' : '' }}">Confirmation</li>
        </ul>
    </div>

    
    
    @if ($form_step === 1)
    
        <form id="pledge-form-1" class="prose" action="{{ route('pledge.donation') }}" method="POST">
            @csrf
            <input type="hidden" name="step" value="2" />
        
            <h2>Donation</h2>
        
            <div class="form-page">
                <fieldset>
                    {{-- {{ dd( $destinations)}} --}}
                    <legend class="th-s3">About Your Donation</legend>
                    <label for="destinations">* Select a destination:</label>
                    <select name="destinations" id="destinations" class="input-small no-sort required select" aria-required>
						<option disabled>Please select an option</option>
						@php
							$destinationOptions = session('destinationOptions', []);
						@endphp

						@foreach ($destinationOptions as $option)
							<option{{ $option['selected'] }} value="{{ $option['value'] }}">{{ $option['description'] }}</option>
						@endforeach
					</select>
                </fieldset>
        
                <fieldset>
                    <legend class="th-s3 pt-4">Your Regular Donation</legend>
                    <label for="frequency">Donation Frequency</label>
                    <select name="frequency" id="frequency" class="input-small no-sort select">
                        <option {{ (session('frequency') == 'Monthly') ? 'selected' : '' }} value="Monthly">Monthly</option>
                        <option {{ (session('frequency') == 'Quarterly') ? 'selected' : '' }} value="Quarterly">Quarterly</option>
                        <option {{ (session('frequency') == 'Annually') ? 'selected' : '' }} value="Annually">Annually</option>
                    </select>
        
                    <br><br>
        
                    <label for="amount">* Regular amount&nbsp;£</label>
                    <input name="amount" type="text" id="amount" class="input required" value="{{ session('amount', '') }}">
        
                    <br><br>
        
                    <label for="start_date_day">Start Date</label>
                    <select name="start_date_day" id="start_date_day" class="input-small no-sort select">
                        <option {{ (session('start_date_day') == '01') ? 'selected' : '' }} value="01">01</option>
                        {{-- <option {{ (session('start_date_day') == '15') ? 'selected' : '' }} value="15">15</option> --}}
                    </select>
        
                    @php
                         $current = date('F');
                        $current_year = date('Y');
                    @endphp
                    <select name="start_date_month" id="start_date_month" class="input-small no-sort select">
                        @for ($i = 1; $i <= 12; $i++)
                            @php
                                $month_selected = ($current == date('F', mktime(0, 0, 0, $i, 1, date('Y')))) ? 'selected="selected"' : '';
                            @endphp
                            <option value="{{ date('m', strtotime(date('F', mktime(0, 0, 0, $i, 1, date('Y'))))) }}" {{ $month_selected }}>{{ date('F', mktime(0, 0, 0, $i, 1, date('Y'))) }}</option>
                        @endfor
                    </select>&nbsp;
        
                    <select name="start_date_year" id="start_date_year" class="input-small no-sort select">
                        @for ($i = 0; $i <= 1; $i++)
                            @php
                                $year_selected = ($current_year == session('start_date_year')) ? 'selected="selected"' : '';
                            @endphp
                            <option value="{{ $current_year }}" {{ $year_selected }}>{{ $current_year }}</option>
                            @php $current_year++; @endphp
                        @endfor
                    </select>
        
                    <legend class="th-s3 pt-4">Gift Aid</legend>
        
                    <p>If you are a UK taxpayer, we can claim an additional 25p for every £1 you give through the Gift Aid scheme. If you would like us to do this, please confirm that you have read the statement below by ticking the &quot;I want to create a Gift Aid declaration&quot; box.</p>
        
                    <p>Please Gift Aid any donations that I make in the future or have made in the past four years to the Queen Mary University of London Foundation. I am a UK taxpayer and understand that if I pay less Income Tax and/ or Capital Gains Tax than the amount of Gift Aid claimed on all my donations in that tax year it is my responsibility to pay any difference.</p>
        
                    <p>I will contact Queen Mary if: </p>
                    <ul>
                        <li>I want to cancel this declaration</li>
                        <li>I change my name or home address</li>
                        <li>I no longer pay sufficient tax on my income and/or capital gains</li>
                    </ul>
        
                    <div class="checkbox">
                        <input id="giftaid" class="checkbox__input" type="checkbox" name="giftaid" value="1" style="display: inline-block" {{ (session('giftaid', '') == '1') ? 'checked' : '' }}>
                        <label class="checkbox__label" for="giftaid">I want to create a Gift Aid declaration&nbsp;</label>
                    </div>
        
                    <p>Please note that if you pay Income Tax at the higher or additional rate and want to receive the additional tax relief due to you, you must include all your Gift Aid donations on your Self-Assessment tax return or ask HM Revenue and Customs to adjust your tax code.</p>
        
                    <br><br>
                    <input class="action mb-4" type="submit" value="Continue" />
                </fieldset>
            </div>
        </form>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.2/locale/en-gb.js"></script>
        <script>
            $(document).ready(function () {
                $.validator.addMethod(
                    "pledgeDateRange",
                    function(value, element) {
                        var compareDate = moment($('#start_date_day').val()+'/'+$('#start_date_month').val()+'/'+'/'+$('#start_date_year').val(), 'DD/MM/YYYY');
                        var startDate   = moment().add(2, 'weeks');
                        var endDate     = moment().add(1, 'year');
        
                        return compareDate.isBetween(startDate, endDate);
                    },
                    "The Start Date must be between 14 days and 12 months into the future."
                );
                $("#pledge-form-1").validate({
                    rules: {
                        "amount": {
                            required: true,
                            min: 1
                        },
                        "start_date_year": {
                            pledgeDateRange: true
                        }
                    },
                    messages: {
                        "amount": {
                            required: "Please specify an amount greater than or equal to £1 using only numbers."
                        }
                    },
                    unhighlight: function (element, errorClass, validClass) {
                        $('#start_date_year').removeClass('error');
                    }
                });
            });
        </script>
    
    @endif
    @if ($form_step === 2)

        <form id="pledge-form-2" class="prose" action="{{ route('pledge.donation') }}" method="POST">
            @csrf
            <input type="hidden" name="step" value="3" />

            <h2>Contact Details</h2>

            <div class="form-page">
                @include('form-element.contact-details')

                <input class="action my-4 mr-2" type="submit" value="Continue" />
                <input type="hidden" id="prevClicked" name="prevClicked" value="false" />
                <input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />
            </div>
        </form>

        <script>
            $(document).ready(function () {
                $.validator.addMethod(
                    "postcode",
                    function(value, element) {
                        return this.optional(element) || /^((([A-PR-UWYZ][0-9])|([A-PR-UWYZ][0-9][0-9])|([A-PR-UWYZ][A-HK-Y][0-9])|([A-PR-UWYZ][A-HK-Y][0-9][0-9])|([A-PR-UWYZ][0-9][A-HJKSTUW])|([A-PR-UWYZ][A-HK-Y][0-9][ABEHMNPRVWXY]))\s?([0-9][ABD-HJLNP-UW-Z]{2})|(GIR)\s?(0AA))$/i.test(value);
                    },
                    "Please specify a valid postcode"
                );
                $("#pledge-form-2").validate({
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
                            postcode: true
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
                    }
                });
            });
        </script>
    @endif
    @if ($form_step === 3)

        <form id="pledge-form-3" class="prose" action="{{ route('pledge.donation') }}" method="POST">
            @csrf
            <input type="hidden" name="step" value="4" />

            <h2>Direct Debit</h2>

            <div class="form-page">
                <fieldset>
                    <legend class="th-s3">Direct Debit</legend>

                    <div class="tqRow">
                        <label for="dd_acc_name"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;Account Name</label><br>
                        <input class="input" name="dd_acc_name" type="text" maxlength="100" id="dd_acc_name" value="{{ session('dd_acc_name', '') }}">
                    </div>

                    <br>

                    <div class="tqRow">
                        <label for="dd_acc_sortcode"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;Sort Code</label><br>
                        <input class="input" name="dd_acc_sortcode" type="text" maxlength="10" id="dd_acc_sortcode" value="{{ session('dd_acc_sortcode', '') }}">
                    </div>

                    <br>

                    <div class="tqRow">
                        <label for="dd_acc_number"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;Account Number</label><br>
                        <input class="input" name="dd_acc_number" type="text" maxlength="26" id="dd_acc_number" value="{{ session('dd_acc_number', '') }}">
                    </div>

                    <br><br>

                    <input class="action my-4 mr-2" type="submit" value="Continue" />
                    <input type="hidden" id="prevClicked" name="prevClicked" value="false" />
			        <input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />
                </fieldset>
            </div>
        </form>

        <script>
            $(document).ready(function () {
                $("#pledge-form-3").validate({
                    rules: {
                        "dd_acc_name": {
                            required: true
                        },
                        "dd_acc_number": {
                            required: true
                        },
                        "dd_acc_sortcode": {
                            required: true
                        }
                    }
                });
            });
        </script>
    @endif
    @if ($form_step === 4)

        <form id="pledge-form-4" class="prose" action="{{ route('pledge.donation') }}" method="POST">
            @csrf
            <input type="hidden" name="step" value="5" />
            <input type="hidden" name="submitted" value="true" />

            <h2>Review</h2>

            <div class="form-page">
                <fieldset>
                    <legend class="th-s3">Your Pledge</legend>

                    <div class="tqRow">
                        <b>Pledge destination:</b> {{ $destinationName }}<br>
                        <b>Donation frequency:</b> {{ session('frequency') }}<br>
                        <b>Regular amount:</b> &pound;{{ session('amount') }}<br>
                        
                        @php
                            $start_date = date_create(session('start_date'));
                            echo '<b>Start date:</b> '.$start_date->format('d-m-Y').'<br>';
                        @endphp
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="th-s3 pt-4">Your details</legend>

                    <div class="tqRow">
                        <b>Title:</b> {{ session('title') }}<br>
                        <b>First name:</b> {{ session('firstname') }}<br>
                        <b>Surname:</b> {{ session('lastname') }}<br>
                        <b>Country:</b> {{ session('country') }}<br>
                        <b>Address:</b> {{ session('address') }}<br>
                        <b>Town:</b> {{ session('town') }}<br>
                        <b>County:</b> {{ session('county') }}<br>
                        <b>Postcode:</b> {{ session('postcode') }}<br>
                        <b>Email:</b> {{ session('email') }}<br>
                        <b>Daytime telephone:</b> {{ session('telephone_day') }}<br>
                        <b>Evening telephone:</b> {{ session('telephone_evening') }}<br>
                        <b>Mobile:</b> {{ session('mobile') }}<br><br>

                        {!! (session('contact_email', '') == '1') ? 'I do <b>not</b> want to be contacted by email' : 'I am <b>happy</b> for you to contact me by email' !!}
                        <br>
                        {!! (session('contact_post', '') == '1') ? 'I do <b>not</b> want to be contacted by telephone' : 'I am <b>happy</b> for you to contact me by telephone' !!}
                        <br>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="th-s3 pt-4">Gift Aid</legend>

                    <div class="tqRow">
                        <b>I want to create a Gift Aid declaration:</b> {{ (session('giftaid', '') == '1') ? 'Yes' : 'No' }}<br>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="th-s3 pt-4">Your Direct Debit</legend>

                    <div class="tqRow">
                        <b>Account name:</b> {{ session('dd_acc_name') }}<br>
                        <b>Account sort code:</b> {{ session('dd_acc_sortcode') }}<br>
                        <b>Account number:</b> {{ session('dd_acc_number') }}<br>
                    </div>
                </fieldset>

                <div class="tqRow">
                    <input class="action my-4 mr-2" type="submit" value="Submit" />
                    <input type="hidden" id="prevClicked" name="prevClicked" value="false" />
			        <input class="action action--secondary my-4" type="button" value="Previous" onclick="document.getElementById('prevClicked').value='true'; this.form.submit();" />
                </div>
            </div>
        </form>

    @endif
    @if ($form_step === 5)

        <form id="pledge-form-5" class="prose" action="{{ route('pledge.donation') }}" method="POST">
            @csrf

            @if(session('ddpledge_id'))
                <h2>Confirmation</h2>
            
                <div class="form-page">
                    <fieldset>
                        <legend class="th-s3">Thank you</legend>
                        <p>Your regular donation has been successfully set up.</p>
                        <p>Your pledge reference number is: <b>{{ $ddpledge_id }}</b></p>
                    </fieldset>
                </div>
                 @php
                    session()->flush();
                @endphp
            @else
                <div class="form-page">
                    <fieldset>
                        <legend class="th-s3">An error has occurred</legend>
                        <p>We were unable to set up your regular donation.</p>
                    </fieldset>
                </div>
        @endif

        </form>
    @endif


@endsection