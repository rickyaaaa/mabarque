<?php

namespace App\Livewire;

use Livewire\Component;

class SidebarSessionStatus extends Component
{
    public function render()
    {
        $session = auth()->user()?->liveSessions()->active()->latest('started_at')->first();

        return view('livewire.sidebar-session-status', [
            'session' => $session,
            'queueCount' => $session?->queueEntries()->reorder()->whereIn('status', ['ready', 'offline', 'playing'])->count() ?? 0,
        ]);
    }
}
