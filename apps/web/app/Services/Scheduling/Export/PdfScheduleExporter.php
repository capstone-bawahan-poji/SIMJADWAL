<?php

namespace App\Services\Scheduling\Export;

use App\Exceptions\Shared\ApiException;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Renders resources/views/pdf/schedule.blade.php and has Gotenberg (headless Chromium, see
 * compose.yaml) print it on A4 landscape. Gotenberg down = 503 PDF_UNAVAILABLE.
 */
readonly class PdfScheduleExporter implements ScheduleExporter
{
    public function __construct(
        private ViewFactory $views,
        private HttpFactory $http,
    ) {}

    public function extension(): string
    {
        return 'pdf';
    }

    public function contentType(): string
    {
        return 'application/pdf';
    }

    public function render(ScheduleExport $export): string
    {
        $html = $this->views->make('pdf.schedule', ['export' => $export, 'matrix' => $export->matrix()])->render();

        try {
            $response = $this->http->timeout(30)
                ->attach('files', $html, 'index.html')
                ->post(rtrim(config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                    'paperWidth' => '11.7',
                    'paperHeight' => '8.27',
                    'marginTop' => '0.4',
                    'marginBottom' => '0.4',
                    'marginLeft' => '0.4',
                    'marginRight' => '0.4',
                    'printBackground' => 'true',
                ]);
        } catch (ConnectionException $e) {
            report($e);

            throw ApiException::pdfUnavailable();
        }

        if (! $response->successful()) {
            report(new \RuntimeException("Gotenberg answered {$response->status()}: {$response->body()}"));

            throw ApiException::pdfUnavailable();
        }

        return $response->body();
    }
}
