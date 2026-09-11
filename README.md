# Tackle Grokbot

`jordandalton/tackle-grokbot` adds named Cursor automation webhooks to Laravel Tackle. Register any number of bots with instructions, let the agent discover and invoke them, and inspect delivery history.

Requires PHP 8.3+, Laravel 12/13, and Laravel Tackle ^1.56.8. Tackle 1.56.8 adds automatic discovery of package tools through the `tackle.tools` container tag.

## Installation

With Tackle 1.56.9 or later, install and set up Grokbot in one command:

```bash
php artisan tackle:install grokbot
```

This installs `jordandalton/tackle-grokbot:^0.1.1` as a development dependency, then starts `grokbot:install` in a fresh Artisan process. Use `--no-dev` to install it as a production dependency. If the package is already installed with its setup command, Composer is skipped and setup runs again.

For unattended production setup:

```bash
php artisan tackle:install grokbot --no-dev --no-interaction --force
```

Registration is offered only in an interactive terminal. Without a TTY, setup runs non-interactively; register credentials afterward from a terminal.

Alternatively, install the package directly (also supported with Tackle 1.56.8):

```bash
composer require jordandalton/tackle-grokbot:^0.1.1 -W
php artisan grokbot:install
```

Laravel discovers the service provider automatically. Migrations are loaded by the package. The application must have an `APP_KEY` for encrypted credentials.

The installer runs only this package’s migrations, then offers to register the first bot. It is safe to rerun: existing migrations and bots are preserved. Decline registration to configure bots later. For unattended deployment, use `php artisan grokbot:install --no-interaction`; production also requires `--force`. Registration prompts are skipped in unattended mode.

## Manage bots

- `grokbot:register` prompts for a unique name, instructions, Cursor automation webhook URL, hidden bearer token, and enabled status. Run it again for each additional bot. There is no bot count limit.
- `grokbot:edit` selects a bot by name and edits the same fields. Leave the token blank to keep it, or enter a replacement to rotate it.
- `grokbot:remove` selects a bot and asks for confirmation. It soft-deletes the bot, clears its URL and token, and disables it. History remains. Removed names remain reserved so past deliveries retain an unambiguous identity. This cannot cancel an HTTP request already in flight.
- `grokbot:history --bot=1 --page=1` shows 25 deliveries per page, including removed bots. Omit `--bot` for all bots.

Credentials have no CLI argument or option: enter them directly into the hidden terminal prompt. No supplied credentials or endpoints are seeded by the package.

Instructions should explain when to use the bot and what JSON fields it expects. For example: “Reviews pull requests. Send repository_url, pr_number, and optional review_concerns.” Instructions are agent-facing metadata, not automatically added to the outbound payload.

## Agent tools

The default Tackle agent receives three tools through the `tackle.tools` container tag. They retain Tackle's allowlist, hooks, and tool event handling:

- `ListGrokbots(page?)`: enabled bot IDs, names, instructions, and enabled status, 25 per page.
- `SendToGrokbot(grokbot_id, payload)`: payload is a string containing a JSON object, maximum 64 KiB. The package supplies the saved URL and authorization internally.
- `ListGrokbotDeliveries(grokbot_id?, page?)`: sanitized delivery history, 25 per page.

If `tackle.tools` is configured as an allowlist, include the tool class names above. Custom agents that override `tools()` must include these classes themselves; read-only and lean agents do not gain sending access automatically. MCP tools are separately configured in `tackle.mcp.tools`.

## Delivery behavior

Requests use POST, JSON, and bearer authentication. Destinations are restricted to HTTPS Cursor automation URLs on `api2.cursor.sh`. Redirects are not followed. Connect timeout is 10 seconds and overall timeout is 30 seconds. Requests are synchronous and are never automatically retried.

Every attempt is persisted before HTTP:

- `sending`: the attempt was recorded; a process interruption can leave this state unresolved.
- `accepted`: the webhook returned 2xx. This does not report completion of the remote automation.
- `failed`: the webhook returned a non-2xx status, including redirects.
- `unknown`: transport failed; the remote automation may have started. Investigate before resending.

Logs retain the bot ID, sanitized payload, status, HTTP status, duration, timestamps, and a generic error. Response bodies, headers, and exception messages are deliberately not persisted. Payload fields named like credentials and occurrences of the destination's bearer token are redacted. Other task content is retained; do not include unnecessary secrets in payloads. History is retained indefinitely unless the host application removes it.

Tokens are encrypted at rest and both tokens and URLs are hidden from model serialization and discovery tools. These boundaries do not stop an agent with arbitrary application PHP/shell/database access from decrypting credentials. Configure host permissions accordingly. Host HTTP instrumentation such as Telescope must separately redact Authorization headers.

## Development

From this package directory, using the sibling Tackle checkout’s installed dependencies:

```bash
../tackle/vendor/bin/phpunit -c phpunit.xml
../tackle/vendor/bin/pint --test .
```

With this package’s own Composer dependencies installed, run `composer test`. Tests fake HTTP; no live automation is triggered.
