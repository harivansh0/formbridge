# Setup: a model, Claude Desktop and other MCP clients

[Back to the README](../README.md)

`docker compose up` is enough to try FormBridge. This page covers the rest.

## Without an API key

Without `AI_API_KEY` the assistant runs in **offline mode**: a built-in responder answers the
suggested prompts with the tool calls a model makes for them, and the screen says so. Everything
after the model is unchanged: the Symfony AI agent, its tool call, the form rendered by Symfony, the
validation, the booking. Offline mode exists for local development and CI. In `prod` there are no
canned replies: without a key the assistant screen says that no model is configured, and the form
filler is not offered.

`docker compose exec app composer db:reset` deletes all bookings and loads the fixtures again. It is
a development command and is not coordinated with requests that are running.

## With a model

The defaults point at [Hugging Face Inference Providers](https://huggingface.co/docs/inference-providers)
with `openai/gpt-oss-120b`. Create `.env.local` next to `.env` with a token that may call Inference
Providers:

```dotenv
AI_API_KEY=hf_...
```

The assistant then takes free text, for example "two seats for Mia, mia@example.com, on the first day
that still has room for both of us" or, after a booking, "now move that booking to Sunday". The
animation in the README was recorded this way.

In production, keep the key in Symfony's secrets vault (`bin/console secrets:set AI_API_KEY`)
rather than in a file.

Any other OpenAI-compatible endpoint works too:

```dotenv
AI_API_KEY=sk-...
AI_BASE_URL=https://api.openai.com
AI_MODEL=gpt-5-mini
```

## In an MCP client

The MCP endpoint is `http://localhost:8000/mcp`; the same server also speaks STDIO.

- **Claude Desktop** (shows the form inside the conversation). Add this to
  `~/Library/Application Support/Claude/claude_desktop_config.json`, with the full path of `docker`
  (`which docker`) and of this folder, and restart Claude Desktop while `docker compose up` is running:

  ```json
  {
    "mcpServers": {
      "workshops": {
        "command": "/usr/local/bin/docker",
        "args": ["compose", "-f", "/absolute/path/to/this/folder/compose.yaml", "exec", "-T", "app", "php", "bin/console", "mcp:server"]
      }
    }
  }
  ```

- **Claude Code** (no forms in the conversation, so the assistant hands over a link to the same form):
  `claude mcp add --transport http workshops http://localhost:8000/mcp`
- **MCP Inspector:** `npx @modelcontextprotocol/inspector`, connect to `http://localhost:8000/mcp`.
