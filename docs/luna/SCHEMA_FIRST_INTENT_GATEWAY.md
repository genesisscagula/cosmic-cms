# Two-Stage Luna Intent Gateway

The gateway has two strict internal calls.

1. API 1 returns only `chat` or `action`.
2. API 2 runs only for action turns and returns structured machine-only action JSON.

Chat goes to the natural reply service and stops. Action JSON may route to build, update, publish, delete, or navigation. Classifier and planner outputs never become user-facing replies.

Build and update execute directly. Publish and navigation use dedicated executors. Only destructive delete may create an explicit token-backed confirmation.

The gateway does not deduct user credits. A customer-facing final action reply is produced only from post-execution verification facts.
