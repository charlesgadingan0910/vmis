{{--
    Digitized Vehicle Repair Requisition Slip — shared by Maintenance & PMS
    and Repairs (@include'd once from each module's index.blade.php), same
    convention as technical-inspections/_modal.blade.php. Opened via a
    "Vehicle Repair Requisition Slip" row button that both
    MaintenanceController::index() and RepairController::index() add next to
    the Technical Inspection Checklist button, shown whenever a record's
    inspection has flagged parts/materials as needed
    (MaintenanceRecord::canFillRequisition()). See
    App\Http\Controllers\VehicleRepairRequisitionController for the AJAX
    endpoints this modal talks to, and MaintenanceRecord::
    hasRequisitionFilled() for how filling this out now gates whether the
    record can be marked Completed.

    Styling is scoped to #vehicleRequisitionModal / .vrr-* classes and
    defined inline here (rather than relying on the including page's own
    <style> block) so this partial renders correctly wherever it's included.
--}}
<style>
    #vehicleRequisitionModal .modal-content { border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; }
    #vehicleRequisitionModal .modal-header { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 20px 24px; border-bottom: none; align-items: flex-start; }
    #vehicleRequisitionModal .modal-header .modal-title { font-weight: 800; font-size: 17px; margin: 0; }
    #vehicleRequisitionModal .vrr-header-sub { font-size: 12.5px; color: #94a3b8; font-weight: 600; margin-top: 4px; }
    #vehicleRequisitionModal .modal-header .close { color: #fff; opacity: .75; text-shadow: none; margin-top: -2px; }
    #vehicleRequisitionModal .modal-header .close:hover { opacity: 1; }
    /* Same flex-column fix as #technicalInspectionModal — see that partial's
       own comment for the full explanation of why the <form> wrapper needs
       this to make .modal-body scroll instead of clipping/growing forever. */
    #vehicleRequisitionModal form#vehicleRequisitionForm { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    #vehicleRequisitionModal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 24px; background: #fbfcfe; }
    #vehicleRequisitionModal .modal-footer { flex: 0 0 auto; }
    #vehicleRequisitionModal .field-label { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin-bottom: 6px; display: block; }
    #vehicleRequisitionModal .form-control-modern { border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 13.5px; height: 42px; color: #0f172a; }
    #vehicleRequisitionModal .form-control-modern:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
    #vehicleRequisitionModal textarea.form-control-modern { height: auto; }
    #vehicleRequisitionModal .vrr-section-divider { font-size: 11.5px; font-weight: 800; color: #3b82f6; text-transform: uppercase; margin: 18px 0 12px; padding-bottom: 6px; border-bottom: 1.5px solid #f1f5f9; }
    #vehicleRequisitionModal .vrr-section-divider i { margin-right: 4px; }
    #vehicleRequisitionModal .vrr-req-no { font-size: 12px; font-weight: 700; color: #475569; font-family: 'Courier New', monospace; }

    .vrr-items-table { margin-bottom: 8px !important; }
    .vrr-items-table thead th { background: #f8fafc; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #64748b; border-top: none; padding: 10px 12px; }
    .vrr-items-table td { padding: 6px 8px; vertical-align: middle; border-color: #f1f5f9; }
    .vrr-items-table input { border-radius: 7px; border: 1.5px solid #e2e8f0; font-size: 12.5px; height: 38px; }
    .vrr-items-table input:focus { box-shadow: 0 0 0 3px rgba(59,130,246,0.12); border-color: #3b82f6; }
    .vrr-items-table .vrr-qty { width: 70px; }
    .vrr-items-table .vrr-price { width: 130px; }
    .vrr-items-table .vrr-remove-row { width: 40px; text-align: center; }
    .vrr-total-row td { font-weight: 800; font-size: 13.5px; color: #0f172a; background: #f8fafc; }
    .vrr-add-row-btn { font-size: 12px; font-weight: 700; }
</style>
<div class="modal fade" id="vehicleRequisitionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="fas fa-boxes mr-2"></i>Vehicle Repair Requisition Slip</h5>
                    <div class="vrr-header-sub" id="vrrVehicleLabel"></div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <form id="vehicleRequisitionForm">
                <div class="modal-body">
                    <div id="vrrErrorBanner" class="alert alert-danger" style="display:none;"></div>
                    <div id="vrrReadOnlyBanner" class="alert alert-secondary py-2" style="display:none;">
                        <i class="fas fa-eye mr-1"></i> View-only — Viewer accounts cannot edit the requisition slip.
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:6px;">
                        <span class="vrr-req-no" id="vrrRequisitionNo"></span>
                    </div>

                    <div class="vrr-section-divider mt-0"><i class="fas fa-car"></i> Vehicle Particulars</div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="field-label">Date</label>
                            <input type="date" id="vrr_requisition_date" class="form-control form-control-modern">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Office / Unit</label>
                            <input type="text" id="vrr_office_unit" class="form-control form-control-modern" maxlength="150">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="field-label">Current Mileage (km)</label>
                            <input type="number" id="vrr_current_mileage" class="form-control form-control-modern" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Driver / Custodian</label>
                            <input type="text" id="vrr_driver_custodian" class="form-control form-control-modern" maxlength="150">
                        </div>
                    </div>

                    <div class="vrr-section-divider"><i class="fas fa-file-signature"></i> Reason for Request / Findings</div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Reason for Request / Complaint</label>
                            <textarea id="vrr_reason_for_request" class="form-control form-control-modern" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Findings / Diagnosis</label>
                            <textarea id="vrr_findings_diagnosis" class="form-control form-control-modern" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="field-label">Requested By</label>
                            <input type="text" id="vrr_requested_by" class="form-control form-control-modern" maxlength="150">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="field-label">Inspected By (Diagnostic Mechanic)</label>
                            <input type="text" id="vrr_inspected_by" class="form-control form-control-modern" maxlength="150">
                        </div>
                    </div>

                    <div class="vrr-section-divider"><i class="fas fa-boxes"></i> Requested Parts / Materials</div>
                    <table class="table table-sm table-bordered vrr-items-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="vrr-qty">Qty</th>
                                <th>Particulars</th>
                                <th class="vrr-price">Price</th>
                                <th class="vrr-remove-row"></th>
                            </tr>
                        </thead>
                        <tbody id="vrrItemsBody"></tbody>
                        <tfoot>
                            <tr class="vrr-total-row">
                                <td colspan="2" class="text-right">TOTAL</td>
                                <td id="vrrTotalDisplay">&#8369;0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <button type="button" class="btn btn-sm btn-light border vrr-add-row-btn" id="vrrAddItemBtn"><i class="fas fa-plus mr-1"></i> Add Item</button>

                    <div class="vrr-section-divider"><i class="fas fa-wrench"></i> Motorpool Action <small class="text-muted text-uppercase" style="font-weight:600;">(For Motorpool Use Only)</small></div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Repair Conducted</label>
                            <textarea id="vrr_repair_conducted" class="form-control form-control-modern" rows="2" maxlength="2000"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label class="field-label">Mechanic/s in Charge</label>
                            <textarea id="vrr_mechanics_in_charge" class="form-control form-control-modern" rows="2" maxlength="1000" placeholder="One name per line"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary font-weight-bold" id="vrrSaveBtn"><i class="fas fa-save mr-1"></i> Save Requisition Slip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Same reasoning as technical-inspections/_modal.blade.php's own script for
// deferring to DOMContentLoaded — this partial is rendered before the
// layout's jQuery <script> tag further down the page.
document.addEventListener('DOMContentLoaded', function () {
    const vrrRouteTemplate = "{{ route('vehicle-requisitions.edit', ':id') }}";
    const vrrIsViewer = @json($isViewer ?? false);

    function vrrRoute(id) {
        return vrrRouteTemplate.replace(':id', id);
    }

    function vrrFormatMoney(n) {
        n = Number(n) || 0;
        return '₱' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function vrrRecalcTotal() {
        let total = 0;
        $('#vrrItemsBody tr').each(function () {
            total += parseFloat($(this).find('.vrr-price-input').val()) || 0;
        });
        $('#vrrTotalDisplay').text(vrrFormatMoney(total));
    }

    function vrrAddRow(item) {
        item = item || {};
        const $row = $('<tr></tr>');
        $row.append($('<td></td>').append(
            $('<input type="number" min="0" class="form-control form-control-sm vrr-qty-input">').val(item.qty || '')
        ));
        $row.append($('<td></td>').append(
            $('<input type="text" maxlength="255" class="form-control form-control-sm vrr-particulars-input" placeholder="Part / material description">').val(item.particulars || '')
        ));
        $row.append($('<td></td>').append(
            $('<input type="number" min="0" step="0.01" class="form-control form-control-sm vrr-price-input">').val(item.price || '')
        ));
        $row.append($('<td class="text-center"></td>').append(
            $('<button type="button" class="btn btn-sm btn-light border text-danger vrr-remove-row-btn"><i class="fas fa-times"></i></button>')
        ));
        $('#vrrItemsBody').append($row);
    }

    $(document).on('input', '.vrr-price-input', vrrRecalcTotal);
    $(document).on('click', '.vrr-remove-row-btn', function () {
        $(this).closest('tr').remove();
        vrrRecalcTotal();
    });
    $('#vrrAddItemBtn').on('click', function () {
        vrrAddRow();
    });

    function vrrResetForm() {
        $('#vehicleRequisitionForm')[0].reset();
        $('#vrrItemsBody').empty();
        $('#vrrErrorBanner').hide().text('');
        vrrRecalcTotal();
    }

    function vrrPopulate(data) {
        vrrResetForm();

        $('#vrrVehicleLabel').text(data.vehicle_label || '');
        $('#vrrRequisitionNo').text(data.requisition_no ? ('Requisition No: ' + data.requisition_no) : 'Requisition No: (assigned on first save)');
        $('#vrr_requisition_date').val(data.requisition_date || '');
        $('#vrr_office_unit').val(data.office_unit || '');
        $('#vrr_driver_custodian').val(data.driver_custodian || '');
        $('#vrr_current_mileage').val(data.current_mileage || '');
        $('#vrr_reason_for_request').val(data.reason_for_request || '');
        $('#vrr_findings_diagnosis').val(data.findings_diagnosis || '');
        $('#vrr_requested_by').val(data.requested_by || '');
        $('#vrr_inspected_by').val(data.inspected_by || '');
        $('#vrr_repair_conducted').val(data.repair_conducted || '');
        $('#vrr_mechanics_in_charge').val(data.mechanics_in_charge || '');

        const items = (data.items && data.items.length) ? data.items : [{}];
        items.forEach(function (item) { vrrAddRow(item); });
        vrrRecalcTotal();

        const readOnly = vrrIsViewer;
        $('#vehicleRequisitionForm :input').prop('disabled', readOnly);
        $('#vrrSaveBtn').toggle(!readOnly);
        $('#vrrReadOnlyBanner').toggle(readOnly);
    }

    $(document).on('click', '.btn-requisition-slip', function () {
        const id = $(this).data('id');
        $('#vehicleRequisitionForm').data('record-id', id);

        $.get(vrrRoute(id), function (data) {
            vrrPopulate(data);
            $('#vehicleRequisitionModal').modal('show');
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) || "Could not load this vehicle's requisition slip.");
        });
    });

    $('#vehicleRequisitionForm').on('submit', function (e) {
        e.preventDefault();
        if (vrrIsViewer) { return; }

        const id = $(this).data('record-id');

        Swal.fire({
            title: 'Save this Requisition Slip?',
            text: 'Please review the parts/materials list and total before saving.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save Requisition Slip',
            confirmButtonColor: '#3b82f6',
            cancelButtonText: 'Review Again',
        }).then(function (result) {
            if (! result.isConfirmed) return;

            const items = [];
            $('#vrrItemsBody tr').each(function () {
                items.push({
                    qty:         $(this).find('.vrr-qty-input').val(),
                    particulars: $(this).find('.vrr-particulars-input').val(),
                    price:       $(this).find('.vrr-price-input').val(),
                });
            });

            const payload = {
                requisition_date:    $('#vrr_requisition_date').val(),
                office_unit:         $('#vrr_office_unit').val(),
                driver_custodian:    $('#vrr_driver_custodian').val(),
                current_mileage:     $('#vrr_current_mileage').val(),
                reason_for_request:  $('#vrr_reason_for_request').val(),
                findings_diagnosis:  $('#vrr_findings_diagnosis').val(),
                requested_by:        $('#vrr_requested_by').val(),
                inspected_by:        $('#vrr_inspected_by').val(),
                repair_conducted:    $('#vrr_repair_conducted').val(),
                mechanics_in_charge: $('#vrr_mechanics_in_charge').val(),
                items: items,
            };

            const $btn = $('#vrrSaveBtn');
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            $('#vrrErrorBanner').hide().text('');

            $.post(vrrRoute(id), payload, function (resp) {
                toastr.success(resp.message || 'Requisition slip saved.');
                $('#vehicleRequisitionModal').modal('hide');
                // Just filled/updated the parts list that gates Completed — reload
                // so the row's Complete button / amber requisition button reflect
                // it without a manual refresh. See maintenance/repairs index.blade.php
                // for where window.table is exposed.
                if (typeof table !== 'undefined') {
                    table.ajax.reload(null, false);
                }
            }).fail(function (xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || Object.values(xhr.responseJSON.errors || {}).flat()[0]))
                    || 'Could not save the requisition slip. Please try again.';
                $('#vrrErrorBanner').text(msg).show();
            }).always(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Requisition Slip');
            });
        });
    });
});
</script>
