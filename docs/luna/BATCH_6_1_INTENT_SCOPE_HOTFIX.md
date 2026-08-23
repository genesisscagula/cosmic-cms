# Batch 6.1 Intent Router Scope Hotfix

Fixed `Undefined variable $intentCategory` in `LunaKnowledgeRouter::contextFor()`.

Cause: the capability-scoring closure passed `$intentCategory` to `capabilityScore()` but did not capture it in the closure `use (...)` list.

Fix: capture `$intentCategory` alongside `$query` and `$scope`.

Static PHP lint passes for the intent index, capability registry, knowledge router, Builder Luna controller, and Global Luna controller.
