# Tests and status

[Back to the README](../README.md)

## Tests

```bash
docker compose exec app php bin/phpunit
docker compose exec app vendor/bin/phpstan analyse      # level 8
docker compose exec app vendor/bin/php-cs-fixer fix     # @Symfony
docker compose exec app composer check                  # all of the above, plus linting and schema validation
```

`php bin/phpunit` runs 152 tests: 73 of the application in `tests/` and 79 of the package in
`formbridge/tests/`. The package's tests use a small application of their own (`tests/App`), so
they also run in the package's repository, on PHP 8.2 with Symfony 7.4 and on PHP 8.4 with
Symfony 8.1.

Among them: the same validation messages from the application's pages and over MCP
(`McpEndpointTest`, `WebsiteAndToolParityTest`), each supported kind of field (`FieldTypesTest`),
saving once whichever way is used (`SavingOnceTest`, `ReviewPageTest`, `DraftStoreTest`), an agent
correcting itself from the form's answer (`FormToolboxTest`), the last seat for a booking and for a
move and seats released by another request (`LastSeatTest`), cancelling (`CancelBookingTest`), the
limit on questions and pasted messages (`AssistantTest`, `FillPageTest`), tool-name clashes and
server scoping (`WiringTest`), refusing a save from a listener (`FormToolRunnerTest`), translated
texts (`TranslationTest`), and a real long-running server process that must show a booking made
elsewhere (`StdioServerTest`). The tests run without a provider key.

## What was checked, and what was not

Verified beyond the automated tests:

- Over HTTP and STDIO: handshake, tool list, drafts, saving, the token, and a whole conversation in
  one long-running STDIO process.
- In Chrome: the form in a sandboxed frame, validation messages, multiple-choice and nested fields,
  saving, the review page, the "paste a message" page and the assistant screen.
- With `openai/gpt-oss-120b` through Hugging Face: the agent picks the right form and values from
  free text, leaves out what it was not told, follows up within a conversation, and does not claim a
  booking when told to skip the form. The form filler reads a customer's email into the booking form
  through structured output.
- In Claude Desktop over STDIO: the booking form appears inside the conversation, and pressing
  **Book** there saves the booking through the form-only submit tool.

Not covered yet:

- Other MCP clients (ChatGPT, Cursor, VS Code).
- Truly simultaneous requests. The locks are tested by running the competing steps one after the
  other, not in parallel.
- The form screen's own script (message checks, sending the form, resizing) has no automated test.
- No test calls a real model.
- A production install (`composer install --no-dev`, `APP_ENV=prod`). The Docker setup is a
  development stack.
- Versions in between: the package is tested on PHP 8.2 with Symfony 7.4 and on PHP 8.4 with
  Symfony 8.1, the application on PHP 8.4 with Symfony 8.1 only.
- An application with several MCP servers: scoping the form tools to some of them is tested at the
  container level, not against running servers.

Deployment notes for the workshops application:

- It has no authentication. `compose.yaml` binds it to the loopback interface, the MCP endpoint
  accepts browser requests only from its own pages and, in development, from the MCP Inspector, and
  the preview page exists in debug mode only.
- No page may be shown inside a frame of another site (`App\EventListener\FrameGuardListener`),
  since every page has a button that saves.
- Questions to the assistant and messages pasted for the form filler are limited to 20 per minute
  per client address (`config/packages/rate_limiter.yaml`). One question can lead to several requests
  to the model provider (a tool call, then the answer), so this bounds usage without counting
  provider requests. The address is that of the direct peer: behind a reverse proxy, configure
  trusted proxies, or all visitors share one limit.
- Its pages carry their styles and scripts inline instead of going through AssetMapper, and its
  own texts are not translated. Both keep it to one `docker compose up` with nothing to build.
- Offline mode builds its canned replies with `MockPlatformFactory`, a test helper of
  `symfony/ai-platform`; it is never used when a key is set.
- In the development preview and on the assistant screen the form frame has no access to the page
  around it or its cookies, and a content policy stops it from loading resources or sending
  requests; it does not stop the frame from navigating itself elsewhere.
