<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeContractController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $contractPath = $request->user()->employeeProfile?->work_contract_path;

        abort_unless(
            $contractPath && Storage::disk('local')->exists($contractPath),
            404,
        );

        return Storage::disk('local')->response($contractPath, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }
}
