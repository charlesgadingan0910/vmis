{{--
    Digitized Technical Inspection Report checklist — shared by Maintenance & PMS
    and Repairs (@include'd once from each module's index.blade.php) so the huge
    checklist markup below only has to be written once. See
    App\Models\TechnicalInspection for the CHECKLIST/STATUSES source of truth and
    App\Http\Controllers\TechnicalInspectionController for the AJAX endpoints this
    modal talks to. Opened via a "Technical Inspection Checklist" row button that
    both MaintenanceController::index() and RepairController::index() add next to
    the existing PDF-view buttons — available to every role (including Viewer,
    read-only) since it now holds real per-part history, not just an attachment.

    Styling is scoped to #technicalInspectionModal / .ti-* classes and defined
    inline here (rather than relying on the including page's own <style> block)
    so this partial renders correctly on its own wherever it's included.
--}}
@php
    // One icon per system, purely cosmetic — all drawn from Font Awesome 5's
    // free solid set (same set already used elsewhere in this app) so nothing
    // new needs to be loaded.
    $tiSystemIcons = [
        'ENGINE_ASSEMBLY'     => 'fa-cog',
        'COOLING_SYSTEM'      => 'fa-snowflake',
        'AC_UNIT'             => 'fa-wind',
        'CHASSIS_SUSPENSION'  => 'fa-car',
        'TRANSMISSION'        => 'fa-cogs',
        'ELECTRIC_SYSTEM'     => 'fa-bolt',
        'FUEL_SYSTEM'         => 'fa-gas-pump',
        'INTERIOR_PARTS'      => 'fa-couch',
        'EXTERNAL_PARTS'      => 'fa-car-side',
        'TIRES_BRAKES'        => 'fa-compact-disc',
    ];
@endphp
<style>
    #technicalInspectionModal .modal-content { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
    #technicalInspectionModal .modal-header { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 20px 24px; border-bottom: none; align-items: flex-start; }
    #technicalInspectionModal .modal-header .modal-title { font-weight: 800; font-size: 17px; margin: 0; }
    #technicalInspectionModal .ti-header-sub { font-size: 12.5px; color: #94a3b8; font-weight: 600; margin-top: 4px; }
    #technicalInspectionModal .modal-header .close { color: #fff; opacity: .75; text-shadow: none; margin-top: -2px; }
    #technicalInspectionModal .modal-header .close:hover { opacity: 1; }
    /* The header/body/footer aren't direct children of .modal-content here — the
       body+footer sit inside <form id="technicalInspectionForm"> so the whole thing
       posts as one AJAX payload. Bootstrap's .modal-dialog-scrollable only makes
       .modal-body scroll when it's a direct flex child of .modal-content; with the
       <form> (a plain block element) in between, that flex sizing never reaches
       .modal-body, so it grows to its full ~130-row height and everything past the
       modal's visible area — including the Save button — becomes unreachable, with
       no scrolling possible. Making the form itself a flex column (and giving the
       body a real flex-basis to shrink from via min-height: 0) restores the intended
       "sticky header/footer, scrollable body" behavior.  */
    #technicalInspectionModal form#technicalInspectionForm { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #technicalInspectionModal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 24px; background: #fbfcfe; }
    #technicalInspectionModal .modal-footer { flex: 0 0 auto; }
    #technicalInspectionModal .field-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin-bottom: 6px; display: block; }
    #technicalInspectionModal .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px; color: #0f172a; }
    #technicalInspectionModal .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
    #technicalInspectionModal textarea.form-control-modern { height: auto; }

    .ti-summary-bar { display: flex; align-items: center; gap: 22px; background: #fff; border: 1px solid #eef1f6; border-radius: 12px; padding: 14px 20px; margin: 4px 0 18px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(15,23,42,0.04); }
    .ti-summary-stat { display: flex; flex-direction: column; min-width: 64px; }
    .ti-summary-stat .ti-stat-num { font-size: 19px; font-weight: 800; color: #0f172a; line-height: 1.1; }
    .ti-summary-stat .ti-stat-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin-top: 2px; }
    .ti-summary-stat.flagged .ti-stat-num { color: #dc2626; }
    .ti-progress-track { flex: 1; min-width: 140px; height: 9px; border-radius: 6px; background: #eef1f6; overflow: hidden; }
    .ti-progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg,#3b82f6,#60a5fa); border-radius: 6px; transition: width .25s ease; }
    .ti-summary-hint { font-size: 11.5px; color: #94a3b8; flex-basis: 100%; }
    .ti-summary-hint i { color: #dc2626; }

    .ti-accordion .card { border: 1px solid #eef1f6; border-radius: 12px !important; overflow: hidden; margin-bottom: 10px !important; box-shadow: 0 1px 3px rgba(15,23,42,0.04); }
    .ti-accordion .card-header { background: #fff !important; border-bottom: none; padding: 0; }
    .ti-system-toggle { display: flex; align-items: center; width: 100%; padding: 14px 18px; text-decoration: none !important; color: #0f172a; background: none; border: none; }
    .ti-system-toggle:hover, .ti-system-toggle:focus { background: #f8fafc; color: #0f172a; text-decoration: none; }
    .ti-system-icon { width: 34px; height: 34px; border-radius: 9px; background: rgba(59,130,246,0.1); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 14px; margin-right: 13px; flex-shrink: 0; }
    .ti-system-label { font-weight: 800; font-size: 13.5px; flex: 1; text-align: left; }
    .ti-system-count { font-size: 11px; color: #94a3b8; font-weight: 700; margin-right: 12px; white-space: nowrap; }
    .ti-system-flag-badge { margin-right: 12px; font-size: 10.5px; font-weight: 700; padding: 4px 9px; }
    .ti-chevron { color: #94a3b8; transition: transform .2s ease; flex-shrink: 0; }
    .ti-system-toggle.collapsed .ti-chevron { transform: rotate(-90deg); }
    .ti-accordion .card-body { padding: 0; border-top: 1px solid #f1f5f9; }

    .ti-table { margin-bottom: 0 !important; }
    .ti-table thead th { background: #f8fafc; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; border-top: none; padding: 10px 16px; }
    .ti-table td { padding: 10px 16px; vertical-align: middle; font-size: 12.5px; border-color: #f1f5f9; }
    .ti-table tbody tr:hover { background: #fafbfd; }
    .ti-component-name { font-weight: 600; color: #1e293b; }
    .ti-history-hint { font-size: 11px; margin-top: 3px; font-weight: 600; }

    .ti-status-select { font-weight: 700; font-size: 12px; border-radius: 8px; border: 1.5px solid #e2e8f0; cursor: pointer; background-color: #fff; color: #334155; }
    .ti-status-select:focus { box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
    .ti-status-select.status-SVC   { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    .ti-status-select.status-NA    { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
    .ti-status-select.status-REPR  { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .ti-status-select.status-RPLC  { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    .ti-status-select.status-UNSVC { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .ti-status-select.status-BER   { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }

    .ti-remarks-input { border-radius: 8px; border: 1.5px solid #e2e8f0; font-size: 12.5px; }
    .ti-remarks-input:focus { box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }

    .ti-findings-card, .ti-parts-card { border-radius: 12px; border: 1px solid #eef1f6; background: #fff; padding: 16px 18px; margin-top: 16px; }
    .ti-parts-card { display: flex; align-items: flex-start; gap: 12px; transition: background .2s ease, border-color .2s ease; }
    .ti-parts-card.flagged { background: #fffbeb; border-color: #fde68a; }
    .ti-parts-card input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0; }
    .ti-parts-card label { font-weight: 800; font-size: 13.5px; color: #0f172a; margin-bottom: 4px; }
    .ti-parts-card small { font-size: 12px; color: #64748b; display: block; }
</style>
<div class="modal fade" id="technicalInspectionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="fas fa-diagnoses mr-2"></i>Technical Inspection Report</h5>
                    <div class="ti-header-sub" id="tiVehicleLabel"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="technicalInspectionForm">
                <div class="modal-body">
                    <div id="tiErrorBanner" class="alert alert-danger" style="display:none;"></div>
                    <div id="tiReadOnlyBanner" class="alert alert-secondary py-2" style="display:none;">
                        <i class="fas fa-eye mr-1"></i> View-only — Viewer accounts cannot edit the checklist.
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Inspection Date</label>
                            <input type="date" id="ti_inspection_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Inspected By (Duty Mechanic)</label>
                            <input type="text" id="ti_inspected_by" class="form-control form-control-modern" maxlength="150">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Witness (Request Party)</label>
                            <input type="text" id="ti_witness" class="form-control form-control-modern" maxlength="150">
                        </div>
                    </div>

                    <div class="ti-summary-bar">
                        <div class="ti-summary-stat">
                            <span class="ti-stat-num" id="tiProgressCount">0/0</span>
                            <span class="ti-stat-lbl">Reviewed</span>
                        </div>
                        <div class="ti-progress-track"><div class="ti-progress-fill" id="tiProgressFill"></div></div>
                        <div class="ti-summary-stat flagged">
                            <span class="ti-stat-num" id="tiFlaggedCount">0</span>
                            <span class="ti-stat-lbl">Flagged Now</span>
                        </div>
                        <div class="ti-summary-hint"><i class="fas fa-exclamation-triangle mr-1"></i> A hint under a component means it's had issues on past inspections.</div>
                    </div>

                    <div class="accordion ti-accordion" id="tiAccordion">
                        @foreach (\App\Models\TechnicalInspection::CHECKLIST as $systemKey => $system)
                            <div class="card mb-1">
                                <div class="card-header">
                                    <button class="ti-system-toggle{{ $loop->first ? '' : ' collapsed' }}" type="button" data-toggle="collapse" data-target="#tiCollapse{{ $systemKey }}">
                                        <span class="ti-system-icon"><i class="fas {{ $tiSystemIcons[$systemKey] ?? 'fa-cog' }}"></i></span>
                                        <span class="ti-system-label">{{ $system['label'] }}</span>
                                        <span class="ti-system-count">{{ count($system['components']) }} parts</span>
                                        <span class="badge badge-danger ti-system-flag-badge" data-system-badge="{{ $systemKey }}" style="display:none;">0 flagged</span>
                                        <i class="fas fa-chevron-down ti-chevron"></i>
                                    </button>
                                </div>
                                <div id="tiCollapse{{ $systemKey }}" class="collapse{{ $loop->first ? ' show' : '' }}" data-parent="#tiAccordion">
                                    <div class="card-body">
                                        <table class="table table-sm table-bordered ti-table">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width:36%;">Component</th>
                                                    <th style="width:22%;">Status</th>
                                                    <th>Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($system['components'] as $i => $component)
                                                    <tr>
                                                        <td>
                                                            <span class="ti-component-name">{{ $component }}</span>
                                                            <span class="ti-history-hint text-danger d-block" style="display:none;"></span>
                                                        </td>
                                                        <td>
                                                            <select class="form-control form-control-sm ti-status-select" data-system="{{ $systemKey }}" data-component="{{ $component }}">
                                                                <option value="">—</option>
                                                                @foreach (\App\Models\TechnicalInspection::STATUSES as $code => $label)
                                                                    <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm ti-remarks-input" maxlength="255">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="ti-findings-card">
                        <label class="field-label">Findings and Recommendation</label>
                        <textarea id="ti_findings" class="form-control form-control-modern" rows="4" maxlength="5000"></textarea>
                    </div>

                    {{--
                        Drives the process-flow gate: checking this routes the record to
                        Awaiting Parts (Vehicle Repair Requisition Slip required before it
                        can be Completed) instead of straight to Inspected. See
                        MaintenanceRecord::canComplete()/canFillRequisition() and
                        TechnicalInspectionController::save().
                    --}}
                    <div class="ti-parts-card" id="tiPartsCard">
                        <input type="checkbox" id="ti_parts_needed">
                        <div>
                            <label for="ti_parts_needed" class="mb-1">Parts or materials needed to complete this?</label>
                            <small>If checked, this job moves to <strong>Awaiting Parts</strong> and the Vehicle Repair Requisition Slip must be filled out before it can be marked Completed.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary font-weight-bold" id="tiSaveBtn"><i class="fas fa-save mr-1"></i> Save Checklist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// This partial is pulled into the page's main content area, which the layout
// renders BEFORE the jQuery script tag that lives down near the page's own
// script block — so a bare jQuery-ready call here would reference "$" before
// it exists and throw "$ is not defined" immediately (that's not a deferred
// callback error, it's the script failing to even parse its own top-level
// call). Waiting for DOMContentLoaded defers this whole block until after
// every earlier synchronous script tag — jQuery included — has already run,
// so "$" is safely defined by the time any of this actually executes.
document.addEventListener('DOMContentLoaded', function () {
    const tiRouteTemplate = "{{ route('technical-inspections.edit', ':id') }}";
    const tiIsViewer = @json($isViewer ?? false);
    const tiOkStatuses = @json(\App\Models\TechnicalInspection::OK_STATUSES);

    function tiRoute(id) {
        return tiRouteTemplate.replace(':id', id);
    }

    // Every system's "Others" row shares the same label, so the lookup key has
    // to be system+component together (matches TechnicalInspection's own
    // \x1F-joined keys server-side) — plain component name alone isn't unique.
    function tiKey(system, component) {
        return system + "\u001F" + component;
    }

    // Colors the select itself based on the chosen status (green = fine, amber/red
    // = needs attention) so a scan down a long system list surfaces problems
    // visually instead of requiring every dropdown to be read individually.
    function tiApplyStatusColor($select) {
        const val = $select.val();
        $select.removeClass('status-SVC status-UNSVC status-BER status-REPR status-RPLC status-NA');
        if (val) {
            $select.addClass('status-' + val);
        }
    }

    // Live "Reviewed / Flagged Now" summary at the top of the modal — purely a
    // convenience readout, computed from whatever's currently selected in the
    // form (not from server history, which the per-row hints already cover).
    function tiUpdateSummary() {
        const $selects = $('.ti-status-select');
        const total = $selects.length;
        let reviewed = 0;
        let flaggedNow = 0;

        $selects.each(function () {
            const val = $(this).val();
            if (val) {
                reviewed++;
                if (tiOkStatuses.indexOf(val) === -1) {
                    flaggedNow++;
                }
            }
        });

        $('#tiProgressCount').text(reviewed + '/' + total);
        $('#tiProgressFill').css('width', (total ? (reviewed / total * 100) : 0) + '%');
        $('#tiFlaggedCount').text(flaggedNow);
    }

    $(document).on('change', '.ti-status-select', function () {
        tiApplyStatusColor($(this));
        tiUpdateSummary();
    });

    $('#ti_parts_needed').on('change', function () {
        $('#tiPartsCard').toggleClass('flagged', $(this).is(':checked'));
    });

    function tiResetForm() {
        $('#technicalInspectionForm')[0].reset();
        $('.ti-status-select').val('').each(function () { tiApplyStatusColor($(this)); });
        $('.ti-remarks-input').val('');
        $('.ti-history-hint').hide().text('');
        $('.ti-system-flag-badge').hide().text('0 flagged');
        $('#ti_parts_needed').prop('checked', false);
        $('#tiPartsCard').removeClass('flagged');
        $('#tiErrorBanner').hide().text('');
        tiUpdateSummary();
    }

    function tiPopulate(data) {
        tiResetForm();

        $('#tiVehicleLabel').text(data.vehicle_label || '');
        $('#ti_inspection_date').val(data.inspection_date || '');
        $('#ti_inspected_by').val(data.inspected_by || '');
        $('#ti_witness').val(data.witness || '');
        $('#ti_findings').val(data.findings_recommendation || '');
        $('#ti_parts_needed').prop('checked', !! data.parts_needed);
        $('#tiPartsCard').toggleClass('flagged', !! data.parts_needed);

        const savedItems = data.saved_items || {};
        const history = data.history || {};
        const flaggedPerSystem = {};

        $('.ti-status-select').each(function () {
            const $select = $(this);
            const system = $select.data('system');
            const component = $select.data('component');
            const key = tiKey(system, component);
            const saved = savedItems[key];

            $select.val(saved ? (saved.status || '') : '');
            tiApplyStatusColor($select);
            $select.closest('tr').find('.ti-remarks-input').val(saved ? (saved.remarks || '') : '');

            const hist = history[key];
            const $hint = $select.closest('tr').find('.ti-history-hint');
            if (hist && hist.flagged_count > 0) {
                const lastLabel = (data.statuses && data.statuses[hist.latest_status]) ? data.statuses[hist.latest_status] : hist.latest_status;
                $hint.html('<i class="fas fa-triangle-exclamation mr-1"></i>Flagged ' + hist.flagged_count + 'x recently — last: ' + lastLabel + (hist.latest_date ? ' (' + hist.latest_date + ')' : '')).show();
                flaggedPerSystem[system] = (flaggedPerSystem[system] || 0) + 1;
            } else {
                $hint.hide().text('');
            }
        });

        $('.ti-system-flag-badge').each(function () {
            const system = $(this).data('system-badge');
            const count = flaggedPerSystem[system] || 0;
            if (count > 0) {
                $(this).text(count + ' flagged before').show();
            } else {
                $(this).hide();
            }
        });

        tiUpdateSummary();

        const readOnly = tiIsViewer;
        $('.ti-status-select, .ti-remarks-input, #ti_inspection_date, #ti_inspected_by, #ti_witness, #ti_findings, #ti_parts_needed').prop('disabled', readOnly);
        $('#tiSaveBtn').toggle(!readOnly);
        $('#tiReadOnlyBanner').toggle(readOnly);
    }

    $(document).on('click', '.btn-inspection-checklist', function () {
        const id = $(this).data('id');
        $('#technicalInspectionForm').data('record-id', id);

        $.get(tiRoute(id), function (data) {
            tiPopulate(data);
            $('#technicalInspectionModal').modal('show');
        }).fail(function () {
            toastr.error("Could not load this vehicle's inspection checklist.");
        });
    });

    $('#technicalInspectionForm').on('submit', function (e) {
        e.preventDefault();
        if (tiIsViewer) { return; }

        const id = $(this).data('record-id');
        const partsNeeded = $('#ti_parts_needed').is(':checked');

        Swal.fire({
            title: 'Save this Technical Inspection checklist?',
            text: partsNeeded
                ? 'Please review the checklist before saving. This will move the record to Awaiting Parts.'
                : 'Please review the checklist before saving. This will move the record forward in the process.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Checklist',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Review Again',
        }).then(function (result) {
            if (! result.isConfirmed) return;

            const items = [];
            $('.ti-status-select').each(function () {
                const $select = $(this);
                items.push({
                    system_category: $select.data('system'),
                    component_name: $select.data('component'),
                    status: $select.val(),
                    remarks: $select.closest('tr').find('.ti-remarks-input').val(),
                });
            });

            const payload = {
                inspection_date: $('#ti_inspection_date').val(),
                inspected_by: $('#ti_inspected_by').val(),
                witness: $('#ti_witness').val(),
                findings_recommendation: $('#ti_findings').val(),
                parts_needed: partsNeeded ? 1 : 0,
                items: items,
            };

            const $btn = $('#tiSaveBtn');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            $('#tiErrorBanner').hide().text('');

            $.post(tiRoute(id), payload, function (resp) {
                toastr.success(resp.message || 'Checklist saved.');
                $('#technicalInspectionModal').modal('hide');
                // The checklist just moved this record's stage (Requested ->
                // Inspected/Awaiting Parts) — reload so the Stage badge and
                // Complete Service button reflect it without a manual refresh.
                if (typeof table !== 'undefined') {
                    table.ajax.reload(null, false);
                }
            }).fail(function (xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors || {}).flat()[0]))
                    || 'Could not save the checklist. Please try again.';
                $('#tiErrorBanner').text(msg).show();
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Checklist');
            });
        });
    });
});
</script>
