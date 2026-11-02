<?php

namespace App\Notifications\Practice;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\Engagement;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Minimal content by design: matter reference only, details after sign-in. */
class EngagementAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Engagement $engagement) {}

    /** Only approvers who can actually see the matter (ethical walls). */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! method_exists($notifiable, 'getKey') || \App\Support\MatterAccess::canView($notifiable, $this->engagement->matter);
    }

    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'getKey') ? ['mail', 'database'] : ['mail'];
    }

    private function url(): string
    {
        return MatterResource::getUrl('view', ['record' => $this->engagement->matter], panel: 'admin');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Engagement awaiting approval ('.$this->engagement->matter->reference.')')
            ->line('An engagement for matter '.$this->engagement->matter->reference.' is awaiting your approval.')
            ->action('Review the engagement', $this->url());
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Engagement awaiting approval')
            ->body($this->engagement->matter->reference)
            ->icon('heroicon-o-document-check')
            ->actions([Action::make('open')->label('Review')->url($this->url())])
            ->getDatabaseMessage();
    }
}
