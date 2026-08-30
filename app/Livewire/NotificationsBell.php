<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationsBell extends Component
{
    public function markAsRead(string $notificationId): void
    {
        auth()->user()->unreadNotifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        return view('livewire.notifications-bell', [
            'notifications' => auth()->user()->notifications()->latest()->limit(10)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
