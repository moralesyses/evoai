# 1EVOAI Mike Maranan – 88MMM Circle Executive Portal (PHP / MySQL)

A PHP + MySQL/MariaDB rebuild of the **1EVOAI Mike Maranan E2EE Executive Portal & Mobile Hub**
(<https://app-1evoaimmc-140251160212.asia-southeast1.run.app/>). The original is a React single-page
app; this version renders every page with PHP, uses vanilla JavaScript for interactivity and stores
all data in MySQL. The reference's compiled Tailwind stylesheet is reused unchanged
(`assets/css/app.css`), so pages keep the original layout, colours, spacing and branding.

| Area | URL | Access |
|---|---|---|
| Main Portal (landing, sign-in, registration) | `index.php` | everyone |
| 1EVOAI Card (public digital business card) | `/<link name>`, e.g. `/mike` (see [Card links](#card-links)) | everyone |
| Member Backoffice & CMS (rewards plan) | `index.php?view=backoffice` | signed-in members |
| Management Admin | `index.php?view=admin` | `admin` role |
| E2EE Chat | `index.php?view=chat` | signed-in members |

---

## Requirements

* XAMPP 8.x (Apache + PHP **8.2 or newer** + MariaDB/MySQL) or any equivalent stack
  * PHP extensions: `pdo_mysql`, `mbstring`, `json`, `curl`, `openssl` (all enabled by default in XAMPP)
* [Composer](https://getcomposer.org) to install the libraries in `vendor/`: the Anthropic PHP SDK
  (AI assistance) with Guzzle, and PHPMailer (SMTP email). Without `vendor/` the portal still runs;
  only AI assistance and SMTP email are unavailable.
* A modern browser. Chat encryption uses the Web Crypto API, which browsers only expose on
  **HTTPS or `http://localhost`**.

## Installation (XAMPP on Windows)

1. **Copy the project** to `C:\xampp\htdocs\evoai` (the folder name becomes the URL path).
2. **Start Apache and MySQL** in the XAMPP Control Panel.
3. **Create the database and import the data** with phpMyAdmin:
   1. Open <http://localhost/phpmyadmin>.
   2. Click **New**, enter the database name `evoai`, choose collation `utf8mb4_unicode_ci`, and click **Create**.
   3. Select the `evoai` database, open the **Import** tab, choose `database/evoai.sql`, and click **Import**.
      The script creates all tables and loads the demo data. Re-importing it resets the demo.
   4. *(Optional, for WeServe member cards)* With `evoai` still selected, import
      `database/weserve_members.sql` the same way. It adds the `UserProfile` and `NetworkAccount`
      tables copied from the WeServe vCard app. Re-importing `evoai.sql` doesn't touch them.
4. **Configure the connection** (optional on a default XAMPP install):
   copy `config/config.example.php` to `config/config.php` and edit the `db` section
   (host, port, database name, user, password). If `config/config.php` does not exist, the example
   file is used, and it matches XAMPP's defaults (`root`, no password).
5. **Install the libraries**: in the project folder run `composer install` (this creates `vendor/`).
6. **Open** <http://localhost/evoai/>.

Command-line alternative to step 3:

```bat
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE evoai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 evoai < database\evoai.sql
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 evoai < database\weserve_members.sql
```

### Other PHP/MySQL hosts

Upload the folder, import `database/evoai.sql` into an empty database, create `config/config.php`
with your credentials, and set `app.base_url` (e.g. `https://portal.example.com`) so share links,
QR codes and vCards use your public domain. For the real launch, import
`database/evoai_launch.sql` instead. It has the same tables without the demo data: the
founder's account, locked until you set its password with `bin/set-password.php … --sql`, with his
17 backoffice accounts (MMM-88001 and the corporate accounts MMMC1 to MMMC16, MMM-88002 to MMM-88017) and his
website (it holds personal data: import it, never upload it). The
production copy's `LAUNCH.md` has the steps. Apache must allow `.htaccess` overrides
(`AllowOverride All`) and have `mod_rewrite` on (card addresses such as `/mike`). The `.htaccess`
files block web access to `config/`, `includes/`, `views/`, `api/`, `database/`, `vendor/`,
`storage/`, hidden folders such as `.git`, archives and Composer's files. On Nginx, deny those
paths in the server block and send other single-segment paths to `index.php?card=<segment>`.
`storage/` must be writable by PHP when `mail.transport` is `log`.

## Demo accounts

| Name | Email | Password | Role |
|---|---|---|---|
| Mike Maranan | mike@mmmcircle.com | `admin88` | admin (Super Admin / CEO) |
| Sofia Cruz | sofia.cruz@apexholdings.ph | `vip88` | vip (Chairman's Circle) |
| David Tan | david.tan@horizoncap.sg | `member88` | member |
| Elena Santos | elena@biotechcorp.ph | `member88` | member |

Sign in with these on the Main Portal; the login page has no one-click demo buttons. With
`demo_mode` enabled (the default), the header also lets you switch between accounts.

## Configuration (`config/config.php`)

| Key | Default | Purpose |
|---|---|---|
| `db.*` | `127.0.0.1:3306`, `evoai`, `root`, empty | Database connection |
| `app.timezone` | `Asia/Manila` | Server-side dates |
| `app.base_url` | auto-detected | Public URL for share links, QR codes and vCards |
| `app.demo_mode` | `true` | Demo conveniences: header account switcher (any signed-in user can switch to any account) and on-screen recovery pass. **Set to `false` in production.** |
| `app.debug` | `false` | Show PHP errors (development only) |
| `session.remember_days` | `30` | Lifetime of "Keep executive session active" |
| `card.link_secret` | empty (member cards off) | Signs WeServe member card links (`/<id>-<code>`). Give each installation its own long random value, as the WeServe app does for each copy, so member links differ per installation. Don't reuse the WeServe working copy's `LINK_SECRET`: its handoff says it was exposed. Changing it changes every member's link. |
| `card.network_account_statuses` | active, inactive, expired | A member gets a card only with a `NetworkAccount` in one of these states |
| `maya.environment` | `sandbox` | Maya Checkout environment: `sandbox` (test payments) or `production` |
| `maya.public_key`, `maya.secret_key` | empty (no online payments) | Maya Checkout API keys. Maya is the only way to pay online; without both keys the checkout says payment isn't available (see below) |
| `mail.transport` | `off` | Email from Backoffice → Contacts: `off` (emails wait in the outbox), `log` (written to `storage/mail/mail.log`; development) or `smtp` |
| `mail.from_email`, `mail.from_name` | empty, `88MMM Circle` | Sender address (one your SMTP server may send from). Emails show "<member> via 88MMM Circle" and replies go to the member |
| `mail.smtp.*` | empty host, `587`, `tls` | SMTP server: `host`, `port`, `encryption` (`tls`, `ssl` or empty), `username`, `password` |
| `ai.provider` | `groq` | AI for Backoffice → Contacts (drafts, AI-written auto responder emails): `groq` (Groq) or `anthropic` (Claude) |
| `ai.chat_provider` | `anthropic` | AI for the 88MMM Assistant chat: `anthropic` or `groq` (empty: the same as `ai.provider`) |
| `ai.groq.api_key`, `ai.groq.model` | empty (AI off), `openai/gpt-oss-120b` | Groq API key (`gsk_…`, from console.groq.com; `GROQ_API_KEY` also works) and model |
| `ai.anthropic.api_key`, `.model`, `.effort` | empty, `claude-opus-5-5`, `low` | Anthropic API key (`ANTHROPIC_API_KEY` also works), Claude model, and how much it thinks before writing |
| `ai.daily_limit` | `40` | AI drafts each member may request per day |
| `ai.daily_auto_limit` | `100` | Auto responder emails AI may write per member per day (messages set to "AI writes it"); the rest wait |
| `ai.chat_limit`, `ai.chat_guest_limit` | `100`, `20` | 88MMM Assistant questions per member per day, and per visitor (IP address) per hour |
| `ai.base_url`, `ai.groq.base_url`, `ai.anthropic.base_url` | empty | Only for an API proxy or testing (per provider, or for both) |

The `.com` domain feature reads its settings from **`.env`** in the project root (copy
`.env.example`): registrar and payment drivers, ResellerClub keys, `SERVER_IP` and the domain
price. `MAYA_ENV`, `MAYA_PUBLIC_KEY` and `MAYA_SECRET_KEY` in `.env`, when set, replace the `maya`
keys above. See [.com domains](#com-domains-members) and `README-API.md`.

## Card design and members' products

**Card Design** (Backoffice → Digital Card CMS → Card Design) lets each member make their 1EVOAI
card their own:

* **Colour:** one of seven themes (Executive Gold, Rose Gold, Platinum, Emerald, Sapphire, Ruby,
  Amethyst) or their own colour. A very dark colour is lightened so button text stays readable.
* **Font:** Modern, Classic (serif) or Rounded.
* **Cover photo:** a wide photo across the top of the card, saved as soon as it is uploaded.
* **Card sections:** show or hide each block under the profile, and set their order:
  * Join the Circle
  * About me
  * My Products
  * Keynote video
  * The 88MMM Circle story
  * Membership tiers

**My Products** (Backoffice → tab 10) lets members sell their own products on their card and in
the **Shop** section of their website (see Member websites):

* **Products:** up to 30, each with a picture, name, price (₱1–₱500,000), optional stock (empty
  means no limit) and description. Products can be reordered, hidden and deleted.
* **On the card:** a **My Products** block with **Buy now**. The buyer chooses a quantity, gives
  their name, email, mobile and a delivery address or notes, and pays on Maya's page.
* **Back from Maya:** the buyer returns to where they bought (the card, or the website on the
  member's domain or its portal address), which shows whether the payment went through.
* **When Maya confirms the payment** (on the return page or by webhook):
  * the order is paid and the stock goes down
  * the amount, less the **platform fee**, goes to the member's Available Balance (transaction
    "Card Sales"), which they can withdraw
  * the member gets a notification, and the buyer is added to their Contacts (source "1EVOAI card"
    or "My website")
* **Expired or cancelled payments** leave the order cancelled. Nothing is credited.
* **Delivery:** members see paid orders with the buyer's contact and delivery details and where
  the order came from (Card or Website), and mark them **Shipped** or **Completed**. Payment
  goes through the company's Maya account; delivery is up to the member.

**Management Admin → Shop Products → Members' card sales:**

* **Platform fee:** the percentage kept from each sale: 10% unless changed here (0–50%).
* **Totals:** paid sales, what buyers paid, fees kept and what members received.
* **Recent sales:** each one with **Record refund**. Refund the buyer in Maya Business Manager
  first, then record it here:
  * the member's balance gives back what the sale added (it can go below zero, and later income
    makes up for it)
  * the stock returns
  * the member is told

Pictures go to `uploads/covers/` and `uploads/card-products/` with the same checks as other uploads.
On an existing database, run `database/migrations/2026-10-12-card-design-products.sql` and then
`database/migrations/2026-10-12-website-shop.sql` once (both are safe to run twice).

## Profile pictures

Members set their own profile picture in **Backoffice → Digital Card CMS → Profile Picture**:

* **Upload photo:** saves it straight away. It shows on their card, the share preview, the
  home-screen app and, for a new website, its "About me" section.
* **Remove:** goes back to their initials.
* **A web address instead:** still possible, under "Or use a picture's web address".

Photos are JPEG, PNG, WebP or GIF up to 5 MB, stored in `uploads/avatars/` with random names. They
go through the same checks as shop pictures: real images only, with GPS and other hidden data
removed. A replaced or removed photo is deleted once no card, website or portal setting uses it.
The founder's photo also updates the portal's main picture (Portal CMS).

## Card links

Every card opens at `<site>/<link name>`, for example http://localhost/evoai/mike or
http://localhost/evoai/juan-dela-cruz. Share buttons, QR codes, vCards and the home-screen app use
this address. A member's own .com domain, when their card uses one, comes first.

* **Where the link name comes from:** a new member gets one from their full name
  (`juan-dela-cruz`, then `juan-dela-cruz-2` for the next Juan Dela Cruz). It stays the same when
  the name changes.
* **Changing it:** members do it in **Backoffice → Digital Card CMS → Your Card Link**, and
  admins in **Management Admin → Members**. Leaving it empty makes one from the name.
* **What's allowed:**
  * 3–60 letters, numbers and dashes, starting with a letter
  * unique (the database enforces it)
  * not one of the portal's own words (`admin`, `assets`, `backoffice`…; `CARD_SLUG_RESERVED` in
    `includes/repo.php`)
* **Old addresses** (`index.php?view=card&profile=usr_x`, `?card=mike`) redirect to the new one,
  so printed QR codes and NFC cards keep working. Changing a link name does make the old name
  stop working.
* **Existing databases:** run `C:\xampp\php\php.exe bin\card-links.php` to preview, then add
  `--apply`, once. It turns `1evoai.com/mike` into `mike` and makes link names for cards with a
  missing, invalid or duplicate one. It also adds the unique index on link names.

## WeServe member cards

The `UserProfile` and `NetworkAccount` tables were copied unchanged from the WeServe digital vCard
app's database (`weservecorp_production`) into `evoai`, and are also exported to
`database/weserve_members.sql`. Every member with a `NetworkAccount` row in an allowed state
(`card.network_account_statuses`) gets a 1EVOAI Card at `/<userId>-<code>` (`?card=<userId>-<code>`
redirects there). This follows the
WeServe app's rules:

* The code is signed with `card.link_secret`, so other members' links can't be guessed.
* The card shows the name with the middle name reduced to an initial, "Member since {year}",
  initials instead of a photo, the WeServe links, and Send / QR Code / Save Contact.
* Member cards are not indexed by search engines.
* Only `userId`, the name columns and `createdAt` are read. Birth date, gender and tax ID never
  leave the database.
* Unknown or tampered links show "Card not found" (HTTP 404).

Member details are maintained in the WeServe app; this portal only reads them.

Export every member's link (for NFC cards, QR codes or a mail merge), saving outside `htdocs`:

```bat
C:\xampp\php\php.exe bin\member-links.php D:\member-links.csv
```

The WeServe handoff notes that since February 2026, 353 of 371 new profiles have no
`NetworkAccount` row, so they get no card here either. Once WeServe confirms how accounts are
recorded, keep the rule or adjust `card.network_account_statuses`.

Set `app.base_url` before exporting links to hand out. **`database/weserve_members.sql` contains
personal data** (birth dates, gender, tax identification numbers); it is blocked from the web and
listed in `.gitignore`, so don't publish or commit it.

## Maya Checkout (online payments)

Maya is the only way to pay on this site:

* **Checkout:** memberships, renewals and invoices.
* **Sign-up:** pay-first sign-ups.
* **Member Shop:** next to the member's own wallet balance.
* **.com domains:** buying and renewing.

With `maya.public_key` and `maya.secret_key` set, **Pay with Maya** takes the customer to Maya's
hosted payment page. There they pay with their Maya wallet or a credit or debit card, then come
back for the receipt. Without the keys (as on the live site until they are added), every place
Maya would be offered says **"Online checkout is still unavailable"** (`MAYA_UNAVAILABLE` in
`includes/maya.php`). These places are the sign-up's Online Checkout option, the Member Shop's
Maya choice, the card and website shops, My Products, the membership payment window, Your .com
Domain, and the AI assistant. Sign-up then uses activation codes only. Nothing is ever marked
paid without Maya.

The local `config/config.php` uses Maya's shared public sandbox keys ("Sandbox Party 1"), so
sandbox payments work out of the box. No real money moves in the sandbox.

**To make a sandbox test payment:**

1. Open http://localhost/evoai/index.php?view=pay and click **Open Checkout**.
2. Fill in the receipt details and click **Pay … with Maya**.
3. On Maya's page, use a test card from
   [Maya's sandbox credentials](https://developers.maya.ph/reference/sandbox-credentials-and-cards).
   For example, Visa `4123450131000508`, expiry `12/30`, CVV `111` (no 3-D Secure). Or pay with
   a test Maya wallet account from the same page.
4. Tick "I'm not a robot" and click **Confirm and pay**. You come back to the receipt. The payment
   appears in **Management Admin → Online Payments & Ledger** with the method `maya`.

**How a payment is confirmed:**

* Each attempt is stored in the `maya_checkouts` table.
* The ledger (`payments`) gets the payment only after this server asks Maya for its status with
  the secret key and Maya says `PAYMENT_SUCCESS` for the same reference and the full amount.
  Nothing in the return address or a webhook body is trusted.
* A payment is recorded once, even when the return page and webhooks arrive at the same time.
* An invoice paid with Maya is marked completed, and a membership or renewal activates the
  member's card.
* **Member Shop orders paid with Maya:**
  * **Waiting:** the order waits as "awaiting payment" and is hidden from **My Orders**. The cart
    keeps its items.
  * **Paid:** once Maya confirms the payment, the order shows as paid, the stock goes down and the
    items leave the cart.
  * **Not paid:** the member returns to the cart with the items still in it, so they can try again.
* If Maya is still processing when the customer comes back, the page waits and turns into the
  receipt by itself. Cancelled, declined and expired payments show what happened and a
  **Try Again** button.
* A payment whose amount or reference doesn't match is not recorded. It is written to the audit
  log for you to check in Maya Business Manager.

**Webhooks** let Maya report payments the customer didn't return from, for example if they closed
the browser. Register `https://<your-domain>/maya_webhook.php` in Maya Business Manager
(Settings → Webhooks) for `PAYMENT_SUCCESS`, `PAYMENT_FAILED`, `PAYMENT_EXPIRED` and
`PAYMENT_CANCELLED`. The same address handles membership payments and `.com` domain orders; the
older `maya-webhook.php` still works. Maya needs a public HTTPS address, so webhooks can't reach `localhost`;
locally, the return page does the confirming.

**Existing databases** need the new table. Re-importing `database/evoai.sql` creates it, but resets
the demo data. To keep your data, run only the `CREATE TABLE maya_checkouts …` statement from that
file in phpMyAdmin (SQL tab).

**Before taking real payments:**

1. Get your own sandbox keys from Maya Business Manager. The shared keys belong to other
   developers too, so webhooks can't be registered on them.
2. Complete Maya's
   [integration checklist](https://developers.maya.ph/reference/testing-and-validating-your-maya-checkout-integration):
   one successful payment each with Visa, Mastercard and JCB, and a `200 OK` webhook.
3. Then set `maya.environment` to `production` and type the production keys into
   `config/config.php`. Never share them in a chat or email.

Marking a payment **refunded** in the admin ledger only changes the ledger. Make the actual refund
in Maya Business Manager.

### Paying for a new account

**Create Account** on the main portal is pay-first. Under **Pay With**, the new member chooses
**Online Checkout** (Maya) or **Activation Code**.

**With an activation code:**

* The member types the code and its PIN. The code must be **active** and for the package they
  selected; a Chairman's Circle code needs the Chairman's Circle package.
* The account is created and signed in at once, and the code is marked used by that account.
* A wrong code and a wrong PIN get the same answer, so codes can't be probed. After 10 wrong
  tries, an IP address is blocked for 15 minutes.
* No ledger payment is recorded: the code itself was paid for, either from a member's balance or
  to the company for codes made in **Management Admin → Activation Codes**.

**With Online Checkout:**

1. The form is checked as before: name, email not yet registered, password, referrer ID and
   package. Nothing is created yet. The sign-up waits in the `registrations` table, with the
   password stored only as a hash.
2. **Continue to Payment** opens Maya for the package price. The prices are the CMS prices, as in
   the checkout.
3. When Maya confirms the payment, the account is created in the same database transaction that
   records the payment. Placement in the referrer's team and referral counts happen only then: see
   [Rewards plan](#rewards-plan-superadmin) for where the new member goes and what the referrer
   earns.
4. The browser that paid is signed in on the receipt page and goes on to the Member Backoffice.
   If the customer closed the browser, the webhook still creates the account, and they sign in
   with their email and password.

Some sign-ups can't be completed:

* **Cancelled, declined or expired:** no account is created, and **Try Again** returns to the form.
* **Paid, but the email got an account meanwhile** (for example, paid twice): the payment is
  recorded, no second account is made, and the audit log flags it for a refund.
* **Never paid:** after 24 hours the stored password hash is erased. It is also erased as soon as
  the account exists.

Without Maya keys, Create Account explains that online sign-up isn't open yet; there is no free
sign-up. On an existing database, also run the `CREATE TABLE registrations …` statement from
`database/evoai.sql`.

## .com domains (members)

Members buy a `.com` domain for their digital card in **Member Backoffice → Digital Card CMS →
Your .com Domain**, and pay with Maya Checkout. The domain is registered at ResellerClub, gets
DNS A records pointing to this server (`SERVER_IP`), and opens the member's card. Setup, API
keys, testing and going live: **`README-API.md`**.

* **Search:** up to 15 `.com` names in one availability check: the member's name with
  `iam` / `its` / `hello` before it or `ph` / `official` / `hq` after it, full-name combinations,
  and the registrar's suggestions.
  * Each name is also checked with the `.com` registry (RDAP), and only free names are offered.
  * If the name the member typed is taken, they're told so and shown the free alternatives.
  * Only `.com` is ever queried or sold.
* **Buy:** the price is today's .com market price: Porkbun's public .com price × today's USD→PHP
  rate, rounded up to the peso, plus your optional markup. That was ₱699 on Oct 9, 2026.
  `DOMAIN_RETAIL_PRICE` is the fallback. Each refresh is cross-checked against a second
  exchange-rate source and, with a GoDaddy API key, GoDaddy's .com price. Admins are warned when
  the sources disagree.
* **Market value:** with a GoDaddy API key in `.env`, each free name is priced at its own
  market value: GoDaddy's GoValue estimate in pesos, never below the registration cost. Names
  without an estimate cost the registration cost, and renewals always do. Members' own domains
  show their estimated value too. The payment is
  confirmed with Maya by the server (return page, webhook or daily job), then registered.
* **Paid but not registered:** if the registrar fails after payment (for example, the reseller
  balance ran out), the order waits in **Management Admin → .com Domains** for **Retry**. The
  member is told the payment arrived and there's no need to pay again.
* **The card's address:** a member's first domain becomes their card's address: the share link,
  QR code, vCard and link previews use `http://yourname.com` once its DNS is set. They can pick
  another of their domains (**Use for my card**) or go back to the 1EVOAI link. The 1EVOAI link
  keeps working either way.
* **Renewals:** **Renew** pays another year with Maya. **Auto-renew** pays from the member's
  available balance 7 days before expiry. Reminders go out 30, 7 and 1 days before expiry.
* **Drivers:** `REGISTRAR_DRIVER=mock` (the default) is a built-in test registrar. Payments use
  Maya (`PAYMENT_DRIVER=maya`, the default). For testing offline on XAMPP, `PAYMENT_DRIVER=mock`
  shows a **Pay now / Fail / Cancel** test page instead, and nobody is charged.
* **Members' details:** domains are registered in the member's name with the address and mobile
  number from **My Account**, so buying asks for them first.

Schedule the daily job (reminders, auto-renew, expiry, unpaid-order checks):

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\evoai\bin\domains-daily.php
```

On an existing database, add the tables (safe to run twice):

```bat
C:\xampp\mysql\bin\mysql.exe -u root evoai < database\migrations\2026-10-09-domains.sql
```

## Member websites

Every member builds their own website in **Member Backoffice → 9. My Website**; no `.com` domain
is needed (since 2026-10-15).

* **Without a domain:** the published site is at its portal address,
  `index.php?view=site&u=<user id>` (shown in the builder), and the member's card gets a
  **Visit my website** button. The builder offers **Get a .com domain**.
* **With a domain:** the site is also shown at the domain (see **Publish** below).
* Only an inactive account (suspended, or the `visitor` role) can't build, and its site is
  offline.

**Building a site:**

* **Starting point:** the first draft comes from the member's card profile: a hero banner with
  their name, About (bio and photo), Services, Shop (when they have products on their card),
  Digital card, Social links and Contact.
* **Start from a design:** 8 ready-made designs, each with its own theme, font and sections:
  Professional, Business & Shop, Coach & Mentor, Join My Team, Personal Brand, Local Services,
  Portfolio and Simple Card.
  * **Filled in from the member's card profile:** name, title, bio, photo, services, email and phone.
  * **Placeholders are guidance, never invented facts:** testimonials say "Ask a client…", numbers
    start at 0, and prices say "Set your price".
  * **Join My Team** uses the real packages, prices and perks from Portal CMS & Pricing, and the
    member's own sign-up link and Referrer ID.
  * **Choosing one** replaces the draft. The published site stays until the next Publish. A member
    with no saved website sees the picker open.
* **Sections** (22 types, up to 30 per site), grouped in the **Add section** list:
  * **Introduce yourself:** Hero banner, About, Image & text, Image banner, Text, Video (YouTube
    or Vimeo).
  * **What you offer:** Services, Packages & prices (price, "per …", what's included, a badge,
    highlight, button), Shop, Steps (how it works).
  * **Build trust:** Numbers, Testimonials, Gallery, Team, Logos & partners, Questions & answers.
  * **Get in touch:** Call to action, Contact, Location & hours (address, a "Get directions" map
    link, opening hours), Link buttons (link-in-bio style), Digital card, Social links.
  * **Starter content:** a new section starts with a little guiding content, so it shows in the
    preview at once.
* **Arranging them:** add, reorder, hide, duplicate or delete any of them. Each section's
  fields open below it.
* **Site settings:** site name, theme (Executive Gold, Clean Light, Ocean, Rose, Forest), an
  optional accent colour, font, menu, a "Digital card" button, footer text, and the search title
  and description.
  * **Menu:** it lists the sections that have a title. On computers it stays one row in the
    sticky header: a long menu (a site with many sections) scrolls sideways, and a short one sits
    at the right next to the "Digital card" button. On phones it opens from the **Menu** button.
    (`assets/css/site.css`, `.site-menu`.)
* **Images:** upload them (JPEG, PNG, WebP or GIF, up to 5 MB, 100 per member) or paste an
  address.
* **Saving and preview:** every change saves the draft a moment later. The live preview (desktop
  or phone width) jumps to the section being edited.
* **Publish:** the site goes online at its portal address. With a domain, the domain then shows
  the website, the card moves to `yourname.com/?view=card`, and the card's share link, QR code and
  vCard follow it.
* **Card and website link to each other:** the site's "Digital card" links open the card, and the
  card shows **Back to website** at the top, returning to the section the visitor left. Anyone
  who opens the card another way (a shared link or QR code) sees **Visit my website** instead.
* **Take down website:** the site goes offline (the domain, if any, shows the card again) and the
  card's **Visit my website** button disappears. The draft is kept, so the member can
  publish it again later. **Start again** rebuilds the draft from the profile.
* **Delete my website:** permanently removes the draft, the published site and every uploaded
  image (the member types DELETE to confirm). Messages from the contact form stay in Contacts.
  * **Taken offline by an admin:** the site stays offline after a delete, so deleting can't get
    around the admin's decision.
* **The portal address** (`index.php?view=site&u=<user id>`) always works for a published site:
  it is the address without a domain, and works before a domain's DNS resolves (and on XAMPP).

**The Shop section:**

* **What it sells:** the member's products from **Backoffice → My Products**: the ones shown on
  their card, in the same order. The section has its own title and introduction.
* **Buying:** **Buy now** opens a form (quantity, name, email, mobile, delivery address or notes)
  and the buyer pays on Maya's page, exactly as on the card: the same platform fee, Available
  Balance credit, notification and orders list (marked **Website**).
* **Back from Maya:** the buyer returns to the website (on the domain they bought on, or the
  portal address), which shows paid, waiting (it checks again every few seconds) or not paid.
* **No products yet:** visitors don't see the section; the builder's preview shows a hint.
* **Preview:** the buy form opens but can't pay until the site is published.
* **Maya not set up:** the products show without **Buy now**, with a note to contact the member.

**The contact form:**

* **Where messages go:** they land in the member's **Contacts** with the source "My website",
  and the member gets a backoffice notification.
* **Repeat visitors:** a visitor who writes again with the same email is added to their
  existing contact.
* **Auto responders:** messages start the member's auto responders, like any new contact.
* **Spam protection:** a hidden spam-trap field and a limit of 5 messages per visitor per
  10 minutes (100 per site per day).

**Safety:**

* **Text:** everything members type is shown as text (no HTML).
* **Links:** only `https://`, `#section`, `mailto:` and `tel:` links are allowed.
* **Videos:** only YouTube (privacy mode) and Vimeo players are embedded.
* **Pages:** sites are served with a strict Content-Security-Policy, and only `assets/js/site.js`
  runs on them.
* **Uploads:** must be real images. Files with script or PHP code are refused.
* **Photo location:** GPS position and other private data are removed from JPEG and PNG photos;
  the orientation is kept.
* **The upload folder:** `uploads/sites/` serves image files only and never runs scripts.

**Management Admin → .com Domains → Members' websites** lists every site with its status and
visits:

* **View:** opens the site.
* **Take offline:** with a reason the member sees. The domain shows the card, and the member
  can't publish until an admin presses **Restore**.

On an existing database, run `database/migrations/2026-10-09-websites.sql` once (safe to run
twice).

## Shop products (admins)

**Management Admin → Shop Products** manages what members can buy in **Backoffice → Shop**:

* **Add product:**
  * a picture (JPEG, PNG, WebP or GIF, up to 5 MB)
  * name and category (existing categories are suggested)
  * member price and regular price (the member price can't be higher)
  * points, stock and the order in the shop
  * a description
  * whether it is shown in the shop
* **Edit:** change any of these, or upload a new picture. The old uploaded picture is deleted.
* **Hide / Show:** takes a product off the shop and puts it back. A hidden product can't be added
  to a cart, and a cart that still holds it can't be checked out until it is removed.
* **Delete:** removes the product for good, and from members' carts.

**Orders and pictures:**

* Orders keep their own copy of each product's name and price, so editing or deleting a product
  never changes an order. The list shows how many of each were sold.
* Pictures are stored in `uploads/products/` with random names. They must be real images (files
  with script code are refused), and GPS and other hidden data are removed from photos.

On an existing database, run `database/migrations/2026-10-12-shop-products.sql` once. It adds the
"shown in the shop" switch and is safe to run twice.

## Mentor Academy (learning library)

**Mentor Academy** is the members' learning library: training videos, documents, slides, audio
lessons, articles and links, sorted into categories.

**Members: Backoffice → 11. Mentor Academy**

* **Browse:** lessons are grouped by category, in the admin's order. The tab shows how many new
  lessons (added in the last 30 days) the member hasn't opened yet.
* **Filter:**
  * by category (chips at the top; the choice is kept in the address as `&cat=…`)
  * by kind (videos, documents, audio, articles, web links)
  * by progress (not completed / completed)
  * by searching titles and descriptions
* **Open a lesson** in the viewer. Previous / Next go through the lessons as listed.

  | Kind | Shown as |
  |---|---|
  | YouTube or Vimeo video | Embedded player (YouTube's privacy-enhanced address) |
  | Video or audio file | Player in the page; members can skip through it |
  | PDF or picture | Shown in the page, with "open in a new tab" and Download |
  | Word, Excel or PowerPoint | Download button |
  | Article | The text, with paragraphs and lists |
  | Web link | Opens in a new tab |

* **Progress:** **Mark as completed** (press again to undo). Progress shows overall and per
  category, and **Pick up where you left off** offers the last lesson opened but not finished.

**Admins: Management Admin → Mentor Academy**

* **Categories:** add, rename (with a short description), reorder, and delete once empty. Six
  starting categories come with the database. A category shows to members once it has a
  published lesson.
* **Add material:** title, category, kind, then:
  * a **video** file (`.mp4`, `.m4v`, `.webm`) or a YouTube / Vimeo link
  * a **document** (`.pdf`, pictures, `.docx`, `.xlsx`, `.pptx`, `.doc`, `.xls`, `.ppt`)
  * **audio** (`.mp3`, `.m4a`, `.wav`)
  * an **article** (a blank line starts a paragraph; lines starting with `- ` make a list)
  * a **web link**

  Each can also have a short description, its length in minutes, its order in the category and a
  cover picture (YouTube videos use their own). A progress bar shows the upload.
* **Publish / Hide**, **Edit** (a new file replaces the old one, which is deleted) and **Delete**
  (the file, cover and members' progress go with it).
* **Statistics:** materials, categories, members learning and completions; each material shows
  how many members opened and completed it. The list filters by category and searches by title.
* **File checks:** only the types above, and the content must match the file name: a web page
  renamed to `.pdf` is refused. The size limit is the server's upload limit (`upload_max_filesize`
  and `post_max_size`; 40 MB on XAMPP) or 500 MB, whichever is lower. For longer videos, upload
  them to YouTube (Unlisted) or Vimeo and paste the link.

**Where files go:** uploaded materials are kept in `storage/academy/`, which the web server never
serves. `academy-file.php?id=<material>` hands them to signed-in users only: published materials
to members, hidden ones to admins too. Cover pictures go to `uploads/academy/`.

On an existing database, run `database/migrations/2026-10-12-mentor-academy.sql` once. It adds the
tables, the starting categories and a "Welcome to Mentor Academy" article, and is safe to run twice.

## Activation codes (superadmin)

Every code has a **serial number** (`SN-000001`, in order of generation) and one of three
**statuses**:

* **Active:** ready to use for a sign-up or a new team member, and the owner can transfer it.
* **Inactive:** switched off by an admin. It can't be used or transferred until it is activated.
* **Used:** spent on a sign-up or a team member. A used code can't change status again.

**Management Admin → Activation Codes** (`index.php?view=admin&tab=codes`, `admin` role only) has
three parts:

* **Generate:** choose the package (Executive or Chairman's Circle, at the CMS price), how many
  codes to make (1–100), and whether they start **active** or **inactive**. Inactive suits
  printed cards that you activate when they are sold.
  * The codes go into a backoffice account's vault: type its ID (`MMM-…`) or `@username`, or
    leave it empty for the company stock (the founder's account, `MMM-88001`).
  * The serials, codes and 6-digit PINs are shown once, 25 per page, with **Copy all** and
    **Download CSV** for the whole batch. The owner also sees them in their backoffice.
  * Admin codes look like `EVO88-EXE-K7M2Q9XA` (`VIP` for Chairman's Circle) and leave out
    0/O and 1/I.
* **Verify a code:** shows whether the code exists and, if so:
  * its serial, status, package and value
  * its owner, when it was generated and by whom
  * who used it, and its last transfer
  * whether the PIN is right, when you type one
* **Activation Code Logs:** every code, from the admin and from members, 25 per page, newest
  first.
  * Columns: **Serial, Package, Code, PIN, Status, Used By, Date Used**. Click a masked PIN to
    show it.
  * Search by serial (`SN-000012` or `12`), code, owner or user, and filter by status and package.
  * **Serial range:** "Serial … to …" shows only the codes in a range, for example `SN-000001` to
    `SN-000010` (`1` to `10` and `SN000001` work too). Leave one end empty for "from … onwards" or
    "up to …"; a reversed range is put the right way round. The range works with the other filters
    and the pages, and **Select all … that match**, Activate / Deactivate and **Download CSV** use it,
    so a printed batch can be activated or downloaded by its serials.
  * **Deactivate** and **Activate** switch an unused code off and on.
  * **Several at once:** tick codes (or the header box for the whole page) and use **Activate
    selected** or **Deactivate selected**. When the whole page is ticked, **Select all N codes
    that match** extends the selection to every page of the current filters. For example,
    filter on *Inactive*, select all, and activate a whole printed batch. Used codes and codes
    already in that status are left as they are (the message says how many), and up to 500 codes
    can be ticked by hand.
  * **Download CSV:** downloads the selected codes (used ones too) as
    `activation-codes-<date>.csv`, oldest serial first, up to 10,000 codes per file. Columns:
    Serial, Package, Value, Code, PIN, Status, Owner Account, Owner Username, Owner Name, Used By,
    Used By Email, Date Used, Generated. The file is UTF-8 and opens directly in Excel; a value
    that starts with `=`, `+`, `-` or `@` is saved as text, not as a formula.

Generating, activating and deactivating codes are written to the audit log, and also to the
owner's backoffice activity log. Each CSV download is written to the audit log too (the file
holds PINs).

Members still buy Executive codes in their backoffice (**My Account → Activation Codes**), where
each code shows its serial and status, 25 codes per page, newest first. **Add Team Member** accepts only active Executive codes,
because a team member enters on the ₱2,000 package; Chairman's Circle codes are for sign-ups.

On an existing database, apply the `mlm_codes` changes (the `serial` column and the
`active` / `inactive` / `used` statuses, with `unused` becoming `active`) and the `rate_limits`
table from `database/evoai.sql`, or re-import it.

## Rewards plan (superadmin)

The standard plan:

* **Package entry:** ₱2,000.
* **Referral bonus:** ₱200 to the referrer for each new team member.
* **Match bonus:** ₱300 for each 1 Left : 1 Right match.
* **Match limit:** 10 matches per round, with 2 rounds per day (12:00 AM – 11:59 AM and 12:00 PM –
  11:59 PM). That's 20 matches per day, or at most ₱6,000 a day in match bonuses, for each member.
  * **Past the limit:** when a match would form after a member's 10th match in a round, the pair
    is blocked and the member is flushed. No match bonus is paid, and all their left and right
    points go to 0, the new member's included. For example, 5 points waiting on the left plus a new
    member on the right gives 0 : 0. It is recorded as a ₱0 transaction of type `binary_flush`,
    shown as "Match Over Round Limit: Points Cleared" with the points cleared per side. On screen
    the word "flush" is never used. The referrer's ₱200 Referral Bonus is paid as usual.
  * **Paid matches stay:** the first 10 matches of a round are paid as normal and never reversed.
  * **People stay:** only points are cleared; team counts and the tree are untouched.
  * **No pair, no flush:** a point with nothing waiting on the other team keeps waiting, even past
    the limit and into later rounds. Points are never cleared at a round reset or on a timer.
  * **Counts:** match counts start again each round and at midnight. A member's counts are brought
    up to date the moment a new member pays them, so the first sign-ups after midnight use the new
    day's counts.

**Placement.** Every paid sign-up (Maya or activation code) and every **Add Team Member** joins
the referrer's team tree:

* **Where (sign-ups):** under the member whose ID the new member enters as **Referrer ID**, on the
  team (left or right) that member chose in **My Team → Team Tree → Sign-up Placement**
  (`mlm_accounts.signup_placement`, Auto until they choose). The sign-up form shows the choice
  under the ID: whose team the new member joins, and on which side.
* **Where (Add Team Member):** the member chooses the team in the form (My Team → Team Tree →
  **Add Team Member**, which starts on their Sign-up Placement choice, or an empty slot to place
  under that member).
* **The choices:**
  * **Auto:** the team with fewer members (left on a tie).
  * **Left** or **Right:** that team.

  The new member takes the top slot of that team when it is free. Otherwise they go to the first
  free spot further down the same team, level by level and left before right. Two sign-ups at the
  same moment never take the same slot. Add Team Member's confirmation says exactly where they
  landed.
* **What the referrer earns:** the referral bonus.
* **Counts, points and matches (every member above):** the new member adds 1 to the team count and
  1 point on their side for every member above them in the tree, whoever referred them. A member
  whose other team has a point waiting makes a match at once (within their own round limit): the
  match bonus, and one point used from each team. For example, abc1 on the left and abc2 on the
  right of MMM-88102 earn MMM-88102 ₱200 + ₱200 + ₱300 if MMM-88102 referred both, and the ₱300
  match even if someone else referred them. Until 2026-10-13 only the referrer could make a match;
  `C:\xampp\php\php.exe bin\settle-matches.php` pays the matches that rule left waiting (it shows
  what it would do; add `--apply` to do it).

On an existing database, add the Sign-up Placement column (the dev database already has it):

```sql
ALTER TABLE mlm_accounts ADD COLUMN signup_placement ENUM('auto','left','right') NOT NULL DEFAULT 'auto' AFTER leg_position;
```

Sign-ups made before this placement existed can be placed, and their referrers paid, with
`C:\xampp\php\php.exe bin\place-signups.php`. It shows what would happen; add `--apply` to do it.
Running it again does nothing.

**Management Admin → Rewards Plan** (`index.php?view=admin&tab=complan`, `admin` role only)
changes these values:

* **Fields:** Referral Bonus, Match Bonus, Matches per Round and Rounds per Day (1 or 2).
* **Matches per Day** is matches per round × rounds per day. The side panel shows it, with the
  maximum match income per round and per day, as you type.
* **Fill in the standard plan** puts back ₱200 / ₱300 / 10 × 2.

A saved plan applies to every new team member's placement and every match from then on; bonuses
already paid stay as they were. The backoffice (header, dashboard rounds, My Team, reports,
transaction filters) and the main page quote the current plan. Saving is written to the audit
log. The plan is stored in `cms_settings` under `mlm_complan`, and **Reset CMS** leaves it alone.

On an existing database, run `database/migrations/2026-10-14-match-limit.sql` once (safe to run
twice) **before** uploading the new `includes/mlm.php`. It adds the `binary_flush` transaction type;
without it a sign-up that flushes a match fails. A saved plan keeps its own matches per round:
the new standard of 10 only applies where no plan was saved, or after **Fill in the standard plan**.

## Managing users (superadmin)

**Management Admin → Users** (`index.php?view=admin&tab=users`) lists every account that can sign
in to the portal (the `users` table). Only the `admin` (Super Admin) role sees it.

* **Nobody is listed until you press Show users.** The page opens with the filters and a note
  giving the total number of users.
  * **Filters** (`admin_user_filter()` in `includes/users_admin.php`; all kept in the address and
    the page links):
    * **Name:** also finds a user by email, user ID, or one of their backoffice accounts (its ID such
      as MMM-88004, its name such as MMMC3, or its username).
    * **Package:** Executive Member or Chairman's Circle, from the tier on the user's card profile
      (set at sign-up or by an upgrade payment; members can't change it). Users without a card
      profile, such as a staff admin, only show under All packages.
    * **Items per page:** 10, 25 (default), 50 or 100.
    * **Activated from / Activated to:** the day the user's account was created (sign-ups are created
      once paid or activated with a code), both days included. A "from" after the "to" shows an
      error and lists nobody.
    * **Sort by:** date activated (newest or oldest first) or name.
  * **Show users with no filters** lists everyone. **Clear** goes back to nobody listed.
  * **"Back to Users"** in a member's read-only Backoffice returns to the list showing that member.
* **List:** name, email, role with the package under it, backoffice account, status, date activated
  and last sign-in. Users with a 1EVOAI Card get a **Card** button. On phones the table becomes a
  stacked list.
* **Every backoffice account is shown** (`mlm_accounts.user_id`), with no dropdown:
  * **The count line** gives users and accounts, e.g. "3 users · 18 backoffice accounts".
  * **The user's row** has the main account in the **Account** column: its ID, available balance
    and "+N more below" when there are more. A user with no account shows "—".
  * **Each other account** (e.g. the founder's corporate accounts MMMC1 to MMMC16) is a row of its
    own right under the user, indented. It shows the account's name, @username, whose account it
    is, balance, earnings and team (left:right), an "Account #" badge, its ID, status and start
    date. Phones show the same as entries in the stacked list.
  * **Each account's Backoffice button:** on your own accounts it makes that account your Active
    Account and opens your Backoffice. On another user's it opens their read-only view at that
    account (`&member=…&head=MMM-…`).
  * **Search:** accounts the search found are marked.
  * **Referred members:** people who joined with the user's Referrer ID aren't listed under them;
    they're in the team.
  * **Narrower windows:** the table scrolls sideways inside its box.
* **Edit:** name, sign-in email (must be unique), executive title, access role, status
  (**Suspended** blocks sign-in at once), the verified badge, and an optional new password
  (at least 6 characters).
  * Only changed fields are validated and written.
  * If the account changed after you opened the editor (for example the user signed in, or another
    admin saved it), nothing is saved and the latest details are shown.
  * A new password signs the user out everywhere they are signed in.
* **Backoffice:** opens that member's Member Backoffice read-only (`index.php?view=backoffice&member=usr_x`).
  * A banner names the member, and every tab shows their data: accounts, wallet, team, codes,
    transactions, orders, profile and card.
  * Nothing can be changed: purchases, transfers, withdrawals, checkout and profile edits are
    refused with a read-only message.
  * The account switcher opens the member's other accounts, and your own session and selected
    account stay as they were.
  * Only the `admin` role can use `member=`; anyone else simply sees their own backoffice.
  * This is the only way an admin reaches someone else's backoffice. In their own backoffice an
    admin, like every member, sees and switches between only the accounts they own
    (`mlm_accounts.user_id`). Members who joined with the admin's Referrer ID are in the admin's
    team, not their account list. An admin user with no backoffice account of their own gets a
    notice pointing here, and no account is created for them.
* **Delete:** asks for confirmation, then permanently removes the account together with its card
  profile and backoffice accounts (wallet, transactions, activation codes, cart and orders).
  Payments, leads, chat messages, audit entries and team-tree placements are kept as records.
* **Safeguards:** you can't delete, suspend or demote your own account, and nobody can delete the
  founder's account (`usr_mike`, which holds the master card).
* Every edit and delete is written to **Security & Audit Log** with the field names only, never the
  values.

## Withdrawals (superadmin)

Members cash out in **Member Backoffice → Dashboard → Request Withdrawal**:

* the amount (minimum ₱500), a payout channel (GCash, Maya, bank transfer or cheque), the account
  details and their security PIN;
* 10% tax and a ₱50 processing fee come off the payout (₱1,000 gives ₱850);
* the full amount leaves their available balance at once, and the request waits as **Pending**.

**Management Admin → Withdrawals** (`index.php?view=admin&tab=encashments`, `admin` role only)
is where requests are paid out. The tab shows how many are pending:

* **Figures:** pending requests and the net amount to pay out, released this month, released all
  time (with tax withheld and fees), and rejected requests.
* **Pending by payout channel:** the net amount to send per channel; click one to see its queue.
* **Monthly report:** the last six months by request date: requests, amount requested, released
  (net), tax, fees, and pending / rejected counts. Click a month to list its requests.
* **Withdrawal Requests:** every request, processed ones included: pending requests first
  (oldest at the top), then released and rejected ones (newest first). Filter by status, channel
  and request date, or search by member, account ID, account details or reference. The list shows
  25 requests per page. The line above it totals gross, tax, fees and net for everything that
  matches. **Download all as CSV** saves that whole list, for example as a payout batch for GCash
  or the bank.
* **Ticking requests:** tick requests, or the box in the header for the whole page, to work on
  several at once:
  * **Download selected CSV** saves just those, whatever their status.
  * **Release selected** lists the pending ones with their net amounts. Give each its payout
    reference, or type one batch reference and click **Use for all**.
  * **Reject selected** gives them all one reason.

  Released or rejected requests in the selection are left out, and so is one another admin
  processes at the same moment; the message after saving names it.

Processing a request:

1. **Release:** send the net amount to the account shown (the copy button copies the account
   details). Then click **Release** and enter the payout reference, the GCash, Maya or bank
   transaction number. A note for admins is optional. The member's transaction becomes
   **Completed** with the reference, and they get a notification with the amount and reference.
2. **Reject:** click **Reject** and give the reason, for example a wrong account number. The full
   requested amount goes back to the member's available balance. Their transactions show the
   request as **Rejected** and a **+ refund** line, and the notification gives the reason.

Each request is processed once. If two admins click at the same moment, only one action counts
and the other sees "already released" or "already rejected". Every release, rejection and CSV
download is written to the audit log with the admin's name.

On an existing database, add the new columns once (safe to run twice):

```bat
C:\xampp\mysql\bin\mysql.exe -u root evoai < database\migrations\2026-10-09-encashments.sql
```

## Contacts, auto responder and AI assistance (members)

**Member Backoffice → 8. Contacts** (`index.php?view=backoffice&tab=contacts`) gives each member
their own contact list, automatic follow-up emails and an outbox. Members only ever see and change
their own contacts.

* **Contacts:** name, email, phone, company, stage (New, Contacted, Interested, Joined, Not
  interested), how you met, tags and notes. Search, filter by stage, 25 per page.
  * Opening a contact shows their emails (sent, scheduled, failed, cancelled) and lets the member
    write a new one: **Send Email** sends it now, **Copy** puts it on the clipboard for Messenger,
    Viber or SMS. The first email moves a New contact to Contacted.
* **Auto Responder:** messages sent by themselves to each contact the member adds with an email
  address. "Send on day" 0 goes out right away, later days follow. A message can go to all new
  contacts or only those from one source (for example Event).
  * **Who writes it:** "AI writes a personal email for each contact" (the default when AI is on)
    or "I write one message for everyone". See *AI-written auto responder emails* below.
  * Placeholders fill in for each contact: `{first_name}`, `{full_name}`, `{my_name}`,
    `{my_first_name}`, `{my_email}`, `{my_phone}`, `{my_referral_id}` and `{signup_link}` (the sign-up
    form with the member's ID filled in as referrer). A misspelt placeholder is refused when saving,
    and a preview shows the email for a sample contact.
  * Messages are scheduled when a contact is added (or first gets an email address), so a new
    message applies to contacts added from then on.
  * A contact's automatic emails stop when they unsubscribe, are marked Joined or Not interested,
    or auto follow-up is unticked for them. Turning a message off, or deleting it, cancels the emails
    it had scheduled.
* **Outbox:** every email with its status, filterable; scheduled ones can be cancelled. A failed
  email keeps the reason (for example the SMTP server couldn't be reached).

**Sending.** Due emails go out when the member opens the backoffice, and with
`C:\xampp\php\php.exe bin\send-autoresponders.php`, which sends everyone's (up to 200 per run,
`--limit=N` for more). Run it every few minutes with Windows Task Scheduler (or cron) so emails
go out on time. With `mail.transport` `smtp`, it needs `app.base_url` set, because emails link
back to the site.

* Emails come from `mail.from_email` as "<member name> via 88MMM Circle", and replies go to the
  member's own email.
* Every email ends with a line saying why the contact gets it and an unsubscribe link, and carries a
  `List-Unsubscribe` header. The link opens `index.php?view=unsubscribe&t=…`, which asks first;
  the **Unsubscribe** button stops all emails from that member. Opening the link alone changes
  nothing, so email link scanners can't unsubscribe anyone.
* With `mail.transport` `off`, nothing is sent: emails wait in the outbox as Scheduled, and the tab
  says so. `log` writes them to `storage/mail/mail.log` for development.

**AI assistance.** With an API key for the chosen provider, the auto responder editor and the
contact window get **Write with AI** (the panel names the provider): the member picks a goal, a tone
(friendly, professional, enthusiastic) and a language (English, Filipino, Taglish), adds anything
specific, and the AI writes a draft into the fields.

* **Providers** (`ai.provider` for this tab; the 88MMM Assistant has its own, `ai.chat_provider`;
  `includes/ai.php`):
  * `groq` (in use for the Contacts tab): Groq's chat API (`https://api.groq.com/openai/v1/chat/completions`) over
    curl, with `ai.groq.model` (`openai/gpt-oss-120b` by default). Answers use Groq's strict JSON
    schema output on `openai/gpt-oss-120b` / `openai/gpt-oss-20b` (other models get JSON mode),
    with medium reasoning that isn't returned. A draft takes about 1–2 seconds. Usage shows in
    the Groq console.
  * `anthropic`: Claude through the official Anthropic PHP SDK with `ai.anthropic.model`
    (`claude-opus-5-5`), structured output, and the API's server-side refusal fallback
    (`fallbacks: "default"`). Usage shows in the Anthropic Console.
* Drafts are only suggestions. The member reads and edits them, and nothing is sent or saved until
  they click Send or Save.
* Auto responder drafts use the placeholders. If the fields already hold a draft, the AI improves
  it instead of starting over.
* Follow-up drafts use the contact's name, company, stage, source, tags, notes and the subjects of
  past emails, plus a suggested next step for the member. These details are sent to the provider's
  API to write the draft; the contact's email address and phone number are not.
* The instructions to the AI rule out promises of income or earnings, added details (times,
  durations, places, links, offers) and pressure, and treat contact notes as information, not
  instructions. Every prompt ends with the language to write in (with a Taglish example), which is
  what keeps models from answering in English.
* If the AI declines, the API key is rejected, or the service is busy or unreachable, the member
  sees a short explanation. A busy Groq is asked once more after a second before giving up.
* Each member may request `ai.daily_limit` drafts a day (40 by default).

**AI-written auto responder emails.** An auto responder message set to "AI writes a personal email
for each contact" has no fixed text. It stores a goal, tone, language and the member's instructions
("Anything to add?"). When the email is due, the AI writes it for that contact from those settings
and the contact's details, notes and earlier emails, and it goes out without the member reading it
first. The editor explains this.

* **Show an example** writes one for a sample contact (Juan Dela Cruz, met at a business expo), so
  the member sees what contacts will get. It counts as a draft.
* Until an email goes out, the outbox shows "AI writes it when it goes out"; afterwards it keeps
  exactly what was sent.
* Writing takes a few seconds per email, so a member's visit to the backoffice writes at most one;
  `bin/send-autoresponders.php` writes the rest, so schedule it.
* Each member's AI-written emails count against `ai.daily_auto_limit` (100 a day by default).
* **When AI can't write it:**
  * AI busy or unreachable: the email waits and is tried again 15 minutes later.
  * Over the daily limit: it is tried again every hour, until the limit resets.
  * AI off (no key): it waits, and goes out once AI is back on.
  * The AI declines, or another error: the email is marked Failed, with the reason.
* With AI off, members can't create new AI-written messages, and the editor starts on "I write
  it". Existing AI-written messages can still be edited, turned off or deleted.

The superadmin's read-only view of a member's backoffice shows their contacts and opens a contact,
but nothing can be added, sent or changed there.

**Database.** The tables are `crm_contacts`, `crm_autoresponders` and `crm_messages`. On an existing
database, run their `CREATE TABLE` statements from `database/evoai.sql` (the dev database already
has them).

## 88MMM Assistant (AI chat)

An **Ask AI** button on the Main Portal, Member Backoffice and Management Admin opens the
88MMM Assistant, a chat that answers questions about the rewards plan, joining, packages,
activation codes, cashing out and the portal's features, in English, Filipino or Taglish. It isn't
shown on members' digital cards or the payment page.

* **AI:** its own provider, `ai.chat_provider`: **Claude** (`anthropic`, model `ai.anthropic.model`,
  `claude-opus-5-5`), while the Contacts tab uses Groq (`ai.provider`). Requests go through the
  official Anthropic PHP SDK with low effort (quick chat answers), up to 16,000 output tokens and
  the server-side refusal fallback (`fallbacks: "default"`). An answer takes about 4–7 seconds.
  Usage and cost show in the Anthropic Console.
* **Stand-in provider** (`ai_chat()` in `includes/ai.php`): when the chat provider has no API key,
  rejects its key (wrong, revoked or expired), is busy or unreachable, or returns an error, the other
  provider answers instead, if it has a key. A rejected key is then skipped for 10 minutes
  (`AI_KEY_REJECTED_PAUSE`; remembered in `rate_limits` as `ai-key-rejected:<provider>`), so a
  revoked key doesn't slow every question or fill the log. After that it is tried again. A
  refusal is final. The error log says when the stand-in answers.
* **On / off:** the button appears once **either** API key is set (`ai.anthropic.api_key` or
  `ai.groq.api_key`). With neither, members and visitors don't see the button; administrators
  still do, and the window tells them to add either key.

* **Facts from the live settings** (`includes/assistant.php`): the plan's current bonuses and
  match limits (Management Admin → Rewards Plan), package prices and perks and the contact details
  (CMS), withdrawal rules, code rules, shop products, and where everything is in the portal. When
  the admin changes the plan, the answers change with it.
* **Signed-in members** also get answers about their own account: available balance, income so
  far, left and right teams and waiting points, matches this round, Sign-up Placement and unused
  codes. That summary
  of their own data is sent to the AI with their question; nobody else's data is.
* **Rules for the AI:** answer only from those facts and say so when it doesn't know (pointing to
  the contact details or the referrer); never promise or estimate guaranteed income; never ask for
  passwords or PINs; reply in the person's language.
* **Only 88MMM questions** (`assistant_system_prompt()`, `ai_chat()`, `ASSISTANT_OFF_TOPIC`):
  * **The rule:** the instructions list what's in scope (joining, packages, payments, the
    rewards plan, codes, the cards, every Backoffice tab, E2EE Chat, the person's account, contact
    details, greetings) and what isn't (general knowledge, news, sports, maths, coding,
    translations, writing, recipes, advice, other companies, jokes, role-play, questions about the
    AI itself). Nothing typed in the chat can change that ("ignore your instructions", "pretend",
    "it's for 88MMM").
  * **The gate:** the AI must answer in JSON: `onTopic` (decided first) and `reply`. Claude uses
    `AiChatReply` in `includes/ai_models.php`; Groq uses a strict JSON schema, `AI_CHAT_FIELDS`.
  * **Off-topic answers:** when `onTopic` is false, the portal shows its own fixed answer (English
    and Filipino: "Sorry, I can only help with questions about 88MMM Circle and this portal …") and
    never the AI's text.
  * **Mixed questions:** the 88MMM part is answered and the rest declined in one sentence.
  * **Limits:** off-topic questions still count towards the hourly and daily limits.
* **Conversation:** kept in the browser tab (sessionStorage) and sent back with each question
  (the last 12 messages); nothing is stored on the server. **New chat** starts over. Starter
  questions differ for visitors and members.
* **Limits:** `ai.chat_limit` questions per member per day (100) and `ai.chat_guest_limit` per
  visitor per hour (20, by IP address).
* **Busy AI:** when the provider asks to wait (rate limits), the Anthropic SDK waits and retries
  once by itself; if it is still busy, the chat counts down and asks again (twice at most). With
  `ai.chat_provider` set to `groq`, mind Groq's free tier: 8,000 tokens a minute and one question
  uses about 2,200, so only about 3 questions a minute fit across the whole site.
* Files: `includes/assistant.php`, `api/actions/assistant.php` (`assistant.ask`),
  `views/partials/assistant.php`, `assets/js/assistant.js`, styles in `assets/css/custom.css`.

## Project structure

```
index.php            Front controller (?view=main|card|backoffice|admin|chat|pay|unsubscribe|domain|site); members' own .com domains open their website, or their card
maya_webhook.php     Maya Checkout webhook receiver for memberships and domain orders (re-checks every event with Maya)
maya-webhook.php     The older webhook address (runs maya_webhook.php)
.env.example         .com domain settings (copy to .env; never served, never committed)
README-API.md        .com domains: Maya and ResellerClub setup, local testing, going live
LAUNCH-HOSTINGER.md  Launching 88evoai.com: Namecheap domain, Hostinger hosting, step by step
SYSTEM-DOCUMENTATION.md  The whole system on one page: features, roles, architecture, data, security, operations
BINARY-COMPLAN.md    The team tree and Rewards Plan rules, with a worked example
api.php              JSON API (POST, CSRF-protected) used by the JavaScript
vcard.php            vCard 3.0 download (?id=usr_x, or ?card=<id>-<code> for WeServe members)
academy-file.php     Mentor Academy files for signed-in users (?id=<material>[&download=1]; byte ranges for video)
manifest.php         Web app manifest for one card (?profile=usr_x or ?card=<slug>)
bin/member-links.php CLI: export every WeServe member's card link as CSV
bin/card-links.php   CLI: give every card a clean, unique link name (once per existing database)
bin/set-password.php CLI: set an account's password (e.g. the founder's before launch)
bin/place-signups.php CLI: place earlier sign-ups in the team tree and pay their referrers
bin/send-autoresponders.php CLI: send every member's due auto responder emails (schedule it)
bin/domains-daily.php CLI: .com domain reminders, auto-renew, expiry and payment checks (schedule it daily)
config/              config.example.php (copy to config.php)
includes/            bootstrap, PDO helpers, auth, data access (repo.php, mlm.php, chat.php, weserve.php, users_admin.php, encashments.php,
                     maya.php, signup.php, activation_codes.php, crm.php, assistant.php), mailer.php (email), ai.php +
                     ai_models.php (AI drafts: Groq or Claude), env.php (.env), registrar.php (ResellerClub / mock registrar),
                     payment_clients.php (Maya / mock payments), domains.php (.com domains), domain_valuation.php (GoDaddy
                     market value), website.php (members' websites), products.php (Member Shop products), cards.php (card
                     design, members' own products and card sales), academy.php (Mentor Academy), icons
api/actions/         API extensions (admin_users.php, admin_codes.php, admin_complan.php, admin_encashments.php, admin_products.php: superadmin; profile.php: members' profile pictures; card.php: card design, members' products, card sales; maya.php, signup.php: payments and sign-up; network.php: team tree levels and sign-up placement; crm.php: Contacts, auto responder, AI drafts; assistant.php: the AI chat; domains.php: .com domains; website.php: website builder and contact form; academy.php: Mentor Academy)
views/               layout + one template per view; views/backoffice/* and views/admin/* tabs; partials/
assets/css/app.css   Reference stylesheet (compiled Tailwind, unchanged)
assets/css/custom.css  Small additions (toasts, print, builder and site styles)
composer.json        Libraries (composer install -> vendor/): Anthropic PHP SDK + Guzzle, PHPMailer
storage/             Runtime files (development mail log, price cache) and Mentor Academy files (storage/academy/); not web accessible
uploads/sites/       Images members upload for their websites (images only; not in git)
uploads/products/    Shop product pictures uploaded in Management Admin (images only; not in git)
uploads/academy/     Mentor Academy cover pictures (images only; not in git)
uploads/avatars/     Profile pictures members upload in Digital Card CMS (images only; not in git)
uploads/covers/      Card cover photos, uploads/card-products/: members' own product pictures (images only; not in git)
assets/js/           app.js (shared runtime), crypto.js (E2EE), checkout.js, assistant.js (AI chat), views/*.js, vendor/qrcode.js
assets/images/       Placeholder artwork and product photos (stored locally)
database/evoai.sql   Schema + demo data (phpMyAdmin import)
database/evoai_launch.sql  Schema + launch data: the founder (locked) with his 17 accounts and website, CMS and products
database/migrations/ SQL for databases created before a feature (2026-10-09-domains.sql, 2026-10-09-encashments.sql)
database/weserve_members.sql  WeServe UserProfile + NetworkAccount tables (personal data; do not publish)
manifest.webmanifest, sw.js, icon.svg   Installable PWA
```

**Old words in code.** The screens use team words (team tree, referrer, match, round, withdrawal,
Rewards Plan). Code and database names keep their original wording: files such as `mlm.php` and
`encashments.php`, tables and columns such as `mlm_accounts` and `sponsor_id`, stored values such
as `binary_pairing` and `encashment`, API actions such as `mlm.registerDownline`, and URL values
such as `tab=complan`, `tab=encashments`, `tab=network` and `net=genealogy`. Renaming them would
break saved data and links.

## Features

* **Authentication & roles**: PHP sessions; bcrypt password hashes (`password_hash`); session ID
  regeneration on sign-in; "keep session active" cookie; per-session sign-in throttling;
  one-time recovery passes (30 minutes); changing a password ends the account's other sessions.
  Roles are `admin`, `vip`, `member` and `visitor` (setting a member to `visitor` revokes portal
  access).
* **Registration (pay first)**: **Create Account** is paid first, through Maya Checkout or with
  an activation code + PIN (see [Paying for a new account](#paying-for-a-new-account)). Then the
  user, the 1EVOAI card profile and a backoffice account linked to the referrer ID are
  created, and that browser is signed in and generates the member's E2EE key.
* **1EVOAI Card**: the reference portal's card page, for the founder and every member. The
  **1EVOAI Card** menu opens the signed-in member's own card. Visitors without a link get a sign-in
  prompt (no default card), and `?profile=usr_x` or a share link opens a particular card.
  * **Profile card:** photo (initials when there is none), name, title, company and location.
    **Save** downloads a vCard; **Chat E2EE**, **Share** and **QR Code** (with PNG download) sit
    beside it, followed by the social links and **Send vCard**.
  * **The 88MMM Circle story:** "Connect. Grow. Prosper.", Who is …, Keynote video, Executive
    Society, Digital Advantage, Weekly Meetings, Events, Education and Member Network, then the
    membership tiers and a closing call to action. All of it is edited in **Portal CMS & Pricing**.
    A member's card shows "About {name}" with their own photo and bio instead of the founder's
    story.
  * **Join the Circle** opens the membership application, which is saved as a CRM lead. Its
    **Sign Up Now** button opens the sign-up form with the chosen package.
  * **The green Sign Up buttons** (header, hero, both membership tiers, closing call to action)
    open the sign-up form (`index.php?view=main&ref=<card owner's main account>&package=…#register`).
    The form arrives with the card owner's Referrer ID and the package filled in, and takes **Maya
    Checkout or an activation code and PIN**. A note under the closing call to action says so, or,
    without Maya keys, that online checkout is still unavailable. WeServe cards have no account
    here, so their buttons leave the Referrer ID at MMM-88001.
  * **The card owner is remembered as the referrer:** opening a member's card stores the owner's
    main account in the browser for 30 days (`signup_remember_referrer()` in
    `includes/signup.php`: cookie `evo_ref`, HttpOnly, the last card opened counts).
    * **Where it's used:** Create Account uses it whenever the link has no `ref` of its own, for
      example after the card's logo, on a later visit to the portal, or via the old
      `?view=pay&checkout=…` link (`views/main.php`).
    * **An emptied field:** the server uses it too (`registration_request()`).
    * **Precedence:** a `ref` in the link always wins. A remembered account that no longer exists,
      or a tampered value, is ignored, and the default is MMM-88001.
  * **Header:** **Sign Up**, **NFC Tap** and the EN / JA / ZH / ES / FR headline language.
  * **Search and sharing:** the page title and link preview show the person, and member cards
    are not indexed by search engines. Each card has its own web app manifest
    (`manifest.php`), with the person's photo as the home-screen icon.
* **Member Backoffice**: dashboard, accounts, profile/security/PIN, activation codes
  (buy, transfer), **3. My Team** (Team Summary, Team Tree, Referral Tree, Team List), adding team
  members (referral and match bonuses, 10 matches per round, counters reset daily), transactions
  ledger, reports, member shop (cart, checkout, orders) and the Digital Card CMS.
  * **Team Tree:** click a member to open the two team members below them; the members
    above stay in view, so you can keep opening level after level. Click the member again to fold
    the branch. Deeper levels load when opened (`mlm.genealogyChildren`). The eye icon on a card
    shows the member's points, and an empty slot at any level opens **Add Team Member** for that
    spot. Members can only open their own team; the superadmin's read-only view of a member's
    backoffice can open that member's tree too.
    * **Moving around:** the tree sits in a fixed-height canvas. Drag it with the mouse in any
      direction; a press only becomes a drag once the mouse moves, so clicking a member still opens
      their branch. Touch screens scroll it with a finger, the arrow keys move it once it has focus,
      and **Center** brings the root account back to the middle.
  * **Add Team Member** (tree header): place a new member on your **Left** or **Right** team, or
    let **Auto** pick the team with fewer members, from the **Placement** dropdown. Each option
    shows its team's member count, and the form says where the member will go (see
    [Rewards plan](#rewards-plan-superadmin)).
  * **Sign-up Placement** (below the tree header): the team where new members who sign up with your
    ID are placed (Auto, Left or Right). It is saved as soon as you change it.
  * **Contacts** (tab 8): contact manager, auto responder emails and outbox, with AI drafts from
    Groq or Claude (see [Contacts, auto responder and AI assistance](#contacts-auto-responder-and-ai-assistance-members)).
  * **Your .com Domain** (Digital Card CMS): search, buy and renew a `.com` domain that opens the
    member's card (see [.com domains](#com-domains-members)).
  * **My Website** (tab 9): with a domain, build and publish a one-page website shown at the
    domain (see [Member websites](#member-websites)).
* **Online payment page** (`index.php?view=pay`): admin invoice links (`&invoice=INV-…`) and
  membership/renewal checkout links (`&checkout=<item>`) open here, with a printable receipt.
  Maya payments go through Maya Checkout (see [Maya Checkout](#maya-checkout-online-payments)).
* **Management Admin**: system analytics, member roles and NFC card management, payments ledger
  (refunds, invoices with pay links), leads CRM with CSV export, portal CMS and pricing editor,
  security and audit log, **Users** (see [Managing users](#managing-users-superadmin)),
  **Withdrawals** (pay out or reject members' requests, with reports; see
  [Withdrawals](#withdrawals-superadmin)), and **.com Domains** (paid orders to retry, payment re-checks, DNS, the registrar log).
* **E2EE Chat**: AES-GCM-256 encryption in the browser. The VIP group room uses a PBKDF2 room key;
  direct rooms use an ECDH P-256 key exchange. Includes burn timers, reactions, typing indicators,
  ciphertext inspection, safety numbers and chimes. The server stores only ciphertext and IVs,
  and new messages arrive by polling.
* **Security**: PDO prepared statements everywhere; server-side validation of every action;
  output escaping (`htmlspecialchars`); CSRF tokens on all API calls; role checks on every
  admin and member action; `.htaccess` deny rules for internal folders; no card numbers, PINs or
  wallet numbers are ever sent to the server (Maya payments are typed on Maya's own page).

## Differences from the reference (intentional)

* The reference showed the Backoffice and Admin console (as Mike) to anyone who was not signed in.
  Here they require sign-in, and the admin tabs require the `admin` role. Non-admins see the
  reference's "Restricted System Access" banner instead of the tabs.
* The reference accepted the password `password123` for every account and silently registered
  unknown emails on sign-in. Neither happens here. "Forgot password" issues a real one-time pass,
  shown on screen only in demo mode.
* The reference's direct-chat room IDs differed between the two participants, so direct messages
  never reached the other person. Room IDs are now symmetric. Its WebSocket transport is
  replaced by HTTP polling, which works on any PHP host.
* Data the reference generated in the browser (random transaction IDs, local-storage state) is now
  generated by the server and persisted in MySQL.
* The reference's three photos (`/src/assets/images/*.jpg`) are missing on the live site and show as
  broken images there. Original placeholder artwork in the same style replaces them in
  `assets/images/`. Replace the files, or point the Portal CMS image fields at real photos.
* The 1EVOAI Card has the reference's layout. Its pay buttons open the separate payment page
  (`view=pay`) instead of a pop-up on the card, so Maya Checkout and its receipt work the same
  everywhere. A member without a photo shows their initials, and a member's card shows only
  their own location and links. The reference fell back to the founder's photo, location and
  links instead.
* The QR code is a real, scannable code for the card's share link. The reference displayed a
  decorative pattern.
* The Backoffice "Reports" charts are calculated from the active account's real transactions and
  team tree. Where an account has no data for a period, the reference's demo series is shown.
  In the reference, the period and filter toggles only changed the highlighted button.
* Admin "Security & Audit Log" adds a compact audit-log list, using the reference's own card
  style, under the reference's key cards. The reference showed no log on this tab.
* Browser `alert()` pop-ups from the reference are shown as in-page toast messages.
* Daily round counters reset at midnight, and matches before noon count toward round 1.
  The reference never reset them and always used round 2.
* A member who opens the Backoffice without a backoffice account gets one automatically,
  referred by MMM-88001.

## Known limitations

* **E-mail is not sent.** Recovery passes appear on screen in demo mode. With demo mode off they
  are generated but not delivered until a mailer is added to `issue_recovery_pass()` in
  `includes/auth.php`.
* **E2EE keys live in the browser.** Each browser or device has its own key, as in the reference,
  so direct messages encrypted for one browser can't be read from another browser or after
  clearing site data (they show the reference's "Decryption error" text). The VIP room uses a key
  derived from the room name, as in the reference. It protects against passive storage access but
  is not secret from someone who knows the scheme.
* Chat updates arrive by polling every 2 seconds, not by push.
* On phones, the header is wider than the screen, the same as the reference.
* **The ResellerClub client was tested against a local stand-in, not ResellerClub itself.** The
  stand-in follows ResellerClub's documented requests and answers; no ResellerClub credentials
  were available. Before going live, run a few searches and one registration in ResellerClub's
  test environment and check the registrar log. Maya's sandbox accepted a real domain checkout;
  completing a sandbox payment needs a person (reCAPTCHA).
