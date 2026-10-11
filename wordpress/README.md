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
5. **Fill in Settings → Vision Dinner:** venue, Table Leader Coordinator, the four "What to expect" photos, and the times. Anything left blank shows plain wording ("Venue to be announced", "Call 1-866-LEADCMA."). The photo band stays hidden until all four photos are set.
6. **Check status** in Tools → Site Health → Info → LEAD Vision Dinner. It shows whether the token and boards were found and whether writes are allowed.
7. **Create the Monday automations** below.

## Monday.com boards

Both boards are in **Fundraising › Vision Dinner – March 2027**, next to the countdown board. Their IDs are recorded in `dinners/2027-03.json` under `monday.boards.guests` and `monday.boards.table_leaders`.

| Board | One item per | Notes |
|---|---|---|
| Vision Dinner Guests – March 2027 | person | Table Leaders, guests and spouses/+1s. A registrant and their +1 share a Party ID. |
| Table Leaders – March 2027 | Table Leader household | Feeds the Table Leader search on the page. Linked two-way to Guests. |

Columns added beyond the handoff's list:

- **Guests › First name** and **Table Leaders › First name**: for greetings.
- **Guests › Contact**: the person's All Contacts record (see below).
- **Guests › Guest link**: Table Leaders only.
- **Table Leaders › Email, Mobile, Guest link token, Guest link**: the private link and where it goes.
- **All Contacts › VD Email** and **VD Guest Link**: what triggers the emails (see "Emails").

The **Guests › Confirmation** and **Table Leaders › Link email** status columns are no longer used (version 1.2.0 sends email through All Contacts). They can be deleted.

**Seats filled** is a Number column that the site recalculates after every change it makes. **Seats left** is a formula, 10 − Seats filled. The page itself always counts live from the Guests board, so a guest moved by hand in Monday shows correctly on the page right away. The Seats filled number in Monday catches up on the next registration for that table.

How a seat is counted: a seat is anyone connected to the Table Leader whose Status isn't Cancelled. That includes the Table Leader and their spouse. A Table Leader couple starts at 2 of 10. The guest page lists the Table Leader first, labeled "Table Leader", with no Edit link. Changes to their own record go through the coordinator.

### All Contacts (the master record)

Every registrant with an email is matched to **All Contacts** by email and linked through the Guests board's **Contact** column (All Contacts shows the link back as "link to Vision Dinner Guests – March 2027").

- **Existing contact:** "Vision Dinner Guest" is **added** to their Contact Type alongside what's already there. Only blank First Name, Last Name, Mobile Phone Number and Church Name fields are filled; nothing on file is overwritten.
- **New contact:** created in "Incoming Form Group (do not delete)" with name, email, mobile, church and Contact Type = Vision Dinner Guest.
- **Student Status and Marketing Status are never set**, since Student Status drives the student automations.
- **People without an email** (a +1, or a guest a Table Leader added by phone only) aren't linked, to avoid duplicate contacts. The coordinator can link them by hand.
- **After the gift import**, "Vision Donor" is added to the contacts who gave. That's part of the import, not the website.
- If linking fails, the registration still goes through, and a note on the Guests item asks for the link to be made by hand.

The label comes from `contact_type_label` under `monday.boards.all_contacts` in the dinner file.

### Rules the site follows

- **One record per person.** If the email is already on the Guests board, that item is updated instead of a new one being created. A spouse/+1 without an email is matched by Party ID.
- **Guest picks a Table Leader:** connected to that Table Leader, Seating = Assigned. **"Please seat me":** no Table Leader, Seating = Needs a seat.
- **Table Leader registers:** creates a Table Leaders item (Status Registered, a new 160-bit token) and a Guests item (Role = Table Leader) connected to it. Their spouse becomes a Spouse/+1 item at the same table.
- **Returning Table Leader** (the email is already on the Table Leaders board): **nothing is changed**, because anyone can type an email. The site emails the private link to the address on file (the same as "Lost your link?") and adds a note on their item. The thank-you screen says they're already registered and that details are updated through the Table Leader Coordinator.
- **Private link:** the guest page accepts only a 40-character token that matches a Table Leader whose Status isn't Stepped back. To revoke a link, clear the Guest link token (or set Stepped back). "Lost your link?" then issues a new one.
- **Guests added from the private link:** Registered by = Table Leader link. **Edit** re-sends the confirmation. **Release** sets Status = Cancelled and posts an update on the item.
- **Spam:** a hidden honeypot field, plus per-IP limits (12 registrations an hour, 5 link requests an hour, 60 table changes an hour).

## Emails

The site sends no email itself. Emails are **Monday Sequences on All Contacts**, sent from clint@leadcma.org like LEAD's student sequences (Tools → Sequences in Monday). Edit the wording there.

| Sequence | Sent when | VD Email label |
|---|---|---|
| Vision Dinner 2027 — Guest Confirmation | a guest or +1 with an email registers, or a Table Leader edits them | Guest confirmation |
| Vision Dinner 2027 — Table Leader Confirmation | a new Table Leader registers (includes {VD Guest Link} and the 5:30 pm arrival) | TL confirmation |
| Vision Dinner 2027 — Your Guest Link | "Lost your link?", or a Table Leader registers again | TL link |

How it works: the site links the registrant to their All Contacts record, copies a Table Leader's private link to **VD Guest Link**, then sets **VD Email** to the label above. A Monday automation on All Contacts enrolls the contact in the matching sequence and sets VD Email to **Sent**. The site clears VD Email before setting it, so the same label twice in a row still fires.

### To switch the emails on

1. In each sequence, replace **[Venue name and address]** and check the wording.
2. Activate the three sequences (they were created paused).
3. On **All Contacts**, add three automations (Automate → "When status changes to something, enroll item in sequence"):
   - When **VD Email** changes to **Guest confirmation** → enroll in *Vision Dinner 2027 — Guest Confirmation* → set VD Email to **Sent**.
   - When **VD Email** changes to **TL confirmation** → enroll in *Vision Dinner 2027 — Table Leader Confirmation* → set VD Email to **Sent**.
   - When **VD Email** changes to **TL link** → enroll in *Vision Dinner 2027 — Your Guest Link* → set VD Email to **Sent**.

Until those automations exist, registrations still work; VD Email just sits on its label and no email goes out.

### Still to set up

- **Seat released:** on the Guests board, when **Status** changes to **Cancelled**, notify the Table Leader Coordinator (once they're named).
- **Dinner-week reminders** (Mon 1 pm, Wed 7 pm, day before 11 am, morning of 8 am, 2 hours before doors): the countdown task "Set up dinner email sequences in Monday", once the venue is set.
- People without an email (a +1 or a phone-only guest) get no email; the coordinator follows up.
- A sequence may not re-send to a contact who already completed it. If an edited guest doesn't get an updated confirmation, that's why. The edit is still saved on the board.

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
