# Launching 1EVOAI (production copy)

This folder is the production copy of the portal. Preview it locally at
http://localhost/evoai/prod/. It uses its own database, `evoai_prod`.

How it differs from the development copy in `C:\xampp\htdocs\evoai`:

* **Demo mode is off.** There are no Quick Demo Credentials, no account switcher for non-admins
  and no on-screen recovery passes.
* **Every password is locked**, including the demo passwords listed in README.md.
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

1. **Set the founder's password.** Open a command prompt in this folder
   (`C:\xampp\htdocs\evoai\prod`) and run:

   ```bat
   C:\xampp\php\php.exe bin\set-password.php mike@mmmcircle.com
   ```

   Type the password in that window only. Never put it in a chat, email or file. Then sign in at
   http://localhost/evoai/prod/ to check it.
2. **Decide about the sample data.** `evoai_prod` starts from the demo seed. It contains:
   * the sample members Sofia Cruz, David Tan and Elena Santos (locked; they can't sign in)
   * their sample payments, leads, chat messages, backoffice transactions, codes, encashments and
     shop orders

   Delete the sample members in **Management Admin → Users** if you don't want them. Payments,
   leads and chat records stay after a delete.
3. **Member card links.** This copy has its own link secret, so member card links here differ
   from the development copy. To give members the same links as the WeServe app's live site, type
   that site's `LINK_SECRET` into `card.link_secret` in `config/config.php` yourself. Changing it
   later changes every member's link.

## 2. Move to the web host

1. **Check the host provides:**
   * **PHP 8.2 or newer** (the version this was built and tested on), with `pdo_mysql`,
     `mbstring`, `openssl` and `curl`.
   * **MySQL or MariaDB.**
   * **HTTPS.**
   * **Apache that honours `.htaccess` files.** They block `config/`, `includes/`, `views/`,
     `api/`, `bin/`, `storage/`, `vendor/` and the `.env` file. On any other web server, block
     those yourself.
   * **Scheduled jobs** (cron).
   * **Members' own .com domains:** the site must answer for every member's domain, not only
     yours. On a VPS or dedicated server, Apache's default virtual host does this. On shared
     hosting, each member's domain must be added as an alias of the site. See README-API.md,
     section 6.
2. **Export the database.** In phpMyAdmin on this PC, select `evoai_prod`, then
   **Export → Quick → SQL**. Do this after step 1.1, so the founder's password goes with it.
3. **Import it on the host.** Create a database and a database user in the host's control panel,
   then import the file there with phpMyAdmin.

   The export contains members' personal data (birth dates, gender, tax IDs). Delete the file once
   it's imported, and never email it or leave it in the website folder.
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
   * The home page loads, and you can sign in as the founder.
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
   * A member card opens. Export the links with `C:\xampp\php\php.exe bin\member-links.php` once
     `app.base_url` is set.

## Known limits

* **"Forgot password" sends no email.** The screen says recovery instructions were sent, but
  nothing arrives. Until that is added, reset passwords in **Management Admin → Users**.
* **A password change** signs that account out everywhere else.
* **Members' domains open over `http://`** until the server has a certificate for each of them
  (`DOMAIN_URL_SCHEME` in `.env`; README-API.md, section 6).
* **Website images aren't resized,** because this build doesn't use PHP's image library (GD).
  Uploads are limited to 5 MB each.
