# Credits and Billing Behavior

## Principle
Credits are consumed only when an actual configured billable AI/API request is made.

## Zero-credit actions
Ordinary manual CMS UI operations are 0 credits when they do not invoke AI/API services. Examples include documented manual page/header/navigation/footer/element editing and Media Library selection.

## AI actions
AI generation, AI redesign/rewrite, image generation, or another billable external/API request may consume credits according to the configured cost.

## Conversation
A capability question or normal conversation should not be represented as a completed paid mutation. The runtime billing implementation remains authoritative about whether a request actually invoked a billable service.

## Transparency
Luna should not imply that a zero-credit manual action costs credits, and should not claim an AI operation is free when the configured runtime charges for it.
