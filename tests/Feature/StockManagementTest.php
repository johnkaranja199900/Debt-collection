<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FinancialService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $cement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.ke',
            'password' => 'Password123!',
            'role' => 'owner',
            'is_active' => true,
        ]);

        \App\Models\Business::create([
            'name' => 'Mama Mboga Supplies',
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'invoice_prefix' => 'INV',
            'quotation_prefix' => 'QTN',
        ]);

        $this->cement = Product::create([
            'sku' => 'CEM-01',
            'name' => 'Cement Bamburi 50kg',
            'type' => 'product',
            'cost_price' => 450,
            'selling_price' => 600,
            'tax_rate' => 0,
            'stock_quantity' => 100,
            'low_stock_threshold' => 20,
            'unit' => 'bags',
            'status' => 'active',
        ]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'customer_code' => 'CUS-001',
            'name' => 'John Test',
            'phone' => '0700000001',
            'payment_terms' => 30,
            'status' => 'active',
        ]);
    }

    public function test_sale_decrements_stock_and_records_ledger_movement(): void
    {
        $this->actingAs($this->owner);

        $sale = app(FinancialService::class)->createSale(
            customerId: $this->customer()->id,
            lines: [['product_id' => $this->cement->id, 'quantity' => 20]],
            meta: ['sale_date' => now()->toDateString(), 'discount' => 0],
            createInvoice: false,
            dueDays: 30,
        );

        $this->assertEquals('80.00', (string) $this->cement->fresh()->stock_quantity);

        $movement = StockMovement::where('product_id', $this->cement->id)->sole();
        $this->assertSame(StockMovement::SALE_OUT, $movement->type);
        $this->assertSame(-1, $movement->direction);
        $this->assertEquals('20.00', (string) $movement->quantity);
        $this->assertEquals($sale->id, $movement->reference_id);
        $this->assertSame(Sale::class, $movement->reference_type);
    }

    public function test_sale_fails_when_stock_is_insufficient(): void
    {
        $this->actingAs($this->owner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        app(FinancialService::class)->createSale(
            customerId: $this->customer()->id,
            lines: [['product_id' => $this->cement->id, 'quantity' => 150]],
            meta: ['sale_date' => now()->toDateString()],
            createInvoice: false,
            dueDays: 30,
        );
    }

    public function test_failed_sale_rolls_back_stock_and_sale_rows(): void
    {
        $this->actingAs($this->owner);

        try {
            app(FinancialService::class)->createSale(
                customerId: $this->customer()->id,
                lines: [
                    ['product_id' => $this->cement->id, 'quantity' => 50],
                    ['product_id' => $this->cement->id, 'quantity' => 999], // exceeds remaining stock
                ],
                meta: ['sale_date' => now()->toDateString()],
                createInvoice: false,
                dueDays: 30,
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertSame(0, Sale::count());
        $this->assertEquals('100.00', (string) $this->cement->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_restock_endpoint_adds_stock_updates_average_cost_and_audits(): void
    {
        // Weighted-average math check: (100 * 450 + 50 * 500) / 150 = 466.67
        $response = $this->actingAs($this->owner)->post(route('products.restock', $this->cement), [
            'quantity' => 50,
            'unit_cost' => 500,
            'supplier' => 'Sahara Suppliers',
            'reference_no' => 'DN-991',
        ]);

        $response->assertRedirect(route('products.stock', $this->cement));
        $this->assertEquals('150.00', (string) $this->cement->fresh()->stock_quantity);
        $this->assertEquals('466.67', (string) $this->cement->fresh()->cost_price);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockMovement::PURCHASE_IN, $movement->type);
        $this->assertSame(1, $movement->direction);
        $this->assertStringContainsString('Sahara Suppliers', (string) $movement->notes);

        $this->assertDatabaseHas('audit_logs', ['action' => 'stock_purchase_in', 'model_id' => $this->cement->id]);
    }

    public function test_restock_validation_rejects_zero_quantity(): void
    {
        $response = $this->actingAs($this->owner)->post(route('products.restock', $this->cement), [
            'quantity' => 0,
            'unit_cost' => 500,
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertEquals('100.00', (string) $this->cement->fresh()->stock_quantity);
    }

    public function test_adjustment_requires_reason_and_manager_role(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'email' => 'staff@test.ke',
            'password' => 'Password123!', 'role' => 'staff', 'is_active' => true,
        ]);

        // Staff cannot adjust stock.
        $this->actingAs($staff)->post(route('products.adjust-stock', $this->cement), [
            'direction' => 'out', 'quantity' => 5, 'reason' => 'Damaged bags',
        ])->assertForbidden();

        // Missing reason rejected for owner.
        $this->actingAs($this->owner)->post(route('products.adjust-stock', $this->cement), [
            'direction' => 'out', 'quantity' => 5,
        ])->assertSessionHasErrors('reason');

        // Valid decrease works and is audited.
        $this->actingAs($this->owner)->post(route('products.adjust-stock', $this->cement), [
            'direction' => 'out', 'quantity' => 5, 'reason' => 'Damaged in transport',
        ])->assertRedirect(route('products.stock', $this->cement));

        $this->assertEquals('95.00', (string) $this->cement->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->cement->id,
            'type' => StockMovement::ADJUSTMENT,
            'direction' => -1,
            'reason' => 'Damaged in transport',
        ]);
    }

    public function test_cancelling_a_sale_restores_stock_through_the_ledger(): void
    {
        $this->actingAs($this->owner);

        $sale = app(FinancialService::class)->createSale(
            customerId: $this->customer()->id,
            lines: [['product_id' => $this->cement->id, 'quantity' => 30]],
            meta: ['sale_date' => now()->toDateString()],
            createInvoice: false,
            dueDays: 30,
        );
        $this->assertEquals('70.00', (string) $this->cement->fresh()->stock_quantity);

        $this->post(route('sales.cancel', $sale))->assertOk();

        $this->assertEquals('100.00', (string) $this->cement->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->cement->id,
            'type' => StockMovement::SALE_CANCEL_IN,
            'reference_id' => $sale->id,
        ]);
        // Ledger nets back to the opening balance.
        $this->assertEquals('100.00', number_format(app(StockService::class)->ledgerBalance($this->cement->fresh()) + 100, 2));
    }

    public function test_services_have_no_stock_tracking(): void
    {
        $service = Product::create([
            'sku' => 'SRV-01', 'name' => 'Delivery service', 'type' => 'service',
            'cost_price' => 0, 'selling_price' => 500, 'tax_rate' => 0,
            'stock_quantity' => 0, 'low_stock_threshold' => 0, 'unit' => 'trip', 'status' => 'active',
        ]);

        $this->actingAs($this->owner)->post(route('products.restock', $service), [
            'quantity' => 10, 'unit_cost' => 100,
        ])->assertSessionHas('error');

        $this->assertSame(0, StockMovement::where('product_id', $service->id)->count());
    }

    public function test_stock_page_renders_for_authorised_user(): void
    {
        $response = $this->actingAs($this->owner)->get(route('products.stock', $this->cement));

        $response->assertOk()
            ->assertSee('Stock on hand')
            ->assertSee('Record restock')
            ->assertSee('Sales of this product');
    }
}
