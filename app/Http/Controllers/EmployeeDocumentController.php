<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:payslip,cud,other'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $profile = $request->user()->employeeProfile;

        abort_unless($profile, 404);

        $documents = $profile->documents()
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('document_type', $type))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->where('period_year', $year))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('issued_on')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (EmployeeDocument $document) => [
                'id' => $document->id,
                'type' => $document->document_type,
                'title' => $document->title,
                'period_year' => $document->period_year,
                'period_month' => $document->period_month,
                'issued_on' => $document->issued_on?->format('Y-m-d'),
            ]);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'filters' => [
                'type' => $filters['type'] ?? '',
                'year' => isset($filters['year']) ? (string) $filters['year'] : '',
                'search' => $filters['search'] ?? '',
            ],
            'years' => $profile->documents()
                ->whereNotNull('period_year')
                ->distinct()
                ->orderByDesc('period_year')
                ->pluck('period_year')
                ->values(),
        ]);
    }

    public function show(Request $request, EmployeeDocument $document): StreamedResponse
    {
        abort_unless($document->employee_profile_id === $request->user()->employeeProfile?->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        if ($document->viewed_at === null) {
            $document->update(['viewed_at' => now()]);
        }

        return Storage::disk('local')->response($document->storage_path, $document->original_filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
        ]);
    }
}
