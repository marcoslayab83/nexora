<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Combo;
use App\Models\ComboSlot;
use App\Models\ComboSlotOption;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductSubRecipe;
use App\Models\RawMaterial;
use App\Models\Setting;
use App\Models\SubRecipe;
use App\Models\SubRecipeItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default Admin & Cashier Users (idempotent)
        User::query()->firstOrCreate(
            ['email' => 'admin@nexora.centralces.pro'],
            [
                'name' => 'Administrador Nexora',
                'password' => Hash::make('Nexora2026*'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'caja@nexora.centralces.pro'],
            [
                'name' => 'Cajero Principal',
                'password' => Hash::make('Caja2026*'),
                'role' => 'cashier',
                'is_active' => true,
            ]
        );

        // 2. Global Settings
        $defaults = [
            ['key' => 'business_name', 'value' => 'Nexora Pastelería', 'group' => 'general'],
            ['key' => 'business_slogan', 'value' => 'Aquí empieza el futuro de tu negocio', 'group' => 'general'],
            ['key' => 'costing_method', 'value' => 'weighted_average', 'group' => 'costing'],
            ['key' => 'allow_negative_stock', 'value' => '0', 'group' => 'inventory'],
            ['key' => 'bcv_auto_sync', 'value' => '1', 'group' => 'currency'],
            ['key' => 'default_display_currency', 'value' => 'USD', 'group' => 'currency'],
            ['key' => 'evolution_api_url', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'evolution_api_key', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'evolution_instance', 'value' => 'nexora', 'group' => 'whatsapp'],
            ['key' => 'evolution_webhook_secret', 'value' => 'nexora-wh-2026', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_auto_reply_menu', 'value' => '1', 'group' => 'whatsapp'],
        ];
        foreach ($defaults as $setting) {
            Setting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }

        // 3. Measurement Units (Base units + Conversions)
        $gram = Unit::query()->firstOrCreate(
            ['symbol' => 'g'],
            ['name' => 'Gramo', 'base_unit_id' => null, 'conversion_factor' => 1]
        );
        $kg = Unit::query()->firstOrCreate(
            ['symbol' => 'kg'],
            ['name' => 'Kilogramo', 'base_unit_id' => $gram->id, 'conversion_factor' => 1000]
        );
        $ml = Unit::query()->firstOrCreate(
            ['symbol' => 'ml'],
            ['name' => 'Mililitro', 'base_unit_id' => null, 'conversion_factor' => 1]
        );
        $liter = Unit::query()->firstOrCreate(
            ['symbol' => 'l'],
            ['name' => 'Litro', 'base_unit_id' => $ml->id, 'conversion_factor' => 1000]
        );
        $und = Unit::query()->firstOrCreate(
            ['symbol' => 'und'],
            ['name' => 'Unidad', 'base_unit_id' => null, 'conversion_factor' => 1]
        );

        // 4. Active BCV Rate (only seed if none exists)
        if (! ExchangeRate::query()->where('is_active', true)->exists()) {
            ExchangeRate::query()->create([
                'currency_code' => 'USD',
                'rate_bs' => 45.500000,
                'source' => 'manual',
                'is_active' => true,
                'effective_at' => now(),
                'notes' => 'Tasa inicial BCV de referencia',
            ]);
        }

        // 5. Cash Register & Payment Methods
        CashRegister::query()->firstOrCreate(
            ['name' => 'Caja Principal'],
            ['location' => 'Mostrador', 'is_active' => true]
        );

        $methods = [
            ['code' => 'bs_cash', 'name' => 'Bs. Efectivo', 'currency' => 'VES', 'is_cash' => true, 'requires_reference' => false, 'sort_order' => 1],
            ['code' => 'pago_movil', 'name' => 'Pago Móvil', 'currency' => 'VES', 'is_cash' => false, 'requires_reference' => true, 'sort_order' => 2],
            ['code' => 'pos_card', 'name' => 'Punto de Venta', 'currency' => 'VES', 'is_cash' => false, 'requires_reference' => true, 'sort_order' => 3],
            ['code' => 'usd_cash', 'name' => 'Divisas ($) Efectivo', 'currency' => 'USD', 'is_cash' => true, 'requires_reference' => false, 'sort_order' => 4],
            ['code' => 'zelle', 'name' => 'Zelle ($)', 'currency' => 'USD', 'is_cash' => false, 'requires_reference' => true, 'sort_order' => 5],
        ];
        foreach ($methods as $method) {
            PaymentMethod::query()->firstOrCreate(['code' => $method['code']], $method);
        }

        // 6. Initial Demo Catalog (only if DB has no raw materials or products yet)
        if (RawMaterial::query()->count() === 0 && Product::query()->count() === 0) {
            Supplier::query()->create([
                'name' => 'Distribuidora Central Alimentos C.A.',
                'tax_id' => 'J-40123456-7',
                'phone' => '0414-0000000',
            ]);

            $harina = RawMaterial::query()->create([
                'sku' => 'MP-HARINA',
                'name' => 'Harina de Trigo Todo Uso',
                'base_unit_id' => $gram->id,
                'default_purchase_unit_id' => $kg->id,
                'stock_qty' => 25000,
                'min_stock_qty' => 5000,
                'avg_cost_usd' => 0.001400, // $1.40 / kg
                'last_cost_usd' => 0.001400,
            ]);
            $aceite = RawMaterial::query()->create([
                'sku' => 'MP-ACEITE',
                'name' => 'Aceite Vegetal Comestible',
                'base_unit_id' => $ml->id,
                'default_purchase_unit_id' => $liter->id,
                'stock_qty' => 15000,
                'min_stock_qty' => 3000,
                'avg_cost_usd' => 0.002200, // $2.20 / L
                'last_cost_usd' => 0.002200,
            ]);
            $sal = RawMaterial::query()->create([
                'sku' => 'MP-SAL',
                'name' => 'Sal Refinada',
                'base_unit_id' => $gram->id,
                'default_purchase_unit_id' => $kg->id,
                'stock_qty' => 5000,
                'min_stock_qty' => 1000,
                'avg_cost_usd' => 0.000800, // $0.80 / kg
                'last_cost_usd' => 0.000800,
            ]);
            $carne = RawMaterial::query()->create([
                'sku' => 'MP-CARNE',
                'name' => 'Carne Molida de Res',
                'base_unit_id' => $gram->id,
                'default_purchase_unit_id' => $kg->id,
                'stock_qty' => 10000,
                'min_stock_qty' => 2000,
                'avg_cost_usd' => 0.006500, // $6.50 / kg
                'last_cost_usd' => 0.006500,
            ]);
            $pollo = RawMaterial::query()->create([
                'sku' => 'MP-POLLO',
                'name' => 'Pechuga de Pollo',
                'base_unit_id' => $gram->id,
                'default_purchase_unit_id' => $kg->id,
                'stock_qty' => 10000,
                'min_stock_qty' => 2000,
                'avg_cost_usd' => 0.005200, // $5.20 / kg
                'last_cost_usd' => 0.005200,
            ]);
            $queso = RawMaterial::query()->create([
                'sku' => 'MP-QUESO',
                'name' => 'Queso Blanco Semiduro',
                'base_unit_id' => $gram->id,
                'default_purchase_unit_id' => $kg->id,
                'stock_qty' => 8000,
                'min_stock_qty' => 1500,
                'avg_cost_usd' => 0.005800, // $5.80 / kg
                'last_cost_usd' => 0.005800,
            ]);
            $condimentos = RawMaterial::query()->create([
                'sku' => 'MP-COND',
                'name' => 'Aliños y Condimentos (Ajo/Cebolla/Pimentón)',
                'base_unit_id' => $gram->id,
                'default_purchase_unit_id' => $kg->id,
                'stock_qty' => 5000,
                'min_stock_qty' => 1000,
                'avg_cost_usd' => 0.002500, // $2.50 / kg
                'last_cost_usd' => 0.002500,
            ]);

            // Sub-recipes: 1 Dough + 3 Fillings
            $masa = SubRecipe::query()->create([
                'name' => 'Masa Tradicional de Pastel',
                'type' => 'dough',
                'yield_units' => 50,
                'total_cost_usd' => 4.440000,
                'unit_cost_usd' => 0.088800,
            ]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $masa->id, 'raw_material_id' => $harina->id, 'unit_id' => $kg->id, 'quantity' => 2.5, 'base_quantity' => 2500, 'line_cost_usd' => 3.500000]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $masa->id, 'raw_material_id' => $aceite->id, 'unit_id' => $ml->id, 'quantity' => 400, 'base_quantity' => 400, 'line_cost_usd' => 0.880000]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $masa->id, 'raw_material_id' => $sal->id, 'unit_id' => $gram->id, 'quantity' => 75, 'base_quantity' => 75, 'line_cost_usd' => 0.060000]);

            $rellenoCarne = SubRecipe::query()->create([
                'name' => 'Relleno Carne Molida Guisada',
                'type' => 'filling',
                'yield_units' => 50,
                'total_cost_usd' => 13.880000,
                'unit_cost_usd' => 0.277600,
            ]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $rellenoCarne->id, 'raw_material_id' => $carne->id, 'unit_id' => $kg->id, 'quantity' => 2.0, 'base_quantity' => 2000, 'line_cost_usd' => 13.000000]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $rellenoCarne->id, 'raw_material_id' => $condimentos->id, 'unit_id' => $gram->id, 'quantity' => 352, 'base_quantity' => 352, 'line_cost_usd' => 0.880000]);

            $rellenoPollo = SubRecipe::query()->create([
                'name' => 'Relleno Pollo Esmechado',
                'type' => 'filling',
                'yield_units' => 50,
                'total_cost_usd' => 11.275000,
                'unit_cost_usd' => 0.225500,
            ]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $rellenoPollo->id, 'raw_material_id' => $pollo->id, 'unit_id' => $kg->id, 'quantity' => 2.0, 'base_quantity' => 2000, 'line_cost_usd' => 10.400000]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $rellenoPollo->id, 'raw_material_id' => $condimentos->id, 'unit_id' => $gram->id, 'quantity' => 350, 'base_quantity' => 350, 'line_cost_usd' => 0.875000]);

            $rellenoQueso = SubRecipe::query()->create([
                'name' => 'Relleno Queso Blanco Rallado',
                'type' => 'filling',
                'yield_units' => 50,
                'total_cost_usd' => 10.440000,
                'unit_cost_usd' => 0.208800,
            ]);
            SubRecipeItem::query()->create(['sub_recipe_id' => $rellenoQueso->id, 'raw_material_id' => $queso->id, 'unit_id' => $kg->id, 'quantity' => 1.8, 'base_quantity' => 1800, 'line_cost_usd' => 10.440000]);

            // Categories & Products
            $catPasteles = Category::query()->create(['name' => 'Pasteles', 'slug' => 'pasteles', 'sort_order' => 1]);
            $catBebidas = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas', 'sort_order' => 2]);

            $pastelCarne = Product::query()->create([
                'category_id' => $catPasteles->id,
                'sku' => 'PAS-CARNE',
                'name' => 'Pastel de Carne Molida',
                'type' => 'manufactured',
                'stock_qty' => 30,
                'min_stock_qty' => 10,
                'cost_usd' => 0.366400,
                'avg_stock_cost_usd' => 0.366400,
                'sale_price_usd' => 1.2500,
            ]);
            ProductSubRecipe::query()->create(['product_id' => $pastelCarne->id, 'sub_recipe_id' => $masa->id, 'role' => 'dough', 'portions_factor' => 1]);
            ProductSubRecipe::query()->create(['product_id' => $pastelCarne->id, 'sub_recipe_id' => $rellenoCarne->id, 'role' => 'filling', 'portions_factor' => 1]);

            $pastelPollo = Product::query()->create([
                'category_id' => $catPasteles->id,
                'sku' => 'PAS-POLLO',
                'name' => 'Pastel de Pollo',
                'type' => 'manufactured',
                'stock_qty' => 30,
                'min_stock_qty' => 10,
                'cost_usd' => 0.314300,
                'avg_stock_cost_usd' => 0.314300,
                'sale_price_usd' => 1.2000,
            ]);
            ProductSubRecipe::query()->create(['product_id' => $pastelPollo->id, 'sub_recipe_id' => $masa->id, 'role' => 'dough', 'portions_factor' => 1]);
            ProductSubRecipe::query()->create(['product_id' => $pastelPollo->id, 'sub_recipe_id' => $rellenoPollo->id, 'role' => 'filling', 'portions_factor' => 1]);

            $pastelQueso = Product::query()->create([
                'category_id' => $catPasteles->id,
                'sku' => 'PAS-QUESO',
                'name' => 'Pastel de Queso',
                'type' => 'manufactured',
                'stock_qty' => 35,
                'min_stock_qty' => 10,
                'cost_usd' => 0.297600,
                'avg_stock_cost_usd' => 0.297600,
                'sale_price_usd' => 1.0000,
            ]);
            ProductSubRecipe::query()->create(['product_id' => $pastelQueso->id, 'sub_recipe_id' => $masa->id, 'role' => 'dough', 'portions_factor' => 1]);
            ProductSubRecipe::query()->create(['product_id' => $pastelQueso->id, 'sub_recipe_id' => $rellenoQueso->id, 'role' => 'filling', 'portions_factor' => 1]);

            $refresco = Product::query()->create([
                'category_id' => $catBebidas->id,
                'sku' => 'BEB-REF355',
                'name' => 'Refresco Lata 355ml',
                'type' => 'resale',
                'stock_qty' => 48,
                'min_stock_qty' => 12,
                'cost_usd' => 0.550000,
                'avg_stock_cost_usd' => 0.550000,
                'last_cost_usd' => 0.550000,
                'sale_price_usd' => 1.2000,
            ]);

            $malta = Product::query()->create([
                'category_id' => $catBebidas->id,
                'sku' => 'BEB-MALTA',
                'name' => 'Malta Botella 250ml',
                'type' => 'resale',
                'stock_qty' => 36,
                'min_stock_qty' => 12,
                'cost_usd' => 0.480000,
                'avg_stock_cost_usd' => 0.480000,
                'last_cost_usd' => 0.480000,
                'sale_price_usd' => 1.0000,
            ]);

            // Flexible Mix-and-Match Combo: 3 Pasteles (any flavor) + 1 Bebida
            $combo = Combo::query()->create([
                'name' => 'Combo Trío Pastelero',
                'description' => 'Elige 3 pasteles a tu gusto (Carne, Pollo o Queso) + 1 bebida fría',
                'promo_price_usd' => 3.7500,
                'is_active' => true,
            ]);

            $slotPasteles = ComboSlot::query()->create([
                'combo_id' => $combo->id,
                'label' => 'Elige tus 3 Pasteles',
                'required_qty' => 3,
                'sort_order' => 1,
            ]);
            ComboSlotOption::query()->create(['combo_slot_id' => $slotPasteles->id, 'product_id' => $pastelCarne->id, 'extra_price_usd' => 0]);
            ComboSlotOption::query()->create(['combo_slot_id' => $slotPasteles->id, 'product_id' => $pastelPollo->id, 'extra_price_usd' => 0]);
            ComboSlotOption::query()->create(['combo_slot_id' => $slotPasteles->id, 'product_id' => $pastelQueso->id, 'extra_price_usd' => 0]);

            $slotBebida = ComboSlot::query()->create([
                'combo_id' => $combo->id,
                'label' => 'Elige tu Bebida',
                'required_qty' => 1,
                'sort_order' => 2,
            ]);
            ComboSlotOption::query()->create(['combo_slot_id' => $slotBebida->id, 'product_id' => $refresco->id, 'extra_price_usd' => 0]);
            ComboSlotOption::query()->create(['combo_slot_id' => $slotBebida->id, 'product_id' => $malta->id, 'extra_price_usd' => 0]);
        }
    }
}
