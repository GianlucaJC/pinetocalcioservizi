<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DocumentBatch;
use App\Models\EmployeeDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoContractPath = 'contracts/luca-marrone-contratto-demo.pdf';

        Storage::disk('local')->put($demoContractPath, $this->demoContractPdf());

        $people = [
            ['name' => 'Amministratore Pineto', 'email' => 'admin@pinetocalcio.test', 'role' => 'admin', 'type' => 'employee', 'tax_code' => 'PNTAMN80A01A345X', 'phone' => '+39 085 949 0001', 'pec_email' => 'amministrazione@pec.pinetocalcio.test', 'birth_date' => '1980-01-01', 'birth_place' => 'Atri', 'position' => 'Amministrazione', 'started_on' => '2022-07-01'],
            ['name' => 'Luca Marrone', 'email' => 'luca.marrone@pinetocalcio.test', 'role' => 'employee', 'type' => 'athlete', 'tax_code' => 'MRRLCU97B02A345T', 'phone' => '+39 333 120 4501', 'pec_email' => 'luca.marrone@pec.pinetocalcio.test', 'birth_date' => '1997-02-02', 'birth_place' => 'Pescara', 'position' => 'Centrocampista', 'started_on' => '2025-07-01', 'work_contract_path' => $demoContractPath],
            ['name' => 'Matteo Tonti', 'email' => 'matteo.tonti@pinetocalcio.test', 'role' => 'employee', 'type' => 'athlete', 'tax_code' => 'TNTMTT92C03A345E', 'phone' => '+39 333 120 4502', 'pec_email' => 'matteo.tonti@pec.pinetocalcio.test', 'birth_date' => '1992-03-03', 'birth_place' => 'Teramo', 'position' => 'Portiere', 'started_on' => '2024-07-01'],
            ['name' => 'Giulia Rossi', 'email' => 'giulia.rossi@pinetocalcio.test', 'role' => 'employee', 'type' => 'employee', 'tax_code' => 'RSSGLI89D44A345S', 'phone' => '+39 333 120 4503', 'pec_email' => 'giulia.rossi@pec.pinetocalcio.test', 'birth_date' => '1989-04-04', 'birth_place' => 'Chieti', 'position' => 'Fisioterapista', 'started_on' => '2023-09-01'],
            ['name' => 'Marco De Santis', 'email' => 'marco.desantis@pinetocalcio.test', 'role' => 'employee', 'type' => 'employee', 'tax_code' => 'DSNMRC85E05A345P', 'phone' => '+39 333 120 4504', 'pec_email' => 'marco.desantis@pec.pinetocalcio.test', 'birth_date' => '1985-05-05', 'birth_place' => 'Pineto', 'position' => 'Responsabile materiale', 'started_on' => '2021-08-01'],
        ];

        foreach ($people as $person) {
            $profile = collect($person)->except(['name', 'email', 'role'])->all();

            $user = User::updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'role' => $person['role'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ],
            );

            $user->employeeProfile()->updateOrCreate([], $profile + [
                'address' => 'Via della Stazione 1',
                'city' => 'Pineto',
                'province' => 'TE',
                'postal_code' => '64025',
                'emergency_contact_name' => 'Contatto di emergenza',
                'emergency_contact_phone' => '+39 333 000 0000',
            ]);
        }

        $administrator = User::where('email', 'admin@pinetocalcio.test')->firstOrFail();
        $luca = User::where('email', 'luca.marrone@pinetocalcio.test')->firstOrFail();
        $profile = $luca->employeeProfile;
        $folder = 'documents/luca-marrone';
        $batchPath = 'imports/buste-paga-ottobre-2025-demo.pdf';

        Storage::disk('local')->put($batchPath, $this->demoDocumentPdf([
            'Archivio buste paga - Ottobre 2025',
            'Documento sorgente dimostrativo',
        ]));

        $batch = DocumentBatch::updateOrCreate(
            ['original_filename' => 'buste-paga-ottobre-2025-demo.pdf'],
            [
                'uploaded_by' => $administrator->id,
                'storage_path' => $batchPath,
                'document_type' => 'payslip',
                'period_year' => 2025,
                'period_month' => 10,
                'status' => 'completed',
                'documents_count' => 2,
                'metadata' => ['source' => 'demo'],
            ],
        );

        $documents = [
            [
                'document_type' => 'payslip',
                'title' => 'Busta paga - Ottobre 2025',
                'period_year' => 2025,
                'period_month' => 10,
                'issued_on' => '2025-10-31',
                'filename' => 'busta-paga-2025-10.pdf',
                'lines' => ['Busta paga - Ottobre 2025', 'Atleta: Luca Marrone', 'Codice fiscale: '.$profile->tax_code, 'Documento dimostrativo'],
            ],
            [
                'document_type' => 'payslip',
                'title' => 'Busta paga - Settembre 2025',
                'period_year' => 2025,
                'period_month' => 9,
                'issued_on' => '2025-09-30',
                'filename' => 'busta-paga-2025-09.pdf',
                'lines' => ['Busta paga - Settembre 2025', 'Atleta: Luca Marrone', 'Codice fiscale: '.$profile->tax_code, 'Documento dimostrativo'],
            ],
            [
                'document_type' => 'cud',
                'title' => 'Certificazione Unica 2025',
                'period_year' => 2025,
                'period_month' => null,
                'issued_on' => '2025-03-17',
                'filename' => 'certificazione-unica-2025.pdf',
                'lines' => ['Certificazione Unica 2025', 'Atleta: Luca Marrone', 'Codice fiscale: '.$profile->tax_code, 'Documento dimostrativo'],
            ],
            [
                'document_type' => 'other',
                'title' => 'Comunicazione societaria - Agosto 2025',
                'period_year' => 2025,
                'period_month' => 8,
                'issued_on' => '2025-08-01',
                'filename' => 'comunicazione-societaria-2025-08.pdf',
                'lines' => ['Comunicazione societaria', 'Destinatario: Luca Marrone', 'Codice fiscale: '.$profile->tax_code, 'Documento dimostrativo'],
            ],
        ];

        foreach ($documents as $document) {
            $path = $folder.'/'.$document['filename'];

            Storage::disk('local')->put($path, $this->demoDocumentPdf($document['lines']));

            EmployeeDocument::updateOrCreate(
                ['storage_path' => $path],
                [
                    'employee_profile_id' => $profile->id,
                    'document_batch_id' => $document['document_type'] === 'payslip' ? $batch->id : null,
                    'document_type' => $document['document_type'],
                    'title' => $document['title'],
                    'tax_code' => $profile->tax_code,
                    'period_year' => $document['period_year'],
                    'period_month' => $document['period_month'],
                    'issued_on' => $document['issued_on'],
                    'original_filename' => $document['filename'],
                    'first_page' => 1,
                    'last_page' => 1,
                    'metadata' => ['source' => 'demo'],
                ],
            );
        }
    }

    private function demoContractPdf(): string
    {
        $content = <<<'PDF'
BT
/F1 20 Tf
72 760 Td
(PINETO CALCIO SERVIZI) Tj
0 -36 Td
/F1 14 Tf
(Contratto di collaborazione sportiva - FAC-SIMILE) Tj
0 -48 Td
/F1 12 Tf
(Atleta: Luca Marrone) Tj
0 -24 Td
(Ruolo: Centrocampista) Tj
0 -24 Td
(Decorrenza: 01 luglio 2025) Tj
0 -48 Td
(Documento dimostrativo privo di valore contrattuale.) Tj
ET
PDF;

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";
    }

    private function demoDocumentPdf(array $lines): string
    {
        $content = "BT\n/F1 18 Tf\n72 760 Td\n(".addcslashes(array_shift($lines), '()\\').") Tj\n/F1 12 Tf\n";

        foreach ($lines as $line) {
            $content .= "0 -32 Td\n(".addcslashes($line, '()\\').") Tj\n";
        }

        $content .= "0 -56 Td\n(Fac-simile privo di valore amministrativo.) Tj\nET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";
    }
}
