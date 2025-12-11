<?php

namespace App\DTOs;

class IntentResult
{
    public function __construct(
        public string $replyText,
        public ?string $intentName = null,
        public ?string $intentStatus = null,
        public array $slots = [],
        public ?int $llmRunId = null,
    ) {
    }
}
