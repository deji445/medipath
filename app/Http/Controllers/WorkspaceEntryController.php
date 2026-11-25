<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceEntryController extends Controller
{
    public function hospitalLogin(Request $request): Response|RedirectResponse
    {
        return $this->login($request, 'hospital');
    }

    public function platformLogin(Request $request): Response|RedirectResponse
    {
        return $this->login($request, 'platform');
    }

    public function tenant(Request $request, Hospital $hospital): RedirectResponse
    {
        abort_unless($hospital->is_active, 404);
        $staff = $request->user();

        if (! $staff) {
            $request->session()->put('url.intended', $request->url());

            return to_route('hospital.login');
        }

        abort_unless($staff instanceof StaffUser && $staff->is_active
            && ! $staff->isSuperAdmin() && $staff->hospital_id === $hospital->getKey(), 403);

        return to_route('dashboard');
    }

    private function login(Request $request, string $portal): Response|RedirectResponse
    {
        if ($request->user()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/Login', [
            'portal' => $portal,
            'status' => $request->session()->get('status'),
        ]);
    }
}
