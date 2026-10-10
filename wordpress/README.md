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

Columns added beyond the handoff's list, all needed for Monday to send the emails:

- **Guests › Confirmation** (Send / Sent): the site sets Send on every registration and every edit.
- **Guests › Guest link**: Table Leaders only, so their confirmation email can include the link.
- **Table Leaders › Email, Mobile**: where the private link goes.
- **Table Leaders › Guest link**: the full private URL.
- **Table Leaders › Link email** (Send / Sent): the site sets Send when someone uses "Lost your link?".

**Seats filled** is a Number column that the site recalculates after every change it makes. **Seats left** is a formula, 10 − Seats filled. The page itself always counts live from the Guests board, so a guest moved by hand in Monday shows correctly on the page right away. The Seats filled number in Monday catches up on the next registration for that table.

How a seat is counted: a seat is anyone connected to the Table Leader whose Status isn't Cancelled. That includes the Table Leader and their spouse. A Table Leader couple starts at 2 of 10. The guest page lists the Table Leader first, labeled "Table Leader", with no Edit link. Changes to their own record go through the coordinator.

### Rules the site follows

- **One record per person.** If the email is already on the Guests board, that item is updated instead of a new one being created. A spouse/+1 without an email is matched by Party ID.
- **Guest picks a Table Leader:** connected to that Table Leader, Seating = Assigned. **"Please seat me":** no Table Leader, Seating = Needs a seat.
- **Table Leader registers:** creates a Table Leaders item (Status Registered, a new 160-bit token) and a Guests item (Role = Table Leader) connected to it. Their spouse becomes a Spouse/+1 item at the same table.
- **Returning Table Leader** (the email is already on the Table Leaders board): their details are updated, and the private link is **not** shown on screen. It only goes to the email on file. Otherwise, anyone who typed a Table Leader's email could open their table.
- **Private link:** the guest page accepts only a 40-character token that matches a Table Leader whose Status isn't Stepped back. To revoke a link, clear the Guest link token (or set Stepped back). "Lost your link?" then issues a new one.
- **Guests added from the private link:** Registered by = Table Leader link. **Edit** re-sends the confirmation. **Release** sets Status = Cancelled and posts an update on the item.
- **Spam:** a hidden honeypot field, plus per-IP limits (12 registrations an hour, 5 link requests an hour, 60 table changes an hour).

## Monday automations to create

The site sends no email itself. Per your decision, Monday does all of it. Create these on the boards, using the Gmail or Outlook integration so the emails come from a LEAD address. In each recipe, "Email" is the Email column on that board.

**Guests board**

1. **Guest confirmation:** When **Confirmation** changes to **Send**, and only if **Role** is *Guest* or *Spouse/+1*, send an email to **Email**, then set **Confirmation** to **Sent**.
2. **Table Leader confirmation:** When **Confirmation** changes to **Send**, and only if **Role** is *Table Leader*, send an email to **Email** that includes **{Guest link}**, then set **Confirmation** to **Sent**.
3. **Seat released:** When **Status** changes to **Cancelled**, notify the Table Leader Coordinator.
4. **Dinner-week reminders** (Mon 1 pm, Wed 7 pm, day before 11 am, morning of 8 am, 2 hours before doors): these belong to the countdown task "Set up dinner email sequences in Monday".

**Table Leaders board**

5. **Lost link:** When **Link email** changes to **Send**, send an email to **Email** that includes **{Guest link}**, then set **Link email** to **Sent**.

The site clears these columns before setting Send, so an automation that never reset them still fires next time.

### Email copy (from mockup 1e)

Subject: **You're registered for the LEAD Africa Vision Dinner**

> Hi {first name},
>
> **Guest:** Thank you for registering for the LEAD Africa Vision Dinner. We've saved you a seat.
> **Table Leader:** Thank you for leading a table at the LEAD Africa Vision Dinner. We're grateful for you, and we can't wait to see your table full.
>
> **When:** Saturday, March 6, 2027. Reception 5:45 pm · Done by 8:15 pm
> **Where:** [Venue name and address]
> **Attire:** Business attire strongly suggested
>
> *(Table Leaders only)* **Register your guests.** This link is just for you. Use it anytime to add guests and see who's at your table: {Guest link}
>
> Your Table Leader Coordinator, [name], will call you in the next few days. *(Table Leaders)*
>
> Grateful for you,
> Clint Bieri
> Co-founder and Executive Director, LEAD
>
> The LEAD Program · 180 N Clayton St, Centerburg, OH 43011 · 1-866-LEADCMA
> You're receiving this because you registered at leadcma.org/dinner.

Lost-link email: "Here's your private link to register guests for the LEAD Africa Vision Dinner: {Guest link}. It's just for you. Use it anytime to add guests and see who's at your table."

Monday's email builder can't reproduce the full photo-header design from mockup 1e. If you later want that exact design, it would mean sending from WordPress instead.

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
