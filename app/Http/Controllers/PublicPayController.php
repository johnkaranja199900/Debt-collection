<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer-facing payment page (STK Push wiring lives in MpesaService; this is
 * the safe read-only surface until a live Daraja account is configured).
 */
class PublicPayController extends Controller
{
    public function show(string $publicId): View
    {
        $invoice = Invoice::where('public_id', $publicId)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->firstOrFail();

        return view('payments.public', [
            'invoice' => $invoice->load('customer'),
            'mpesaReady' => \App\Models\MpesaSetting::where('is_active', true)->exists(),
        ]);
    }
}
