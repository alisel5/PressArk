# Token Budget And Billing Alignment

Anthropic keeps two separate truths:

- local/session cost tracking for planning, UX, and warnings
- server-authoritative quota, subscription, and billing state

Pressark now follows the same split in WordPress terms:

- `PressArk_Token_Budget_Manager` is the local planner. It estimates prompt/context pressure, projected output, projected request ICUs, deferred hydration cost, and financial pressure for the current run. Those numbers are advisory and explicitly not provider-billed or bank-authoritative.
- `PressArk_Token_Bank` plus the Token Bank API are the billing authority. They own verified handshake state, billing tier, monthly included ICU budget, purchased credits, total spendable remaining balance, reserve/settle/release, and actual charged ICUs.

That gives us one consistent contract:

- `estimated_prompt_tokens` and `estimated_output_tokens` are local planning inputs.
- `raw_actual_tokens` are provider-usage facts returned after execution.
- `estimated_request_icus` is a local projection that can shape hydration behavior, especially when spendable balance is low.
- `actual_icus` is bank-settled billing output.
- `monthly_remaining`, `purchased_credits_remaining`, `legacy_bonus_remaining`, and `total_remaining` come from the Token Bank snapshot and stay authoritative.
- `using_purchased_credits` means the monthly included allowance is exhausted and the request is now spending purchased credits. Legacy bonus is tracked separately instead of being blurred into the same flag.

To make drift harder:

- plugin checkout/catalog data now prefers the bank's shared billing catalog and compares a `contract_hash` against the plugin fallback constants
- `token_usage` ledger writes in the bank now stamp `tier` and `icu_budget` from the authoritative `sites` table, not from caller-shaped request values
- existing token-era aliases remain for backward compatibility, but ICU-first names are the canonical meaning moving forward
