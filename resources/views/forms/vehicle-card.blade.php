@extends('forms.layout')

@section('doc-title', 'Vehicle Data Card - ' . strtoupper($vehicle->plate_number))

@php
  $dash = fn ($value) => filled($value) ? $value : '—';
  $date = fn ($value) => $value ? $value->format('M d, Y') : '—';
  $person = fn ($p) => $p ? trim(implode(' ', array_filter([$p->rank, $p->firstname, $p->lastname]))) : null;
  $registration = $vehicle->latestRegistration;
  $statusLabels = ['SERVICEABLE' => 'Serviceable', 'UNSERVICEABLE' => 'Unserviceable', 'BER' => 'BER'];
  $status = $statusLabels[$vehicle->status] ?? $vehicle->status;
  if ($vehicle->status === 'BER' && $vehicle->ber_sub_status) {
      $status .= ' - ' . (\App\Models\Vehicle::BER_SUB_STATUSES[$vehicle->ber_sub_status] ?? $vehicle->ber_sub_status);
  }
@endphp

@section('pages')
<div class="page">
  @include('forms._letterhead')
  <div class="form-title">Vehicle Data Card</div>
  <div class="form-sub">{{ strtoupper($vehicle->plate_number) }} &middot; {{ trim($vehicle->make . ' ' . $vehicle->model) }}</div>

  <div style="display:flex; gap:14px; align-items:flex-start;">
    <div style="flex:1;">
      <div class="part-title">Vehicle</div>
      <table class="grid">
        <tr><td class="label">Plate No.</td><td>{{ strtoupper($vehicle->plate_number) }}</td></tr>
        <tr><td class="label">Make / Model</td><td>{{ trim($vehicle->make . ' ' . $vehicle->model) }}</td></tr>
        <tr><td class="label">Type</td><td>{{ $dash(optional($vehicle->type)->name) }}</td></tr>
        <tr><td class="label">Year / Color</td><td>{{ $dash($vehicle->year_model) }} / {{ $dash($vehicle->color) }}</td></tr>
        <tr><td class="label">Engine No.</td><td>{{ $dash($vehicle->engine_number) }}</td></tr>
        <tr><td class="label">Chassis No.</td><td>{{ $dash($vehicle->chassis_number) }}</td></tr>
        <tr><td class="label">Acquired</td><td>{{ $date($vehicle->acquisition_date) }}</td></tr>
        <tr><td class="label">Status</td><td>{{ $status }}</td></tr>
        <tr><td class="label">Odometer</td><td>{{ number_format((int) $vehicle->odometer_km) }} km</td></tr>
      </table>
    </div>
    <div class="qr" style="width:44mm;">
      <img src="{{ route('vehicles.qr-image', $vehicle) }}" alt="QR code">
      <div class="box-label">Scan for live record</div>
    </div>
  </div>

  <div class="part-title">Assignment</div>
  <table class="grid">
    <tr><td class="label">Unit</td><td>{{ $dash(optional($vehicle->unit)->unit_name) }}</td><td class="label">Station</td><td>{{ $dash(optional($vehicle->station)->station_name) }}</td></tr>
    <tr><td class="label">Assigned driver</td><td>{{ $dash($person($vehicle->driver)) }}</td><td class="label">Second driver</td><td>{{ $dash($person($vehicle->driver2)) }}</td></tr>
  </table>

  <div class="part-title">Registration and Maintenance Schedule</div>
  <table class="grid">
    <tr><td class="label">Registration year</td><td>{{ $dash(optional($registration)->registration_year) }}</td><td class="label">Registration expiry</td><td>{{ $date(optional($registration)->expiry_date) }}</td></tr>
    <tr><td class="label">Next PMS date</td><td>{{ $date($vehicle->next_pms_date) }}</td><td class="label">Next PMS odometer</td><td>{{ $vehicle->next_pms_odometer_km ? number_format((int) $vehicle->next_pms_odometer_km) . ' km' : '—' }}</td></tr>
  </table>

  <div class="part-title">Recent Completed Service (last 5)</div>
  <table class="grid">
    <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Performed by</th><th class="num">Odometer</th><th class="num">Cost (₱)</th></tr></thead>
    <tbody>
      @forelse($services as $service)
        <tr>
          <td>{{ $date($service->service_date) }}</td>
          <td>{{ $typeLabels[$service->maintenance_type] ?? $service->maintenance_type }}</td>
          <td>{{ $service->description }}</td>
          <td>{{ $service->performed_by }}</td>
          <td class="num">{{ $service->odometer_km ? number_format((int) $service->odometer_km) : '' }}</td>
          <td class="num">{{ $service->cost !== null ? number_format((float) $service->cost, 2) : '' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;color:#64748b;">No completed service on record.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer-note"><span>{{ strtoupper($vehicle->plate_number) }}</span><span>Printed {{ now()->format('M d, Y h:i A') }} &middot; VMIS</span></div>
</div>
@endsection
