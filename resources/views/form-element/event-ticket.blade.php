<fieldset>
    @php
        $ticketDisplayTitle = session($tickettype.'TicketsDisplayTitle');
        $ticketCost = session($tickettype.'TicketsCost');
    @endphp

    <legend class="th-s3">{{ $ticketDisplayTitle }} Attendee {{ ($i+1) }}</legend>

    <input type="hidden" name="bookingid[{{ $attendeeFormNo }}]" value="{{ $tickettype }}" />
    <input type="hidden" name="bookingtype[{{ $attendeeFormNo }}]" value="{{ $ticketDisplayTitle }}" />
    <input type="hidden" name="bookingcost[{{ $attendeeFormNo }}]" value="{{ $ticketCost }}" />

    @if ($i == 0)
        <div class="checkbox">
            <input id="firstattendeeisbooker" class="checkbox__input" type="checkbox" name="firstattendeeisbooker" value="1" style="display: inline-block" {{ (isset($firstattendeeisbooker) && $firstattendeeisbooker == 1) ? ' checked' : '' }}>
            <label class="checkbox__label" for="firstattendeeisbooker">This attendee is also making the booking</label>
        </div>
    @endif

    @php
        $multiform = true;
    @endphp

    @include('form-element.title')

    <label for="firstname" class="label field__label mt-2"><span class="esitAsterisk" title="Compulsory field">*</span>&nbsp;First name</label>
    <input type="text" class="input" id="firstname" maxlength="50" name="attendeefirstname[{{ $attendeeFormNo }}]" required value="{{ old('attendeefirstname.' . $attendeeFormNo) }}">

    <label for="lastname" class="label field__label mt-2"><span class="esitAsterisk" title="Compulsory field">*</span>&nbsp;Surname</label>
    <input type="text" class="input" id="lastname" maxlength="100" name="attendeesurname[{{ $attendeeFormNo }}]" required value="{{ old('attendeesurname.' . $attendeeFormNo) }}">

    <label for="dietary" class="label field__label mt-4">Dietary requirements</label>
    <select id="dietary" class="no-sort select" name="attendeedietary[{{ $attendeeFormNo }}]">
        @php
            $dietaryTypes = ["None", "Diabetic", "Gluten-free", "Halal", "Kosher", "No beef", "No dairy", "No fish", "No pork", "No shellfish", "Nut Allergy", "Other (please state in additional needs)", "Pescatarian", "Vegan", "Vegetarian", "Wheat Allergy"];
        @endphp
        @foreach ($dietaryTypes as $dietaryType)
            @php
                $dietaryTypeSelected = '';
                if (old('attendeedietary.' . $attendeeFormNo) === $dietaryType) {
                    $dietaryTypeSelected = 'selected';
                }
            @endphp
            <option value="{{ $dietaryType }}" {{ $dietaryTypeSelected }}>{{ $dietaryType }}</option>
        @endforeach
    </select>

    <label for="special" class="label field__label mt-2">Special requirements</label>
    <textarea rows="3" cols="30" class="textarea mb-4" name="attendeespecialrequirements[{{ $attendeeFormNo }}]">{{ old('attendeespecialrequirements.' . $attendeeFormNo) }}</textarea>
</fieldset>
