<?php

use App\Enums\SettingKey;

/**
 * Configures demo mode functionality for HTTP method restrictions and database resets.
 *
 * Structure:
 * - `enabled`: Boolean (via APP_MODE env, 'demo' enables). Toggles demo mode.
 * - `messages`: Contains `global` message, used as fallback for restrictions.
 * - `method_usage`: Array of HTTP methods (e.g., 'GET', 'POST') with:
 *   - `enabled`: Boolean. True allows method (blacklist applies), false blocks (whitelist or blacklist applies).
 *   - `message`: Message for restricted methods.
 *   - `whitelisted_routes`: Routes exempt from restriction (when enabled=false).
 *   - `blacklisted_routes`: Routes to restrict (when enabled=true or enabled=false).
 *   - `priority`: 'whitelisted_routes' or 'blacklisted_routes' (default 'whitelisted_routes'). Resolves conflicts.
 * - `database_reset_unit`: Time unit for resets ('second', 'minute', 'hour', 'day', 'month', 'year').
 * - `database_reset_duration`: Integer duration for reset intervals.
 *
 * Restriction Logic:
 * - If `method_usage[method]` undefined, method allowed.
 * - If `enabled=true`:
 *   - No `blacklisted_routes`: Allow all routes.
 *   - Empty `blacklisted_routes`: Block all routes.
 *   - Non-empty `blacklisted_routes`: Block listed routes.
 *   - Non-empty `whitelisted_routes`: Allow listed routes, override blacklist if conflict (based on priority).
 * - If `enabled=false`:
 *   - Non-empty `blacklisted_routes`: Block listed routes, allow others.
 *   - No or empty `blacklisted_routes` and no `whitelisted_routes`: Block all routes.
 *   - No or empty `blacklisted_routes` and non-empty `whitelisted_routes`: Allow listed routes, block others.
 * - If both lists present and route in both, `priority` decides (`whitelisted_routes` allows, `blacklisted_routes` blocks).
 *
 * Messaging:
 * - Restricted methods use `method_usage[method][message]` or `messages.global` fallback.
 *
 * Database Reset:
 * - If `enabled=true`, resets database using SQL file at `resource_path('database/database_demo.sql')`.
 * - Resets based on `database_reset_unit` and `database_reset_duration` (e.g., every 1 hour).
 * - Tracks last reset in `storage/demo_reset.json`.
 *
 * Example:
 * - `GET` with `enabled=false`, `blacklisted_routes=['api.incoming.whatsapp.send.query']`: Blocks `api.incoming.whatsapp.send.query`, allows others.
 * - `POST` with `enabled=false`, `whitelisted_routes=['admin.login']`: Allows `admin.login`, blocks others.
 * - `GET` with `whitelisted_routes=['admin.login']`, `blacklisted_routes=['admin.login']`, `priority='blacklisted_routes'`: Blocks `admin.login`.
 */

return [
    'enabled'   => env('APP_MODE', 'live') === 'demo',
    'messages'  => [
        'global' => 'This is a demo environment. Some actions are restricted.',
    ],
    'database_reset_unit'       => 'hour',
    'database_reset_duration'   => 1,
    'method_usage' => [
        'GET' => [
            'enabled' => false,
            'message' => 'GET method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [
                'api.incoming.whatsapp.send.query',
                'api.incoming.email.send.query',
                'api.incoming.sms.send.query',
            ],
            'priority' => 'whitelisted_routes',
        ],
        'POST' => [
            'enabled' => false,
            'message' => 'POST method usage is restricted in demo mode.',
            'whitelisted_routes' => [
                'admin.authenticate',
                'login.store',
            ],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
        'PUT' => [
            'enabled' => false,
            'message' => 'PUT method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
        'PATCH' => [
            'enabled' => false,
            'message' => 'PATCH method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
        'DELETE' => [
            'enabled' => false,
            'message' => 'DELETE method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
        'HEAD' => [
            'enabled' => true,
            'message' => 'HEAD method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
        'OPTIONS' => [
            'enabled' => true,
            'message' => 'OPTIONS method usage is restricted in demo mode.',
            'whitelisted_routes' => [],
            'blacklisted_routes' => [],
            'priority' => 'whitelisted_routes',
        ],
    ]
];