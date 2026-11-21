# Upstream findings

[Back to the README](../README.md)

These issues were found while building on the pre-releases. The first is confirmed against the current
`main`; the others were seen on `symfony/ai` 1.0.0-BETA1 and `mcp/sdk` 0.9.0.

1. **`symfony/ai-bundle` breaks the container when `symfony/security-core` is installed without
   SecurityBundle.** `composer require symfony/form symfony/security-csrf symfony/ai-bundle` in a new
   skeleton fails with `The service "ai.security.is_granted_attribute_listener" has a dependency on a
   non-existent service "security.authorization_checker"`. The bundle keeps the listener whenever the
   security-core class exists, but the service it needs comes from SecurityBundle. Worked around in
   `src/Kernel.php`.
2. `symfony/mcp-bundle`: after `composer require`, the HTTP endpoint answers 500 until a PSR-17
   implementation such as `nyholm/psr7` is installed.
3. `@Mcp/app/base.html.twig`: a frame can grow but not shrink, because `reportSize()` measures the
   document, which is never shorter than the frame.
4. Same template: a frame scrolled out of view does not report a new size (seen in Chrome), so it
   stays at its first height until it becomes visible.
5. Same template: `<form data-call>` collects fields with `Object.fromEntries(new FormData(form))`,
   which keeps only the last value of a multiple-choice field and leaves out the pressed button.
6. Same template: its `message` listener accepts messages from any window, not only from the client
   that embeds the screen.
7. Same template: `render()` copies every text or number of a tool result into a form control of
   the same name, so a field called `html`, for example, is overwritten with the result's own data.
8. HTML-over-the-wire puts the rendered `html` into `structuredContent`; `_meta` would keep it out
   of the model's context on clients that pass `structuredContent` to the model.
9. `symfony/mcp-bundle` passes `allowed_origins` to the DNS-rebinding check but not to the SDK's
   CORS middleware, so no `Access-Control-Allow-Origin` header is sent and a browser-based client
   needs it from elsewhere (this application uses NelmioCorsBundle, in development only).
10. `bin/console mcp:server` serves a whole conversation from one process without resetting
    services between requests, so Doctrine entities and cached choice lists go stale: a booking
    made in another process did not show up in a form asked for afterwards.
11. `mcp/sdk`: an empty object inside the arguments of a tool call, such as `{"address": {}}`, is
    rejected with `Expected object, but received array`, although it matches the tool's schema.
    Only an empty object at the top level is handled.
12. `mcp/sdk` logs the complete arguments of every tool call at debug level (`CallToolHandler`),
    which puts personal data, and any token passed as an argument, into a debug log.

Numbers 3 to 7 are worked around in `formbridge/templates/app.html.twig`, number 8 in
`formbridge/src/Mcp/FormToolResponder.php` together with that template, number 10 in
`formbridge/src/Mcp/FreshStateListener.php`, and number 12 in `config/packages/monolog.yaml`.
