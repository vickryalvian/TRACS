# TRACS — Project Instructions

Read `AI_MEMORY.md` first. It is durable project memory: product rules, security
invariants, and the multi-machine git workflow below.

## Multi-Machine Git Sync (read every session)

This repo is developed from more than one machine (PC and MacBook). GitHub
(`origin`) is the single source of truth.

- **Start of session:** `git fetch origin` and compare local `HEAD` to
  `origin/<current-branch>`. If behind, `git pull --rebase` before editing. If
  the working tree already has uncommitted changes, tell the user — don't
  assume they're safe to discard.
- **Never force-push** a shared branch unless explicitly asked. It can
  silently discard commits pushed from the other machine.
- **End of session / natural stopping points:** commit and push finished work.
  If a session ends with unpushed commits, say so.
- Production (`/opt/tracs` on the VPS) deploys via manual file-copy, not a
  `git pull` of `main`. Pushing to GitHub does not update production by
  itself — see `deployment-summary.md`.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
