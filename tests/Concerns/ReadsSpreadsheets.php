<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * İndirilen Excel çıktısını okumak için (rapor testleri).
 */
trait ReadsSpreadsheets
{
    protected function sheet(TestResponse $response): Worksheet
    {
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);

        return IOFactory::load($base->getFile()->getPathname())->getActiveSheet();
    }

    protected function sheetText(TestResponse $response): string
    {
        return collect($this->sheet($response)->toArray(null, false, false))->flatten()->filter()->implode(' | ');
    }
}
