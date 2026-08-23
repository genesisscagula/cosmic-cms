# Phase-Aware Thinking Status Hotfix

- Conversation/intent routing: `Thinking…`
- Confirmed page build: `Creating your page…`
- Informational/clarification response: returns to Ready without pretending changes were applied.
- Real non-confirmation mutation response: `Applying changes…`
- Post-confirmed execution response: `Checking the result…`
- Builder and Global Luna use a compact rotating ring beside the status.
- Removed timer-driven fake statuses such as `Choosing the best design…` from Builder intent routing.
