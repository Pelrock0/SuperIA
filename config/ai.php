<?php

return [
    'provider' => env('AI_PROVIDER', 'claude'),
    'api_key' => env('CLAUDE_API_KEY'),
    'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
    'api_base_url' => env('CLAUDE_API_BASE_URL', 'https://api.anthropic.com/v1'),
    'timeout_seconds' => (int) env('AI_TIMEOUT', 30),
    'seed_timeout_seconds' => (int) env('AI_SEED_TIMEOUT', 120),

    'budget_cap_monthly_usd' => (float) env('AI_BUDGET_CAP_MONTHLY_USD', 50),
    'admin_alert_email' => env('AI_ADMIN_ALERT_EMAIL'),

    'rate_limits' => [
        'free' => [
            'suggestions_per_day' => (int) env('AI_FREE_SUGGESTIONS_PER_DAY', 20),
        ],
    ],

    // Consumed by Epic 5A (Suggestion) and Epic 5B (Replenishment + Complement).
    'thresholds' => [
        'min_occurrences' => (int) env('AI_MIN_OCCURRENCES', 3),
        'min_completed_lists' => (int) env('AI_MIN_COMPLETED_LISTS', 5),
        'co_occurrence_ratio' => (float) env('AI_CO_OCCURRENCE_RATIO', 0.60),
        'replenishment_factor' => (float) env('AI_REPLENISHMENT_FACTOR', 0.80),
    ],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 3),
        'cool_down_seconds' => (int) env('AI_CB_COOL_DOWN_SECONDS', 60),
    ],

    'prompt' => [
        'max_user_input_chars' => (int) env('AI_PROMPT_MAX_USER_INPUT', 200),
        'max_history_items_in_context' => (int) env('AI_PROMPT_MAX_HISTORY_ITEMS', 20),
    ],

    // Rough USD cost estimates when the API does not return token counts.
    // Values intentionally conservative to avoid underestimating spend.
    'cost_estimation' => [
        'input_per_1k_tokens_usd' => (float) env('AI_INPUT_COST_PER_1K', 0.003),
        'output_per_1k_tokens_usd' => (float) env('AI_OUTPUT_COST_PER_1K', 0.015),
        'fallback_per_call_usd' => (float) env('AI_FALLBACK_COST', 0.01),
    ],

    'timezone' => env('AI_TIMEZONE', 'Europe/Madrid'),

    // Consumed by Epic 7 (FEAT-EPIC7-PRICES). Price estimation catalog seeding.
    'prices' => [
        'seed_model' => env('AI_PRICES_SEED_MODEL', 'claude-haiku-4-5-20251001'),
        'seed_max_tokens' => (int) env('AI_PRICES_SEED_MAX_TOKENS', 4000),
        'seed_batch_size' => (int) env('AI_PRICES_SEED_BATCH_SIZE', 25),
    ],

    // Consumed by Epic 6 (FEAT-EPIC6-GENERATION). List generation from natural language description.
    'generation' => [
        'model' => env('AI_GENERATION_MODEL', 'claude-sonnet-4-6'),
        'max_tokens' => (int) env('AI_GENERATION_MAX_TOKENS', 3000),
        'max_prompt_chars' => (int) env('AI_GENERATION_MAX_PROMPT_CHARS', 500),
        'max_items' => (int) env('AI_GENERATION_MAX_ITEMS', 25),
        'generation_per_day' => (int) env('AI_GENERATION_PER_DAY', 5),
        'default_people' => (int) env('AI_GENERATION_DEFAULT_PEOPLE', 2),
    ],

    // Consumed by Epic 5C (FEAT-EPIC5C-SUMMARY). Weekly shopping summary via scheduled cron.
    'weekly_summary' => [
        'enabled' => (bool) env('AI_WEEKLY_SUMMARY_ENABLED', true),
        'model' => env('AI_WEEKLY_SUMMARY_MODEL', 'claude-haiku-4-5-20251001'),
        'max_tokens' => (int) env('AI_WEEKLY_SUMMARY_MAX_TOKENS', 1500),
        'history_weeks' => (int) env('AI_WEEKLY_SUMMARY_HISTORY_WEEKS', 4),
        'min_history_weeks' => (int) env('AI_WEEKLY_SUMMARY_MIN_HISTORY_WEEKS', 3),
        'inactivity_cutoff_days' => (int) env('AI_WEEKLY_SUMMARY_INACTIVITY_DAYS', 60),
        'unsubscribe_token_ttl_days' => (int) env('AI_WEEKLY_SUMMARY_UNSUB_TTL_DAYS', 30),
        'dispatch_chunk_size' => (int) env('AI_WEEKLY_SUMMARY_CHUNK_SIZE', 100),
    ],
];
