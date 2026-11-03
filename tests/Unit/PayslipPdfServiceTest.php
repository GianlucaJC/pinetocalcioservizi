<?php

namespace Tests\Unit;

use App\Exceptions\PayslipTextExtractionException;
use App\Services\PayslipPdfService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PayslipPdfServiceTest extends TestCase
{
    public function test_it_groups_pages_using_tax_codes_extracted_by_poppler(): void
    {
        Process::fake([
            '*' => Process::result("Codice fiscale RSSMRA90A01A000A\fCodice fiscale RSSMRA90A01A000A\fCodice fiscale VRDLGI88A01A000B\f"),
        ]);

        $segments = app(PayslipPdfService::class)->analyze('source.pdf');

        $this->assertSame([
            [
                'tax_code' => 'RSSMRA90A01A000A',
                'first_page' => 1,
                'last_page' => 2,
                'issue' => null,
            ],
            [
                'tax_code' => 'VRDLGI88A01A000B',
                'first_page' => 3,
                'last_page' => 3,
                'issue' => null,
            ],
        ], $segments);
        Process::assertRan(fn ($process) => in_array('pdftotext', $process->command, true));
    }

    public function test_it_throws_a_clear_exception_when_poppler_cannot_extract_text(): void
    {
        Process::fake([
            '*' => Process::result('', 'pdftotext not found', 1),
        ]);

        $this->expectException(PayslipTextExtractionException::class);
        $this->expectExceptionMessage('Verifica che Poppler pdftotext sia installato');

        app(PayslipPdfService::class)->analyze('source.pdf');
    }
}
