# Destructive Delete Confirmation Popup

The popup is reserved for destructive delete.

- It stores and submits the exact pending-action token.
- The Delete button submits the original delete request with `confirmed=1`.
- The Keep content button dismisses the confirmation without creating a new chat turn.
- Generic affirmative or negative messages do not consume pending normal-action state.
- Missing, expired, or stale tokens do not re-plan or execute a deletion.

Normal build, update, publish, and navigation actions bypass this popup.
