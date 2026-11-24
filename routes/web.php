<?php

use App\Http\Controllers\AuthController;
use App\Livewire\Cash\CashManager;
use App\Livewire\Combos\CombosManager;
use App\Livewire\Dashboard;
use App\Livewire\Inventory\RawMaterialsManager;
use App\Livewire\Pos\PosTerminal;
use App\Livewire\Production\ProductionManager;
use App\Livewire\Products\ProductsManager;
use App\Livewire\Purchases\PurchaseManager;
use App\Livewire\Recipes\SubRecipesManager;
use App\Livewire\Reports\ProfitabilityReport;
use App\Livewire\Settings\SettingsManager;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware(['auth', 'role:admin,supervisor,cashier,kitchen'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/pos', PosTerminal::class)->name('pos');
    Route::get('/caja', CashManager::class)->name('cash');

    Route::middleware('role:admin,supervisor,kitchen')->group(function () {
        Route::get('/materia-prima', RawMaterialsManager::class)->name('raw-materials');
        Route::get('/compras', PurchaseManager::class)->name('purchases');
        Route::get('/sub-recetas', SubRecipesManager::class)->name('sub-recipes');
        Route::get('/productos', ProductsManager::class)->name('products');
        Route::get('/produccion', ProductionManager::class)->name('production');
        Route::get('/combos', CombosManager::class)->name('combos');
    });

    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/reportes', ProfitabilityReport::class)->name('reports');
        Route::get('/configuracion', SettingsManager::class)->name('settings');
    });
});
