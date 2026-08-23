# Batch 3 — Documentation/Capability Grounded Conversation Router

**PASS — static integration QA**

## Runtime flow
User message → `LunaKnowledgeRouter` → relevant capability records + canonical documentation excerpts → Luna reply/planner.

The router scores the request against the machine-readable capability registry and injects only the most relevant documentation instead of the entire manual.

## Capability questions
Questions such as:
- "What can Luna do?"
- "Can you create a header mega menu?"
- "Is Mega Footer supported?"
- "Can you make a theme from #224248?"

are treated as informational. Luna receives canonical capability/doc context, returns a grounded natural response, and the controller performs **no mutation and charges 0 credits** for the informational path.

## Action requests
Normal action requests also receive a knowledge packet so Luna's planning remains aware of documented limits and product behavior. Existing server-side validation, billing, permissions, action routers, and verification remain authoritative.

## Surfaces wired
- Public/global Luna chat
- Authenticated global Luna chat
- Trial page Luna chat/planner
- Authenticated Builder page Luna chat/planner

## Important
This batch does not remove legacy hardcoded conversation branches yet. That is Batch 4. Batch 3 establishes the canonical grounding path first so the legacy reply cleanup can be done safely.
