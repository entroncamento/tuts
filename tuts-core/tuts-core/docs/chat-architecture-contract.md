# TUTS Chat Architecture Contract

Status: contract freeze for the next chat phase. This document defines the canonical interfaces and ownership rules before implementation work starts.

This is not an implementation plan for immediate feature work. Existing text streaming must keep working while later phases add history hydration, structured sources, and rich UI payloads.

## 1. Architecture Map

### Frontend Vue/Vite

- Owns the visible chat experience, local optimistic state, route-level hydration, input drafts, temporary local IDs, and renderers.
- Calls Laravel endpoints only. It must not call the protected RAG service directly.
- Treats backend data as authoritative after hydration or stream reconciliation.
- Renders text chunks by default and may progressively render sources or UI payloads when present.

Note: this workspace did not contain the current `src/app/...` Vue/Vite files named in the implementation brief. Older local frontend files under `resources/js/app` may exist, but they are not the current architecture source for this contract.

### Laravel Backend

- Source of truth for application chat state.
- Owns authentication, authorization, chat creation, message persistence, material ref validation, metadata compaction, and SSE proxying.
- Calls RAG using internal credentials and forwards backward-compatible SSE events to the frontend.
- Revalidates all material references from the frontend before sending any request to RAG.

### RAG/FastAPI

- Owns query expansion, retrieval, reranking, prompt assembly, generation, and RAG status events.
- Does not own app state.
- Does not persist chat history or material refs as canonical app data.
- Receives the current user message, selected context, current attached refs, and a bounded same-chat history window from Laravel.

### PostgreSQL

- Canonical persistence for chats, messages, message material refs, compact message metadata, source metadata, and UI payload metadata.
- Retains full message content unless a future explicit archival policy is designed.

### FAISS

- Searchable vector index for material chunks.
- Not chat state.
- Not a durable store for personal temporary files.
- Official subject material chunks may be indexed in the subject/UC index. Personal request-scoped materials must not be written to shared subject FAISS.

### R2 / Private Storage

- Stores private user materials and other protected files.
- Storage keys, private paths, and signed URLs must not be exposed to the frontend, logs, RAG metadata, or persisted message metadata.
- Frontend access must go through authorized Laravel routes.

## 2. Source Of Truth Rules

- Laravel DB is the source of truth for chats, messages, message material refs, compact metadata, sources, and UI payloads.
- Frontend state is an optimistic/session cache only.
- RAG is retrieval and generation only, not app state.
- FAISS is searchable material chunks only, not app state and not chat history.
- R2/private storage is file storage only, not a public citation contract.

## 3. Canonical Chat List Shape

`GET /api/chat` should return a stable list shape:

```json
{
  "status": "sucesso",
  "aluno": "Nome do utilizador",
  "chats": [
    {
      "chat_id": 123,
      "title": "Titulo da conversa",
      "context_type": "uc",
      "subject_id": 31,
      "subject_name": "Tecnologias e Arquiteturas de Computacao",
      "study_space_id": null,
      "space_id": null,
      "space_name": null,
      "space_folder_id": null,
      "folder_id": null,
      "folder_name": null,
      "is_temporary": false,
      "last_message": "Preview curto",
      "last_message_role": "ai",
      "created_at": "2026-06-19T10:00:00.000000Z",
      "updated_at": "2026-06-19T10:05:00.000000Z"
    }
  ]
}
```

Rules:

- `context_type` is one of `uc`, `space`, or `temporary`.
- `space_id` and `folder_id` are compatibility aliases for `study_space_id` and `space_folder_id`.
- List responses should be compact and should not include full message arrays.
- `last_message_role` may remain the backend role (`user` or `ai`) for backward compatibility; frontend normalization maps `ai` to `assistant`.

## 4. Canonical Chat Detail Shape

`GET /api/chat/{id}` should return:

```json
{
  "status": "sucesso",
  "chat_id": 123,
  "titulo": "Titulo da conversa",
  "title": "Titulo da conversa",
  "context_type": "uc",
  "subject_id": 31,
  "subject_name": "Tecnologias e Arquiteturas de Computacao",
  "study_space_id": null,
  "space_id": null,
  "space_name": null,
  "space_folder_id": null,
  "folder_id": null,
  "folder_name": null,
  "mensagens": [
    {
      "id": 1001,
      "chat_id": 123,
      "role": "user",
      "content": "Explica este ficheiro.",
      "meta_data": {
        "context": {
          "context_type": "uc",
          "subject_id": 31,
          "section_id": null,
          "space_id": null,
          "folder_id": null
        }
      },
      "materials": [
        {
          "source": "subject",
          "id": 1,
          "material_id": 1,
          "subject_id": 31,
          "section_id": null,
          "display_name": "Plano de Storage do TUTS.pdf",
          "mime_type": "application/pdf",
          "size_bytes": 123456,
          "meta_data": {}
        }
      ],
      "message_material_refs": [],
      "created_at": "2026-06-19T10:00:00.000000Z",
      "updated_at": "2026-06-19T10:00:00.000000Z"
    }
  ]
}
```

Rules:

- `mensagens` is the current response key for messages.
- Future responses may add `messages` as an alias, but must not remove `mensagens` without a versioned migration.
- Each message includes role, content, compact metadata, material refs, and timestamps.
- Assistant messages may include `meta_data.sources`, `meta_data.ui_payload`, and `meta_data.mode` when available.

## 5. Canonical Frontend Message Shape

Frontend should normalize backend messages into one internal model:

```ts
type ChatMessageRole = "user" | "assistant";
type ChatMessageStatus = "optimistic" | "streaming" | "persisted" | "error";

interface FrontendChatMessage {
  local_temp_id?: string;
  backend_message_id?: number;
  chat_id?: number;
  role: ChatMessageRole;
  content: string;
  status: ChatMessageStatus;
  materials: MessageMaterialRef[];
  sources: SourceV1[];
  ui_payload?: UiPayloadV1 | null;
  mode?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}
```

Rules:

- Normalize backend `ai` to frontend `assistant`.
- Preserve backend IDs when known.
- Keep local temporary IDs only for reconciliation and rendering while the backend response is pending.
- Attachments, sources, and UI payloads belong to messages, not to global page state.

## 6. `ChatStructuredSendPayload` Rule

`ChatStructuredSendPayload` is a frontend/UI send contract. It should not be persisted raw as permanent DB truth.

Persist only canonical resolved fields:

- `context_type`
- `subject_id`
- `section_id`
- `space_id`
- `folder_id`
- `mode` / `command`
- validated `message_material_refs`
- compact RAG metadata
- `sources` and `ui_payload` when available

Frontend-only fields:

- `rawText`
- local temp IDs
- input draft
- popup state
- display-only selected cards
- unvalidated mock IDs
- any other ephemeral UI selection state

Laravel must resolve and validate the send payload into canonical DB fields before persistence.

## 7. Message Metadata Policy

Allowed compact metadata in `messages.meta_data`:

- `mode`
- `preference`
- `sem_contexto`
- `cache_hit`
- `sources` v1
- `ui_payload` v1
- small status/debug summary
- `request_id` when useful
- compact context snapshot for display and auditing

Not allowed in `messages.meta_data`; move to future `rag_runs` or discard:

- raw prompts
- full retrieval chunks
- long debug logs
- token-level traces
- large frontend payload snapshots
- storage keys
- R2 paths
- signed URLs
- file contents
- unredacted private metadata

Heavy diagnostic metadata belongs in a future `rag_runs` or `message_ai_metadata` table with retention rules.

## 8. `message_material_refs` Contract

Canonical material ref shape:

```json
{
  "id": 10,
  "message_id": 1001,
  "source": "subject",
  "material_id": 1,
  "subject_id": 31,
  "section_id": null,
  "snapshot_name": "Plano de Storage do TUTS.pdf",
  "snapshot_type": "application/pdf",
  "snapshot_size": 123456,
  "created_at": "2026-06-19T10:00:00.000000Z"
}
```

Current backend field names may include `display_name`, `mime_type`, and `size_bytes`. Those are compatible with the canonical snapshot concept:

- `display_name` maps to `snapshot_name`
- `mime_type` maps to `snapshot_type`
- `size_bytes` maps to `snapshot_size`

Allowed `source` values:

- `subject`
- `personal`
- `space`

Security rules:

- Laravel must revalidate refs.
- Frontend refs are never trusted.
- Personal files are owner-scoped.
- Subject materials require UC access.
- Space materials require owner/space access.
- No R2 keys, private paths, or signed URLs in frontend responses.

## 9. RAG History Window Contract

Desired design:

- Laravel sends the last 20 relevant same-chat messages.
- Only `user` and `assistant` role/content are sent.
- Backend `ai` is mapped to RAG/frontend `assistant`.
- Status messages are excluded.
- Empty assistant messages are excluded.
- Technical failure messages are excluded when safely detectable.
- The current user message is sent separately as `texto`, not duplicated in `historico`.
- RAG defensively caps to 20 messages.
- Older messages remain in PostgreSQL.
- Future rolling summary may summarize older conversation, but must not replace stored messages.

Example payload sent from Laravel to RAG:

```json
[
  { "role": "user", "content": "Pergunta anterior" },
  { "role": "assistant", "content": "Resposta anterior" }
]
```

Current known gap:

- Current backend/RAG may still use 6/10-message limits and must be patched in a later phase.
- This contract freezes the desired target, not the current implementation.

## 10. Long Chat Policy

Do not:

- Destroy `messages.content`.
- Convert old messages into plain strings to save space.
- Delete material refs as a compaction mechanism.
- Truncate canonical history in PostgreSQL just because RAG uses a sliding window.

Use:

- Sliding window last 20 for RAG.
- Future rolling summary for older conversation.
- Future `rag_runs` cleanup for heavy diagnostics.
- Metadata compaction only for heavy debug fields.

## 11. Sources/Citations V1

Source object:

```json
{
  "id": "src_1",
  "source": "subject",
  "material_id": 1,
  "message_material_ref_id": 10,
  "title": "Plano de Storage do TUTS.pdf",
  "page": 1,
  "quote": "optional short quote",
  "access": "authorized-route"
}
```

Rules:

- Keep inline citations in assistant text for backward compatibility.
- Prefer RAG emitting structured sources later.
- Laravel persists structured sources when available.
- Frontend renders source chips/list from structured sources when present.
- If structured sources are absent, frontend may keep rendering inline citations as text or best-effort chips.
- Never expose R2 keys, storage paths, signed URLs, or private filesystem paths.
- Source links must resolve through authorized Laravel routes.
- Personal temporary sources should use safe snapshots and should not create durable access to files the user no longer owns.

## 12. UI Payload V1

UI payloads are additive. They do not replace normal assistant text.

### Quiz

```json
{
  "type": "quiz",
  "version": 1,
  "title": "Quiz sobre ...",
  "questions": [
    {
      "id": "q1",
      "prompt": "...",
      "choices": [
        { "id": "a", "text": "..." }
      ],
      "answer_id": "a",
      "explanation": "...",
      "source_ids": []
    }
  ]
}
```

Required fields:

- `type`
- `version`
- `title`
- `questions[].id`
- `questions[].prompt`
- `questions[].choices[].id`
- `questions[].choices[].text`

Optional fields:

- `answer_id`
- `explanation`
- `source_ids`

### Chart

```json
{
  "type": "chart",
  "version": 1,
  "chart_kind": "bar",
  "title": "...",
  "data": {
    "labels": [],
    "datasets": []
  },
  "source_ids": []
}
```

Required fields:

- `type`
- `version`
- `chart_kind`
- `title`
- `data`

Optional fields:

- `source_ids`
- chart-kind-specific display hints

### Feynman

```json
{
  "type": "feynman",
  "version": 1,
  "title": "...",
  "steps": [
    {
      "id": "s1",
      "plain_explanation": "...",
      "analogy": "...",
      "check_question": "...",
      "answer": "..."
    }
  ],
  "summary": "...",
  "source_ids": []
}
```

Required fields:

- `type`
- `version`
- `title`
- `steps[].id`
- `steps[].plain_explanation`

Optional fields:

- `analogy`
- `check_question`
- `answer`
- `summary`
- `source_ids`

Rules:

- Persist UI payload on assistant message metadata initially.
- Validate schema server-side before trusting or persisting.
- Frontend falls back to assistant text if payload type/version is unsupported.
- Payloads must be small enough for message metadata. Large generated artifacts require a future separate table.

## 13. SSE Extension Contract

Current required stream events:

```txt
data: {"chat_id":...,"message_id":...}
: heartbeat
data: {"status_msg":"..."}
data: {"chunk":"..."}
data: [DONE]
```

Future optional events:

```txt
data: {"sources":[...]}
data: {"ui_payload":{...}}
data: {"assistant_message_id":123}
```

Rules:

- Optional events must not replace `chunk`.
- Optional events must appear before `[DONE]`.
- Old frontend clients must continue rendering `chunk` text normally.
- Old frontend clients may ignore unknown JSON through the meta handler.
- New frontend stores optional events in the assistant message state.
- `assistant_message_id` is for reconciliation only; text rendering must not depend on it.

## 14. Frontend History Hydration Plan

On route open:

```txt
GET /api/chat/{id}
normalize roles ai -> assistant
hydrate messages/materials/meta_data
render attachments
render sources if present
render ui_payload if present
fallback to assistant text
```

During stream:

```txt
create optimistic user message
create assistant placeholder
receive chat_id/message_id
reconcile local user message to backend message_id
stream chunks into assistant content
store status_msg separately from message content
store sources/ui_payload/assistant_message_id meta events on assistant message
after DONE optionally refresh detail from backend
```

Rules:

- Local state is not authoritative after hydration.
- Refresh after `[DONE]` is recommended once assistant message IDs and metadata are persisted.
- If refresh fails, keep the streamed text and mark state as requiring background reconciliation.

## 15. Security Checklist

- User can only list/open own chats.
- UC access/membership is checked before UC chat or subject material access.
- Subject material refs are revalidated backend-side.
- Personal material access is owner-scoped.
- R2 paths, storage keys, signed URLs, and private filesystem paths are never exposed.
- Attached refs from the frontend are never trusted.
- Personal chunks are not persisted to shared FAISS.
- RAG service remains protected behind Laravel/internal credentials.
- Spaces remain disabled/cautious for RAG until real IDs, authorization, and indexing contracts are complete.
- Logs contain IDs/counts/status only, not full prompts, file contents, R2 paths, or private payloads.

## 16. Implementation Roadmap

### Phase 1: Contract Freeze Doc

- Objective: freeze contracts before behavior changes.
- Likely files: `docs/chat-architecture-contract.md`.
- Files to avoid: frontend, RAG retrieval, stream implementation.
- Risks: documenting behavior that current code has not reached yet.
- Validation: `git diff --check -- docs`.
- Manual tests: none required.
- Commit boundary: safe as a docs-only commit.

### Phase 2: Frontend History Hydration

- Objective: hydrate `GET /api/chat/{id}` into normalized frontend messages.
- Likely files: `src/app/services/api/chat.api.ts`, `src/app/pages/ChatHubPage.vue`, `src/app/pages/UCDetailPage.vue`, central chat store/composable.
- Files to avoid: RAG retrieval, FAISS, backend persistence beyond response aliases if needed.
- Risks: stale local state conflicting with backend history.
- Validation: route-open reload tests for UC, temporary, and empty chats.
- Manual tests: open existing chat, refresh page, verify messages/materials render.
- Commit boundary: safe alone.

### Phase 3: Optimistic Streaming Reconciliation

- Objective: reconcile local temp IDs with `chat_id`, user `message_id`, and future assistant message ID.
- Likely files: stream API service, central chat store, Laravel stream response if adding `assistant_message_id`.
- Files to avoid: RAG retrieval.
- Risks: duplicate messages after refresh, assistant placeholder not matched.
- Validation: send first message in new chat and follow-up in existing chat.
- Manual tests: stream interruption, retry, refresh after `[DONE]`.
- Commit boundary: safe alone.

### Phase 4: Backend/RAG Last-20 Window

- Objective: implement the target history contract.
- Likely files: Laravel chat controller/history builder, RAG `/perguntar`, prompt history caps.
- Files to avoid: frontend renderers, FAISS persistence.
- Risks: leaking wrong-chat context if scoping is wrong.
- Validation: unit/feature tests for same-chat last 20 role/content only.
- Manual tests: long chat follow-up question that references prior answer.
- Commit boundary: safe alone.

### Phase 5: Structured Sources

- Objective: add structured `sources` SSE event and persistence.
- Likely files: RAG source extraction/emission, Laravel stream metadata persistence, frontend source display.
- Files to avoid: UI payload generation.
- Risks: exposing private paths or unauthorised source links.
- Validation: official and personal source security tests.
- Manual tests: source chips open only authorized Laravel routes.
- Commit boundary: safe alone.

### Phase 6: `ui_payload` Generation/Persistence

- Objective: generate and persist v1 quiz/chart/feynman payloads.
- Likely files: RAG generation contracts, Laravel validation/persistence, message metadata.
- Files to avoid: unrelated chat list/history behavior.
- Risks: invalid JSON, oversized metadata, text fallback broken.
- Validation: schema validation tests and fallback tests.
- Manual tests: unsupported version falls back to text.
- Commit boundary: safe alone.

### Phase 7: Frontend Renderers

- Objective: add `AssistantMessageRenderer` and rich components.
- Likely files: frontend message components.
- Files to avoid: backend retrieval and persistence.
- Risks: old messages with no payload fail to render.
- Validation: old text-only history, quiz, chart, feynman examples.
- Manual tests: mobile/desktop rendering and refresh.
- Commit boundary: safe alone.

### Phase 8: Rolling Summary

- Objective: summarize older conversation without destroying canonical messages.
- Likely files: backend summary metadata or future summary table, RAG prompt assembly.
- Files to avoid: deletion of `messages.content`.
- Risks: summary drift, privacy leakage, stale summaries.
- Validation: summary regeneration and invalidation tests.
- Manual tests: long chat with references older than 20 messages.
- Commit boundary: separate commit with migration if needed.

### Phase 9: Spaces

- Objective: enable safe space-scoped retrieval once IDs, authorization, and indexing are real.
- Likely files: space material backend, RAG indexing/retrieval filters, frontend attachments.
- Files to avoid: UC retrieval regressions.
- Risks: cross-space data leakage.
- Validation: owner/space membership security tests.
- Manual tests: attach space material and verify scoped answer.
- Commit boundary: separate feature phase.

## Current Implementation Discrepancies To Patch Later

- Current backend/RAG history window may still be 6/10 messages, not the target 20.
- Current assistant message ID may not be emitted during stream.
- Structured `sources` and `ui_payload` are not part of the current required stream contract.
- Current local workspace did not expose the real `src/app` frontend architecture files, so frontend implementation details remain unverified here.
- Existing inline citations must remain supported even after structured sources are added.
