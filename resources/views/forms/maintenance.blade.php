@extends('forms.layout')

@section('doc-title', 'Forms - ' . ($record->control_number ?: 'Record ' . $record->id))

@php
  $plate = strtoupper($vehicle->plate_number);
  $driver = $vehicle->driver;
  $driverName = $driver ? trim(implode(' ', array_filter([$driver->rank, $driver->firstname, $driver->lastname]))) : null;
  $dash = fn ($value) => filled($value) ? $value : '—';
  $date = fn ($value) => $value ? $value->format('M d, Y') : '—';
  $checked = fn ($on) => $on ? '☒' : '☐';
  $completed = $record->stage === \App\Models\MaintenanceRecord::STAGE_COMPLETED;
@endphp

@section('pages')

@if($show['service-request'])
<div class="page">
  @include('forms._letterhead')
  <div class="form-title">Motorpool Service Request Form</div>
  <div class="form-sub">Control No. {{ $dash($record->control_number) }} &middot; Status: {{ $stageLabels[$record->stage] ?? $record->stage }}</div>

  <div class="part-title">Part I &mdash; Vehicle and Request</div>
  <table class="grid">
    <tr><td class="label">Plate No.</td><td>{{ $plate }}</td><td class="label">Make / Model</td><td>{{ trim($vehicle->make . ' ' . $vehicle->model) }}</td></tr>
    <tr><td class="label">Vehicle type</td><td>{{ $dash(optional($vehicle->type)->name) }}</td><td class="label">Year model</td><td>{{ $dash($vehicle->year_model) }}</td></tr>
    <tr><td class="label">Unit</td><td>{{ $dash(optional($vehicle->unit)->unit_name) }}</td><td class="label">Station</td><td>{{ $dash(optional($vehicle->station)->station_name) }}</td></tr>
    <tr><td class="label">Driver / custodian</td><td>{{ $dash($driverName) }}</td><td class="label">Current odometer</td><td>{{ number_format((int) $vehicle->odometer_km) }} km</td></tr>
    <tr><td class="label">Date of request</td><td>{{ $date($record->request_date ?? $record->service_date) }}</td><td class="label">Record type</td><td>{{ $typeLabels[$record->maintenance_type] ?? $record->maintenance_type }}</td></tr>
  </table>

  <div class="box-label">Nature of request</div>
  <div class="checks">
    @foreach($natureOptions as $key => $label)
      <span>{{ $checked($record->nature_of_request === $key) }} {{ $label }}</span>
    @endforeach
  </div>

  <div class="box-label">Description / concern</div>
  <div class="box">{{ $record->description }}</div>

  <div class="part-title">Part II &mdash; Request, Recommendation and Approval</div>
  <div class="sign-row">
    <div class="sign"><div class="line">{{ $record->requested_by }}</div><div class="cap">Requested by</div></div>
    <div class="sign"><div class="line">{{ $record->recommended_by }}</div><div class="cap">Recommending approval</div></div>
    <div class="sign"><div class="line">{{ $record->approved_by }}</div><div class="cap">Approved by</div></div>
  </div>

  <div class="part-title" style="margin-top:16px;">Part III &mdash; Inspection Result</div>
  <table class="grid">
    <tr>
      <td class="label">Technical inspection</td>
      <td>{{ $inspection ? 'Done on ' . $date($inspection->inspection_date) . ' by ' . $dash($inspection->inspected_by) : 'Not yet recorded' }}</td>
      <td class="label">Parts needed</td>
      <td>{{ $checked($record->parts_needed === true) }} Yes &nbsp; {{ $checked($record->parts_needed === false) }} No</td>
    </tr>
  </table>

  <div class="part-title">Part IV &mdash; Certification of Completion</div>
  <table class="grid">
    <tr><td class="label">Date completed</td><td>{{ $completed ? $date($record->service_date) : '' }}</td><td class="label">Odometer at service</td><td>{{ $completed && $record->odometer_km ? number_format((int) $record->odometer_km) . ' km' : '' }}</td></tr>
    <tr><td class="label">Work performed by</td><td>{{ $completed ? $record->performed_by : '' }}</td><td class="label">Total cost</td><td>{{ $completed && $record->cost !== null ? '₱' . number_format((float) $record->cost, 2) : '' }}</td></tr>
  </table>
  <div class="sign-row">
    <div class="sign"><div class="line">{{ $completed ? $record->performed_by : '' }}</div><div class="cap">Service provider / mechanic</div></div>
    <div class="sign"><div class="line">{{ $completed ? $record->received_by : '' }}</div><div class="cap">Received by (driver / custodian)</div></div>
  </div>

  <div class="footer-note"><span>{{ $plate }} &middot; {{ $dash($record->control_number) }}</span><span>Printed {{ now()->format('M d, Y h:i A') }} &middot; VMIS</span></div>
</div>
@endif

@if($show['inspection'])
<div class="page">
  @include('forms._letterhead')
  <div class="form-title">Technical Inspection Report</div>
  <div class="form-sub">Control No. {{ $dash($record->control_number) }}</div>

  <table class="grid">
    <tr><td class="label">Plate No.</td><td>{{ $plate }}</td><td class="label">Make / Model</td><td>{{ trim($vehicle->make . ' ' . $vehicle->model) }}</td></tr>
    <tr><td class="label">Unit</td><td>{{ $dash(optional($vehicle->unit)->unit_name) }}</td><td class="label">Date of inspection</td><td>{{ $date(optional($inspection)->inspection_date) }}</td></tr>
  </table>

  <div class="legend">
    <strong>Status legend:</strong>
    @foreach($statusLegend as $code => $label) {{ $code }} = {{ $label }}@if(! $loop->last); @endif @endforeach
  </div>

  <div class="two-col">
    @foreach($checklist as $systemKey => $system)
      <div class="sys">
        <h4>{{ $system['label'] }}</h4>
        <table>
          @foreach($system['components'] as $component)
            @php $item = $inspectionItems[$systemKey . "\x1F" . $component] ?? null; @endphp
            <tr>
              <td>{{ $component }}@if($item && $item->remarks) <em>&mdash; {{ $item->remarks }}</em>@endif</td>
              <td class="st">{{ $item ? $item->status : '' }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    @endforeach
  </div>

  <div class="box-label">Findings and recommendation</div>
  <div class="box">{{ optional($inspection)->findings_recommendation }}</div>

  <div class="sign-row">
    <div class="sign"><div class="line">{{ optional($inspection)->inspected_by }}</div><div class="cap">Inspected by</div></div>
    <div class="sign"><div class="line">{{ optional($inspection)->witness }}</div><div class="cap">Witness</div></div>
  </div>

  <div class="footer-note"><span>{{ $plate }} &middot; {{ $dash($record->control_number) }}</span><span>Printed {{ now()->format('M d, Y h:i A') }} &middot; VMIS</span></div>
</div>
@endif

@if($show['requisition'])
@php
  $req = $requisition;
  $lines = $req ? $req->items : collect();
  $blankRows = max(0, 12 - $lines->count());
@endphp
<div class="page">
  @include('forms._letterhead')
  <div class="form-title">Vehicle Repair Requisition Slip</div>
  <div class="form-sub">Requisition No. {{ $dash(optional($req)->requisition_no) }} &middot; Control No. {{ $dash($record->control_number) }}</div>

  <table class="grid">
    <tr><td class="label">Date</td><td>{{ $date(optional($req)->requisition_date) }}</td><td class="label">Office / unit</td><td>{{ $dash(optional($req)->office_unit ?: optional($vehicle->unit)->unit_name) }}</td></tr>
    <tr><td class="label">Plate No.</td><td>{{ $plate }}</td><td class="label">Make / Model</td><td>{{ trim($vehicle->make . ' ' . $vehicle->model) }}</td></tr>
    <tr><td class="label">Driver / custodian</td><td>{{ $dash(optional($req)->driver_custodian ?: $driverName) }}</td><td class="label">Current mileage</td><td>{{ optional($req)->current_mileage ? number_format((int) $req->current_mileage) . ' km' : '—' }}</td></tr>
  </table>

  <div class="box-label">Reason for request</div>
  <div class="box">{{ optional($req)->reason_for_request }}</div>
  <div class="box-label">Findings / diagnosis</div>
  <div class="box">{{ optional($req)->findings_diagnosis }}</div>

  <table class="grid">
    <thead><tr><th style="width:60px;" class="num">Qty</th><th>Particulars</th><th style="width:110px;" class="num">Price (₱)</th></tr></thead>
    <tbody>
      @foreach($lines as $line)
        <tr><td class="num">{{ $line->qty }}</td><td>{{ $line->particulars }}</td><td class="num">{{ $line->price !== null ? number_format((float) $line->price, 2) : '' }}</td></tr>
      @endforeach
      @for($i = 0; $i < $blankRows; $i++)
        <tr><td>&nbsp;</td><td></td><td></td></tr>
      @endfor
      <tr><td></td><td class="num"><strong>TOTAL</strong></td><td class="num"><strong>{{ $req ? number_format($req->totalPrice(), 2) : '' }}</strong></td></tr>
    </tbody>
  </table>

  <div class="sign-row">
    <div class="sign"><div class="line">{{ optional($req)->requested_by }}</div><div class="cap">Requested by</div></div>
    <div class="sign"><div class="line">{{ optional($req)->inspected_by }}</div><div class="cap">Inspected by</div></div>
  </div>

  <div class="part-title" style="margin-top:16px;">Repair conducted</div>
  <div class="box">{{ optional($req)->repair_conducted }}</div>
  <div class="sign-row">
    <div class="sign"><div class="line">{{ optional($req)->mechanics_in_charge }}</div><div class="cap">Mechanic(s) in charge</div></div>
  </div>

  <div class="footer-note"><span>{{ $plate }} &middot; {{ $dash($record->control_number) }}</span><span>Printed {{ now()->format('M d, Y h:i A') }} &middot; VMIS</span></div>
</div>
@endif

@endsection
