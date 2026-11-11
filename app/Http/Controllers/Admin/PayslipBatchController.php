<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PayslipTextExtractionException;
use App\Http\Controllers\Controller;
use App\Models\DocumentBatch;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Notifications\PayslipPublished;
use App\Services\PayslipPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipBatchController extends Controller
{
    private const BATCHES_PER_PAGE = 10;

    private const DELIVERIES_PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $area = $this->area();
        $filters = $request->validate([
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_month' => $area === 'cuds' ? ['prohibited'] : ['nullable', 'integer', 'between:1,13'],
        ]);

        return Inertia::render('Admin/Payslips/Index', [
            'area' => $area,
            'batches' => DocumentBatch::query()
                ->whereIn('document_type', $area === 'cuds' ? ['cud'] : ['payslip', 'thirteenth'])
                ->when($filters['period_year'] ?? null, fn ($query, int $year) => $query->where('period_year', $year))
                ->when($filters['period_month'] ?? null, function ($query, int $month) {
                    if ($month === 13) {
                        $query->where('document_type', 'thirteenth');
                    } else {
                        $query->where('document_type', 'payslip')->where('period_month', $month);
                    }
                })
                ->latest()
                ->paginate(self::BATCHES_PER_PAGE)
                ->withQueryString()
                ->through(fn (DocumentBatch $batch) => $this->batchData($batch)),
            'filters' => [
                'period_year' => isset($filters['period_year']) ? (string) $filters['period_year'] : '',
                'period_month' => isset($filters['period_month']) ? (string) $filters['period_month'] : '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Payslips/Upload', ['area' => $this->area()]);
    }

    public function store(Request $request, PayslipPdfService $payslipPdfService): RedirectResponse
    {
        $area = $this->area();
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => $area === 'cuds' ? ['prohibited'] : ['required', 'integer', 'between:1,13'],
        ]);
        $documentType = $area === 'cuds' ? 'cud' : ((int) $data['period_month'] === 13 ? 'thirteenth' : 'payslip');

        /** @var UploadedFile $file */
        $file = $data['file'];
        try {
            $segments = $payslipPdfService->analyze($file->getRealPath(), $area !== 'cuds');
        } catch (PayslipTextExtractionException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $profiles = EmployeeProfile::query()
            ->whereIn('tax_code', array_filter(array_column($segments, 'tax_code')))
            ->get()
            ->keyBy('tax_code');

        $segments = array_map(function (array $segment) use ($profiles) {
            $profile = $segment['tax_code'] ? $profiles->get($segment['tax_code']) : null;
            $segment['employee_profile_id'] = $profile?->id;

            if ($segment['tax_code'] && ! $profile) {
                $segment['issue'] = 'Nessun profilo corrisponde al codice fiscale.';
            }

            return $segment;
        }, $segments);

        $sourcePath = $file->storeAs('payslips/batches', Str::uuid().'.pdf', 'local');

        $batch = DocumentBatch::create([
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $sourcePath,
            'document_type' => $documentType,
            'period_year' => $data['period_year'],
            'period_month' => $documentType === 'payslip' ? $data['period_month'] : null,
            'status' => 'review',
            'metadata' => ['segments' => $segments],
        ]);

        return redirect()->route('admin.'.$area.'.show', $batch);
    }

    public function show(Request $request, DocumentBatch $batch): Response
    {
        $this->ensureBatchForArea($batch);

        $notificationCounts = $batch->status === 'completed'
            ? $batch->documents()
                ->where('is_visible_to_employee', true)
                ->selectRaw('COUNT(DISTINCT employee_profile_id) AS total, COUNT(DISTINCT CASE WHEN notification_sent_at IS NULL THEN employee_profile_id END) AS pending')
                ->first()
            : null;
        $pendingNotifications = (int) ($notificationCounts?->pending ?? 0);

        return Inertia::render('Admin/Payslips/Review', [
            'area' => $this->area(),
            'batch' => $this->batchData($batch),
            'deliveries' => $batch->documents()
                ->with([
                    'employeeProfile.user:id,first_name,last_name,email',
                    'replacedByDocument.batch:id,revision_number',
                ])
                ->orderBy('tax_code')
                ->paginate(self::DELIVERIES_PER_PAGE)
                ->withQueryString()
                ->through(fn (EmployeeDocument $document) => [
                    'id' => $document->id,
                    'name' => $document->employeeProfile->user->full_name,
                    'email' => $document->employeeProfile->user->email,
                    'tax_code' => $document->tax_code,
                    'first_page' => $document->first_page,
                    'last_page' => $document->last_page,
                    'viewed_at' => $document->viewed_at?->format('d/m/Y H:i'),
                    'notification_sent_at' => $document->notification_sent_at?->format('d/m/Y H:i'),
                    'is_visible_to_employee' => $document->is_visible_to_employee,
                    'replaced_by_revision_number' => $document->replacedByDocument?->batch?->revision_number,
                ]),
            'pending_notifications_count' => $pendingNotifications,
            'sent_notifications_count' => (int) ($notificationCounts?->total ?? 0) - $pendingNotifications,
            'has_replaced_documents' => $batch->status === 'completed'
                && $batch->documents()->where('is_visible_to_employee', false)->exists(),
            'profiles' => EmployeeProfile::query()
                ->with('user:id,first_name,last_name')
                ->orderBy('tax_code')
                ->get()
                ->map(fn (EmployeeProfile $profile) => [
                    'id' => $profile->id,
                    'name' => $profile->user->full_name,
                    'tax_code' => $profile->tax_code,
                ]),
        ]);
    }

    public function update(Request $request, DocumentBatch $batch): RedirectResponse
    {
        $this->ensureBatchForArea($batch);
        abort_unless($batch->status === 'review', 409);

        $data = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*' => ['nullable', 'integer', Rule::exists('employee_profiles', 'id')],
        ]);
        $segments = $batch->metadata['segments'] ?? [];

        foreach ($segments as $index => $segment) {
            if (array_key_exists((string) $index, $data['assignments'])) {
                $segments[$index]['employee_profile_id'] = $data['assignments'][(string) $index];
                $segments[$index]['issue'] = $data['assignments'][(string) $index] ? null : $segment['issue'];
            }
        }

        $batch->update(['metadata' => ['segments' => $segments]]);

        return redirect()->route('admin.'.$this->area().'.show', $batch)->with('success', 'Assegnazioni aggiornate.');
    }

    public function publish(DocumentBatch $batch, PayslipPdfService $payslipPdfService): RedirectResponse
    {
        $this->ensureBatchForArea($batch);
        abort_unless($batch->status === 'review', 409);

        $segments = $batch->metadata['segments'] ?? [];
        $published = 0;

        DB::transaction(function () use ($batch, $segments, $payslipPdfService, &$published) {
            $hasPublishedPayslips = EmployeeDocument::query()
                ->where('document_type', $batch->document_type)
                ->where('period_year', $batch->period_year)
                ->where('period_month', $batch->period_month)
                ->where('is_visible_to_employee', true)
                ->exists();
            $revisionNumber = $hasPublishedPayslips
                ? DocumentBatch::query()
                    ->where('document_type', $batch->document_type)
                    ->where('period_year', $batch->period_year)
                    ->where('period_month', $batch->period_month)
                    ->where('status', 'completed')
                    ->max('revision_number') + 1
                : 0;

            foreach ($segments as $segment) {
                if (! $segment['employee_profile_id']) {
                    continue;
                }

                $profile = EmployeeProfile::findOrFail($segment['employee_profile_id']);
                $documentPath = 'documents/'.$segment['employee_profile_id'].'/payslip-'.Str::uuid().'.pdf';
                $payslipPdfService->split(
                    Storage::disk('local')->path($batch->storage_path),
                    range($segment['first_page'], $segment['last_page']),
                    $documentPath,
                );

                $document = EmployeeDocument::create([
                    'employee_profile_id' => $segment['employee_profile_id'],
                    'document_batch_id' => $batch->id,
                    'document_type' => $batch->document_type,
                    'title' => $this->documentTitle($batch)
                        .($revisionNumber > 0 ? ' - Rettifica n. '.$revisionNumber : ''),
                    'tax_code' => $profile->tax_code,
                    'period_year' => $batch->period_year,
                    'period_month' => $batch->period_month,
                    'storage_path' => $documentPath,
                    'original_filename' => $this->documentFilename($batch),
                    'first_page' => $segment['first_page'],
                    'last_page' => $segment['last_page'],
                ]);

                EmployeeDocument::query()
                    ->where('employee_profile_id', $segment['employee_profile_id'])
                    ->where('document_type', $batch->document_type)
                    ->where('period_year', $batch->period_year)
                    ->where('period_month', $batch->period_month)
                    ->where('is_visible_to_employee', true)
                    ->where(fn ($query) => $query->whereNull('document_batch_id')->orWhere('document_batch_id', '!=', $batch->id))
                    ->whereKeyNot($document->id)
                    ->update([
                        'is_visible_to_employee' => false,
                        'replaced_by_document_id' => $document->id,
                    ]);
                $published++;
            }

            $batch->update([
                'status' => 'completed',
                'documents_count' => $published,
                'revision_number' => $revisionNumber,
            ]);
        });

        return redirect()->route('admin.'.$this->area().'.index')->with('success', $published.' documenti pubblicati. Gli elementi non riconosciuti restano nel lotto per revisione.');
    }

    public function destroy(DocumentBatch $batch): RedirectResponse
    {
        $this->ensureBatchForArea($batch);

        DB::transaction(function () use ($batch) {
            $batch = DocumentBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $documents = $batch->documents()->lockForUpdate()->get();

            abort_if(
                $documents->contains(fn (EmployeeDocument $document) => $document->viewed_at !== null),
                409,
                'Non puoi eliminare una busta già visualizzata da un dipendente o atleta.',
            );

            foreach ($documents as $document) {
                EmployeeDocument::query()
                    ->where('replaced_by_document_id', $document->id)
                    ->where(fn ($query) => $query->whereNull('document_batch_id')->orWhere('document_batch_id', '!=', $batch->id))
                    ->update([
                        'is_visible_to_employee' => $document->replaced_by_document_id === null,
                        'replaced_by_document_id' => $document->replaced_by_document_id,
                    ]);
            }

            Storage::disk('local')->delete([
                $batch->storage_path,
                ...$documents->pluck('storage_path')->all(),
            ]);

            $documents->each->delete();
            $batch->delete();
        });

        return redirect()->route('admin.'.$this->area().'.index')->with('success', 'Lotto eliminato.');
    }

    public function sendNotification(DocumentBatch $batch): JsonResponse
    {
        $this->ensureBatchForArea($batch);
        abort_unless($batch->status === 'completed' && $batch->documents()->exists(), 409);

        $profileIds = $batch->documents()
            ->where('is_visible_to_employee', true)
            ->whereNull('notification_sent_at')
            ->distinct()
            ->pluck('employee_profile_id');
        $sent = 0;
        $failed = [];

        foreach ($profileIds as $profileId) {
            $profile = EmployeeProfile::with('user')->findOrFail($profileId);

            try {
                $wasSent = DB::transaction(function () use ($batch, $profile) {
                    EmployeeProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
                    $documents = $batch->documents()
                        ->where('employee_profile_id', $profile->id)
                        ->where('is_visible_to_employee', true)
                        ->whereNull('notification_sent_at');

                    if (! $documents->exists()) {
                        return false;
                    }

                    $profile->user->notify(new PayslipPublished($batch->period_month, $batch->period_year, $batch->document_type));
                    $documents->update(['notification_sent_at' => now()]);

                    return true;
                });
                $sent += (int) $wasSent;
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = [
                    'name' => $profile->user->full_name,
                    'email' => $profile->user->email,
                ];
            }
        }

        return response()->json(['sent_count' => $sent, 'failed' => $failed]);
    }

    public function document(EmployeeDocument $document): StreamedResponse
    {
        abort_unless(in_array($document->document_type, $this->area() === 'cuds' ? ['cud'] : ['payslip', 'thirteenth'], true), 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->response($document->storage_path, $document->original_filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->original_filename.'"',
        ]);
    }

    private function batchData(DocumentBatch $batch): array
    {
        $segments = $batch->metadata['segments'] ?? [];

        return [
            'id' => $batch->id,
            'original_filename' => $batch->original_filename,
            'document_type' => $batch->document_type,
            'period_year' => $batch->period_year,
            'period_month' => $batch->period_month,
            'status' => $batch->status,
            'documents_count' => $batch->documents_count,
            'revision_number' => $batch->revision_number,
            'segments' => $segments,
            'assignable_count' => count(array_filter($segments, fn (array $segment) => $segment['employee_profile_id'])),
            'unmatched_count' => count(array_filter($segments, fn (array $segment) => ! $segment['employee_profile_id'])),
            'can_delete' => ! $batch->documents()->whereNotNull('viewed_at')->exists(),
        ];
    }

    private function area(): string
    {
        return request()->routeIs('admin.cuds.*') ? 'cuds' : 'payslips';
    }

    private function ensureBatchForArea(DocumentBatch $batch): void
    {
        abort_unless(in_array($batch->document_type, $this->area() === 'cuds' ? ['cud'] : ['payslip', 'thirteenth'], true), 404);
    }

    private function documentTitle(DocumentBatch $batch): string
    {
        return match ($batch->document_type) {
            'cud' => 'Certificazione Unica - redditi '.$batch->period_year,
            'thirteenth' => 'Tredicesima - '.$batch->period_year,
            'payslip' => 'Busta paga - '.$batch->period_month.'/'.$batch->period_year,
        };
    }

    private function documentFilename(DocumentBatch $batch): string
    {
        return match ($batch->document_type) {
            'cud' => 'certificazione-unica-redditi-'.$batch->period_year.'.pdf',
            'thirteenth' => 'tredicesima-'.$batch->period_year.'.pdf',
            'payslip' => 'busta-paga-'.$batch->period_year.'-'.str_pad((string) $batch->period_month, 2, '0', STR_PAD_LEFT).'.pdf',
        };
    }
}
