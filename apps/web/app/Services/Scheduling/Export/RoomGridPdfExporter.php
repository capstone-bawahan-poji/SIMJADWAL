<?php

namespace App\Services\Scheduling\Export;

use App\Exceptions\Shared\ApiException;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * One page set per building (a whole campus does not fit one sheet), then the clash list.
 * Rendered by resources/views/pdf/room-grid.blade.php and printed by Gotenberg on A3 landscape.
 */
readonly class RoomGridPdfExporter implements RoomGridExporter
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

    public function render(RoomGridExport $export): string
    {
        $html = $this->views->make('pdf.room-grid', ['export' => $export, 'grid' => $export->grid])->render();
        $footer = $this->views->make('pdf.schedule-footer')->render();

        try {
            $response = $this->http->timeout(60)
                ->attach('files', $html, 'index.html')
                ->attach('files', $footer, 'footer.html')
                ->post(rtrim(config('services.gotenberg.url'), '/').'/forms/chromium/convert/html', [
                    'paperWidth' => '16.54',
                    'paperHeight' => '11.69',
                    'marginTop' => '0.4',
                    'marginBottom' => '0.6',
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
