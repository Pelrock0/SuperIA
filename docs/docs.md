# Documentation Contract

/doc is the single source of truth for human-authored documentation.

## Structure
- /doc/architecture → system and technical architecture
- /doc/workflow → workflow definitions and versions
- /doc/decisions → ADRs (Architecture Decision Records)
- /doc/features → feature-level documentation
- /doc/metrics → metrics and analysis

## Rules
- Do not create new folders without explicit approval
- Do not place human documentation outside /doc
- Filenames must be explicit and stable
- If unsure where a doc belongs → stop and ask

## Exclusions (NOT /doc)
The following are NOT considered documentation for /doc:
- OpenAPI / Swagger specs
- PHPDoc / JSdoc
- Generated reports (coverage, lint, build)
- Tool-generated diagrams

These artifacts must live next to the code they describe.
