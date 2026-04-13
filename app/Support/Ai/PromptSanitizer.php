<?php

namespace App\Support\Ai;

class PromptSanitizer
{
    private const INJECTION_PATTERNS = [
        '/ignore\s+(all\s+)?previous\s+instructions/i',
        '/disregard\s+(all\s+)?(previous|prior)\s+(instructions|prompts)/i',
        '/you\s+are\s+a\s+(new|different|helpful)\s+(assistant|model|bot)/i',
        '/system\s*prompt/i',
        '/<\|.*?\|>/',
        '/```(system|prompt|instructions)/i',
        '/\[INST\]|\[\/INST\]/i',
        '/\bassistant\s*:/i',
    ];

    public function clean(string $input, ?int $maxChars = null): string
    {
        $cleaned = $input;
        foreach (self::INJECTION_PATTERNS as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned) ?? '';
        }

        $cleaned = trim($cleaned);

        $max = $maxChars ?? (int) config('ai.prompt.max_user_input_chars', 200);

        return mb_substr($cleaned, 0, $max);
    }
}
