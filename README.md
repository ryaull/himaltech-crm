# Himal Tech CRM — Setup Guide

A lightweight internal CRM: leads → clients → quotes, with a client login
portal. Plain PHP + MySQL, built to run on your existing shared cPanel
hosting — no extra software needed.

## What's included
- **Leads** — pipeline with stages (New → Contacted → Qualified → Quoted → Won/Lost), multi-person assignment, "My tasks" filter
- **Clients** — company records, contacts, notes, activity log (collapsible), file attachments with delete
- **Quotes** — line-item quotes with tax calc, status tracking, printable view, PDF download, and editing while still in Draft
- **Client self-service** — clients can accept or decline a quote themselves from their portal, right from the quote list
- **Tickets** — clients raise support requests with file attachments; staff reply, assign (to multiple people), prioritize with color-coded badges, and track status; both sides get emailed on updates
- **Client portal** — clients log in and see only their own quotes and tickets
- **Staff management** — admins add staff from the UI; new accounts get an email invite to set their own password (nothing typed or shared in plain text)
- **Password reset** — "Forgot password" on the login page, plus a "Change password" option for any logged-in user
- **Assignment notifications** — staff get emailed when a lead or ticket is assigned to them
- **Search & filters** — on the Clients, Leads, and Tickets lists
- **Dashboard** — pipeline value, pending quote value, quotes accepted this month, open tickets
- **Roles** — admin, staff, client, all enforced server-side

## Step 1 — Create the database
In cPanel:
1. Go to **MySQL Databases**.
2. Create a database, e.g. `himaltec_crm` (cPanel will prefix it with your account username).
3. Create a database user with a strong password.
4. Add that user to the database with **All Privileges**.
5. Go to **phpMyAdmin**, select the new database, click **Import**, and upload `schema.sql` from this package.

## Step 2 — Upload the files
1. Decide where this should live, e.g. `himaltech.co.uk/crm`. In File Manager
   or via FTP, upload everything in this package into `public_html/crm`.
2. Open `config/db.php` and fill in:
   - `DB_NAME`, `DB_USER`, `DB_PASS` — from Step 1
   - `APP_URL` — the full URL where you uploaded it, e.g. `https://himaltech.co.uk/crm`

## Step 3 — Create your admin account
1. Visit `https://himaltech.co.uk/crm/setup.php` in your browser.
2. Fill in your name, email, and a password — this becomes your admin login.
3. **Delete `setup.php` from the server immediately after.** It only allows
   itself to run once (it refuses if an admin already exists), but there's
   no reason to leave it there.

## Step 4 — Log in
Go to `https://himaltech.co.uk/crm/login.php` and log in with the account
you just created.

## Step 5 — PDF quote downloads (optional but recommended)
"Print / PDF view" works out of the box (it uses the browser's own Print →
Save as PDF). The extra "Download PDF" button generates a proper PDF file
server-side and needs one extra step:

1. Check if your host gives you SSH or a Composer button (cPanel → Setup
   Node.js/PHP App, or Terminal, or Softaculous — varies by host).
2. In the `crm` folder on the server, run:
   ```
   composer require dompdf/dompdf
   ```
3. That's it — the Download PDF buttons will start working immediately.

If your host doesn't offer Composer access, the Print/PDF view button still
works fine for day-to-day use — this step just adds a second, cleaner option.

## Step 6 — Email notifications
Emails (quote sent, ticket replies) are sent using PHP's built-in `mail()`
function, which works out of the box on most cPanel hosting without any
setup. Two things worth doing:
1. Open `includes/mailer.php` and check `MAIL_FROM_ADDRESS` — set it to a
   real mailbox on your domain (e.g. `noreply@himaltech.co.uk`) so replies
   don't bounce and spam filters trust it more.
2. If emails aren't arriving, check cPanel → Email → Track Delivery, and
   consider setting up SPF/DKIM for your domain (cPanel → Email Deliverability)
   — this is the single biggest fix for emails landing in spam.

## Step 7 — File uploads
No setup needed — the `uploads/` folder is created automatically the first
time someone uploads a file, and is locked down (`.htaccess` blocks direct
access and script execution) so uploaded files can only ever be downloaded
through the app's own access-controlled download link, never guessed by URL.

## Day-to-day use
- **Add a client** (Clients → Add client) for any company you're working with.
- **Add a lead** against that client to track a pipeline opportunity —
  assign it to one or more staff (they'll get an email), and use the
  **My tasks** filter on the Leads list to see just your own.
- **Create a quote** from a client or a lead — add line items, tax rate is
  editable per quote (defaults to 20%). Quotes get status: Draft → Sent →
  Accepted/Declined/Expired. A quote can still be **edited** while it's a
  Draft; once Sent, it's locked so the record of what the client actually
  saw stays honest — create a new quote instead if pricing changes.
  Marking a quote **Accepted** automatically moves its linked lead to **Won**
  — and the client can do this themselves from their own portal too.
- **Give a client portal access** from the client's page — just enter their
  email. They get an emailed link to set their own password; nothing is
  typed or shared in plain text on your end. They'll only ever see their
  own quotes and tickets.
- **Attach files** to a client record (contracts, brand assets, reports)
  from the client's page — max 8MB, common file types only, deletable
  anytime.
- **Tickets**: clients can raise a request from their portal, optionally
  with a file attached. Staff see it under Tickets, can reply (also with a
  file), assign to one or more people (they'll get an email), set priority
  (color-coded), and change status. A reply to a client auto-emails them;
  a client reply reopens a closed ticket automatically.
- Every client and lead has an **activity log** — use it to note calls,
  emails, and decisions so nothing lives only in someone's head. Older
  entries collapse behind a "show all" so a long history doesn't take over
  the page.

## Adding staff accounts
Admins → **Staff** in the sidebar → **Add staff member**. Fill in name,
email, and role (staff or admin) — no password to type, no manual SQL. The
new person gets an email with a link to set their own password. Existing
accounts can be disabled from the same screen (an account is never deleted
outright, just switched to `disabled` so its history stays intact).

Anyone can reset a forgotten password from the login page ("Forgot
password?"), or change their own password anytime from the link at the
bottom of the sidebar.

## What's next
This is the CRM module, built as a real foundation — not a mockup — so the
next module (a social posting tool) can plug into the same `clients` table
rather than duplicating client data across separate systems. When you're
ready for that, we pick it up from here.

## Security notes
- All passwords are hashed with PHP's `password_hash()` (bcrypt) — never
  stored in plain text.
- All database queries use prepared statements.
- All forms are CSRF-protected.
- Client portal users are scoped server-side to their own `client_id` on
  every query — they cannot see other clients' data by editing a URL.
- Uploaded files are renamed to a random string on disk, only ever served
  through `attachments/download.php` (which checks the requester's role and
  ownership first), and the `uploads/` folder blocks direct access and
  script execution outright — so an uploaded file can never be reached by
  guessing a URL, and can never be executed as code even if someone
  uploaded something malicious.
- Quote line items are validated server-side: quantities must be greater
  than zero and prices cannot be negative, so a typo can't produce a quote
  with a negative total.
- Quote and ticket reference numbers are generated collision-safely: the
  next number is derived from the highest existing one for the year and the
  insert retries on the rare chance two are created at the same instant, so
  numbers are never duplicated or reused (even after a deletion).
- The login form has a simple per-session throttle: after 5 failed attempts
  it pauses further tries for 15 minutes, slowing password-guessing. For a
  public-facing site it's also worth enabling any brute-force protection
  your host offers (e.g. cPanel's cPHulk) as a second layer.
- Password invite/reset links use a random 32-byte token with a short
  expiry (48h for invites, 2h for resets), are single-use, and any older
  unused token for the same person is invalidated the moment a new one is
  issued. The "forgot password" form always shows the same message
  whether or not the email exists, so it can't be used to check who has
  an account.
- Every logged-in page sends `Cache-Control: no-store` — since these pages
  show data scoped to one specific person, this stops a browser (or the
  back button) from ever displaying a stale or cached snapshot of another
  session's data.
- A client can only accept/decline a quote that (a) actually belongs to
  their own company and (b) is still in "Sent" status — both checked
  server-side on every request, not just hidden in the interface.
- Use HTTPS (Let's Encrypt is free in cPanel) — enforce it site-wide if you
  haven't already, since login credentials travel over this connection.
