# leadcma.org/dinner: Vision Dinner registration

A WordPress plugin, **LEAD Vision Dinner Registration**, that puts the registration page from the design handoff on leadcma.org. Every registration writes to the dinner's Monday.com boards. It replaces FundEasy.

It follows the same pattern as the LEAD Learn forms (`theleadprogram/lead-monday-integration`). The page's JavaScript calls the site's own endpoints (`/wp-json/lead-dinner/v1/…`), and PHP on the server calls Monday.com. The API token stays in `wp-config.php` and never reaches the browser. Writes are refused on any site that isn't production.

| Page | Shortcode | Mockup |
|---|---|---|
| leadcma.org/dinner | `[lead_vision_dinner]` | 1a desktop, 1d mobile, 1b / 1g thank-you |
| leadcma.org/dinner/table | `[lead_vision_dinner_table]` | 1c, a Table Leader's private guest page (`?t=<token>`) |

## Install

1. **Build the zip.** Run `wordpress/build.sh`. It copies the newest `dinners/*.json` into the plugin as `dinner.json`, which holds the dinner date and the board and column IDs, and writes `wordpress/dist/lead-vision-dinner.zip`. To build for a specific dinner, pass its name: `wordpress/build.sh 2027-03`.
2. **Add the token** to leadcma.org's `wp-config.php`, above "That's all, stop editing!":
   ```php
   define( 'LEAD_MONDAY_TOKEN', 'the-actual-token' );
   ```
   On the staging copy, also add `define( 'WP_ENVIRONMENT_TYPE', 'staging' );`. That blocks writes so test registrations can't reach the live boards.
3. **Upload the plugin.** Go to Plugins → Add New → Upload, choose the zip, and activate it.
4. **Set up the pages.**
   - Put `[lead_vision_dinner]` in the existing **/dinner** page.
   - Create a child page with the slug **table** (so it's /dinner/table) containing `[lead_vision_dinner_table]`.
   - On both pages, hide the page title and pick the theme's full-width or blank template if it has one. The plugin runs full width either way, but the theme's title would otherwise sit above the photo.
5. **Fill in Settings → Vision Dinner:** Table Leader Coordinator, the four "What to expect" photos, the times, and the venue's street address and directions link when you have them. The venue name and city come from the dinner file (The Woodland, Strongsville, OH) until you type your own. The photo band stays hidden until all four photos are set.
6. **Check status** in Tools → Site Health → Info → LEAD Vision Dinner. It shows whether the token and boards were found and whether writes are allowed.
7. **Make the live-call Register links:** Settings → Vision Dinner → **Make Register links for new prospects**. Click it again whenever you add people to the recruiting board.
8. **Connect Gmail to the email automations** (once, see "Emails").

## Monday.com boards

All five boards (Guests, Table Leaders, Recruiting, Countdown, Logbook) are in the CRM workspace **Fundraising › Vision Dinner – March 2027**. Their IDs are in `dinners/2027-03.json` under `monday.boards`; moving a board doesn't change its ID, so the website keeps working through moves.

| Board | One item per | Notes |
|---|---|---|
| Vision Dinner Guests – March 2027 | person | Table Leaders, guests and spouses/+1s. A registrant and their +1 share a Party ID. |
| Table Leaders – March 2027 | Table Leader household | Feeds the Table Leader search on the page. Linked two-way to Guests. |
| Table Leader Recruiting – March 2027 | prospect | The 83 prospects, copied with their Stage. Work here from October 14, 2026. Each item has a private **Register link** for live-call registration. |

Columns added beyond the handoff's list:

- **Guests › First name** and **Table Leaders › First name**: for greetings.
- **Guests › Contact**: the person's All Contacts record (see below).
- **Guests › Guest link**: Table Leaders only.
- **Table Leaders › Email, Mobile, Guest link token, Guest link**: the private link and where it goes.
- **Table Leaders › Contact**: the registrant's All Contacts record.

- **Guests › Confirmation** and **Table Leaders › Link email**: set to Send to trigger the emails (see "Emails").
- **Guests › Dinner date**: the site sets it to the dinner date on every registration; it drives the dinner-week reminders. It's cleared when Status changes to Cancelled, so a released seat gets no reminders.
- **Recruiting › Register link** and **Register token**: the private live-call link. Clear the token to turn a link off; the Settings button makes a new one.

**Seats filled** is a Number column that the site recalculates after every change it makes. **Seats left** is a formula, 10 − Seats filled. The page itself always counts live from the Guests board, so a guest moved by hand in Monday shows correctly on the page right away. The Seats filled number in Monday catches up on the next registration for that table.

How a seat is counted: a seat is anyone connected to the Table Leader whose Status isn't Cancelled. That includes the Table Leader and their spouse. A Table Leader couple starts at 2 of 10. The guest page lists the Table Leader first, labeled "Table Leader", with no Edit link. Changes to their own record go through the coordinator.

### All Contacts (the master record)

See `docs/vision-dinner-data-model-decisions.md` for the decisions behind this.

**Every person has their own email**: the registrant, a +1, and a guest a Table Leader adds. A +1 can't use the registrant's email, and an email already registered for this dinner by someone with a different first name is refused ("That email belongs to someone else."). Someone without an email is told to call 1-866-LEADCMA.

Each registrant is linked automatically, through the dinner boards' **Contact** column, to their All Contacts record, matched by email:

- **No contact with that email:** one is created in All LEAD Contacts.
- **Contact exists:** the registration is trusted as typed. Name, mobile, church and address (one line, in **Location**) are written to the contact. Blank fields never erase data. The email is never changed by a registration.
- **Contact exists under a different first name:** they're linked to it, but its name and details are left alone, and a note on their Guests item asks you to check.
- **Fundraising Campaigns** gets **2027 March Vision Dinner** (`campaign_id` in the dinner file), added to any campaigns they already have. It is the audience for the dinner campaign in monday campaigns. **Contact Type is never written.**
- **Released seat:** when a Table Leader releases a guest from their private link, the site takes **2027 March Vision Dinner** off that guest's contact and leaves their other campaigns. It stays if the same email still has a seat that isn't Cancelled. Registering again adds it back. A seat set to Cancelled by hand in Monday keeps the label; remove it by hand.
- **Marketing Status**: blank becomes **Marketing contact**. Unsubscribed and Not marketing contact are left as they are, and Clint gets a Monday notification naming the person.
- **Mirrors:** Mobile, Church and Location on Guests, Table Leaders and Recruiting are mirrors of the contact, so editing All Contacts updates the dinner boards. Email and First name stay as plain columns on Guests and Table Leaders, because the email automations send from them. The old typed columns are renamed "Old – …" and can be deleted.
- **Table Leaders can edit a guest's whole record** from their private page. The page lists what's changing and asks them to confirm; the changes go to the guest's contact, the email included, unless the new email belongs to someone else.
- **If anything fails,** the registration still goes through, and a note on the Guests item says what Monday reported.

### Rules the site follows

- **One record per person.** If the email is already on the Guests board under the same first name, that item is updated instead of a new one being created.
- **Guest picks a Table Leader:** connected to that Table Leader, Seating = Assigned. **"Please seat me":** no Table Leader, Seating = Needs a seat.
- **Table Leader registers:** creates a Table Leaders item (Status Registered, a new 160-bit token) and a Guests item (Role = Table Leader) connected to it. Their spouse becomes a Spouse/+1 item at the same table.
- **Returning Table Leader** (the email is already on the Table Leaders board): **nothing is changed**, because anyone can type an email. The site emails the private link to the address on file (the same as "Lost your link?") and adds a note on their item. The thank-you screen says they're already registered and that details are updated through the Table Leader Coordinator.
- **Private link:** the guest page accepts only a 40-character token that matches a Table Leader whose Status isn't Stepped back. To revoke a link, clear the Guest link token (or set Stepped back). "Lost your link?" then issues a new one.
- **Guests added from the private link:** Registered by = Table Leader link. **Edit** re-sends the confirmation. **Release** sets Status = Cancelled and posts an update on the item. The guest keeps the dinner in Fundraising Campaigns (the dinner pool); the monday proxy turns Status into Dinner: Registered and Dinner: Attendance on their contact (1.6.2).
- **Spam:** a hidden honeypot field, plus per-IP limits (12 registrations an hour, 5 link requests an hour, 60 table changes an hour).

## Emails

The site sends no email itself. It sets columns that Monday automations on the new boards turn into email, sent from **clint@leadcma.org** through Gmail:

| Board | When | Email |
|---|---|---|
| Guests | Confirmation changes to Send, Role isn't Table Leader | Guest confirmation, then Confirmation → Sent |
| Guests | Confirmation changes to Send, Role is Table Leader | Table Leader confirmation with their private guest link, then → Sent |
| Table Leaders | Link email changes to Send | "Your link to register guests" (Lost your link? and returning Table Leaders), then → Sent |
| Guests | Dinner date: 5 days before at 1 pm, 3 days before at 7 pm, 1 day before at 11 am, the day of at 8 am and 4:20 pm (2 hours before doors) | Dinner-week reminders. Guests and Table Leaders get different versions; Table Leaders are asked to arrive at 5:30 pm to pray together. Eastern time. |
| Guests | Status changes to Cancelled | Monday notification to Clint (seat released); Dinner date cleared |

The site clears each trigger column before setting Send, so the same trigger twice in a row still fires. A +1 who shares the registrant's email gets no second confirmation.

**One-time step: connect Gmail.** Monday only lets the account owner attach a Gmail login to an automation, so 13 email automations (the 3 triggered ones and the 10 reminders) need it once: open each board's Automate menu, open each "Send email" automation, choose the clint@leadcma.org Gmail account, and save. Until then they fail with "gmailCredentials is required".

The venue line reads "The Woodland, Strongsville, OH" in every email. Edit the wording in each automation once the street address is set.

Since version 1.4.0 the site writes neither **VD Email** nor **VD Guest Link** on All Contacts. Both can be removed after the three recruiting waves (see the decisions doc). The three draft sequences on All Contacts (Vision Dinner 2027 — Guest Confirmation, Table Leader Confirmation and Your Guest Link) are paused and no longer used.

## Live-call registration

When a prospect says yes on a call, open their item on **Table Leader Recruiting – March 2027** (All LEAD Contacts) and click **Register link**. leadcma.org/dinner opens as a Table Leader registration, filled in from their All Contacts record (or the recruiting item if it has no contact). Confirm each detail with them, add their address and spouse, and register.

- The registration updates **that same contact**, found by the link, not by email, so a new email can't create a duplicate. The contact's email never changes; when the email given on the call differs, a note on their Guests item says so.
- Their **Stage** changes to **Yes**, with a note on the recruiting item. **Registered by** is **Staff**.
- If they're already a Table Leader, nothing changes and their private link is emailed to them, as on the public form.
- The thank-you screen shows their private guest link, so you can read it to them.
- Links are private: anyone with one can open that person's details. Clear the Register token to turn a link off.

## Next dinner

1. Create the new dinner file and its boards (see the main README). Record `guests` and `table_leaders` under `monday.boards`, with the same column keys as `dinners/2027-03.json`.
2. Run `wordpress/build.sh YYYY-MM` and upload the new zip over the old one.
3. Update the Settings → Vision Dinner values.

## Files

| File | What it does |
|---|---|
| `lead-vision-dinner.php` | Plugin header and loader |
| `inc/config.php` | Token, `dinner.json`, staging write guard |
| `inc/class-monday.php` | Monday.com GraphQL client (`wp_remote_post`, so the lead-monday-integration staging killswitch also catches it) |
| `inc/class-registry.php` | What each form does to the boards |
| `inc/rest.php` | `/wp-json/lead-dinner/v1/…` endpoints, rate limits |
| `inc/shortcodes.php` | Page markup |
| `inc/settings.php` | Settings → Vision Dinner |
| `inc/site-health.php` | Site Health status panel |
| `assets/` | CSS, JS, Crossten and Open Sans fonts, grayscale hero, logos |

**Crossten web license:** the fonts are bundled from the handoff. The handoff asks LEAD to confirm the Crossten web license before launch.
