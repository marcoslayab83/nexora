<?php

namespace App\Livewire\Crm;

use App\Models\Conversation;
use App\Models\Customer;
use App\Support\CurrencyHelper;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Clientes CRM')]
class CustomersManager extends Component
{
    public string $currency = 'USD';

    public float $rate = 1.0;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->refreshCurrency();
    }

    #[On('currency-changed')]
    #[On('rate-updated')]
    public function refreshCurrency(): void
    {
        $this->currency = CurrencyHelper::displayCurrency();
        $this->rate = CurrencyHelper::activeRate();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'address', 'notes']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $c = Customer::query()->findOrFail($id);
        $this->editingId = $c->id;
        $this->name = (string) ($c->name ?? '');
        $this->phone = $c->phone;
        $this->address = (string) ($c->address ?? '');
        $this->notes = (string) ($c->notes ?? '');
        $this->showModal = true;
    }

    public function save(): void
    {
        $normalized = Customer::normalizePhone($this->phone);
        $this->phone = $normalized;

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:30', 'unique:customers,phone'.($this->editingId ? ','.$this->editingId : '')],
        ]);

        if ($this->editingId) {
            Customer::query()->findOrFail($this->editingId)->update([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address ?: null,
                'notes' => $this->notes ?: null,
            ]);
        } else {
            Customer::query()->create([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address ?: null,
                'notes' => $this->notes ?: null,
            ]);
        }

        $this->showModal = false;
        $this->dispatch('notify', type: 'success', message: 'Cliente guardado correctamente.');
    }

    public function openWhatsAppChat(int $customerId)
    {
        $customer = Customer::query()->findOrFail($customerId);
        $conversation = Conversation::query()->firstOrCreate(
            ['remote_jid' => $customer->phone.'@s.whatsapp.net'],
            ['customer_id' => $customer->id, 'status' => 'human']
        );

        return $this->redirect(route('whatsapp.inbox', ['conversation_id' => $conversation->id]));
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%"))
            ->orderByDesc('last_order_at')
            ->orderBy('name')
            ->get();

        return view('livewire.crm.customers-manager', [
            'customers' => $customers,
        ]);
    }
}
