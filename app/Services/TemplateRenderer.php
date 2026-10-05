<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Debt;
use App\Models\MessageTemplate;
use RuntimeException;

/**
 * Renders {{variable}} templates against real record data only.
 * Unknown variables throw so we never send half-baked financial messages.
 */
class TemplateRenderer
{
    public function forDebt(MessageTemplate $template, Debt $debt): string
    {
        $business = Business::current();
        $customer = $debt->customer;
        $invoice = $debt->invoice;

        if (! $customer || ! $invoice) {
            throw new RuntimeException('Reminder is missing customer or invoice data.');
        }

        return $template->render([
            'customer_name' => $customer->name,
            'invoice_number' => $invoice->invoice_number,
            'balance' => number_format((float) $debt->balance, 2),
            'due_date' => $debt->due_date?->format('d M Y'),
            'business_name' => $business?->name ?? 'Our business',
            'payment_link' => url('/pay/'.$invoice->public_id),
            'quotation_number' => $invoice->invoice_number,
            'total' => number_format((float) $invoice->total, 2),
        ]);
    }
}
