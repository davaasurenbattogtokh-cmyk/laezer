# LaEzer Mongolian sensory shop

**Hostinger shared hosting / laezer.shop:** use the complete PHP + MySQL version in [`hostinger/`](hostinger/README.md). Upload only its `public_html` and sibling `laezer-private` folders according to that guide. The repository root below retains the original Cloudflare/Sites runtime; it is not the Hostinger document root.

The existing LaEzer brand presentation now includes the reference shop's three products, prices, quantities, bundle, basket and manual-payment checkout. Official hero images and logos, botanical accents, pointer-follow PNG products, Mongolian review summaries and the unified self-hosted Manrope typography are preserved.

## Source and runtime

- `content/site.html` is the page source, returned by `app/route.ts`.
- `public/` holds the CSS, JS, fonts and imagery. `dist/` is generated Vinext/Cloudflare build output.
- `public/catalog.js` contains the initial catalog and shared shipping rules; runtime product prices come from D1. Initial prices: Cica 100,000₮, Callus ampoule 100,000₮, cream 150,000₮, bundle 350,000₮. Shipping 5,000₮; free at 250,000₮ and above.
- Cart quantities persist locally; personal checkout details do not. `POST /api/orders` validates all inputs and recomputes totals from the canonical catalog. Orders persist in the Site's D1 `DB` binding using `drizzle/0000_mature_shockwave.sql`.
- An idempotency key prevents a retry from creating duplicate orders. Failed submission preserves the basket. The success view appears only after durable storage succeeds.
- Checkout offers bank transfer or cash on delivery (Ulaanbaatar only), as on the supplied reference. It does not charge a card or mark an order paid. Transfer instructions appear after registration.
- The production server requests email notification through the reference merchant's Formsubmit destination. Email delivery/activation has not been verified by sending a real order. Failed notification retains the order with pending notification status. Development disables outbound notification. No public endpoint exposes customer orders.

## Content update

`Харахаас цааш. Мэдэр.` now presents morning/evening routines in two olive-toned cards. `Өдөр бүр. Өөрийнхөө төлөө.` now explains the three key technologies: Derma-Clera™, natural spicule 5,000ppm and 11 hyaluronic acids. The reference incorrectly conflates Derma-Clera with edelweiss; this version describes it as the separate botanical patented ingredient shown in the official product detail. Unsupported efficacy multipliers are omitted. Product PNGs for the shop cards are the reference's original assets. The existing pointer-follow images remain separate.

Provenance is recorded in `content-sources.json` and `public/assets/official/sources.json`. Generated botanical art is decorative; official product packaging and logos are retained. Motion respects reduced-motion preferences.

## Validation

- `node --test tests/orders.test.mjs`: five tests covering canonical prices/shipping, rejected tampering, durable order storage, retry idempotency, concurrent retry and notification failure.
- Desktop browser: basket, 250,000₮ free shipping, checkout form and local order receipt verified; matching D1 row checked. The disposable local test order was removed. No real order/email was sent.
- 390px mobile preview: product cards, cart and checkout inspected; no horizontal body overflow.
- Build using the Sites `build-site.mjs` helper. Deployment remains owner-private.

## Admin and reseller portal

- `/admin`: owner admin can update four hero slides (desktop/mobile uploads, copy, visibility, order and destination); add/edit products; change retail and reseller prices; activate/deactivate products; grant/revoke reseller membership; search/filter/paginate retail and reseller orders separately; inspect order lines and change status.
- `/reseller`: approved partners see only reseller prices, use their own basket/checkout, and read only their own order history. Unconfigured reseller prices stay unavailable rather than inventing a wholesale rate.
- `/login` is the shared username/password window for admin and reseller. Server-side role detection sends successful logins to `/admin` or `/reseller`; clients cannot select their role. The admin login name and salted password hash live only in server environment secrets. The admin creates reseller usernames and passwords in the account editor; contact email remains separate from the login name. Password hashes use native scrypt with independent salts. No plaintext password is persisted.
- Eight-hour sessions use random tokens in Secure, HttpOnly, SameSite=Strict host-only cookies; D1 stores only token digests. Every protected API call verifies the session and current account status. Password/login-name changes and account deactivation revoke old reseller sessions. Admin secret rotation revokes old admin sessions. Login/logout enforce same-origin requests and failed attempts are rate limited. Migration `0003_eminent_wild_child.sql` adds credentials, sessions and limits.
- Site audience remains owner-private. Partner membership in the app does not bypass the outer Site access policy; reseller testing with external people also requires granting them Site access or an owner-authorized audience change.
- Products and hero configuration persist in D1; the schema-only migration is `0001_organic_secret_warriors.sql`. Initial catalog/banner data is inserted separately using bounded idempotent initialization. Hero/product originals upload to R2 `BUCKET`; SVG/HTML and oversized payloads are rejected. Uploaded media is served with its verified image MIME and `nosniff`.
- Orders store the trusted channel, reseller account and the actual price snapshot. Client-supplied prices/roles cannot select discounted prices. Price changes during checkout require reviewing the refreshed total. Previous order receipts survive later price edits.
- Existing orders default to retail. Search and history queries enforce reseller ownership before applying filters. Changing order status does not claim a payment was received.
- Product/banner edits protect against stale overwrites. No public endpoint exposes reseller prices, customer order lists or account details.
- Previous local role switching and email-header authorization have been removed. No preview-auth endpoint, test identity or test order is deployed.

Validation: 24 Node tests pass across authentication, roles, order ownership/pricing, durable ordering and inactive QPay preparation. Auth checks cover salted hashes, correct/incorrect credentials, client role and header spoofing, cookie/token storage, expiry/logout, credential rotation, account revocation, duplicate/reserved usernames, rate limits and CSRF. A disposable local runtime check exercised native Workers scrypt and D1 sessions without sending real credentials through the browser; it was removed before packaging. The shared login was visually inspected on desktop and at 390px with no horizontal overflow. Earlier portal QA verified price editing, account creation, banner copy, partner checkout/history and admin order status updates. The full browser upload flow and merchant email delivery remain unverified. The outer hosted Site audience remains owner-private and still requires ChatGPT access before the app login.

## QPay preparation (inactive)

The admin now has a read-only payment readiness page. QPay checkout remains hidden and disabled; existing bank/COD ordering is unchanged. Migration `0002_living_lilandra.sql` adds independent payment status/timestamps and an invoice/payment attempt table. Server-only preparation helpers cover environment validation, a Merchant V2 invoice payload, signed callback state and atomic verified-payment recording. Reserved invoice/check/callback routes deliberately return `503 QPAY_NOT_CONNECTED`; credentials or an environment flag alone cannot activate them.

The tracked `.env.example` contains empty server configuration placeholders. `docs/qpay-integration.md` records the later adapter, QR/deeplink UI, ownership, sandbox verification and public callback requirements. No merchant secrets were added, no QPay API was called and no real invoice/payment was created. In particular, the existing private Site gate must be addressed before accepting provider callbacks. Sixteen tests pass, including disabled defaults, forged callbacks, amount/currency/environment mismatch, payment replay and uniqueness, and preservation of manual ordering/fulfillment status.

## Brand and contact content enrichment

Expanded the brand introduction with Korean brand context, research/ingredient focus, Slow Aging philosophy and three practical care principles. Added six native, keyboard-accessible FAQ disclosures covering buying, product order, shipping, payment, order enquiries and reseller cooperation. Added a dedicated Mongolian contact section using the supplied reference's current partner name, both phones, Facebook and Messenger. Rebuilt the footer with partner/contact information and useful internal links, while keeping the official review section immediately above it. Source provenance is recorded in `content-sources.json`; no address, email or opening hours were invented.

Desktop and 360/390px browser views checked for readable layout and horizontal overflow. FAQ expansion and internal destinations verified. Initial hash navigation now repositions once the asynchronous product catalog and fonts have loaded, preventing deep links such as `#contact` and `#footer` from landing above the intended section. This is a content/layout update; payment, admin and reseller APIs remain unchanged.
