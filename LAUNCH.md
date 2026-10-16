# Launching 1EVOAI (production copy)

This folder is the production copy of the portal. Preview it locally at
http://localhost/evoai/prod/. It uses its own database, `evoai_prod`, loaded from the launch
database file `C:\xampp\htdocs\evoai\database\evoai_launch.sql`.

How it differs from the development copy in `C:\xampp\htdocs\evoai`:

* **Demo mode is off.** There are no Quick Demo Credentials, no account switcher (the list of
  members in the account menu, admins included) and no on-screen recovery passes.
* **The database starts empty.** The only account is the founder's,
  `mike@mmmcircle.com`, and it is locked until you set its password. It has its card and the
  top backoffice account (MMM-88001), both at zero. The portal content and the shop products are
  kept. There are no sample members, payments, leads, chat, orders or codes.
* It has its **own database** (`evoai_prod`), **session cookie** and **card link secret**, so
  signing in to one copy never signs you in to the other.
* **Payments and domains are set up for real money,** with no keys yet:
  * **Payments:** Maya production.
  * **Domains:** ResellerClub's live account.
  * **Until you add the keys** (steps 2.5 and 2.6), nobody can pay and no domain can be bought.
    The site says online payment isn't available yet.
* **No AI keys, and email is off.** The AI drafts and the Ask AI chat stay off until you add keys.
  Contacts emails wait in the outbox until you set up SMTP.
* **Nothing private:**
  * no SQL files
  * no handoff notes
  * no members' uploaded images
  * no development settings or keys

Changes made in the development copy don't reach this folder. Make a new clean copy and test it
here before uploading.

## 1. Before launch (on this PC)

1. **Preview it here (optional).** To sign in to the preview, give the founder a password in
   `evoai_prod`. Open a command prompt in this folder (`C:\xampp\htdocs\evoai\prod`) and run:

   ```bat
   C:\xampp\php\php.exe bin\set-password.php mike@mmmcircle.com
   ```

   Type the password in that window only. Never put it in a chat, email or file. Then sign in at
   http://localhost/evoai/prod/. This password stays on this PC; the live site gets its own in
   step 2.3.
2. **Member card links.** This copy has its own link secret, so member card links here differ
   from the development copy. To give members the same links as the WeServe app's live site, type
   that site's `LINK_SECRET` into `card.link_secret` in `config/config.php` yourself. Changing it
   later changes every member's link.

## 2. Move to the web host

1. **Check the host provides:**
   * **PHP 8.2 or newer** (the version this was built and tested on), with `pdo_mysql`,
     `mbstring`, `openssl` and `curl`.
   * **MySQL or MariaDB.**
   * **HTTPS.**
   * **Apache that honours `.htaccess` files, with `mod_rewrite` on.** The rules give every card
     its own address (`https://<your-domain>/<link name>`, e.g. `/mike`). They also block
     `config/`, `includes/`, `views/`, `api/`, `bin/`, `storage/`, `vendor/`, `.git`, archives and
     the `.env` file. On any other web server, set those up yourself (README.md, "Card links").
   * **Scheduled jobs** (cron).
   * **Members' own .com domains:** the site must answer for every member's domain, not only
     yours. On a VPS or dedicated server, Apache's default virtual host does this. On shared
     hosting, each member's domain must be added as an alias of the site. See README-API.md,
     section 6.
2. **Create the database.** Create a database and a database user in the host's control panel.
   Then, in phpMyAdmin on the host, select that database, open **Import**, choose
   `C:\xampp\htdocs\evoai\database\evoai_launch.sql` and press **Import**.

   The file contains no personal data. It drops and recreates the portal's tables, so import it
   only into the new, empty database, never into one that already holds live data.
3. **Set the founder's password on the host.** The imported account is locked. On this PC, open
   a command prompt in this folder and run:

   ```bat
   C:\xampp\php\php.exe bin\set-password.php mike@mmmcircle.com --sql
   ```

   Type the new password there; nothing changes on this PC. The script prints one line starting
   `UPDATE users SET password_hash = …`. Paste that line into phpMyAdmin's **SQL** tab for the
   host's database and press **Go**.

   **Member cards from the WeServe app (optional):** they need its member tables too. Import
   `C:\xampp\htdocs\evoai\database\weserve_members.sql` into the same database the same way.
   That file contains members' personal data (birth dates, gender, tax IDs). Delete it from your
   downloads once it's imported, and never email it or leave it in the website folder.
4. **Upload this folder's contents**, for example into `public_html` for a site at the domain
   root:
   * Leave out `LAUNCH.md`, `README.md` and `README-API.md`.
   * The `.htaccess` files and `.env` must go too. They start with a dot, so show hidden files in
     your upload tool.
   * Make `storage/` and `uploads/` writable by the web server. Members' website images go in
     `uploads/sites/`.
5. **Edit `config/config.php` on the host:**
   * **Database:** type the host's database `host`, `name`, `user` and `pass`.
   * **Address:** set `app.base_url` to the public address, e.g. `https://portal.example.com`
     (no trailing slash). Maya's return pages, emails and card links use it.
   * **Maya:** type your **live** keys from Maya Business Manager into `maya.public_key` and
     `maya.secret_key`. `maya.environment` is already `production`. Before this, finish Maya's
     integration checklist in the sandbox (README-API.md, section 6).
   * **Email (optional):** set `mail.transport` to `smtp` and fill in `mail.from_email` and the
     `smtp` settings, so Contacts emails are sent.
   * **AI (optional):** a Groq key turns on AI drafts in Contacts; an Anthropic key turns on the
     Ask AI chat.
6. **Edit `.env` on the host** (members' .com domains; details in README-API.md):
   * **Server address:** set `SERVER_IP` to the server's public IPv4 address. New domains point
     there.
   * **ResellerClub:** type the live `RESELLERCLUB_ID` and `RESELLERCLUB_API_KEY`. In
     ResellerClub, whitelist the server's IP and add prepaid funds; every registration and renewal
     is paid from them.
   * **GoDaddy (optional):** a GoDaddy key prices each name at its market value. Without it, every
     name costs the registration price.

   Domain purchases stay refused until real payments (Maya production) and real registrations
   (ResellerClub live) are both set. **Management Admin → .com Domains** lists anything missing.
7. **Register the Maya webhook.** In Maya Business Manager (production), add
   `https://<your-domain>/maya_webhook.php` for `PAYMENT_SUCCESS`, `PAYMENT_FAILED`,
   `PAYMENT_EXPIRED` and `PAYMENT_CANCELLED`.
8. **Schedule the jobs** in the host's cron settings:
   * **Every day:** `php /path/to/site/bin/domains-daily.php`. It handles prices, renewals,
     reminders and unpaid domain orders.
   * **Every 5 minutes, once email is on:** `php /path/to/site/bin/send-autoresponders.php`.
9. **Check the live site:**
   * The home page loads, and you can sign in as the founder with the password from step 2.3.
   * These addresses all answer **403 Forbidden**. If any shows anything else, stop and fix the
     server settings before going further.
     * `https://<your-domain>/config/config.php`
     * `https://<your-domain>/includes/auth.php`
     * `https://<your-domain>/.env`
     * `https://<your-domain>/storage/`
   * **Management Admin → Online Payments & Ledger** shows **Maya** as the gateway, and
     **Management Admin → .com Domains** shows no warnings.
   * Make one small real payment with Maya, for example a shop item, and buy one domain yourself
     end to end.
   * The founder's card opens at `https://<your-domain>/mike`, and so does
     `https://<your-domain>/index.php?view=card`, which redirects there.
   * WeServe member cards open. Export their links with
     `C:\xampp\php\php.exe bin\member-links.php` once `app.base_url` is set.

## Known limits

* **"Forgot password" sends no email.** The screen says recovery instructions were sent, but
  nothing arrives. Until that is added, reset passwords in **Management Admin → Users**.
* **A password change** signs that account out everywhere else.
* **Members' domains open over `http://`** until the server has a certificate for each of them
  (`DOMAIN_URL_SCHEME` in `.env`; README-API.md, section 6).
* **Website images aren't resized,** because this build doesn't use PHP's image library (GD).
  Uploads are limited to 5 MB each.
