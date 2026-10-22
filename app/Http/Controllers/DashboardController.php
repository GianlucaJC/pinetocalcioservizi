<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load('employeeProfile');

        return Inertia::render('Dashboard', [
            'profile' => $user->employeeProfile?->only([
                'type',
                'tax_code',
                'phone',
                'pec_email',
                'birth_date',
                'birth_place',
                'address',
                'city',
                'province',
                'postal_code',
                'position',
                'emergency_contact_name',
                'emergency_contact_phone',
                'started_on',
                'work_contract_path',
            ]),
        ]);
    }
}
