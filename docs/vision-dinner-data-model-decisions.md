# Vision Dinner Data Model Decisions

LEAD Africa Vision Dinner, Saturday, March 6, 2027. Version 2, October 12, 2026. Replaces version 1 of the same date. Nothing described here has been built or moved yet.

Team-facing copy: the Google Doc "Vision Dinner Data Model Decisions – v2 – 2026-10-12" in Drive, 05 Guests & Data.

## Summary

The dinner boards in Monday will follow the LEAD Africa pattern. All Contacts holds who a person is, and each event board links to the contact and holds only data about that event. Every dinner registrant ends up with an All Contacts record, either new or updated. Confirmation and guest-link emails run as Monday automations on the dinner boards. Table Leader recruiting starts from a year-neutral status on All Contacts and is worked on the recruiting board. Registration data is trusted as typed, with no team verification before automations run. No board or column moves until the three Table Leader recruiting waves are finished and the new setup is working well.

## The principle

All Contacts holds data that should always be tied to a contact record. Boards for an event or a season in a person's life link to the contact and add data without changing the contact. The LEAD Africa board is the model: it links to All Contacts, shows name, email, phone and location as mirrored columns, and owns only season-specific data such as status, training dates and payments.

## Where things stand on October 12, 2026

| Board | ID | Notes |
|---|---|---|
| All Contacts | 18293116953 | Master record, 3,362 contacts. All 22 sequences run here, including four Vision Dinner sequences. |
| LEAD - Africa | 18390795691 | The model board. Sits in the All LEAD Contacts workspace. |
| Vision Dinner Guests – March 2027 | 18434896459 | Already links to All Contacts through a Contact column that the website sets by email. Still holds its own copies of email, mobile, mailing address and church. 2 test items. |
| Table Leaders – March 2027 | 18434896452 | No link to All Contacts yet. Holds its own email, mobile, church and first name. Feeds the public Table Leader search. 1 item. |
| Table Leader Recruiting – March 2027 | 18435121354 | 83 prospects. Links to All Contacts and mirrors Wave and email. The working column is currently called Stage. |
| Vision Dinner Countdown – March 2027 | 18434832539 | The task list. It is working well and is not changing. |

The Guests, Table Leaders, Recruiting and Countdown boards are in the Fundraising workspace (17946146), folder 21599326.

## Decisions

1. **Follow the LEAD Africa pattern.** The Guests and Table Leaders boards will eventually move together to the All LEAD Contacts workspace (13076717), as their own boards next to All Contacts, the way LEAD - Africa sits. Nothing moves until the new setup is working and Clint is happy with how it works.
2. **A Table Leader household links to the registrant only.** The Table Leaders board gets a Contact link to the person who registered, matched by email. The spouse is handled through the Guests board with Role set to Spouse/+1 and the same Party ID, connected to that Table Leader. A spouse with their own email links to their own contact. The household name, such as David & Ruth Mwangi, stays as the item name.
3. **Every registrant gets an All Contacts record.** When someone registers as a Table Leader, a +1 or a guest, the website finds their contact by email and updates it, or creates a new one if none exists.
4. **Confirmation and guest-link emails are Monday automations on the dinner boards.** The website sets the Confirmation column (Guests) or the Link email column (Table Leaders) to Send, and an automation sends the email and sets the column to Sent. Email and First name stay on the dinner boards as copies, matched to the contact by email at registration, because the automations send from the board's own Email column. The Table Leader Pre-Call Letter stays a sequence on All Contacts. The inactive sequences Guest Confirmation, Table Leader Confirmation and Your Guest Link will be retired.
5. **Two recruiting columns with different jobs.** VD Table Leader on All Contacts holds the outcome and stays with the person from year to year. Recruiting Phase on the recruiting board (today's Stage column) holds the steps of the conversation and is where Clint works. The two names are different on purpose, so the team can tell which column is for editing.
6. **Setting VD Table Leader to Potential starts recruiting.** To make someone a potential Table Leader for a dinner, Clint sets VD Table Leader to Potential on their All Contacts record, and they appear on that dinner's recruiting board linked to the contact.
7. **Live-call registration starts from the recruiting board.** When a potential Table Leader says yes on a call, Clint registers them through leadcma.org/dinner from a prefilled link on their recruiting-board item. The form adds the household to the Table Leaders board, updates the contact, and connects the +1. Starting from the record in the database means the form does not have to look the person up by email, which avoids duplicates when the email differs from the one on file. The prefilled link carries a long random token, like the private Table Leader guest links, rather than a guessable ID, so no one can view other people's details by changing the link.
8. **"No" means only that the person does not want to be a Table Leader.** It says nothing about whether they will attend the dinner or give toward the match. No automation or filter may treat No as a reason to leave someone out of dinner invitations or emails.
9. **Registration data is trusted as typed.** No one on the team verifies a registration before automations run, and Clint accepts some typos to save human time. This holds for self-registration, for a Table Leader adding a guest, and for Clint registering someone live. There is no second email field.

## Rules for writing to contacts

- The form always writes personal details (name, phone, address, church) to the contact.
- The form never changes the email on an existing contact. The email is the match key.
- The form adds the Contact Type March 2027 Vision Dinner, keeps every existing Contact Type value, and does not touch other status columns such as Student Status. Each dinner has two labels: [Month Year] Vision Dinner for everyone who registers and [Month Year] Vision Donor for those who give. When a person gives, the gift step adds that dinner's Vision Donor label and the general Donor label, by hand or by import as was done for April 2026, and the dinner label stays. The general Donor label lets the database answer who has ever given to LEAD. The old Vision Dinner Guest label is retired.
- The form checks email format and offers a "Did you mean gmail.com?" correction for common domain typos.
- A bad overwrite is recovered from Monday's activity log on the item.

## Recruiting columns

The VD Table Leader labels are Potential, Asked, Booked a Call, Yes, Not Now and No. Not Now means ask again next year. No means do not ask this person to lead a table again. The old label Declined on the current Vision Dinner 2027 column is retired, because it can be read as declining the dinner. The proposed mapping from today's Stage labels:

| VD Table Leader | Recruiting Phase (today's Stage) |
|---|---|
| Potential | Not sent yet |
| Asked | Letter sent, Said they'll book |
| Booked a Call | Meeting booked |
| Yes | Yes |
| Not Now | Not this year |
| No | No matching label yet |

Met and No reply have no VD Table Leader equivalent and stay on the recruiting board only.

## What must not change yet

- The Pre-Call Letter sequence is active, with 33 of 66 contacts enrolled on October 12. It runs off the Vision Dinner 2027, VD Wave and VD Pool columns on All Contacts. Leave those columns and their labels as they are until all three recruiting waves are finished.
- Do not rename or relabel Stage, Vision Dinner 2027 or any other recruiting column before then.
- Do not move the dinner boards out of Fundraising yet.
- Keep VD Email and VD Guest Link on All Contacts until the website plugin no longer writes to them.

## Build order

1. **In Monday, now.** Add the Contact link to the Table Leaders board. Build the two email automations and test each on a dummy item. Test whether an automation can create an item on the recruiting board, linked to the contact and without duplicates, when VD Table Leader is set to Potential. Test whether an automation can update VD Table Leader when Recruiting Phase changes.
2. **In Claude Code.** Update the website plugin so that it sets the Contact link on Table Leaders, triggers the Send columns, stops setting VD Email, and sets Recruiting Phase to Yes after a live registration. It also needs to create a contact for every registrant who is not yet in All Contacts, follow the rules for writing to contacts above, and open from a prefilled link on a recruiting-board item. The prefilled link carries the token described in decision 7.
3. **After the three waves.** Rename Stage to Recruiting Phase. Relabel Vision Dinner 2027 as VD Table Leader with the new labels. Remove VD Email and VD Guest Link from All Contacts and retire the three inactive sequences. Decide which copied fields leave the dinner boards. Move the boards to All LEAD Contacts. Update the repo README and `dinners/2027-03.json` to match.

## Open items

- Confirm that Recruiting Phase is the column Clint works in after Potential, with VD Table Leader following it automatically. Monday may or may not support that update through an automation; it needs a test.
- Each dinner has its own recruiting board, so the Potential automation must point at the right board every year. Decide who repoints it.
- Confirm the wording of the No label, for example "No – not a Table Leader", so no one misreads it.
- Confirm that a Monday email automation can send to the Email column on a dinner board.
- Check how long Monday keeps item history on LEAD's plan, since overwrites are recovered from it.
- Check what the website plugin writes today. That code is in the repo, not in Monday.
- Decide which of mobile, mailing address and church stay on the dinner boards as copies and which become mirrors of the contact.
- Before moving boards to the All LEAD Contacts workspace, check who can then see guest dietary needs, accessibility needs and addresses, and whether the Table Leader Coordinator keeps the access she needs.
- Decide whether the "Vision Dinner" board name prefix still applies after the move.
- The March 2027 Vision Donor label has been added to Contact Type. April 2026 Vision Donor (22 contacts, applied by hand to people who gave) stays as it is. April 2026 Vision Dinner has been applied to the 22 April 2026 Vision Donors. Decide whether to backfill it for the April guests who did not give. Vision Dinner Guest has been retired. Run a test registration, because the website plugin may still try to write that label.

## Addendum, October 14, 2026 (decided with Clint while building)

- **Every registrant must give an email**: the registrant, a +1, and a guest a Table Leader adds. Without an email there is nothing to match a contact on.
- **A shared email is one household (option B).** A +1 who uses the registrant's email gets their own Guests item, tied by Party ID, and is recorded as Spouse Name on the email owner's contact instead of getting their own contact. One confirmation goes to the shared inbox. The same applies whenever an email already belongs to a contact with a different first name: the person is added, not merged, and noted on that contact. This is the one exception to decision 3.
- Website plugin 1.4.0 no longer writes VD Email or VD Guest Link, so both can be removed from All Contacts once the three waves are finished.
