@extends('layout.app')
@section('title', 'Current events - ')
@section('content')

<header class="main-header prose mb-4">
  <h1>Current events</h1>
</header>

<div class="dataTables_wrapper mb-10" id="example_wrapper">
  <table class="display" id="example">
    <thead>
      <tr>
        <th class="date sorting_disabled" style="width: 109px;">Date</th>
        <th class="time hideColumn sorting_disabled" style="width: 58px;">Time</th>
        <th class="title sorting_disabled" style="width: 202px;">Title</th>
        <!-- <th class="venue hideColumn sorting_disabled" style="width: 301px;">Venue</th> -->
      </tr>
    </thead>
    <tbody>
     
      @foreach($events as $item)
        @if ($item['publishToWeb'] == true)
          @php
            if (isset($item['publishOnWebUntil']) && DateTime::createFromFormat('Y-m-d\TH:i:s', $item['publishOnWebUntil']) !== false) {
              $publishUntil = DateTime::createFromFormat('Y-m-d\TH:i:s', $item['publishOnWebUntil']);
              $dateNow = new DateTime('now');
              if ($publishUntil > $dateNow) { 
            @endphp
                  <tr>
                    <td class="date" style="width: 120px;">
                      <div class="eventsItemDate">
                        <span style="display: none;">{{ date("Ymd", strtotime($item['startDate'])) }}</span>
                        {{ date("j F Y", strtotime($item['startDate'])) }}
                      </div>
                    </td>
                    <td class="time hideColumn" style="width: 70px;">
                      {{ date("g:i a", strtotime($item['startTime'])) }}
                    </td>
                    <td class="title">
                      <a href="{{ route('event.process', ['eventID' => $item['eventId']]) }}">
                        {{ $item['eventName'] }}
                      </a>
                    </td>
                    <!-- <td class="$class venue hideColumn">
                      {{ $item['webDescription'] }}
                    </td> -->
                  </tr>
            @php
              }
            }
          @endphp
        @endif
      @endforeach
    </tbody>
  </table>
</div>

<script type="text/javascript">
  $(document).ready(function() {
    $('#example').DataTable({
      "order": [[0, "asc"]]
    });
  });
</script>

@endsection
