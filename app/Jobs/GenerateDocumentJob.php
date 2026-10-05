<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Quotation;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly string $type, private readonly int $id) {}

    public function handle(PdfService $pdf): void
    {
        match ($this->type) {
            'invoice' => ($i = Invoice::find($this->id)) && $pdf->invoice($i),
            'quotation' => ($q = Quotation::find($this->id)) && $pdf->quotation($q),
            default => null,
        };
    }
}
