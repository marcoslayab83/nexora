<?php

namespace App\Livewire\Crm;

use App\Models\Conversation;
use App\Models\Customer;
use App\Services\EvolutionApiService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Bandeja WhatsApp CRM')]
class WhatsAppInbox extends Component
{
    public string $search = '';

    public ?int $selectedConversationId = null;

    public string $replyText = '';

    // New conversation modal
    public bool $showNewChatModal = false;

    public string $newPhone = '';

    public string $newName = '';

    public string $newMessage = '';

    public function mount(): void
    {
        $requested = request()->integer('conversation_id');
        if ($requested > 0 && Conversation::query()->whereKey($requested)->exists()) {
            $this->selectConversation($requested);

            return;
        }

        $first = Conversation::query()->latest('last_message_at')->first();
        if ($first) {
            $this->selectConversation($first->id);
        }
    }

    public function selectConversation(int $id): void
    {
        $this->selectedConversationId = $id;
        Conversation::query()->whereKey($id)->update(['unread_count' => 0]);
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

        $conversation = Conversation::query()->firstOrCreate(
            ['remote_jid' => $phone.'@s.whatsapp.net'],
            ['customer_id' => $customer->id, 'status' => 'human', 'assigned_user_id' => Auth::id()]
        );

        $evolution->sendAndRecordMessage($conversation, trim($this->newMessage), Auth::id());

        $this->reset(['showNewChatModal', 'newPhone', 'newName', 'newMessage']);
        $this->selectConversation($conversation->id);
        $this->dispatch('notify', type: 'success', message: 'Conversación iniciada.');
    }

    public function render(EvolutionApiService $evolution)
    {
        $conversations = Conversation::query()
            ->with('customer')
            ->when($this->search !== '', function ($q) {
                $q->whereHas('customer', function ($cq) {
                    $cq->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $selectedConversation = $this->selectedConversationId
            ? Conversation::query()->with(['customer', 'messages.sentByUser'])->find($this->selectedConversationId)
            : null;

        return view('livewire.crm.whatsapp-inbox', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'evoConfigured' => $evolution->isConfigured(),
        ]);
    }
}
