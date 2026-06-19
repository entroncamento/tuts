# Frontend Teacher Endpoints Handoff

Backend route inspection date: 2026-06-19.

These endpoints are exposed from Laravel and require an authenticated Laravel session. The frontend should send the normal session cookies, `Accept: application/json`, and the CSRF token for unsafe web routes.

## Subjects / UCs

### List visible subjects

`GET /api/subjects`

Returns the current user's visible UCs. Teachers receive teaching subjects when memberships exist, otherwise the backend may fall back to legacy active subjects. Students receive enrolled/course subjects.

### List teaching subjects

`GET /api/me/teaching-subjects`

Returns UCs where the current user has an active `teacher` membership.

### List student subjects

`GET /api/me/subjects`

Returns UCs where the current user has an active `student` membership.

### Create subject

`POST /api/subjects`

Professor/teacher only.

Payload:

```json
{
  "name": "Teorias da Comunicacao",
  "degree": "MTC",
  "level": "Licenciatura",
  "year": "1",
  "semester": "1",
  "academic_year": "2025/2026",
  "color": "#009957"
}
```

`name` is required. The other fields above are optional.

### Read subject

`GET /api/subjects/{subject}`

`{subject}` can be the numeric id or the UI form `uc-{id}`.

### Update subject

`PATCH /api/subjects/{subject}`

Allowed for the creator or an active teacher membership.

Payload fields:

```json
{
  "name": "Novo nome",
  "degree": "MTC",
  "level": "Licenciatura",
  "year": "1",
  "semester": "2",
  "academic_year": "2025/2026",
  "color": "#009957",
  "status": "active"
}
```

All fields are optional for patch semantics, except `name` cannot be empty when present. `status` accepts `active`, `inactive`, or `archived`.

### Delete subject

`DELETE /api/subjects/{subject}`

Allowed for the creator or an active teacher membership. Students must not see this control.

Behavior:

- Soft deletes the UC.
- Does not delete physical material files.
- Does not hard delete chats, messages, or material rows in this endpoint.
- Normal list endpoints should stop returning the UC because the `Subject` model uses Eloquent soft deletes.

Response:

```json
{
  "status": "sucesso",
  "message": "UC apagada com sucesso."
}
```

## Sections

### List subject sections

`GET /api/subjects/{subject}/sections`

Confirmed for listing only.

Create/update/delete section endpoints are not confirmed in the current route table. Do not wire those actions yet.

## Official Materials

### List official materials

`GET /api/subjects/{subject}/materials`

Returns official materials for the UC.

### Upload official material

`POST /api/subjects/{subject}/materials`

Allowed for the creator or an active teacher membership.

Multipart fields:

- `file`: required. Allowed extensions: `pdf`, `docx`, `pptx`, `png`, `jpg`, `jpeg`, `txt`.
- `name`: optional display name.
- `section_id`: optional subject section id.
- `type`: optional string.

PDF uploads are automatically sent to RAG ingestion. Non-PDF files are saved and return RAG status `skipped`.

### Trigger official material ingestion

`POST /api/subjects/{subject}/materials/{material}/ingest`

Allowed for the creator or an active teacher membership. Intended for official subject materials.

## Expected Frontend Behavior

- Hide teacher controls from students.
- Do not wire UC delete unless `DELETE /api/subjects/{subject}` is available in the deployed backend.
- After deleting a UC, refresh `GET /api/subjects` and `GET /api/me/teaching-subjects`.
- After material upload, show `rag_ingestion.status`, `rag_ingestion.message`, and optionally `rag_ingestion.reason`.
- Treat RAG ingestion failure as a warning: the material was still saved.

## Known Limitations

- Personal materials are separate and use `/api/me/materials`.
- Only PDFs are indexed by RAG for now.
- Non-PDF official materials are saved, but RAG status is `skipped`.
- RAG failure does not roll back official material upload.
- Soft-deleted UCs are not hard deleted.

## Examples

### Delete a subject

```bash
curl -X DELETE "http://localhost:8000/api/subjects/uc-123" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: ${CSRF_TOKEN}" \
  -b cookies.txt
```

### Upload an official PDF

```bash
curl -X POST "http://localhost:8000/api/subjects/uc-123/materials" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: ${CSRF_TOKEN}" \
  -b cookies.txt \
  -F "file=@/tmp/aula.pdf;type=application/pdf" \
  -F "name=Aula 1" \
  -F "section_id=456"
```

### Upload a non-PDF

```bash
curl -X POST "http://localhost:8000/api/subjects/uc-123/materials" \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: ${CSRF_TOKEN}" \
  -b cookies.txt \
  -F "file=@/tmp/slides.pptx" \
  -F "name=Slides"
```

Expected RAG status for non-PDF:

```json
{
  "status": "skipped",
  "reason": "unsupported_type"
}
```

### Check lists after deletion

```bash
curl "http://localhost:8000/api/subjects" \
  -H "Accept: application/json" \
  -b cookies.txt

curl "http://localhost:8000/api/me/teaching-subjects" \
  -H "Accept: application/json" \
  -b cookies.txt
```
