@extends('layout.master')

@section('title')
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VMIS | Reports</title>
<link href="{{ asset('dist/img/kasurog.png') }}" rel="icon">
@endsection

@section('css')
<style>
  .fleet-card-container { background: #ffffff; border-radius: 16px; border: 1px solid #eef1f6; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); overflow: hidden; margin-bottom: 22px; }
  .toolbar-header { padding: 20px 24px; border-bottom: 1px solid #eef1f6; }
  .toolbar-title h5 { font-weight: 800; color: #0f172a; margin: 0; font-size: 16px; }
  .toolbar-title p { font-size: 12px; color: #94a3b8; margin: 2px 0 0; }

  .report-filters { padding: 20px 24px; display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; }
  @media (max-width: 1199px) { .report-filters { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 767px) { .report-filters { grid-template-columns: 1fr; } }
  .report-filters label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 5px; }
  .report-filters .form-control { height: 38px; border-radius: 9px; border: 1.5px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #334155; }

  .report-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
  .report-card { background: #fff; border: 1px solid #eef1f6; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); }
  .report-card-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(59, 130, 246, 0.12); color: #3b82f6; font-size: 17px; margin-bottom: 12px; }
  .report-card h6 { font-weight: 800; color: #0f172a; margin: 0 0 6px; font-size: 15px; }
  .report-card p { font-size: 12.5px; color: #64748b; margin: 0 0 14px; flex-grow: 1; }
  .report-card .date-note { font-size: 11px; color: #94a3b8; margin-bottom: 12px; }
  .report-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .btn-report { border-radius: 9px; font-weight: 700; font-size: 12.5px; padding: 7px 14px; border: none; color: #fff; }
  .btn-report-print { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
  .btn-report-csv { background: linear-gradient(135deg, #22c55e, #15803d); }
  .btn-report:hover { color: #fff; opacity: .92; }
  .pad { padding: 20px 24px 4px; }
</style>
@endsection

@section('nav-title', 'VMIS | Reports')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">

        <div class="fleet-card-container">
            <div class="toolbar-header">
                <div class="toolbar-title">
                    <h5>Report Filters</h5>
                    <p>Applied to whichever report you open below. Results only include vehicles you are allowed to see.</p>
                </div>
            </div>
            <div class="report-filters">
                <div>
                    <label for="rfUnit">Unit</label>
                    <select id="rfUnit" class="form-control">
                        <option value="">All units</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->unit_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rfStation">Station</label>
                    <select id="rfStation" class="form-control">
                        <option value="">All stations</option>
                        @foreach($stations as $station)
                            <option value="{{ $station->id }}" data-unit="{{ $station->unit_id }}">{{ $station->station_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rfStatus">Vehicle status</label>
                    <select id="rfStatus" class="form-control">
                        <option value="">All statuses</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rfDateFrom">Date from</label>
                    <input type="date" id="rfDateFrom" class="form-control">
                </div>
                <div>
                    <label for="rfDateTo">Date to</label>
                    <input type="date" id="rfDateTo" class="form-control">
                </div>
                <div>
                    <label for="rfType">Repair / maintenance type</label>
                    <select id="rfType" class="form-control">
                        <option value="">All types</option>
                        @foreach($maintenanceTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="report-grid">
            @foreach($reports as $slug => $report)
                <div class="report-card">
                    <div class="report-card-icon"><i class="fas {{ $report['icon'] }}"></i></div>
                    <h6>{{ $report['title'] }}</h6>
                    <p>{{ $report['description'] }}</p>
                    <div class="date-note">
                        @if($report['dates'])
                            Uses the date range{{ $slug === 'maintenance-history' ? ' and type' : '' }} above.
                        @else
                            Current snapshot; the date range does not apply.
                        @endif
                    </div>
                    <div class="report-actions">
                        <a href="#" class="btn btn-report btn-report-print report-open" data-report="{{ $slug }}" data-format="html" data-dates="{{ $report['dates'] ? 1 : 0 }}" data-type="{{ $slug === 'maintenance-history' ? 1 : 0 }}" target="_blank" rel="noopener">
                            <i class="fas fa-print mr-1"></i> View / Print (PDF)
                        </a>
                        <a href="#" class="btn btn-report btn-report-csv report-open" data-report="{{ $slug }}" data-format="csv" data-dates="{{ $report['dates'] ? 1 : 0 }}" data-type="{{ $slug === 'maintenance-history' ? 1 : 0 }}">
                            <i class="fas fa-file-excel mr-1"></i> Download Excel (CSV)
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>
@endsection

@section('script')
<script>
$(function () {
    const baseUrl = "{{ route('reports.index') }}";

    // Station list follows the chosen unit.
    $('#rfUnit').on('change', function () {
        const unitId = $(this).val();
        $('#rfStation option').each(function () {
            const optionUnit = $(this).data('unit');
            const show = !this.value || !unitId || String(optionUnit) === String(unitId);
            $(this).prop('hidden', !show).prop('disabled', !show);
        });
        $('#rfStation').val('');
    });

    $('.report-open').on('click', function (event) {
        const link = $(this);
        const params = new URLSearchParams();

        const add = (key, value) => { if (value) { params.set(key, value); } };
        add('unit_id', $('#rfUnit').val());
        add('station_id', $('#rfStation').val());
        add('status', $('#rfStatus').val());

        if (String(link.data('dates')) === '1') {
            add('date_from', $('#rfDateFrom').val());
            add('date_to', $('#rfDateTo').val());
        }
        if (String(link.data('type')) === '1') {
            add('maintenance_type', $('#rfType').val());
        }
        params.set('format', link.data('format'));

        link.attr('href', baseUrl + '/' + link.data('report') + '?' + params.toString());
        // Let the default navigation proceed with the freshly built href.
    });
});
</script>
@endsection
