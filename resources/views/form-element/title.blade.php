@php
    $multielement = '';

    if ($multiform == true) {
        if (isset($attendeeFormNo)) {
            $multielement = '['.$attendeeFormNo.']';
        } else {
            $multielement = '['.$i.']';
        }

        if (session()->has('booking')) {
            $title_session = session('booking.title.'.$attendeeFormNo, '');
        } else {
            if (session()->has('title.'.$i)) {
                $title_session = session('title.'.$i);
            }
        }
    } else {
        if (session()->has('title')) {
            $title_session = session('title');
        }
    }
@endphp

<div class="person-title">
    <label>* Title</label>

    <div class="element">
        <input id="title" type="radio" name="title{{ $multielement }}" value="Mr"{{ (isset($title_session) && $title_session == 'Mr') ? ' checked' : '' }}>
        <label for="title">Mr</label>
    </div>

    <div class="element">
        <input id="title" type="radio" name="title{{ $multielement }}" value="Mrs"{{ (isset($title_session) && $title_session == 'Mrs') ? ' checked' : '' }}>
        <label for="title">Mrs</label>
    </div>

    <div class="element">
        <input id="title" type="radio" name="title{{ $multielement }}" value="Ms"{{ (isset($title_session) && $title_session == 'Ms') ? ' checked' : '' }}>
        <label for="title">Ms</label>
    </div>

    <div class="element">
        <input id="title" type="radio" name="title{{ $multielement }}" value="Miss"{{ (isset($title_session) && $title_session == 'Miss') ? ' checked' : '' }}>
        <label for="title">Miss</label>
    </div>

    <div class="element">
        <input id="title" type="radio" name="title{{ $multielement }}" value="Dr"{{ (isset($title_session) && $title_session == 'Dr') ? ' checked' : '' }}>
        <label for="title">Dr</label>
    </div>

    <div class="element" id="data-show-control">
        <input id="title" type="radio" name="title{{ $multielement }}" value="Other"{{ (isset($title_session) && !in_array($title_session, ['Mr','Mrs','Ms','Miss','Dr'])) ? ' checked' : '' }}>
        <label for="title">Other</label>
    </div>

    <div class="element block" id="data-reveal">
        <label for="title_other">Other – please specify</label>
        <input name="title_other{{ $multielement }}" type="text" maxlength="30" id="title_other" class="SSAfreeAmount" value="{{ (isset($title_session) && !in_array($title_session, ['Mr','Mrs','Ms','Miss','Dr'])) ? $title_session : '' }}">
    </div>
</div>
