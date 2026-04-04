# Design Note: Run-Scoped Planning State + Durable Task Graph

## Claude Code Model vs PressArk Translation

### Claude Code's Architecture (TypeScript/REPL)

| Concept               | Claude Code                                           |
|-----------------------|-------------------------------------------------------|
| **Plan mode**         | Permission mode toggle: blocks all writes, allows reads. Exit requires user approval of plan file. |
| **Plan storage**      | Markdown file on disk (`~/.claude/plans/{slug}.md`)   |
| **Task model**        | Durable JSON files, one per task. Supports `blocks`/`blockedBy` arrays, `owner`, `status` (pending/in_progress/completed), `metadata`. |
| **Task scope**        | Session or team (shared task list under team name)     |
| **Dependency model**  | Bidirectional: `A.blocks=[B]` ↔ `B.blockedBy=[A]`. Claiming a blocked task fails with reason. |
| **TodoWrite (legacy)**| Ephemeral session-scoped array, no deps. Being phased out. |
| **Concurrency**       | File-level locking with retry backoff                  |

### PressArk's Current State (PHP/WordPress)

| Concept               | PressArk Today                                        |
|-----------------------|-------------------------------------------------------|
| **Plan mode**         | `plan_with_ai()` generates plan_steps at start of run. Injected into system prompt. No formal "read-only exploration" phase. `workflow_stage` includes `plan` but it's not a permission boundary. |
| **Plan storage**      | In-memory on `PressArk_Agent` instance (lost on request end). Surfaced via SSE `plan` event. |
| **Task model**        | `PressArk_Execution_Ledger` — flat list of `{key, label, status, evidence}`. Binary `pending/done`. No deps, no owners, no blockers. |
| **Task scope**        | Nested inside checkpoint → per chat/run                |
| **Dependency model**  | None. Tasks are ordered but with no explicit edges.    |
| **Approvals**         | Separate `pending_actions` on run + `approvals` array on checkpoint. Works well. |

### What's Missing (the gap)

1. **No task-level dependencies** — can't express "SEO task is blocked until post is created"
2. **No in_progress state** — only pending/done, no way to track what's actively executing
3. **No formal planning phase** — plan_steps are generated but there's no checkpoint-level separation of "exploring/planning" vs "executing"
4. **No task-level metadata** — can't attach tool_call references, post_ids, or other structured data to individual tasks
5. **No dynamic task creation** — tasks only come from initial message parsing, never from tool results mid-run

## Phase 1 Design

### Principle: Evolve the Ledger, Don't Replace It

The execution ledger works well for what it does. Rather than creating a parallel system, we **extend the ledger task schema** with optional graph fields and add a **plan_state** to the checkpoint.

### 1. Task Graph: Extended Ledger Task Schema

Current:
```php
['key' => 'create_post', 'label' => '...', 'status' => 'pending', 'evidence' => '']
```

Extended (backward-compatible via safe defaults):
```php
[
    'key'        => 'create_post',
    'label'      => 'Create the requested blog post',
    'status'     => 'pending',       // pending | in_progress | completed | blocked
    'evidence'   => '',
    'depends_on' => ['select_source'],  // keys of tasks that must complete first
    'metadata'   => [],                 // arbitrary structured data (post_id, tool refs, etc.)
]
```

**Key decisions:**
- `status` gains `in_progress` and `blocked` (computed from deps). Old `done` normalized to `completed` with shim.
- `depends_on` is a flat array of task keys (unidirectional — simpler than Claude's bidirectional model, sufficient for sequential PHP execution).
- `blocked` status is derived: a task is blocked if any `depends_on` key is not `completed`.
- `metadata` is an optional associative array for structured context.
- MAX_TASKS stays at 8 (plenty for WordPress content operations).

### 2. Plan State: Checkpoint Extension

New field on `PressArk_Checkpoint`:

```php
private array $plan_state = [];
// Structure:
// [
//     'phase'       => 'exploring' | 'planning' | 'executing' | '',
//     'plan_text'   => 'Markdown plan content',
//     'entered_at'  => '2026-04-03T12:00:00+00:00',
//     'approved_at' => '2026-04-03T12:01:00+00:00' | '',
// ]
```

**Semantics:**
- `exploring` — agent is reading/searching, no writes. Maps to Claude's plan mode.
- `planning` — agent has produced a plan, awaiting user approval.
- `executing` — plan approved, writes enabled.
- Empty string — no plan state (backward compat default).

**Integration with workflow_stage:** Plan state is orthogonal. `workflow_stage` tracks content-level phases (discover/gather/plan/preview/apply/verify/settled). `plan_state.phase` tracks the agent's permission posture.

### 3. Integration Points

**Agent (`class-pressark-agent.php`):**
- `plan_with_ai()` populates both `plan_steps` (backward compat) AND creates tasks with `depends_on` edges.
- New method `resolve_task_graph()` advances blocked→pending when deps complete.
- Agent checks `plan_state.phase` before proposing writes.

**Execution Ledger (`class-pressark-execution-ledger.php`):**
- `sanitize()` gains backward-compat normalization: `done` → `completed`, missing `depends_on` → `[]`.
- New `resolve_blocked()` static method computes blocked states.
- `progress_snapshot()` updated to report `in_progress` and `blocked` counts.
- `mark_task_in_progress()` new method.
- `build_context_lines()` includes dependency info when present.

**Checkpoint (`class-pressark-checkpoint.php`):**
- New `plan_state` field with getter/setters.
- Serialized into `to_array()` / `from_array()`.
- Included in `to_context_header()` when non-empty.

### 4. Backward Compatibility

- Old ledgers with `done` status deserialized correctly (normalized to `completed`).
- Missing `depends_on` defaults to `[]` (no dependencies = never blocked).
- Missing `metadata` defaults to `[]`.
- Old `plan_steps` injection continues to work alongside new task graph.
- `has_remaining_tasks()` and `progress_snapshot()` work identically for old-format tasks.
- No schema migration needed — ledger lives inside checkpoint JSON blob.
