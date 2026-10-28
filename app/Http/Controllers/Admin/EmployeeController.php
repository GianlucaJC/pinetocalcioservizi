<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmployeeCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['employee', 'athlete'])],
        ]);

        $employees = User::query()
            ->where('role', 'employee')
            ->with('employeeProfile')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('email', 'like', '%'.$search.'%')
                        ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('tax_code', 'like', '%'.$search.'%'));

                    $nameParts = preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                    if ($nameParts !== []) {
                        $query->orWhere(function ($query) use ($nameParts) {
                            foreach ($nameParts as $namePart) {
                                $query->where(function ($query) use ($namePart) {
                                    $query->where('first_name', 'like', '%'.$namePart.'%')
                                        ->orWhere('last_name', 'like', '%'.$namePart.'%');
                                });
                            }
                        });
                    }
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->whereHas('employeeProfile', fn ($profile) => $profile->where('type', $type)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->employeeData($user));

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Employees/Form', [
            'employee' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'role' => 'employee',
                'password' => Hash::make($data['password']),
                'must_change_password' => true,
                'temporary_password' => $data['password'],
            ]);

            $profileData = $this->profileData($data);

            if ($data['work_contract'] instanceof UploadedFile) {
                $profileData['work_contract_path'] = $this->storeContract($data['work_contract'], $user);
            }

            $user->employeeProfile()->create($profileData);

            return $user;
        });

        return redirect()->route('admin.employees.index')->with('success', 'Profilo creato correttamente.');
    }

    public function edit(User $employee): Response
    {
        $this->ensureEmployee($employee);

        return Inertia::render('Admin/Employees/Form', [
            'employee' => $this->employeeData($employee->load('employeeProfile')),
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);
        $data = $this->validatedData($request, $employee);

        $previousContractPath = $employee->employeeProfile?->work_contract_path;
        $newContractPath = null;

        DB::transaction(function () use ($data, $employee, &$newContractPath) {
            $userData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
            ];

            if ($data['password'] !== null) {
                $userData['password'] = Hash::make($data['password']);
                $userData['must_change_password'] = true;
                $userData['temporary_password'] = $data['password'];
            }

            $employee->update($userData);
            $profileData = $this->profileData($data);

            if ($data['work_contract'] instanceof UploadedFile) {
                $newContractPath = $this->storeContract($data['work_contract'], $employee);
                $profileData['work_contract_path'] = $newContractPath;
            }

            $employee->employeeProfile()->updateOrCreate([], $profileData);
        });

        if ($newContractPath && $previousContractPath) {
            Storage::disk('local')->delete($previousContractPath);
        }

        return redirect()->route('admin.employees.index')->with('success', 'Profilo aggiornato correttamente.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);
        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', 'Profilo eliminato correttamente.');
    }

    public function sendCredentials(User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);

        $temporaryPassword = $employee->temporary_password;

        if (! $employee->must_change_password || ! $temporaryPassword
            || ! Hash::check($temporaryPassword, $employee->password)) {
            throw ValidationException::withMessages([
                'credentials' => 'Imposta e salva una password di primo accesso nel profilo prima di inviare le credenziali.',
            ]);
        }

        $employee->notify(new EmployeeCredentials($temporaryPassword));

        return redirect()->route('admin.employees.index')->with('success', 'Credenziali inviate a '.$employee->email.'.');
    }

    public function contract(User $employee): StreamedResponse
    {
        $this->ensureEmployee($employee);

        $contractPath = $employee->employeeProfile?->work_contract_path;

        abort_unless(
            $contractPath && Storage::disk('local')->exists($contractPath),
            404,
        );

        return Storage::disk('local')->response($contractPath, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    private function validatedData(Request $request, ?User $employee = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($employee)],
            'password' => [$employee ? 'nullable' : 'required', 'string', 'min:12', 'max:255'],
            'type' => ['required', Rule::in(['employee', 'athlete'])],
            'tax_code' => ['required', 'string', 'max:32', Rule::unique('employee_profiles')->ignore($employee?->employeeProfile)],
            'phone' => ['required', 'string', 'max:50'],
            'pec_email' => ['nullable', 'string', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'position' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'started_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'work_contract' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);
    }

    private function profileData(array $data): array
    {
        return [
            'type' => $data['type'],
            'tax_code' => strtoupper($data['tax_code']),
            'phone' => $data['phone'],
            'pec_email' => $data['pec_email'],
            'birth_date' => $data['birth_date'],
            'birth_place' => $data['birth_place'],
            'address' => $data['address'],
            'city' => $data['city'],
            'province' => $data['province'] ? strtoupper($data['province']) : null,
            'postal_code' => $data['postal_code'],
            'position' => $data['position'],
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
            'started_on' => $data['started_on'],
            'notes' => $data['notes'],
        ];
    }

    private function employeeData(User $user): array
    {
        $profile = $user->employeeProfile;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'type' => $profile?->type,
            'tax_code' => $profile?->tax_code,
            'phone' => $profile?->phone,
            'pec_email' => $profile?->pec_email,
            'birth_date' => $profile?->birth_date?->format('Y-m-d'),
            'birth_place' => $profile?->birth_place,
            'address' => $profile?->address,
            'city' => $profile?->city,
            'province' => $profile?->province,
            'postal_code' => $profile?->postal_code,
            'position' => $profile?->position,
            'emergency_contact_name' => $profile?->emergency_contact_name,
            'emergency_contact_phone' => $profile?->emergency_contact_phone,
            'started_on' => $profile?->started_on?->format('Y-m-d'),
            'notes' => $profile?->notes,
            'has_work_contract' => (bool) $profile?->work_contract_path,
        ];
    }

    private function ensureEmployee(User $employee): void
    {
        abort_unless($employee->role === 'employee', 404);
    }

    private function storeContract(UploadedFile $contract, User $employee): string
    {
        return $contract->storeAs(
            'contracts/'.$employee->id,
            Str::uuid().'.pdf',
            'local',
        );
    }
}
