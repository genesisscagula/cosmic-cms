# Direct Action Execution — Batch 2

## New normal flow

`user -> intent router -> chat OR action`

### chat
Reply from Luna/docs and stop.

### action
Execute directly.

- build -> planner/generator -> apply -> verify -> reply
- update -> edit planner -> apply -> verify -> reply
- publish -> publishing flow
- navigate -> navigation flow

Normal actions no longer create a pending action and no longer ask for `Proceed`, `Continue`, `Go ahead`, or `Build it`.

## Safety exception
Destructive `delete` may still use the existing confirmation infrastructure.

## Compatibility
Legacy pending-action code remains only as compatibility/safety infrastructure. It is no longer entered by normal build/update action routing.
