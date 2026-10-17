<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50];

    public function __construct(private readonly RegistrationService $registrations) {}

    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $requestedPageSize = (int) $request->query('per_page', 10);
        $perPage = in_array($requestedPageSize, self::PAGE_SIZES, true) ? $requestedPageSize : 10;
        $validStatuses = ['pending', 'accepted', 'expired', 'revoked'];
        $requestedStatus = (string) $request->query('status', '');
        $status = in_array($requestedStatus, $validStatuses, true) ? $requestedStatus : '';
        $validSorts = ['email_asc', 'email_desc', 'created_desc', 'created_asc'];
        $requestedSort = (string) $request->query('sort', 'created_desc');
        $sort = in_array($requestedSort, $validSorts, true) ? $requestedSort : 'created_desc';

        $query = Registration::query()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', '%'.$search.'%'))
            ->when($status === 'pending', fn ($query) => $query
                ->where('is_registered', false)
                ->whereNull('revoked_at')
                ->where('otp_expires_at', '>', now()))
            ->when($status === 'accepted', fn ($query) => $query->where('is_registered', true))
            ->when($status === 'revoked', fn ($query) => $query
                ->where('is_registered', false)
                ->whereNotNull('revoked_at'))
            ->when($status === 'expired', fn ($query) => $query
                ->where('is_registered', false)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('otp_expires_at')->orWhere('otp_expires_at', '<=', now())));

        match ($sort) {
            'email_asc' => $query->orderBy('email')->orderBy('id'),
            'email_desc' => $query->orderByDesc('email')->orderByDesc('id'),
            'created_asc' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $invitations = $query
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.invitations.index', compact('invitations', 'search', 'perPage', 'status', 'sort'));
    }

    public function create(): View
    {
        return view('admin.invitations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'user_type' => ['required', 'in:MEDICAL_OFFICER,DERMATOLOGIST'],
        ]);

        $this->registrations->invite(
            $data['email'],
            $data['user_type'],
            null,
            (int) $request->user()->id,
        );

        $status = config('mail.default') === 'log'
            ? 'Invitation created. The email was written to storage/logs/laravel.log.'
            : 'Invitation email sent.';

        return redirect()->route('invitations.index')->with('status', $status);
    }

    public function resend(Request $request, Registration $registration): RedirectResponse
    {
        if ($registration->is_registered) {
            return back()->withErrors(['invitation' => 'This invitation has already been accepted.']);
        }

        $this->registrations->invite(
            $registration->email,
            $registration->role,
            $registration->facility_name,
            (int) $request->user()->id,
        );

        $status = config('mail.default') === 'log'
            ? 'A fresh invitation was generated. Its email was written to storage/logs/laravel.log.'
            : 'A fresh invitation link and code were emailed.';

        return redirect()->route('invitations.index')->with('status', $status);
    }

}
