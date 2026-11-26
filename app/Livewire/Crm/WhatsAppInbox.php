<?php

namespace App\Livewire\Crm;

use App\Models\BusinessAgentSetting;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\WhatsAppOrder;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppOrderDispatchService;
use App\Support\CurrencyHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bandeja WhatsApp CRM')]
class WhatsAppInbox extends Component
{
    public string $search = '';

    public string $filterState = 'all';

    public ?int $selectedConversationId = null;

    public string $replyText = '';

    // Approval form overrides for Human-in-the-Loop operator
    public ?int $approvalOrderId = null;

    public ?int $approvalPaymentMethodId = null;

    public string $approvalPaymentReference = '';

    public string $approvalPaymentAmount = '';

    /** @var array<int, array<string, mixed>> */
    public array $approvalItems = [];

    /** @var array<string, string> */
    public array $approvalDeliveryData = [];

    public ?int $addProductId = null;

    public int $addProductQty = 1;

    // New conversation modal
    public bool $showNewChatModal = false;

    public string $newPhone = '';

    public string $newName = '';

    public string $newMessage = '';

    public function mount(): void
    {
        $this->addProductId = Product::query()->where('is_active', true)->orderBy('name')->value('id');
        $this->approvalPaymentMethodId = PaymentMethod::active()->value('id');

        $requested = request()->integer('conversation_id');
        if ($requested > 0 && Conversation::query()->whereKey($requested)->exists()) {
            $this->selectConversation($requested);

            return;
        }

        $pendingFirst = Conversation::query()
            ->where('agent_state', WhatsAppOrder::STATE_PENDING_STORE_APPROVAL)
            ->latest('last_message_at')
            ->first();

        $first = $pendingFirst ?? Conversation::query()->latest('last_message_at')->first();
        if ($first) {
            $this->selectConversation($first->id);
        }
    }

    public function selectConversation(int $id): void
    {
        $this->selectedConversationId = $id;
        Conversation::query()->whereKey($id)->update(['unread_count' => 0]);
        $this->syncApprovalFormFromConversation($id);
    }

    protected function syncApprovalFormFromConversation(int $conversationId): void
    {
        $conv = Conversation::query()->with(['activeOrder', 'businessAgentSetting', 'customer'])->find($conversationId);
        $order = $conv?->activeOrder;

        if (! $order) {
            $this->approvalOrderId = null;
            $this->approvalItems = [];
            $this->approvalDeliveryData = [];
            $this->approvalPaymentReference = '';
            $this->approvalPaymentAmount = '';

            return;
        }

        $this->approvalOrderId = $order->id;
        $this->approvalPaymentMethodId = $order->payment_method_id ?: PaymentMethod::active()->value('id');
        $this->approvalPaymentReference = (string) ($order->payment_reference ?? '');
        $this->approvalPaymentAmount = $order->payment_amount ? (string) $order->payment_amount : '';
        $this->approvalItems = $order->items_json ?? [];

        $settings = $conv->businessAgentSetting ?? BusinessAgentSetting::activeDefault();
        $fields = $settings?->delivery_required_fields ?: BusinessAgentSetting::defaultDeliveryFields();
        $existingData = $order->delivery_data ?? [];
        $mapped = [];
        foreach ($fields as $field) {
            $mapped[$field] = (string) ($existingData[$field] ?? '');
        }
        $this->approvalDeliveryData = $mapped;
    }

    public function addItemToApproval(): void
    {
        if (! $this->addProductId || $this->addProductQty < 1) {
            return;
        }
        $prod = Product::query()->find($this->addProductId);
        if (! $prod) {
            return;
        }

        foreach ($this->approvalItems as $idx => $item) {
            if (($item['item_type'] ?? '') === 'product' && (int) ($item['product_id'] ?? 0) === $prod->id) {
                $this->approvalItems[$idx]['quantity'] = (int) $this->approvalItems[$idx]['quantity'] + $this->addProductQty;

                return;
            }
        }

        $this->approvalItems[] = [
            'item_type' => 'product',
            'product_id' => $prod->id,
            'name' => $prod->name,
            'quantity' => $this->addProductQty,
            'unit_price_usd' => (float) $prod->sale_price_usd,
        ];
    }

    public function removeApprovalItem(int $index): void
    {
        unset($this->approvalItems[$index]);
        $this->approvalItems = array_values($this->approvalItems);
    }

    public function authorizeAndDispatchOrder(WhatsAppOrderDispatchService $dispatchService): void
    {
        if (! $this->approvalOrderId) {
            return;
        }

        try {
            $order = WhatsAppOrder::query()->findOrFail($this->approvalOrderId);
            $res = $dispatchService->authorizeAndDispatch(
                $order,
                (int) Auth::id(),
                $this->approvalPaymentMethodId,
                $this->approvalPaymentReference ?: null,
                is_numeric($this->approvalPaymentAmount) && (float) $this->approvalPaymentAmount > 0 ? (float) $this->approvalPaymentAmount : null,
                $this->approvalItems,
                $this->approvalDeliveryData
            );

            $this->syncApprovalFormFromConversation($order->conversation_id);

            $msg = "Pedido {$res['order']->order_code} aprobado y descontado de inventario/caja.";
            if ($res['delivery_notified']) {
                $msg .= ' Cliente y proveedor de delivery notificados por WhatsApp.';
            } else {
                $msg .= ' Cliente notificado (configura el teléfono del delivery en Agente IA para notificarle).';
            }

            $this->dispatch('notify', type: 'success', message: $msg);
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function createManualPendingOrder(): void
    {
        if (! $this->selectedConversationId) {
            return;
        }
        $conv = Conversation::query()->with(['customer', 'businessAgentSetting'])->findOrFail($this->selectedConversationId);
        if ($conv->activeOrder) {
            $conv->activeOrder->update(['status' => WhatsAppOrder::STATE_PENDING_STORE_APPROVAL]);
            $conv->update(['agent_state' => WhatsAppOrder::STATE_PENDING_STORE_APPROVAL]);
            $this->syncApprovalFormFromConversation($conv->id);

            return;
        }

        $settings = $conv->businessAgentSetting ?? BusinessAgentSetting::activeDefault();
        WhatsAppOrder::query()->create([
            'order_code' => 'WA-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(3)),
            'business_agent_setting_id' => $settings?->id,
            'conversation_id' => $conv->id,
            'customer_id' => $conv->customer_id,
            'status' => WhatsAppOrder::STATE_PENDING_STORE_APPROVAL,
            'items_json' => [],
            'exchange_rate_bs' => CurrencyHelper::activeRate(),
            'delivery_data' => [
                'Nombre del cliente' => $conv->customer?->displayName() ?? '',
                'Teléfono' => '+'.($conv->customer?->phone ?? ''),
            ],
        ]);
        $conv->update(['agent_state' => WhatsAppOrder::STATE_PENDING_STORE_APPROVAL]);
        $this->syncApprovalFormFromConversation($conv->id);
    }

    public function sendReply(EvolutionApiService $evolution): void
    {
        $this->validate([
            'replyText' => ['required', 'string', 'max:2000'],
        ]);

        if (! $this->selectedConversationId) {
            return;
        }

        $conversation = Conversation::query()->with('customer')->findOrFail($this->selectedConversationId);
        if ($conversation->status === 'bot') {
            $conversation->update(['status' => 'human', 'assigned_user_id' => Auth::id()]);
        }

        $msg = $evolution->sendAndRecordMessage($conversation, trim($this->replyText), Auth::id());
        $this->replyText = '';

        if ($msg->status === 'failed') {
            $this->dispatch('notify', type: 'warning', message: 'Mensaje guardado, pero Evolution API reportó error al enviar. Revisa conexión.');
        } elseif ($msg->status === 'local_only') {
            $this->dispatch('notify', type: 'warning', message: 'Mensaje guardado localmente. Configura Evolution API en Ajustes para enviarlo por WhatsApp.');
        }
    }

    public function sendLiveMenu(EvolutionApiService $evolution): void
    {
        if (! $this->selectedConversationId) {
            return;
        }

        $conversation = Conversation::query()->with('customer')->findOrFail($this->selectedConversationId);
        $menu = $evolution->buildLiveMenuMessage();
        $msg = $evolution->sendAndRecordMessage($conversation, $menu, Auth::id());

        $this->dispatch(
            'notify',
            type: $msg->status === 'sent' ? 'success' : 'warning',
            message: $msg->status === 'sent' ? 'Menú actualizado enviado por WhatsApp.' : 'Menú guardado en chat (revisa conexión Evolution API).'
        );
    }

    public function setConversationStatus(string $status): void
    {
        if (! $this->selectedConversationId || ! in_array($status, ['bot', 'human', 'closed'], true)) {
            return;
        }

        Conversation::query()->whereKey($this->selectedConversationId)->update([
            'status' => $status,
            'assigned_user_id' => $status === 'human' ? Auth::id() : null,
        ]);
    }

    public function startNewChat(EvolutionApiService $evolution): void
    {
        $this->validate([
            'newPhone' => ['required', 'string', 'min:7'],
            'newMessage' => ['required', 'string', 'max:2000'],
        ]);

        $phone = Customer::normalizePhone($this->newPhone);
        if ($phone === '') {
            $this->addError('newPhone', 'Número inválido.');

            return;
        }

        $customer = Customer::query()->firstOrCreate(
            ['phone' => $phone],
            ['name' => trim($this->newName) ?: null]
        );

        $defaultSettings = BusinessAgentSetting::activeDefault();

        $conversation = Conversation::query()->firstOrCreate(
            ['remote_jid' => $phone.'@s.whatsapp.net'],
            [
                'customer_id' => $customer->id,
                'business_agent_setting_id' => $defaultSettings?->id,
                'status' => 'human',
                'agent_state' => WhatsAppOrder::STATE_SELLING_AND_CLOSING,
                'assigned_user_id' => Auth::id(),
            ]
        );

        $evolution->sendAndRecordMessage($conversation, trim($this->newMessage), Auth::id());

        $this->reset(['showNewChatModal', 'newPhone', 'newName', 'newMessage']);
        $this->selectConversation($conversation->id);
        $this->dispatch('notify', type: 'success', message: 'Conversación iniciada.');
    }

    public function render(EvolutionApiService $evolution)
    {
        $conversations = Conversation::query()
            ->with(['customer', 'activeOrder'])
            ->when($this->search !== '', function ($q) {
                $q->whereHas('customer', function ($cq) {
                    $cq->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterState === 'pending', fn ($q) => $q->where('agent_state', WhatsAppOrder::STATE_PENDING_STORE_APPROVAL))
            ->orderByRaw("CASE WHEN agent_state = 'PENDING_STORE_APPROVAL' THEN 0 ELSE 1 END")
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $selectedConversation = $this->selectedConversationId
            ? Conversation::query()->with(['customer', 'messages.sentByUser', 'activeOrder.sale', 'whatsappOrders.sale'])->find($this->selectedConversationId)
            : null;

        if ($selectedConversation && $selectedConversation->activeOrder && $this->approvalOrderId !== $selectedConversation->activeOrder->id) {
            $this->syncApprovalFormFromConversation($selectedConversation->id);
        }

        $pendingCount = WhatsAppOrder::query()
            ->where('status', WhatsAppOrder::STATE_PENDING_STORE_APPROVAL)
            ->count();

        return view('livewire.crm.whatsapp-inbox', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'pendingCount' => $pendingCount,
            'evoConfigured' => $evolution->isConfigured(),
            'paymentMethods' => PaymentMethod::active()->get(),
            'catalogProducts' => Product::query()->where('is_active', true)->where('is_sellable', true)->orderBy('name')->get(),
        ]);
    }
}
