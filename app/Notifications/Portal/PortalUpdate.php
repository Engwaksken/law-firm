<?php

namespace App\Notifications\Portal;

/**
 * Generic portal notification: "something new is waiting". Callers pass only
 * non-confidential text (e.g. a matter reference), never message bodies.
 */
class PortalUpdate extends PortalNotification
{
    public function __construct(
        public string $topicKey,
        public string $titleText,
        public string $lineText,
        public string $link,
    ) {}

    protected function topic(): string { return $this->topicKey; }
    protected function title(): string { return $this->titleText; }
    protected function line(): string { return $this->lineText; }
    protected function url(): string { return $this->link; }
}
