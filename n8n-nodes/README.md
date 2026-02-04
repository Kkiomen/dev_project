# n8n-nodes-aisello

Community n8n nodes for [Aisello](https://aisello.com) — personal brand automation platform.

Provides 3 nodes with 83 operations covering posts, brands, AI content generation, databases, and more.

## Nodes

### Aisello

Main node for managing your personal brand automation. Covers 14 resources:

| Resource | Operations | Description |
|----------|-----------|-------------|
| **Post** | Get Many, Get, Create, Update, Delete, Duplicate, Publish, Approve, Reject, Reschedule, AI Generate, AI Modify | Full post lifecycle including AI-powered generation |
| **Brand** | Get Many, Get, Create, Update, Delete, Set Current | Manage brand profiles |
| **Brand Automation** | Get Stats, Enable, Disable, Trigger, Extend Queue, Update Settings | Control automated posting |
| **Post Automation** | Get Many, Generate Text, Generate Image Prompt, Bulk Generate Text, Bulk Generate Image, Webhook Publish | Automation pipeline for posts |
| **Content Plan** | Generate Plan, Generate Content, Regenerate Content | AI-driven content planning |
| **Platform Post** | Update, Sync, Toggle | Per-platform content (Facebook, Instagram, LinkedIn, TikTok, X, YouTube) |
| **Post Media** | Get Many, Upload, Delete, Reorder | Media management for posts |
| **Calendar Event** | Get Many, Get, Create, Update, Delete, Reschedule | Content calendar |
| **Board** | Get Many, Get, Create, Update, Delete | Kanban boards |
| **Board Column** | Create, Update, Delete, Reorder | Board columns |
| **Board Card** | Create, Update, Delete, Move, Reorder | Board cards |
| **Approval Token** | Get Many, Get, Create, Delete, Regenerate, Get Stats | Approval workflow tokens |
| **Notification** | Get Many, Mark Read, Mark All Read, Get Unread Count | In-app notifications |
| **Stock Photo** | Search, Get Featured | Stock photo search |

### Aisello Database

Airtable-like database operations with cascading dropdowns (Base > Table > Field):

| Resource | Operations | Description |
|----------|-----------|-------------|
| **Base** | Get Many, Get, Create, Update, Delete | Database containers |
| **Table** | Get Many, Get, Create, Update, Delete, Reorder | Tables within bases |
| **Field** | Get Many, Get, Create, Update, Delete, Reorder, Add Choice | Columns (text, number, date, select, attachment, etc.) |
| **Row** | Get Many, Get, Create, Update, Delete, Bulk Create, Bulk Delete, Reorder | Table rows |
| **Cell** | Update, Bulk Update | Individual cell values |
| **Attachment** | Upload, Delete, Reorder | File attachments on cells |

**Supported field types:** text, long_text, email, phone, url, number, date, checkbox, select, multi_select, attachment.

### Aisello Trigger

Webhook trigger node that receives publishing result callbacks when posts are published.

- Listens for POST requests on the `aisello-webhook` path
- Optional `X-Webhook-Secret` header validation

---

## Installation

### Community Nodes (recommended)

1. Go to **Settings > Community Nodes**
2. Select **Install a community node**
3. Enter `n8n-nodes-aisello`
4. Agree to the risks and install

### Manual Installation

```bash
cd ~/.n8n/nodes
npm install n8n-nodes-aisello
```

Then restart n8n.

### Docker

If you run n8n in Docker, mount the package or install it inside the container:

```yaml
# docker-compose.yml / compose.yaml
services:
  n8n:
    image: n8nio/n8n:latest
    volumes:
      - n8n_data:/home/node/.n8n
      - ./n8n-nodes:/home/node/.n8n/nodes/n8n-nodes-aisello
```

Or install via npm inside the container:

```bash
docker exec -it <n8n-container> sh
cd /home/node/.n8n/nodes
npm install n8n-nodes-aisello
```

Restart n8n after installation.

---

## Authentication

1. Log into your Aisello account
2. Go to **Settings > API Tokens**
3. Create a new personal access token (Sanctum token)
4. In n8n, create new **Aisello API** credentials:
   - **API Token** (required) — paste your token
   - **API URL** (optional) — defaults to `https://aisello.com`. Change if using a self-hosted instance

The credentials are validated against `GET /api/v1/user`.

---

## Usage Examples

### Generate a Post with AI

1. Add an **Aisello** node
2. Set Resource: **Post**, Operation: **AI Generate**
3. Select a **Brand** from the dropdown
4. Enter a **Prompt** (e.g. "Write a LinkedIn post about productivity tips")
5. Optionally set platform, tone, and language

### Automate Content Pipeline

```
Schedule Trigger → Aisello (Post Automation: Generate Text) → Aisello (Post Automation: Generate Image Prompt) → Aisello (Post: Publish)
```

### Use Webhook Trigger

1. Add an **Aisello Trigger** node
2. Optionally set a webhook secret
3. Copy the webhook URL and configure it in Aisello settings
4. The trigger fires when a post is published, providing the result data

### Database: Create a Row

1. Add an **Aisello Database** node
2. Set Resource: **Row**, Operation: **Create**
3. Select **Base** from dropdown → **Table** dropdown populates automatically
4. Enter cells as JSON: `{"field_id_1": "value1", "field_id_2": "value2"}`

---

## Dynamic Dropdowns

All ID fields use dynamic dropdowns that fetch data from the API at runtime:

- **Aisello node:** Brand, Post, Board, and Approval Token dropdowns
- **AiselloDatabase node:** Cascading dropdowns — select a Base to populate Tables, select a Table to populate Fields

You can still use expressions to pass IDs dynamically (e.g. from a previous node's output).

---

## Development

### Setup

```bash
git clone <repo-url>
cd n8n-nodes
npm install
```

### Build

```bash
npm run build
```

### Watch Mode

```bash
npm run dev
```

### Lint

```bash
npm run lint
```

### Publish to npm

```bash
npm version patch|minor|major
npm publish
```

The `prepublishOnly` script runs `npm run build` automatically before publishing.

### Local Development with Docker

Mount the `n8n-nodes` directory into your n8n container (see Docker section above), then:

```bash
npm run build && docker compose restart n8n
```

---

## API Reference

All operations use the Aisello REST API at `{apiUrl}/api/v1/...`.

Authentication is via Bearer token (`Authorization: Bearer <token>`).

Pagination follows Laravel conventions (`per_page`, `page`). The "Get Many" operations support `Return All` (auto-paginate) or `Limit`.

---

## Resources

- [Aisello](https://aisello.com)
- [n8n Community Nodes Documentation](https://docs.n8n.io/integrations/community-nodes/)

## License

MIT
