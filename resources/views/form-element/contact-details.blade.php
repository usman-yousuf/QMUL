<h2>Your contact details</h2>

<fieldset>
  <legend>Your contact details</legend>

  @php
    $multiform = false;
  @endphp

  @include('form-element.title')

  <label for="firstname" class="label field__label mt-2"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;First name</label>
  <input type="text" class="input" id="firstname" name="firstname" maxlength="50" value="{{ session('firstname') ?? '' }}">

  <label for="lastname" class="label field__label mt-2"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;Surname</label>
  <input type="text" class="input" id="lastname" name="lastname" maxlength="100" value="{{ session('lastname') ?? '' }}">

  <div class="tqRow">
    <label class="label field__label mt-2" for="country"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;Country</label>

    <select  id="country" name="country" class="input-small no-sort select">
      <option disabled>Please select an option</option>
		  @php
        $countries = array("Afghanistan", "Albania", "Algeria", "American Samoa", "Andorra", "Angola", "Anguilla", "Antarctica", "Antigua and Barbuda", "Argentina", "Armenia", "Aruba", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bermuda", "Bhutan", "Bolivia", "Bosnia and Herzegowina", "Botswana", "Bouvet Island", "Brazil", "British Indian Ocean Territory", "Brunei Darussalam", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Cayman Islands", "Central African Republic", "Chad", "Chile", "China", "Christmas Island", "Cocos (Keeling) Islands", "Colombia", "Comoros", "Congo", "Congo, the Democratic Republic of the", "Cook Islands", "Costa Rica", "Cote d'Ivoire", "Croatia (Hrvatska)", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "East Timor", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", "Ethiopia", "Falkland Islands (Malvinas)", "Faroe Islands", "Fiji", "Finland", "France", "France Metropolitan", "French Guiana", "French Polynesia", "French Southern Territories", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Gibraltar", "Greece", "Greenland", "Grenada", "Guadeloupe", "Guam", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Heard and Mc Donald Islands", "Holy See (Vatican City State)", "Honduras", "Hong Kong", "Hungary", "Iceland", "India", "Indonesia", "Iran (Islamic Republic of)", "Iraq", "Ireland", "Israel", "Italy", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Korea, Democratic People's Republic of", "Korea, Republic of", "Kuwait", "Kyrgyzstan", "Lao, People's Democratic Republic", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libyan Arab Jamahiriya", "Liechtenstein", "Lithuania", "Luxembourg", "Macau", "Macedonia, The Former Yugoslav Republic of", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Martinique", "Mauritania", "Mauritius", "Mayotte", "Mexico", "Micronesia, Federated States of", "Moldova, Republic of", "Monaco", "Mongolia", "Montserrat", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "Netherlands Antilles", "New Caledonia", "New Zealand", "Nicaragua", "Niger", "Nigeria", "Niue", "Norfolk Island", "Northern Mariana Islands", "Norway", "Oman", "Pakistan", "Palau", "Panama", "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Pitcairn", "Poland", "Portugal", "Puerto Rico", "Qatar", "Reunion", "Romania", "Russian Federation", "Rwanda", "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", "Seychelles", "Sierra Leone", "Singapore", "Slovakia (Slovak Republic)", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Georgia and the South Sandwich Islands", "Spain", "Sri Lanka", "St. Helena", "St. Pierre and Miquelon", "Sudan", "Suriname", "Svalbard and Jan Mayen Islands", "Swaziland", "Sweden", "Switzerland", "Syrian Arab Republic", "Taiwan, Province of China", "Tajikistan", "Tanzania, United Republic of", "Thailand", "Togo", "Tokelau", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Turks and Caicos Islands", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States", "United States Minor Outlying Islands", "Uruguay", "Uzbekistan", "Vanuatu", "Venezuela", "Vietnam", "Virgin Islands (British)", "Virgin Islands (U.S.)", "Wallis and Futuna Islands", "Western Sahara", "Yemen", "Yugoslavia", "Zambia", "Zimbabwe");
      @endphp
      @foreach ($countries as $country)
        @php
          $country_selected = '';
          if (session('country')) {
            if ($country == session('country')) {
              $country_selected = ' selected';
            }
          } else {
            if ($country == 'United Kingdom') {
              $country_selected = ' selected';
            }
          }
        @endphp
        <option value="{{ $country }}"{{ $country_selected }}>{{ $country }}</option>
      @endforeach
    </select>
  </div>

  <label for="address" class="label field__label mt-4">* Address</label>
  <textarea id="address" class="input" name="address" rows="3" cols="20">{{ session('address') ?? '' }}</textarea>

  <label for="town" class="label field__label mt-2">* Town / City</label>
  <input type="text" class="input" id="town" name="town" maxlength="100" value="{{ session('town') ?? '' }}">
  
  <label for="county" class="label field__label mt-2">County</label>
  <input type="text" class="input" id="county" name="county" maxlength="100" value="{{ session('county') ?? '' }}">
  
  <label for="postcode" class="label field__label mt-2">* Postcode</label>
  <input type="text" class="input" id="postcode" name="postcode" maxlength="10" value="{{ session('postcode') ?? '' }}">

  <label for="email" class="label field__label mt-4"><span class="esitAsterisk" title="Complusory field">*</span>&nbsp;Email address</label>
  <input type="text" class="input" id="email" name="email" maxlength="100" value="{{ session('email') ?? '' }}">

  <label for="email_confirm" class="label field__label mt-2">* Confirm email address</label>
  <input type="text" class="input" id="email_confirm" name="email_confirm" maxlength="100" value="{{ session('email_confirm') ?? '' }}">

  <label for="telephone_day" class="label field__label mt-4">Telephone (day)</label>
  <input type="text" class="input" id="telephone_day" name="telephone_day" maxlength="30" value="{{ session('telephone_day') ?? '' }}">

  <label for="telephone_evening" class="label field__label mt-2">Telephone (evening)</label>
  <input type="text" class="input" id="telephone_evening" name="telephone_evening" maxlength="30" value="{{ session('telephone_evening') ?? '' }}">

  <label for="mobile" class="label field__label mt-2">Mobile number</label>
  <input type="text" class="input" id="mobile" name="mobile" maxlength="30" value="{{ session('mobile') ?? '' }}">

  <legend class="th-s3 pt-4">Your communications preferences</legend>
  <p>We seek to engage with alumni, students, and supporters about our activities, including events, benefits, services and furthering Queen Mary University of London’s educational and charitable mission. We may, therefore, contact you by email or telephone with your consent. </p>

  <p>Please review the following statements and tick those that apply:</p>

  <legend class="label field__label mt-2"><span class="esitAsterisk" title="Complusory field">*</span> I am happy to be emailed by Queen Mary</legend>
  <input type="radio" id="contact_email_yes" name="contact_email" value="0"> <label for="contact_email_yes" class="ml-1 mr-4">Yes</label>
  <input type="radio" id="contact_email_no" name="contact_email" value="1"> <label for="contact_email_no" class="ml-1">No</label>

  <legend class="label field__label mt-2"><span class="esitAsterisk" title="Complusory field">*</span> I am happy to be telephoned by Queen Mary</legend>
  <input type="radio" id="contact_post_yes" name="contact_post" value="0"> <label for="contact_post_yes" class="ml-1 mr-4">Yes</label>
  <input type="radio" id="contact_post_no" name="contact_post" value="1"> <label for="contact_post_no" class="ml-1">No</label>

  
</fieldset>  
