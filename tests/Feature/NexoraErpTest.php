<?php

namespace Tests\Feature;

use App\Livewire\Combos\CombosManager;
use App\Livewire\Crm\CustomersManager;
use App\Livewire\Crm\WhatsAppInbox;
use App\Livewire\Inventory\RawMaterialsManager;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Products\ProductsManager;
use App\Livewire\Settings\SettingsManager;
use App\Livewire\TopBar;
use App\Models\CashRegister;
use App\Models\Combo;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Models\SubRecipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashService;
use App\Services\PosCheckoutService;
use App\Services\ProductionService;
use App\Services\PurchaseService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NexoraErpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_purchase_triggers_cascade_costing_across_sub_recipes_and_pasteles(): void
    {
        $carne = RawMaterial::query()->where('sku', 'MP-CARNE')->firstOrFail();
        $pastelCarne = Product::query()->where('sku', 'PAS-CARNE')->firstOrFail();
        $rellenoCarne = SubRecipe::query()->where('name', 'Relleno Carne Molida Guisada')->firstOrFail();

        $oldPastelCost = (float) $pastelCarne->cost_usd;
        $oldRellenoUnitCost = (float) $rellenoCarne->unit_cost_usd;

        $kg = Unit::query()->where('symbol', 'kg')->firstOrFail();
        $user = User::query()->where('role', 'admin')->firstOrFail();

        /** @var PurchaseService $purchaseService */
        $purchaseService = app(PurchaseService::class);
        $purchaseService->createPurchase([
            'currency' => 'USD',
            'exchange_rate_bs' => 45.5,
            'invoice_number' => 'FAC-TEST-01',
        ], [[
            'purchasable_type' => 'raw_material',
            'purchasable_id' => $carne->id,
            'unit_id' => $kg->id,
            'qty_purchased' => 10, // 10 kg = 10,000 g at $10.00/kg ($0.01/g vs prev $0.0065/g)
            'unit_cost_original' => 10.00,
        ]], $user->id);

        $carne->refresh();
        $rellenoCarne->refresh();
        $pastelCarne->refresh();

        $this->assertEquals(20000.0, (float) $carne->stock_qty);
        $this->assertGreaterThan($oldRellenoUnitCost, (float) $rellenoCarne->unit_cost_usd);
        $this->assertGreaterThan($oldPastelCost, (float) $pastelCarne->cost_usd);
    }

    public function test_production_batch_deducts_dough_and_filling_raw_materials_and_adds_finished_stock(): void
    {
        $pastelCarne = Product::query()->where('sku', 'PAS-CARNE')->firstOrFail();
        $harina = RawMaterial::query()->where('sku', 'MP-HARINA')->firstOrFail();
        $carne = RawMaterial::query()->where('sku', 'MP-CARNE')->firstOrFail();
        $user = User::query()->where('role', 'admin')->firstOrFail();

        $initialPastelStock = (float) $pastelCarne->stock_qty;
        $initialHarinaStock = (float) $harina->stock_qty;
        $initialCarneStock = (float) $carne->stock_qty;

        /** @var ProductionService $productionService */
        $productionService = app(ProductionService::class);
        $batch = $productionService->executeBatch($pastelCarne, 50, 50, 0, $user->id, 'Lote prueba');

        $pastelCarne->refresh();
        $harina->refresh();
        $carne->refresh();

        $this->assertEquals(50, $batch->produced_qty);
        $this->assertEquals($initialPastelStock + 50, (float) $pastelCarne->stock_qty);
        // 50 pasteles = 1 batch of masa (2500g harina) + 1 batch of relleno (2000g carne)
        $this->assertEquals($initialHarinaStock - 2500, (float) $harina->stock_qty);
        $this->assertEquals($initialCarneStock - 2000, (float) $carne->stock_qty);
    }

    public function test_pos_mixed_combo_and_split_payments_calculate_real_profit_and_close_cash(): void
    {
        $user = User::query()->where('role', 'cashier')->firstOrFail();
        $register = CashRegister::query()->firstOrFail();

        /** @var CashService $cashService */
        $cashService = app(CashService::class);
        $session = $cashService->openSession($register->id, $user->id, 1000.00, 20.00);

        $combo = Combo::query()->with('slots.options.product')->firstOrFail();
        $pastelSlot = $combo->slots->firstWhere('required_qty', 3);
        $drinkSlot = $combo->slots->firstWhere('required_qty', 1);

        $carne = Product::query()->where('sku', 'PAS-CARNE')->firstOrFail();
        $pollo = Product::query()->where('sku', 'PAS-POLLO')->firstOrFail();
        $queso = Product::query()->where('sku', 'PAS-QUESO')->firstOrFail();
        $refresco = Product::query()->where('sku', 'BEB-REF355')->firstOrFail();

        $expectedComboCost = round(
            $carne->effectiveUnitCostUsd()
            + $pollo->effectiveUnitCostUsd()
            + $queso->effectiveUnitCostUsd()
            + $refresco->effectiveUnitCostUsd(),
            4
        );

        $usdCash = PaymentMethod::query()->where('code', 'usd_cash')->firstOrFail();
        $pagoMovil = PaymentMethod::query()->where('code', 'pago_movil')->firstOrFail();

        // Combo price is $3.75. Pay $2.00 USD cash + 79.63 Bs Pago Movil (~$1.75 at 45.5 Bs/$)
        /** @var PosCheckoutService $posService */
        $posService = app(PosCheckoutService::class);
        $sale = $posService->checkout(
            $session,
            $user->id,
            [[
                'item_type' => 'combo',
                'combo_id' => $combo->id,
                'quantity' => 1,
                'selections' => [
                    ['combo_slot_id' => $pastelSlot->id, 'product_id' => $carne->id, 'quantity' => 1],
                    ['combo_slot_id' => $pastelSlot->id, 'product_id' => $pollo->id, 'quantity' => 1],
                    ['combo_slot_id' => $pastelSlot->id, 'product_id' => $queso->id, 'quantity' => 1],
                    ['combo_slot_id' => $drinkSlot->id, 'product_id' => $refresco->id, 'quantity' => 1],
                ],
            ]],
            [
                ['payment_method_id' => $usdCash->id, 'amount_original' => 2.00, 'reference_number' => null],
                ['payment_method_id' => $pagoMovil->id, 'amount_original' => 79.63, 'reference_number' => '987654'],
            ]
        );

        $this->assertEquals(3.75, (float) $sale->total_usd);
        $this->assertEquals($expectedComboCost, (float) $sale->total_cost_usd);
        $this->assertEquals(round(3.75 - $expectedComboCost, 4), (float) $sale->net_profit_usd);

        $this->assertEquals(29, (int) $carne->fresh()->stock_qty);
        $this->assertEquals(29, (int) $pollo->fresh()->stock_qty);
        $this->assertEquals(34, (int) $queso->fresh()->stock_qty);
        $this->assertEquals(47, (int) $refresco->fresh()->stock_qty);

        // Close cash session and check expected cash USD increased by $2.00
        $closed = $cashService->closeSession($session->fresh(), 1000.00, 22.00, 'Turno OK');
        $this->assertEquals('closed', $closed->status);
        $this->assertEquals(0.0, (float) $closed->difference_usd);
        $this->assertEquals(round(3.75 - $expectedComboCost, 4), (float) $closed->estimated_profit_usd);
    }

    public function test_authenticated_pages_render_successfully_for_admin(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/pos')->assertOk();
        $this->actingAs($admin)->get('/caja')->assertOk();
        $this->actingAs($admin)->get('/materia-prima')->assertOk();
        $this->actingAs($admin)->get('/compras')->assertOk();
        $this->actingAs($admin)->get('/sub-recetas')->assertOk();
        $this->actingAs($admin)->get('/productos')->assertOk();
        $this->actingAs($admin)->get('/produccion')->assertOk();
        $this->actingAs($admin)->get('/combos')->assertOk();
        $this->actingAs($admin)->get('/reportes')->assertOk();
        $this->actingAs($admin)->get('/configuracion')->assertOk();
    }

    public function test_livewire_topbar_currency_toggle_and_pos_checkout_flow(): void
    {
        $cashier = User::query()->where('role', 'cashier')->firstOrFail();
        $register = CashRegister::query()->firstOrFail();
        app(CashService::class)->openSession($register->id, $cashier->id, 500, 10);

        $this->actingAs($cashier);

        Livewire::test(TopBar::class)
            ->call('setCurrency', 'VES')
            ->assertSet('displayCurrency', 'VES')
            ->assertDispatched('currency-changed');

        $pastelQueso = Product::query()->where('sku', 'PAS-QUESO')->firstOrFail();
        $usdCash = PaymentMethod::query()->where('code', 'usd_cash')->firstOrFail();

        Livewire::test(PosTerminal::class)
            ->call('addProduct', $pastelQueso->id)
            ->set('payments.0.payment_method_id', $usdCash->id)
            ->set('payments.0.amount_original', '1.00')
            ->call('completeSale')
            ->assertSet('cart', [])
            ->assertDispatched('notify');

        $this->assertEquals(34, (int) $pastelQueso->fresh()->stock_qty);
    }

    public function test_cashier_cannot_access_admin_reports_or_settings(): void
    {
        $cashier = User::query()->where('role', 'cashier')->firstOrFail();

        $this->actingAs($cashier)->get('/reportes')->assertForbidden();
        $this->actingAs($cashier)->get('/configuracion')->assertForbidden();
        $this->actingAs($cashier)->get('/pos')->assertOk();
    }

    public function test_combos_manager_can_create_and_edit_combo(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $carne = Product::query()->where('sku', 'PAS-CARNE')->firstOrFail();
        $refresco = Product::query()->where('sku', 'BEB-REF355')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(CombosManager::class)
            ->call('openCreate')
            ->set('name', 'Combo Test')
            ->set('promo_price_usd', '4.50')
            ->set("comboSlots.0.selected_products.{$carne->id}", true)
            ->set("comboSlots.0.extras.{$carne->id}", '')
            ->call('addSlot')
            ->set("comboSlots.1.selected_products.{$refresco->id}", true)
            ->call('save')
            ->assertHasNoErrors();

        $combo = Combo::query()->where('name', 'Combo Test')->firstOrFail();
        $this->assertEquals(2, $combo->slots()->count());

        Livewire::test(CombosManager::class)
            ->call('openEdit', $combo->id)
            ->set('promo_price_usd', '5.00')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_settings_manager_can_edit_existing_user(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $cashier = User::query()->where('role', 'cashier')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(SettingsManager::class)
            ->call('editUser', $cashier->id)
            ->set('edit_user_name', 'Cajero Turno Tarde')
            ->set('edit_user_email', 'cajero.tarde@nexora.centralces.pro')
            ->set('edit_user_password', 'NuevaClave123')
            ->set('edit_user_role', 'supervisor')
            ->call('updateUser')
            ->assertHasNoErrors()
            ->assertSet('showEditUserModal', false);

        $cashier->refresh();
        $this->assertEquals('Cajero Turno Tarde', $cashier->name);
        $this->assertEquals('cajero.tarde@nexora.centralces.pro', $cashier->email);
        $this->assertEquals('supervisor', $cashier->role);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NuevaClave123', $cashier->password));
    }

    public function test_evolution_webhook_auto_replies_menu_and_links_customer_sale(): void
    {
        Setting::setValue('evolution_api_url', 'https://evo.centralces.pro', 'whatsapp');
        Setting::setValue('evolution_api_key', 'test-secret-key', 'whatsapp');
        Setting::setValue('evolution_instance', 'nexora', 'whatsapp');
        Setting::setValue('evolution_webhook_secret', 'wh-secret', 'whatsapp');

        Http::fake([
            'https://evo.centralces.pro/message/sendText/*' => Http::response([
                'key' => ['id' => 'OUT-MSG-001'],
            ], 200),
        ]);

        // Unauthorized webhook secret should be rejected
        $this->postJson('/api/whatsapp/webhook?secret=wrong', [])->assertUnauthorized();

        // Valid Evolution API MESSAGES_UPSERT payload
        $this->postJson('/api/whatsapp/webhook?secret=wh-secret', [
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'remoteJid' => '584141234567@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'IN-MSG-001',
                ],
                'pushName' => 'María Pérez',
                'message' => [
                    'conversation' => 'Hola, me envías el menú por favor?',
                ],
            ],
        ])->assertOk()->assertJsonPath('processed', true);

        $customer = Customer::query()->where('phone', '584141234567')->firstOrFail();
        $this->assertEquals('María Pérez', $customer->name);

        $conv = Conversation::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(2, $conv->messages()->count()); // inbound + auto-reply menu

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $this->actingAs($admin);
        $this->get('/whatsapp')->assertOk();
        $this->get('/clientes')->assertOk();

        // Test replying from WhatsAppInbox and linking sale in POS
        Livewire::test(WhatsAppInbox::class)
            ->call('selectConversation', $conv->id)
            ->set('replyText', 'Con gusto, ¿de qué sabores deseas tus pasteles?')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertEquals(3, $conv->messages()->count());

        $register = CashRegister::query()->firstOrFail();
        $session = app(CashService::class)->openSession($register->id, $admin->id, 100, 10);
        $pastel = Product::query()->where('sku', 'PAS-CARNE')->firstOrFail();
        $usdCash = PaymentMethod::query()->where('code', 'usd_cash')->firstOrFail();

        $sale = app(PosCheckoutService::class)->checkout(
            $session,
            $admin->id,
            [['item_type' => 'product', 'product_id' => $pastel->id, 'quantity' => 2]],
            [['payment_method_id' => $usdCash->id, 'amount_original' => 2.50]],
            0,
            0,
            0,
            null,
            $customer->id,
            'whatsapp'
        );

        $customer->refresh();
        $this->assertEquals('whatsapp', $sale->channel);
        $this->assertEquals(1, $customer->total_orders);
        $this->assertEquals(2.50, (float) $customer->total_spent_usd);

        Livewire::test(CustomersManager::class)
            ->call('openCreate')
            ->set('name', 'Carlos Ruiz')
            ->set('phone', '04129998877')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Customer::query()->where('phone', '584129998877')->exists());
    }

    public function test_can_edit_initial_cost_before_first_sale_and_delete_unused_entities(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $this->actingAs($admin);

        // 1. Create a resale product with $0.00 initial cost, then fix initial cost to $1.10 before any sale
        Livewire::test(ProductsManager::class)
            ->call('openCreate')
            ->set('name', 'Malta 1.5L')
            ->set('sku', 'MALT-TEST')
            ->set('type', 'resale')
            ->set('sale_price_usd', '2.00')
            ->set('initial_stock_qty', '10')
            ->set('resale_cost_usd', '0')
            ->call('save')
            ->assertHasNoErrors();

        $malta = Product::query()->where('sku', 'MALT-TEST')->firstOrFail();

        Livewire::test(ProductsManager::class)
            ->call('openEdit', $malta->id)
            ->assertSet('hasSales', false)
            ->set('resale_cost_usd', '1.10')
            ->call('save')
            ->assertHasNoErrors();

        $malta->refresh();
        $this->assertEquals(1.10, (float) $malta->avg_stock_cost_usd);

        // 2. Make first sale of Malta 1.5L -> initial cost editing becomes locked & cannot be deleted
        $register = CashRegister::query()->firstOrFail();
        $session = app(CashService::class)->openSession($register->id, $admin->id, 100, 10);
        $usdCash = PaymentMethod::query()->where('code', 'usd_cash')->firstOrFail();

        app(PosCheckoutService::class)->checkout(
            $session,
            $admin->id,
            [['item_type' => 'product', 'product_id' => $malta->id, 'quantity' => 1]],
            [['payment_method_id' => $usdCash->id, 'amount_original' => 2.00]]
        );

        Livewire::test(ProductsManager::class)
            ->call('openEdit', $malta->id)
            ->assertSet('hasSales', true)
            ->set('resale_cost_usd', '9.99')
            ->call('save');

        // Still 1.10 because it already had a sale
        $this->assertEquals(1.10, (float) $malta->fresh()->avg_stock_cost_usd);

        // Attempting to delete sold product is blocked
        Livewire::test(ProductsManager::class)->call('deleteProduct', $malta->id);
        $this->assertNotNull(Product::query()->find($malta->id));

        // 3. Unused product, combo, and raw material CAN be deleted cleanly
        $unusedProduct = Product::query()->create([
            'name' => 'Temporal Borrable',
            'type' => 'resale',
            'sale_price_usd' => 1.0,
        ]);
        Livewire::test(ProductsManager::class)->call('deleteProduct', $unusedProduct->id);
        $this->assertNull(Product::query()->find($unusedProduct->id));

        $unusedCombo = Combo::query()->create(['name' => 'Combo Borrable', 'promo_price_usd' => 2.5]);
        Livewire::test(CombosManager::class)->call('deleteCombo', $unusedCombo->id);
        $this->assertNull(Combo::query()->find($unusedCombo->id));

        $gram = Unit::query()->where('symbol', 'g')->firstOrFail();
        $unusedRm = RawMaterial::query()->create([
            'name' => 'Insumo Borrable',
            'base_unit_id' => $gram->id,
            'default_purchase_unit_id' => $gram->id,
        ]);
        Livewire::test(RawMaterialsManager::class)->call('deleteRawMaterial', $unusedRm->id);
        $this->assertNull(RawMaterial::query()->find($unusedRm->id));
    }
}
