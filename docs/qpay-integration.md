# QPay integration preparation

## Current release

This release prepares data, configuration, invoice payloads, callback authentication helpers and verified-payment recording. **It does not connect to QPay or accept QPay payments.** Bank transfer and cash on delivery continue to work. Both public and reseller checkout still reject `payment: "qpay"`; the flag defaults off. There are no live credentials, token requests, invoices, QR codes or background payment polling.

The admin's “Төлбөрийн тохиргоо” page displays readiness booleans only, never secret values. Server runtime variable names and placeholders are in the tracked `.env.example`. Do not put merchant credentials in browser code, D1 product settings, the hosting manifest or Git. Configure hosted secrets through Sites runtime settings at the later integration stage.

## What is prepared

- Migration `0002_living_lilandra.sql` adds payment status/paid time independently of order fulfillment. Existing bank/COD orders remain **unverified**, rather than being assumed paid.
- `payment_attempts` records the trusted order total, MNT currency, sandbox/production environment, unique local reference, unique provider invoice/payment IDs, state and timestamps. Product price changes do not change an accepted order's payment amount.
- `server/payments/qpay.js` provides a validated environment contract, sandbox/production host allowlist, an invoice request builder, HMAC-bound callback state and an idempotent verified-payment recorder. The recorder checks invoice identity, environment, exact MNT amount and payment ID, then updates payment rows/order payment status atomically. Fulfillment status is never automatically changed.
- Reserved `/api/payments/qpay/invoice`, `/check` and `/callback` contracts fail closed with `503 QPAY_NOT_CONNECTED`, even if somebody supplies credentials or turns the flag on. No caller can mark an order paid through these routes.
- The readiness release gate is intentionally hard-disabled until a tested server-side Merchant API adapter and checkout UI are implemented. These are preparation helpers, not a completed payment gateway.

## Later activation work

1. Obtain QPay Merchant V2 credentials/invoice code and confirm the merchant's exact API contract. Use sandbox credentials first. Keep sandbox and production tokens/invoices isolated.
2. Establish a callback origin that QPay can reach. **The current owner-private Site login gate blocks unauthenticated QPay callbacks.** Resolve this using an owner-authorized public gateway or supported access arrangement before activation. Do not send a Site bypass token to QPay or expose it in a callback URL.
3. Implement a server-only adapter for token acquisition/refresh, invoice creation, and authoritative payment verification. Reuse unexpired tokens; apply provider rate limits. Never log credentials/tokens or full customer/payment payloads.
4. Persist the local payment attempt before requesting an invoice, using its unique ID as `sender_invoice_no`. On an ambiguous provider timeout, reconcile the original reference; do not blindly create another invoice. Bind the returned provider invoice ID to that attempt before showing its QR/deeplinks.
5. Add a scoped, expiring order payment capability for anonymous retail checkout; reseller access must verify active membership and order ownership. No endpoint may trust an order ID alone or accept a price from the client. Restrict QPay invoice creation to QPay orders, and protect against duplicate invoices/retries.
6. Verify the callback's attempt-bound state, then fetch the invoice/payment from QPay on the server. Normalize the verified response into `{environment, invoiceId, paymentId, status, currency, amount}`. Only that adapter's response may be passed to `recordVerifiedQpayPayment`. A callback body or browser success redirect is never evidence of payment. HMAC state protects the callback reference; it is **not** a replacement for Merchant API verification or a QPay-signed webhook signature.
7. Add QR/deeplink/pending/error/paid states to both checkouts only after the API adapter is tested. Safe-link validation, invoice expiry/cancellation, partial/overpayment/manual-review policy and timeout reconciliation must be completed before rollout. Avoid scheduled/continuous payment polling; follow callback-based verification.
8. Test correct amount/currency, forged callbacks, repeated callbacks, ownership, timeouts, duplicate invoices, late payment after cancellation, pending/failed invoices and production/sandbox separation. Add operational reconciliation and notification handling. Refunds, card processing and eBarimt require their own explicitly scoped integration.
9. Only after successful sandbox end-to-end verification should the release guard, flag, production credentials and customer-facing option be enabled together. A flag flip alone is deliberately insufficient in this release.

## Primary reference

QPay official Merchant V2 documentation, checked 2026-10-25:
https://developer.qpay.mn/mn/docs/merchant?version=2.0.0

Official environments: `https://merchant-sandbox.qpay.mn` and `https://merchant.qpay.mn`. Basic credential exchange uses `/v2/auth/token`, invoice creation `/v2/invoice`, and callback-triggered verification `/v2/payment/check`. Re-read the endpoint schemas when implementing the adapter; this preparation release makes no live API calls and assumes no undocumented callback payload/signature format.
