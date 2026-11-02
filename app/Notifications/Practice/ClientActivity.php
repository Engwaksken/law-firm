<?php

namespace App\Notifications\Practice;

/**
 * Staff notification about client activity in the portal (new message,
 * document submitted). Never includes what the client wrote.
 */
class ClientActivity extends PracticeNotification
{
    /** @param list<string> $details non-confidential lines (e.g. "Matter MAT-2611-ABC123") */
    public function __construct(
        public string $titleText,
        public array $details,
        public string $link,
    ) {}

    protected function title(): string { return $this->titleText; }
    protected function lines(): array { return $this->details; }
    protected function url(): string { return $this->link; }
    protected function icon(): string { return 'heroicon-o-chat-bubble-left-right'; }
}
