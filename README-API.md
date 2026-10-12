# .com Domains: Registrar and Maya Setup

Members can buy a `.com` domain for their 1EVOAI digital card in **Member Backoffice → Digital
Card CMS → Your .com Domain**. They pay with Maya Checkout. The domain is then registered at
ResellerClub and pointed at this server, and opening it shows the member's card. A domain also
unlocks **My Website**, the website builder. Once the member publishes a site, the domain shows
it and the card moves to `yourname.com/?view=card` (see "Member websites" in README.md).

This guide covers the API keys, testing on XAMPP, and going live. Settings live in `.env` in the
project root; `.env.example` lists every key with comments.

---

## 1. How it works

```
Search ──► Buy ──► order "pending_payment" ──► Maya Checkout ──► payment confirmed by this server
                                                                        │
      user_domains "active", expires in 1 year ◄── DNS A record ◄── register ◄── registrar customer
                                                                             and contact (first time)
```

1. **Search** checks up to 15 `.com` names in one availability call: the member's name, `iam` /
   `its` / `hello` + name, name + `ph` / `official` / `hq`, full-name combinations
   (`juandelacruz`, `juan-delacruz`, `delacruzjuan`), and the registrar's own suggestions.
   Only `.com` is ever queried or offered.
   * **Registry check:** every name the registrar calls free is also checked with the `.com`
     registry itself (RDAP at Verisign: `rdap.verisign.com`, public, no account), all at once.
   * **Taken names are never offered:** only free names are listed. If the name the member typed is
     taken, they're told so and shown the free alternatives.
   * **Unconfirmed names:** with the offline mock registrar, a name the registry didn't answer for
     isn't offered either.
   * **Checked again at Buy:** the name is checked again just before payment.
2. **Buy** checks the name again, creates a `domain_orders` row (`pending_payment`) and a Maya
   checkout for the price shown (see [Prices](#prices)). The item name is the domain, and the
   `requestReferenceNumber` is the order id (`DOM-…`).
3. **Payment is confirmed server-side.** The member's return page, Maya's webhook and the daily
   job each ask Maya for the payment with the secret key. The order counts as paid only when
   Maya says `PAYMENT_SUCCESS` for this reference, the full amount and PHP. The `?result=success`
   in the return address is never trusted. The payment is recorded once, however many
   confirmations arrive.
4. **Then, in this order:** create the member's registrar customer and contact (first purchase
   only), register the domain for 1 year, add DNS A records (domain and `www`) pointing to
   `SERVER_IP`, and mark the domain `active` with its expiry date.
5. **If registration fails after payment**, the order becomes `paid_failed_registration`. The
   member sees "Payment received… there is no need to pay again". An admin fixes the cause and
   presses **Retry** in **Management Admin → .com Domains**. A paid order is never dropped.
6. **The domain becomes the card's address.** A member's first domain is used for their
   1EVOAI card as soon as it is active and its DNS is set: the share link, QR codes, vCard and
   link previews show `http://yourname.com`, and opening it shows the card. In **Your .com
   Domain**, members choose **Use for my card** on another domain, or **Use the 1EVOAI link
   instead**. If the domain expires, the card falls back to the 1EVOAI link.

Members see clean messages only, such as "That domain is no longer available. Please choose
another one." or "The domain service isn't available right now." The registrar's own error
text is kept for admins: on the order, and in the registrar log.

### Order statuses

| Status | Meaning |
|---|---|
| `pending_payment` | Checkout created, not paid yet |
| `payment_failed` | Card declined, or the payment didn't match the order. A declined card can still be retried on the same Maya page |
| `cancelled` | Cancelled, expired, replaced by a newer checkout, or unpaid after 48 hours |
| `paid` → `registering` | Payment recorded; the registrar steps are running |
| `completed` | Registered (or renewed) and active |
| `paid_failed_registration` | Paid, but the registrar failed. Waiting for an admin **Retry** |

A late payment is still recorded, even on a `cancelled` or `payment_failed` order.

### Drivers

| Setting | Values | Use |
|---|---|---|
| `REGISTRAR_DRIVER` | `mock` (default), `resellerclub` | `mock` works offline: no account, no internet |
| `PAYMENT_DRIVER` | `maya` (default), `mock` | Maya is the only real way to pay. `mock` is for testing only: a local page with **Pay now / Fail / Cancel**, and nobody is charged |

The test payment page runs exactly the same steps as a confirmed Maya payment.

Real payments and real domains always go together, so nobody can register real domains without
paying, or pay for a domain that is never really registered:

* **Maya production** (real money) works only with ResellerClub's **live** account.
* **Test payments** (the mock page or Maya's sandbox) work only with the mock registrar or
  ResellerClub's test account.

The site refuses domain purchases with any other mix, and **Management Admin → .com Domains** says
why.

---

## 2. Settings (`.env`)

Copy `.env.example` to `.env`. The web server never serves either file: `.htaccess` denies every
file that starts with a dot. Check that <http://localhost/evoai/.env> answers **403 Forbidden**.

| Key | Example | Purpose |
|---|---|---|
| `REGISTRAR_DRIVER` | `mock` | `mock` or `resellerclub` |
| `PAYMENT_DRIVER` | `maya` | `maya` (default) or `mock` (testing only) |
| `MAYA_ENV` | `sandbox` | `sandbox` (pg-sandbox.paymaya.com) or `production` (pg.paymaya.com) |
| `MAYA_PUBLIC_KEY`, `MAYA_SECRET_KEY` | | Maya API keys. When set, they replace the `maya` keys in `config/config.php`, which the membership checkout uses too |
| `RESELLERCLUB_ID` | | Reseller ID (the API's `auth-userid`) |
| `RESELLERCLUB_API_KEY` | | API key |
| `RESELLERCLUB_ENV` | `test` | `test` (test.httpapi.com) or `live` (httpapi.com) |
| `RESELLERCLUB_TIMEOUT` | `30` | Seconds per request. Network errors are retried twice |
| `DOMAIN_NAMESERVERS` | | Comma-separated name servers for new domains. Empty: the customer's default ResellerClub name servers, which the A records need |
| `DOMAIN_PRIVACY` | `false` | `true` buys privacy protection with each domain (ResellerClub charges for it) |
| `SERVER_IP` | `203.0.113.10` | This server's public IPv4 address. New domains get A records for the domain and `www` |
| `DOMAIN_PRICE_SOURCE` | `market` | `market`: today's .com market price (see [Prices](#prices)). `fixed`: `DOMAIN_RETAIL_PRICE` |
| `DOMAIN_MARKUP_PERCENT` | `0` | `market`: your margin on top of the market price |
| `DOMAIN_MIN_PRICE` | | `market`: never charge less than this (pesos), for example your ResellerClub cost |
| `DOMAIN_RETAIL_PRICE` | `899` | Pesos per year with `fixed`, and the fallback when the market price can't be fetched |
| `DOMAIN_RENEWAL_PRICE` | | `fixed`: renewal price per year; empty means the retail price |
| `DOMAIN_RDAP` | `on` | Check every name with the .com registry before offering or selling it |
| `DOMAIN_URL_SCHEME` | `http` | How cards link to members' domains. Set `https` only once the server has an SSL certificate for every member's domain (see section 6) |
| `GODADDY_TOKEN` or `GODADDY_API_KEY` + `GODADDY_API_SECRET` | | Optional. Estimated value of each name, and GoDaddy's .com price for the price check (see [Domain market value](#domain-market-value-godaddy-govalue)) |
| `GODADDY_ENV`, `GODADDY_DAILY_LIMIT`, `DOMAIN_APPRAISAL` | `production`, `500`, `on` | GoDaddy environment, appraisals per day, `off` to hide estimates |
| `MAYA_BASE_URL`, `RESELLERCLUB_BASE_URL`, `RDAP_BASE_URL`, `DOMAIN_PRICE_URL`, `DOMAIN_FX_URLS`, `GODADDY_BASE_URL` | | **Testing only**: send API calls to a fake server. Leave empty |

Never put real keys in `.env.example`, a README, a chat or an email. Only `.env` on the server
holds them.

On an existing database, run the migration once (it is safe to run twice):

```bat
C:\xampp\mysql\bin\mysql.exe -u root evoai < database\migrations\2026-10-09-domains.sql
```

A fresh import of `database/evoai.sql` already contains the tables.

### Prices

**Each name has its own price: its market value.** With GoDaddy keys (see
[Domain market value](#domain-market-value-godaddy-govalue)), a new name costs its GoDaddy
GoValue estimate in pesos at today's rate, rounded up to the peso:

* **The floor:** a name never costs less than the registration cost below.
* **No estimate:** a name GoDaddy gives no value for costs the registration cost.
* **Without GoDaddy keys:** every name costs the registration cost.
* **Renewals and auto-renewals:** always the registration (renewal) cost.

Each search result shows its own price, marked **Market value** or **Registration price**.

* **What's recorded:** the order keeps how its amount was set (`domain_orders.price_note`, for
  example "Market value: GoDaddy GoValue $300.00 at ₱58.50"). Admins see it in the orders list,
  and members see it on the payment and result pages.
* **When the price changes before Buy:** if the price changed between the search and **Buy**
  (a new exchange rate, a new estimate), no order is made. The button shows the new price and
  the member clicks again to pay it.

**Registration cost.** With `DOMAIN_PRICE_SOURCE=market` (the default), this is today's .com
market price:

* **USD price:** [Porkbun](https://porkbun.com/products/domains)'s public .com price list
  (`api.porkbun.com/api/json/v3/pricing/get`; no account needed). It was $11.08 a year on
  Oct 9, 2026.
* **Exchange rate:** today's USD→PHP rate from `open.er-api.com`, or `api.frankfurter.dev`
  (ECB rates) when that's unavailable.
* **Formula:** USD price × rate, plus `DOMAIN_MARKUP_PERCENT`, rounded up to the peso. For
  example, $11.08 × 63.00 = ₱698.04, which becomes **₱699**. Never less than
  `DOMAIN_MIN_PRICE`.
* **Refreshing:** the price is kept for a day in `storage/cache/domain-pricing.json`, refreshed
  by the daily job, and on demand with **Refresh** in **Management Admin → .com Domains**. The
  admin tab shows how it was worked out.
* **When the sources are down:** the last price is used for up to a week, then
  `DOMAIN_RETAIL_PRICE` until a refresh works.
* **Orders keep their price:** the amount is fixed on the order when the member clicks
  **Buy**, so a refresh never changes what they pay at Maya.

**Selling through ResellerClub:** ResellerClub charges your reseller balance its own price for
each .com. Set `DOMAIN_MARKUP_PERCENT` or `DOMAIN_MIN_PRICE` so members' price covers it.

**The price is cross-checked** every time it is refreshed:

* **Exchange rate:** both exchange-rate sources are asked. If they differ by more than 2%,
  admins are warned.
* **Second registrar:** with GoDaddy keys (below), GoDaddy's own .com price is read too. If
  Porkbun's price is under 40% or over 150% of GoDaddy's (a flash sale or a price change, say),
  admins are warned.
* **What members and admins see:** when every check passes, members see "checked against N
  sources" next to the price. The admin price card shows each source's figure and the result:
  **Verified**, **Partly checked** (a source missing) or **Sources disagree**.

### Domain market value (GoDaddy GoValue)

**What members see:** with a GoDaddy API key, each free name in a search is **priced at its
market value**: GoDaddy's [GoValue](https://www.godaddy.com/domain-value-appraisal) estimate of
what the name could sell for, converted at today's rate (see [Prices](#prices)). Hovering over
**Market value** shows the dollar estimate and range. Members' own domains show their
estimated value too.

**How estimates are fetched:**

* **Caching:** an estimate is kept 30 days per name in `domain_valuations`. Page views only
  show kept estimates; the daily job refreshes members' domains.
* **Daily limit:** appraisals stop for the day at `GODADDY_DAILY_LIMIT`, or at GoDaddy's own
  daily limit when it reports one. A refused key also stops them.

**Setting it up:**

1. Sign in at [developer.godaddy.com](https://developer.godaddy.com) with a GoDaddy account and
   create a key: a Personal Access Token (`GODADDY_TOKEN`), or a production API key and secret
   (`GODADDY_API_KEY`, `GODADDY_API_SECRET`). GoDaddy decides which accounts may call GoValue
   and how often.
2. Put the key in `.env`.
3. Press **Test GoDaddy** in **Management Admin → .com Domains**. It appraises `example.com`
   and reads GoDaddy's .com price. Every GoDaddy call and answer is in the registrar log (driver
   `godaddy`), without the keys.

The free HumbleWorth valuation API (`valuation.humbleworth.com`) no longer answers; it moved to
Replicate, which needs a paid token. GoDaddy's API is the free option that works.

**A big estimate means a big price:** GoValue can value a name in the thousands of dollars,
so a name can cost tens of thousands of pesos. Maya may refuse a checkout above your account's
transaction limit; the member then sees "Online payment isn't available right now". Set
`DOMAIN_APPRAISAL=off` to go back to one registration price for every name.

---

## 3. Maya Checkout

### Sandbox keys

* **Quick start:** the local `config/config.php` uses Maya's shared public sandbox keys
  ("Sandbox Party 1"), so with `PAYMENT_DRIVER=maya` and `MAYA_ENV=sandbox`, sandbox payments
  work without setting any Maya key in `.env`.
* **Your own sandbox keys** are needed for webhooks: webhooks can't be registered on the shared
  keys. Get them from Maya Business Manager (sandbox) or the
  [Maya Developers portal](https://developers.maya.ph), then set `MAYA_PUBLIC_KEY` and
  `MAYA_SECRET_KEY`. The public key creates checkouts (`POST /checkout/v1/checkouts`). The secret
  key reads payments (`GET /payments/v1/payments/{id}`).

### Test cards

Use the cards on Maya's
[Sandbox Credentials and Cards](https://developers.maya.ph/reference/sandbox-credentials-and-cards)
page, for example Visa `4123450131000508`, expiry `12/30`, CVV `111` (no 3-D Secure). The same
page has test Maya wallet accounts. Maya's page asks you to tick "I'm not a robot" before
**Confirm and pay**. No real money moves in the sandbox.

### Webhook

Register `https://<your-domain>/maya_webhook.php` in Maya Business Manager (Settings →
Webhooks) for `PAYMENT_SUCCESS`, `PAYMENT_FAILED`, `PAYMENT_EXPIRED` and `PAYMENT_CANCELLED`.
Add the folder if the site lives in one, for example `/evoai/maya_webhook.php`. The older
address `maya-webhook.php` still works. One endpoint handles both membership payments and domain
orders.

The webhook body is only a hint about which payment changed. The server then asks Maya itself,
so a forged call can't mark anything paid. A repeated event changes nothing. The webhook answers
`200 OK` at once and registers the domain afterwards, because Maya waits only 5 seconds.

### Return pages

Maya sends the member back to `index.php?view=domain&order=DOM-…&result=success|failure|cancel`.
The page asks Maya for the payment (the fallback status check):

* **Paid:** "yourname.com is yours", with the expiry date.
* **Maya still processing:** a spinner that keeps asking every 3 seconds.
* **Cancelled, declined or expired:** what happened, and **Try Again**.

---

## 4. ResellerClub

1. **Reseller account.** Log in to the ResellerClub control panel. Under **Settings → API**,
   note your **Reseller ID** (`RESELLERCLUB_ID`) and generate an **API key**
   (`RESELLERCLUB_API_KEY`).
2. **Whitelist the server's IP.** ResellerClub rejects API calls from IP addresses that are not
   whitelisted (Settings → API). Add the public IP of every machine that calls the API: the web
   server, and your PC's public IP while testing from XAMPP. A home connection's IP can change;
   update the whitelist when it does. A refused call shows in the registrar log as HTTP 403.
3. **Prepaid credit.** Registrations and renewals are paid from your reseller balance
   (`invoice-option=NoInvoice`). Add funds before going live and keep a margin. With too little
   balance, a paid order becomes `paid_failed_registration` ("funds: … Insufficient funds"). Top
   up, then press **Retry**.
4. **Test vs live.** `RESELLERCLUB_ENV=test` uses `test.httpapi.com`, ResellerClub's test
   environment, which needs its own test (demo) reseller account with its own ID and key. Domains
   "registered" there are not real. `live` uses `httpapi.com` (availability checks go to
   `domaincheck.httpapi.com`) and registers real domains.

Calls the site makes (all parameters, including `auth-userid` and `api-key`, in the query
string):

| Step | Endpoint |
|---|---|
| Availability (one call, up to 15 names, `tlds=com`) | `GET /api/domains/available.json` |
| Suggestions (`tld-only=com`) | `GET /api/domains/v5/suggest-names.json` |
| Customer (once per member) | `POST /api/customers/v2/signup.json`; if it already exists, `GET /api/customers/details.json` |
| Contact (registrant, admin, tech and billing) | `POST /api/contacts/add.json` |
| Default name servers | `GET /api/domains/customer-default-ns.json` |
| Register 1 year | `POST /api/domains/register.json` |
| Expiry | `GET /api/domains/details.json` |
| Renew 1 year | `POST /api/domains/renew.json` |
| DNS | `POST /api/dns/activate.json`, then `POST /api/dns/manage/add-ipv4-record.json` for the domain and `www` |

* **Signup endpoint:** ResellerClub's current endpoint is `/api/customers/v2/signup.json`.
* **Owner details:** the registrant is the member, with the name, address, city, ZIP code,
  country and mobile number from **My Account**. Buying is blocked until those are filled in.
* **Timeouts:** a registration that timed out is recognised when it is retried. "Already
  registered" for a domain in your own account and for this customer counts as success.
* **Logging:** every request and answer is written to the `registrar_log` table, without the API
  key or reseller ID, and with passwords masked.

---

## 5. Testing locally (XAMPP)

### a. Everything offline (mock registrar and payments)

```
REGISTRAR_DRIVER=mock
PAYMENT_DRIVER=mock
SERVER_IP=127.0.0.1
DOMAIN_RETAIL_PRICE=899
```

1. Sign in as a member. In **My Account**, fill in street, city, ZIP code and mobile number.
2. Open **Digital Card CMS → Your .com Domain** and click **Search** with the box empty (ideas
   from your name), or type a name.
3. Click **Buy**, then **Pay now** on the test page. The result page shows the domain, and the
   backoffice lists it with its expiry, **Auto-renew** and **Renew**.

The mock registrar's names:

| Name | Behaviour |
|---|---|
| 5 letters or fewer, common words (`juan`, `shop`, `love`…), names already owned here | Taken |
| Contains `nofunds` | Registration fails as if the reseller balance were empty |
| Contains `failreg` | Registration always fails |
| Contains `failonce` | Fails once; the admin **Retry** works |

The mock registrar's answer is only half of it: with `DOMAIN_RDAP=on` (the default), names that
are really registered on the internet are never offered either. Test names such as
`yourname-nofunds.com` must also be free in the real `.com` registry. Fully offline, set
`DOMAIN_RDAP=off`, which offers names the registry hasn't confirmed, and
`DOMAIN_PRICE_SOURCE=fixed`.

**To see a domain open the card**, add `127.0.0.1 yourname.com` to
`C:\Windows\System32\drivers\etc\hosts` (as Administrator), then open
`http://yourname.com/evoai/`. You can also run
`curl -H "Host: yourname.com" http://localhost/evoai/`.

### b. Maya sandbox on XAMPP

Set `PAYMENT_DRIVER=maya` and `MAYA_ENV=sandbox`. **Buy** opens Maya's sandbox page; pay with a
test card and Maya sends you back to the result page, which confirms the payment with Maya.

Maya's webhooks can't reach `localhost`. Locally, a payment is confirmed in one of these ways:

* by the result page (the normal case);
* by **Check status** on the order in the backoffice;
* by **Re-check payment** in **Management Admin → .com Domains**;
* by the daily job (`bin\domains-daily.php`), which re-checks unpaid orders from the last 48 hours.

**To receive webhooks locally**, use a tunnel such as [ngrok](https://ngrok.com):

1. Run `ngrok http 80`.
2. Set `app.base_url` in `config/config.php` to the tunnel URL plus the folder, for example
   `https://abc123.ngrok-free.app/evoai`, so Maya's return addresses use it.
3. Open the site through that URL and sign in there.
4. Register `https://abc123.ngrok-free.app/evoai/maya_webhook.php` for your own sandbox keys.

Free tunnel URLs change each time ngrok starts.

### c. ResellerClub test environment

Set `REGISTRAR_DRIVER=resellerclub`, `RESELLERCLUB_ENV=test` and the test account's ID and key.
Whitelist your PC's public IP in that account. Searches and registrations then go to
`test.httpapi.com`; nothing real is registered or charged. Check **Management Admin → .com
Domains → Registrar log** for each request and answer.

---

## 6. Going live (sandbox → production)

1. **Maya:** finish Maya's
   [integration checklist](https://developers.maya.ph/reference/testing-and-validating-your-maya-checkout-integration)
   in the sandbox. Then set `MAYA_ENV=production` and the production `MAYA_PUBLIC_KEY` /
   `MAYA_SECRET_KEY`. Register the webhook again for the production keys.
2. **ResellerClub:** set `RESELLERCLUB_ENV=live` and the live ID and key. Whitelist the
   production server's IP and add prepaid funds.
3. Set `PAYMENT_DRIVER=maya` and `REGISTRAR_DRIVER=resellerclub`.
4. Set `SERVER_IP` to the server's public IPv4 address. The web server must answer for members'
   domains: Apache's default virtual host does, or add `ServerAlias *`. Cards link to
   `http://` addresses (`DOMAIN_URL_SCHEME`). For `https://`, the server needs a certificate for
   each member's domain, for example from Caddy's on-demand TLS, certbot run per domain, or a
   proxy such as Cloudflare in front of the server. Then set `DOMAIN_URL_SCHEME=https`.
5. Set `app.base_url` in `config/config.php` to the public HTTPS address (emails and Maya return
   links use it).
6. Schedule the daily job (below).
7. Buy one domain yourself end to end.

**Switching the registrar account:**

* Customer IDs and domains are stored per registrar account (`mock`, `resellerclub:test`,
  `resellerclub:live`).
* Members get a new customer at the live account on their first live purchase.
* Domains from the test account can't be renewed through the live one.

---

## 7. Daily job

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\evoai\bin\domains-daily.php
```

Schedule it once a day: Windows Task Scheduler, or cron (`0 8 * * * php /path/to/evoai/bin/domains-daily.php`).
Running it more often is fine, and only one run works at a time. Each run:

* refreshes today's .com market price (see [Prices](#prices));
* re-checks unpaid orders from the last 48 hours with Maya, and cancels older unpaid ones;
* finishes paid orders that were interrupted, and hands orders stuck in "registering" for 30
  minutes to the admins;
* marks domains past their expiry as `expired`;
* **auto-renews** domains whose member turned **Auto-renew** on, 7 days before expiry. The
  renewal is paid from the member's main-account available balance (Maya Checkout can't charge a
  saved card without the member). A wallet transaction "Domain auto-renewal" records it. If the
  balance is too low, the reminder says so;
* sends renewal reminders by email and backoffice notification, 30, 7 and 1 days before expiry,
  each once.

Exit codes:

| Code | Meaning |
|---|---|
| `0` | OK |
| `1` | Another run is still busy |
| `2` | An order needs an admin |

Reminder emails go through `mail.transport` in `config/config.php`. Set `app.base_url` so their
links point at the real site.

A member can renew an expired domain for 30 days after expiry. After that, the admin decides.

---

## 8. Management Admin → .com Domains

* **Drivers and settings in use:** the registrar, payments, price and `SERVER_IP`, with warnings
  for missing keys or a missing `SERVER_IP`. The price card shows how the market price was worked
  out and when, with **Refresh**. It warns when members see the fallback price.
* **Paid, not registered yet:** each order's error and attempts, with **Retry**. When the error
  says the member already owns the domain (paid twice) or another member registered it, refund
  the payment in Maya Business Manager instead.
* **Recent orders:** **Re-check payment** asks Maya now.
* **Members' domains:** DNS status, with **Retry DNS** (for example after setting `SERVER_IP`).
* **Registrar log:** the latest 30 requests and answers.

Domain payments appear in **Online Payments & Ledger** as `domain_registration` /
`domain_renewal`. Payments from the test page have the method `mock` and are labelled
"Test (mock)".

---

## 9. Troubleshooting

| Symptom | Where to look |
|---|---|
| Member sees "The domain service isn't available right now" | Registrar log: HTTP 403 means the API key is wrong or the IP is not whitelisted; "Network error" means timeouts. Apache's error log has a `[domains]` line |
| Order stuck in `paid_failed_registration` | The error on the order: `funds:` means top up; `taken:` means refund; `config:` means check `.env` |
| Domain active but not opening the card | Members' domains → DNS column. Check `SERVER_IP`, that the domain uses ResellerClub's name servers, and that the web server answers for that host name. DNS changes can take a few hours |
| Maya payment made but the order is still pending | **Re-check payment**, or run the daily job. Locally, webhooks can't arrive |
| "Online payment isn't available right now" | Maya keys, `MAYA_ENV`, or Maya unreachable; Apache's error log has the cause |
| Price shows `DOMAIN_RETAIL_PRICE` instead of the market price | Porkbun or the exchange-rate sites unreachable from the server: see the warning in the admin tab, then press **Refresh** |
| "We couldn't confirm that … is still free" | The .com registry (RDAP) didn't answer in time. Try again in a minute; Apache's error log has a `[domains] RDAP` line |
