# Token Budget Manager

Claude Code's `analyzeContext` and tool-search flow treat context like a budgeted working set:

- keep a stable cached prefix separate from round-specific prompt text
- estimate the cost of tools, history, and recent results before sending them
- defer low-probability tool payloads until the model actually needs them
- favor compact discovery text over dumping full tool universes

Pressark now applies the same pattern in a WordPress-native way:

- `PressArk_Token_Budget_Manager` is the central accounting layer for stable prompt prefix, dynamic prompt text, tool schemas, live history, inline tool results, deferred candidates, and reserved response headroom
- `PressArk_Tool_Loader` still preserves sticky user-loaded groups, but heuristic preload groups are now admitted only when their incremental schema cost fits the current provider/model budget
- `PressArk_Agent` sizes history from the active model context window, then re-evaluates capability/resource support text each round so follow-up turns can drop from full to compact or minimal support when pressure rises
- heavyweight reads now expose explicit summary/detail/raw patterns where they matter, so the model can start cheap and request more only when needed

The main difference from Claude Code is the abstraction level. Claude loads local tools and prompt sections directly; Pressark translates the idea through WordPress registries, handler contracts, and provider-aware request assembly. The goal is the same: keep the model informed without paying for every possible capability on every round.
