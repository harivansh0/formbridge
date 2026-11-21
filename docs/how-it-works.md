# How FormBridge works

[Back to the README](../README.md)

![Six steps between an AI assistant, FormBridge with your form, and a person: the assistant calls the tool; your form checks the values and nothing is saved; the assistant gets the form's answer; the person gets the real form; the person presses Book; your form checks again and your handler saves once. The assistant has no tool that saves](images/formbridge-flow.svg)

## What one attribute gives you

| | What it is | Built on |
|---|---|---|
| An MCP tool | `create_booking`: its input schema is read from the form's fields, choices, labels, help texts and validation constraints. Calling it fills the form in and validates it; it never saves | `mcp/sdk`, `symfony/mcp-bundle` |
| The form inside the conversation | The same form, rendered by Symfony with Twig, shown by the client as an MCP App, with each validation message on its field | MCP Apps (`#[AsMcpApp]`) |
| A submit tool for that form only | `create_booking_submit` runs your handler when the person presses the button. It is marked as not for the model, and it needs a token that only the form carries | MCP Apps tool visibility |
| A tool for Symfony AI agents | The same form as a tool of any agent named under `form_bridge.agents`, straight through the service layer, with no tool for saving at all | `symfony/ai-agent` |
| A "paste a message" page | Free text is read into the form by a model whose structured output has the form's own schema | `symfony/ai-platform` |
| A review link | The filled-in form on a normal web page, for clients that cannot show forms | Symfony Form, Twig |

Every further form needs one more attribute: `RescheduleBookingHandler` and `CancelBookingHandler`
are exposed the same way.

## The path of one call

Every FormBridge caller follows the same path through the application's own form.

| Step | What happens | Where |
|---|---|---|
| Boot | Services carrying `#[AsFormTool]` are collected | `FormBridgeBundle`, `FormToolPass` |
| Describing a form | Field types, the choices the form would offer (submitted values, not labels), labels, help texts and constraints become a JSON Schema | `Form/FormSchemaFactory` |
| An agent calls the tool | The values go through the real form. Nothing is saved. The answer is text for the model, structured data, and the form rendered by Symfony. A draft is opened for the person | `FormToolRunner::draft()`, `DraftStore` |
| The person presses the button | The draft is claimed, the form validates again, and the application's handler runs | `DraftStore::claim()`, `FormToolRunner::submit()` |
| The handler refuses after all | It throws `ValidationFailedException`; its messages are shown on the form | `FormToolRunner::submit()` |

## Who can save

- **No tool an agent is given can save.** The tool a model sees only fills in and validates. A
  Symfony AI agent gets no other tool. Over MCP the submit tool is marked for the form only, which
  clients that support MCP Apps respect.
- **The submit tool needs a token the model is not given.** The token is inside the rendered form,
  which travels in the part of a tool result that clients keep out of the model's context (`_meta`).
  It is the server's own check for clients that do not hide the tool.
- **A draft is saved at most once.** The form in the conversation and the review page are two ways
  to the same draft. Attempts are taken one at a time (`symfony/lock`). The draft is closed before
  the handler runs, so an attempt that is cut off after saving cannot be repeated. It is reopened,
  with its original expiry, in two cases only: the form was not valid, or the handler refused it
  with a `ValidationFailedException`. Handlers must refuse before writing, never after.
- **The review link is not authentication.** An agent passes the link on. A bare request to it does
  not save, because submitting needs a CSRF token issued when the page is loaded. Any program that
  can load a page and post a form can do both steps, though. The review page is an ordinary route of
  your application: put it behind your access control to decide who may save, or switch it off with
  `review_page: false`. A listener of `FormSubmitEvent` can refuse a save whichever way it arrives.

## What the schema enforces

- Field names and types are enforced, inside nested groups too: the MCP SDK rejects a call that
  breaks them before the form runs, with its own message.
- Choice lists are described, not enforced. Each choice field lists the values the form would
  submit with their labels. A schema is read once and then kept by clients and by long-running
  servers, while choices often come from data that changes, so an enforced list would reject a
  booking made a minute ago. The form always knows the current list and answers with it.
- Ranges, lengths and required fields are written into the field descriptions and left to the
  form, so a wrong value still reaches the form, which answers in the application's own words and
  shows the person the field to correct.

For every value the schema lets through, the application's pages and the tool give the same
validation messages, because both run the same form.

## Two requests at the same time

Submitting validates again, and the handler has the last word. The three booking handlers run
through `App\Booking\LockedOperation`: it locks the booking being changed and the session that
would receive seats, in a fixed order, reloads them, and validates the request again before
anything is written. The free seats are counted in the database at that moment. A booking and a move cannot
both get the last seat, and a booking cannot be cancelled twice. It refuses to run inside a caller's
transaction, because its locks are released when it returns.

## Where the code lives

| Path | What it holds |
|---|---|
| `formbridge/src/Form/FormSchemaFactory.php` | A form read as a tool schema |
| `formbridge/src/FormToolRunner.php` | The path every FormBridge caller takes; events and logging |
| `formbridge/src/DraftStore.php` | A draft that can be reached in two ways and saved at most once |
| `formbridge/src/Mcp/` | The MCP tools, the MCP App (one static shell for all forms), and `FreshStateListener`, which keeps a long-running STDIO server from answering with stale data |
| `formbridge/templates/app.html.twig` | The form screen, and where it departs from the MCP bundle's bridge |
| `formbridge/src/Agent/FormToolbox.php` | The forms as tools of a Symfony AI agent |
| `formbridge/src/Ai/FormFiller.php` | Structured output in the shape of a form |
| `src/Booking/` | The three handlers with their attribute, and `LockedOperation`. The application's own pages call the same handlers |
| `src/Assistant/` | The Symfony AI agent behind `/assistant`, its platform and the offline responder |
| `migrations/`, `src/DataFixtures/` | The schema and the fixtures |

The bundle's own documentation is in [`formbridge/docs/index.md`](../formbridge/docs/index.md).
