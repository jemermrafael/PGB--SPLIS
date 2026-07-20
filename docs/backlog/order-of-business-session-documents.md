# Order of Business — Session documents (backlog)

**Status:** Pending SP Office decision — do not implement until confirmed.

Legacy SP Tracker Google Sheets tracked six session-level artifacts under Order of Business. SPLIS OB Maker covers the structured Order of Business calendar; this backlog covers the remaining session documents.

## Sheet column → SPLIS approach

| Document | Approach |
|----------|----------|
| Summary of Committee Reports | **Generate** in SPLIS (condensed list, OB-maker style) |
| Committee Reports | **Upload** signed PDF (chair, vice-chair, members) |
| Draft Journal | **Generate** in SPLIS (working Journal of Proceedings) |
| Draft Minutes | **Generate** in SPLIS (working Minutes, before approval) |
| Final Journal | **Upload** signed journal PDF |
| Final Minutes | **Upload** signed minutes PDF |

## Discovery / approval

- [ ] Confirm with SP Office whether session documents should live in SPLIS (vs. Google Sheets / Drive only)
- [ ] Confirm naming and workflow: Draft → Final for Journal and Minutes; who uploads signed PDFs

## Generated in SPLIS (OB Maker–style)

- [ ] **Summary of Committee Reports** — condensed list for the session (derive from OB committee report blocks or agenda data)
- [ ] **Draft Journal** — working Journal of Proceedings editor + print/PDF export
- [ ] **Draft Minutes** — working Minutes editor + print/PDF export (prior-session approval via `prior_session_id`)

## Upload-only (signed PDFs)

- [ ] **Committee Reports** — upload signed PDF packet
- [ ] **Final Journal** — upload signed journal PDF
- [ ] **Final Minutes** — upload signed minutes PDF

## Session UI & data model

- [ ] Session-level document slots on legislative session show page (six types; draft/final status where relevant)
- [ ] Storage: local `pdf_path` (like resolutions) + optional external URL for Drive migration
- [ ] Link documents to `LegislativeSession` (avoid duplicating journal/minutes URLs on every agenda row)

## Integration (later)

- [ ] Point agenda items at session documents instead of per-row `journal_url` / `minutes_url` where appropriate
- [ ] Import/migrate existing Google Sheet / Drive links if SP moves off sheets

## Related SPLIS context

- `LegislativeSession` + `ObDocument` / OB Maker — structured Order of Business (implemented)
- `LegislativeSession.prior_session_id` — intended for “approval of minutes of previous session” (OB section III)
- Agenda Tracker — per-item `committee_report_url`, `journal_url`, `minutes_url` (legacy row-per-agenda pattern)
