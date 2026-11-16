<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\VehicleRequest;
use App\Services\ApprovalService;
use App\Services\DispatchService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vehicle requests and dispatch calendar (P2-02). Rules live in DispatchService. */
class DispatchController extends Controller
{
    protected function guard(): void
    {
        abort_unless(DispatchService::canView(auth()->user()), 403, 'Your account type cannot use Dispatch.');
    }

    public function index(Request $request): View
    {
        $this->guard();
        $user = auth()->user();

        try {
            $month = Carbon::createFromFormat('Y-m-d', ($request->query('month') ?: now(DispatchService::TIMEZONE)->format('Y-m')) . '-01')->startOfDay();
        } catch (\Throwable $e) {
            $month = now(DispatchService::TIMEZONE)->startOfMonth()->setTimezone('UTC')->startOfDay();
        }
        $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $vehicleFilter = (int) $request->query('vehicle_id') ?: null;

        $events = VehicleRequest::with(['vehicle', 'driver'])
            ->whereIn('status', [VehicleRequest::STATUS_APPROVED, VehicleRequest::STATUS_PENDING])
            ->overlapping($gridStart, $gridEnd->copy()->addSecond())
            ->when($vehicleFilter, fn ($q) => $q->where('vehicle_id', $vehicleFilter))
            ->orderBy('start_at')->get();

        $vehicles = DispatchService::canRequest($user)
            ? DispatchService::bookableVehicles($user)->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model'])
            : collect();

        $drivers = Driver::where('status', 'active')->orderBy('lastname')->get()
            ->reject(fn ($d) => $d->isLicenseExpired())->values();

        $waiting = DispatchService::pendingFor($user);
        $mine = VehicleRequest::with(['vehicle', 'driver'])->where('requested_by', $user->id)->latest('id')->limit(30)->get();

        return view('dispatch.index', [
            'month' => $month, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd,
            'events' => $events, 'vehicles' => $vehicles, 'drivers' => $drivers,
            'waiting' => $waiting, 'mine' => $mine, 'vehicleFilter' => $vehicleFilter,
            'canRequest' => DispatchService::canRequest($user),
            'allVehicles' => \App\Models\Vehicle::excludingDisposed()->orderBy('plate_number')->get(['id', 'plate_number']),
        ]);
    }

    public function store(Request $request, DispatchService $dispatch): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'vehicle_id'  => ['required', 'integer', 'exists:vehicles,id'],
            'driver_id'   => ['nullable', 'integer', 'exists:drivers,id'],
            'start_at'    => ['required', 'date'],
            'end_at'      => ['required', 'date'],
            'purpose'     => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:150'],
            'origin'      => ['nullable', 'string', 'max:150'],
            'passengers'  => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $req = $dispatch->submit($request->user(), $data);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $req->status === VehicleRequest::STATUS_APPROVED
            ? 'Request approved and booked.'
            : 'Request submitted. It is waiting for approval.');
    }

    public function approve(Request $request, VehicleRequest $vehicleRequest, DispatchService $dispatch): RedirectResponse
    {
        $this->guard();
        $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id'  => ['nullable', 'integer', 'exists:drivers,id'],
            'note'       => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $dispatch->approve($vehicleRequest, $request->user(),
                $request->filled('vehicle_id') ? (int) $request->input('vehicle_id') : null,
                $request->filled('driver_id') ? (int) $request->input('driver_id') : null,
                $request->input('note'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request approved. The vehicle is booked.');
    }

    public function reject(Request $request, VehicleRequest $vehicleRequest, DispatchService $dispatch): RedirectResponse
    {
        $this->guard();
        $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $dispatch->reject($vehicleRequest, $request->user(), $request->input('note'));

        return back()->with('success', 'Request rejected.');
    }

    public function cancel(VehicleRequest $vehicleRequest, DispatchService $dispatch): RedirectResponse
    {
        $this->guard();
        $dispatch->cancel($vehicleRequest, auth()->user());

        return back()->with('success', 'Request cancelled.');
    }
}
