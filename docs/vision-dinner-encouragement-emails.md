# Vision Dinner encouragement emails – March 2027

Encouragement and follow-up emails for the LEAD Africa Vision Dinner on **Saturday, March 6, 2027, at The Woodland, Strongsville, OH**. There are two series:

- **Table Leader series:** 13 emails, from 116 days before the dinner to 2 days after.
- **Guest series:** 5 emails, from 88 days before to 18 days before.

These add to the emails the dinner boards already send: the confirmations, the guest-link resend, and the five dinner-week reminders. Neither series sends anything in dinner week, so it doesn't overlap the reminders.

## How people are enrolled

People register at different times, and registration stays open until days before the dinner. So these emails are **not a sequence**. A Monday sequence counts from the day someone is enrolled, which would send a January registrant the November emails, and send the last emails after the dinner.

Instead, each email is a **"When date arrives" automation on the Guests board**, counted back from the **Dinner date** column. The dinner-week reminders already work this way.

- The website sets Dinner date on every registration, so **registering is the enrollment**. Nobody is enrolled by hand.
- Each automation fires once, on its own date, for everyone registered at that moment. Someone who registers late starts with the next email due. Emails whose dates have passed never fire for them.
- The site clears Dinner date when Status changes to Cancelled, so a released seat gets nothing more.
- Every email is written to stand on its own, because for a late registrant any of them can be the first one in this series.

### Automation recipe

> When **Dinner date** arrives, **[offset]**, at **10:00 am** Eastern → and only if **Role** is **[Table Leader / not Table Leader]** → send email to **Email**

- **Board:** Vision Dinner Guests – March 2027. Every person, Table Leaders included, has an item here with their own Email, First name and Dinner date.
- **Table Leader series:** the condition is "Role is Table Leader." This reaches the registrant on each Table Leader household.
- **Guest series:** the condition is "Role isn't Table Leader." This includes guests and every Spouse/+1, including a Table Leader's spouse, who attends as a guest at their table.
- **Sender:** clint@leadcma.org. Attach the Gmail account to each new automation once, as with the existing 13.
- **Merge fields:** `[First name]` is the Guests › First name column. `[Guest link]` is the Guests › Guest link column, which is filled for Table Leaders only.
- **Everything else in [square brackets]** is a fact still to confirm. Fill it in or remove it before turning the automation on.

### Before building all 18

- **Test a long offset.** The existing reminders count back 5 days at most. Make a test item and confirm that Monday accepts 116 days before.
- **Check your plan's automation limit.** Several hundred registrants and up to 18 emails each adds up to thousands of automation actions over the season.
- **Build the early emails first.** Only the first few dates are close. The later automations can be built in the weeks ahead.

## The start-here section for both confirmation emails

The confirmation emails are the one message everyone gets the moment they register. Adding a short start-here section to each one means a late registrant still gets the basics, however much of the series they missed.

### Add to the Table Leader confirmation

> **Where to start**
>
> 1. **Make a list.** Write down 8 to 10 people you would love to sit with for an evening: friends, couples from church, neighbors, colleagues.
> 2. **Pray over it.** Ask God which 4 or 5 couples or friends He wants at your table.
> 3. **Invite in person.** Call or meet, tell them why LEAD matters to you, and ask: "Will you join me at my table on March 6?" There is no cost to attend.
> 4. **Register them that day** with your private link above. Each guest needs their own email address.
>
> Your Table Leader packet will reach you within a day or two. It has save-the-dates, invitations and Don't Forget cards to hand out. A member of our calling team will check in with you regularly until the dinner.

### Add to the guest confirmation

> **What to expect**
>
> Dinner is our gift to you. You'll hear what God is doing through LEAD to equip ministry leaders in East Africa and here at home, through short stories and a few real voices. Near the end, everyone will be invited to partner with LEAD in prayer and giving. There is no obligation.
>
> Business attire is strongly suggested: Men – Suit or Jacket/Tie. Women – Dress or Pants Outfit.
>
> We'll send a few short notes before March 6, and reminders with directions in dinner week.

---

# Table Leader series

| # | Offset | Lands on | Subject |
|---|---|---|---|
| T1 | 116 days before | Tue Nov 10, 2026 | Why your table matters |
| T2 | 102 days before | Tue Nov 24, 2026 | A list worth praying over |
| T3 | 88 days before | Tue Dec 8, 2026 | Save-the-dates: the first step |
| T4 | 81 days before | Tue Dec 15, 2026 | A word for Christmas |
| T5 | 60 days before | Tue Jan 5, 2027 | This is the month to invite |
| T6 | 53 days before | Tue Jan 12, 2027 | Register your guests in two minutes |
| T7 | 46 days before | Tue Jan 19, 2027 | Invitations are in the mail |
| T8 | 39 days before | Tue Jan 26, 2027 | One seat, one story |
| T9 | 32 days before | Tue Feb 2, 2027 | Four weeks out |
| T10 | 25 days before | Tue Feb 9, 2027 | Please put every name in |
| T11 | 18 days before | Tue Feb 16, 2027 | Your table, as it stands |
| T12 | 11 days before | Tue Feb 23, 2027 | Your part on the night |
| T13 | 2 days after | Mon Mar 8, 2027 | Thank you for your table |

The weeks of Dec 26 (Christmas, an off week on the countdown) and Dec 29 are left quiet.

---

## T1 · 116 days before · Tue Nov 10, 2026

**Subject:** Why your table matters
**Preview:** Thank you for saying yes. Here is what your yes makes possible.

Dear [First name],

Thank you for leading a table at the LEAD Africa Vision Dinner on Saturday, March 6, at The Woodland in Strongsville.

We want you to know why your table matters so much. The Vision Dinner has no tickets and no cost to attend. Every guest in the room is there because someone they trust personally invited them. That someone is you. Your table is how new friends first hear what God is doing through leaders in East Africa and here at home.

There is only one thing to do this week: make a list. Write down 8 to 10 people you would love to sit with for an evening. Think of friends, couples from church, neighbors and colleagues. You won't invite all of them. The list just gives you a place to start.

Over the coming weeks we will walk with you step by step. You won't do this alone.

With gratitude,
Clint Bieri
LEAD

---

## T2 · 102 days before · Tue Nov 24, 2026

**Subject:** A list worth praying over
**Preview:** This Thanksgiving week, one small step for your table.

Dear [First name],

This is a week for giving thanks, and we are thankful you are leading a table on March 6.

Here is one small step for this week. Write down the people you would love to have at your table, and pray over that list. Ask God which 4 or 5 couples or friends He wants there. Pray for them by name. Ask Him to prepare their hearts for the evening.

When you invite someone you have prayed for, you are not asking a favor. You are offering them something good.

Happy Thanksgiving,
Clint Bieri
LEAD

---

## T3 · 88 days before · Tue Dec 8, 2026

**Subject:** Save-the-dates: the first step
**Preview:** Get March 6 on their calendars before the holidays fill them.

Dear [First name],

Your Table Leader packet holds everything you need to invite well:

- A letter from us
- An invitation checklist
- A How to Invite & Register sheet
- 10 save-the-date cards
- 10 invitations
- 10 Don't Forget cards

**This month, hand out your save-the-dates.** Give one to each person you hope to have at your table, in person if you can. Say something simple: "I'm leading a table at a dinner on March 6. I'd love for you to be my guest. Will you hold the date?"

You don't need a yes yet. You just want the date on their calendar before the holidays fill it.

If your packet hasn't reached you, reply to this email and we'll get it to you.

Thank you for leading,
Clint Bieri
LEAD

---

## T4 · 81 days before · Tue Dec 15, 2026

**Subject:** A word for Christmas
**Preview:** No tasks this week. Just thanks.

Dear [First name],

There are no tasks for you this week.

As you celebrate the birth of Jesus, we want to say thank you. Leaders in East Africa and here at home are being equipped to serve their churches and communities, and your table on March 6 is part of how that work grows.

"For to us a child is born, to us a son is given" (Isaiah 9:6). Rest well, enjoy the people you love, and keep the people you hope to invite in your prayers.

We'll be in touch in the new year.

Merry Christmas,
Clint Bieri
LEAD

---

## T5 · 60 days before · Tue Jan 5, 2027

**Subject:** This is the month to invite
**Preview:** Two months from Saturday. Here's how to ask.

Dear [First name],

Happy New Year. The dinner is two months from this Saturday, and January is the month to invite.

A personal invitation is the heart of the whole evening. Here is a simple way to ask:

1. **Call or meet in person.** A text or email is easy to set aside; a conversation is not.
2. **Say why it matters to you.** "LEAD equips ministry leaders, and I've seen what it does. I'd love for you to hear about it."
3. **Be clear about the evening.** A dinner, a short program and real stories. There is no cost to attend.
4. **Ask for a yes.** "Will you join me at my table on March 6?"
5. **Register them that day** with your private link:

[Guest link]

If someone says no, thank them warmly and move to the next name. Every yes fills a seat, and every conversation plants a seed.

You can do this,
Clint Bieri
LEAD

---

## T6 · 53 days before · Tue Jan 12, 2027

**Subject:** Register your guests in two minutes
**Preview:** Your private link, and a short training to show you how.

Dear [First name],

When a guest says yes, the best thing you can do is register them right away. Your private guest link is here:

[Guest link]

From that page you can add a guest, see everyone at your table, and edit or release a seat. Every guest needs their own email address so they receive their own confirmation and reminders. Please keep this link to yourself, since it shows your guests' details.

**Table Leader training:** [day, date and time], [place or video link]. In about [length] we will walk through registering guests, what happens on the night, and your questions. [How to RSVP for the training]

This week we are also sending the e-invitation. Feel free to forward it to anyone you've invited, as a follow-up to your conversation.

Grateful for you,
Clint Bieri
LEAD

---

## T7 · 46 days before · Tue Jan 19, 2027

**Subject:** Invitations are in the mail
**Preview:** A good week to follow up with everyone you've asked.

Dear [First name],

Printed invitations are going out in the mail this week, and your Table Leader packet has 10 of your own.

This is a good week to follow up with everyone you have asked or given a save-the-date. Hand them a printed invitation, or give them a call: "Have you had a chance to look at March 6? I'd love to have you at my table."

When they say yes, register them that day with your private link:

[Guest link]

If your table is filling up, wonderful. If it isn't yet, that's common at this point. Tables fill as the asking continues.

Keep going,
Clint Bieri
LEAD

---

## T8 · 39 days before · Tue Jan 26, 2027

**Subject:** One seat, one story
**Preview:** What a single guest at your table can set in motion.

Dear [First name],

We want to share why one seat matters.

[A short, true story of one guest at a past LEAD dinner, or of a leader whose formation began because someone gave. Use a real story with permission, or leave this paragraph out.]

Every guest you invite will hear stories like this on March 6. Some will pray. Some will give. Some will become partners for years. You won't know which guest God is preparing until you ask.

So this week, ask the next person on your list. Then ask one more. Register each yes here:

[Guest link]

Thank you for opening your table,
Clint Bieri
LEAD

---

## T9 · 32 days before · Tue Feb 2, 2027

**Subject:** Four weeks out
**Preview:** Don't Forget cards, and a weekly call from our team.

Dear [First name],

We are four weeks from the dinner.

**Don't Forget cards:** your Table Leader packet has 10. Give one to each guest who has said yes. It holds the date, time and place in one spot so nothing gets lost.

**Weekly calls:** from now until the dinner, a member of our calling team will check in with you each week. Tell them who has said yes, who you are still waiting on, and how we can help.

**Who is at your table so far?** You can always see your list here:

[Guest link]

If a guest isn't showing on your page, they aren't registered yet. Please add them so we can save their seat.

Thank you,
Clint Bieri
LEAD

---

## T10 · 25 days before · Tue Feb 9, 2027

**Subject:** Please put every name in
**Preview:** We confirm the meal count with the venue on [date].

Dear [First name],

This week we confirm the number of meals with The Woodland. We plan every plate from the names our Table Leaders have registered, so **please add every guest who has said yes by [day, date]**.

[Guest link]

If someone told you yes but hasn't been registered, add them now. If someone can no longer come, release their seat on the same page so we can plan well.

If your table is not full, you are not behind. Keep inviting this week. On the night, every table will be full of people glad to be there, and that is what matters.

Thank you for helping us plan well,
Clint Bieri
LEAD

---

## T11 · 18 days before · Tue Feb 16, 2027

**Subject:** Your table, as it stands
**Preview:** One more week to invite. Registration closes [date].

Dear [First name],

The dinner is two weeks from Saturday, and registration closes on [day, date].

Here is your table as it stands:

[Guest link]

This is the last week to invite. If there is one more person you hoped to ask, call them today.

Our calling team will speak with you this week to confirm who is coming. If your table ends up smaller than 10, that's fine. We will seat another small group with you so every table is full and every guest has good company. [Table Leader Coordinator name] will let you know before the dinner.

Thank you for every invitation you have made,
Clint Bieri
LEAD

---

## T12 · 11 days before · Tue Feb 23, 2027

**Subject:** Your part on the night
**Preview:** What to expect on March 6, from arrival to the last envelope.

Dear [First name],

Saturday, March 6, is almost here. Here is your part in the evening.

**Arrive at 5:30 pm.** Table Leaders gather to pray together before guests arrive. Doors open at 6:20 pm.

**Pick up your Table Leader Giving Packet at registration.** It is labeled with your name and table number and holds an attendance sheet, a Day-Of Guidelines sheet, 10 giving envelopes and 10 pens.

**Welcome your guests.** Introduce people to each other, and help anyone seated with you feel at home.

**During the appeal,** our speaker will ask you to hand out the envelopes. Please wait for that moment, then give one to each guest and invite them to fill it in at the table. Collect the envelopes when the speaker asks. You don't need to persuade anyone. Your guests are free to give or not; your part is to make the moment easy and unhurried.

**Mark your attendance sheet,** and hand it in with your envelopes.

We'll send a few reminders with directions in dinner week. Please pray for your guests by name each day.

See you Saturday,
Clint Bieri
LEAD

---

## T13 · 2 days after · Mon Mar 8, 2027

**Subject:** Thank you for your table
**Preview:** What your table made possible on Saturday.

Dear [First name],

Thank you for leading a table on Saturday night.

[One or two sentences on the evening: total given toward the accelerators, number of guests, or a moment from the night. Use the counted figures only.]

None of that happens without Table Leaders. Every guest in the room was there because someone like you invited them personally.

In the next few days, you may hear from your guests. If they ask how to follow up or give, they can call us at 1-866-LEADCMA. If someone at your table would make a great Table Leader next year, please tell us. That is how this dinner multiplies.

With deep thanks,
Clint Bieri
LEAD

---

# Guest series

Guests need less from us than Table Leaders do. Their Table Leader invited them and stays in touch. These emails build anticipation, introduce LEAD, and cover the practical details. Most guests will register in January and February, so most will receive only the last two or three.

| # | Offset | Lands on | Subject |
|---|---|---|---|
| G1 | 88 days before | Tue Dec 8, 2026 | You're on the list for March 6 |
| G2 | 53 days before | Tue Jan 12, 2027 | What the evening holds |
| G3 | 39 days before | Tue Jan 26, 2027 | Why we equip leaders |
| G4 | 25 days before | Tue Feb 9, 2027 | Know someone who should be there? |
| G5 | 18 days before | Tue Feb 16, 2027 | A few details before March 6 |

The dinner-week reminders take over from 5 days before.

There is no guest thank-you email. After the dinner, every guest receives one of the six thank-you letters mailed from the counting sheet (a gift, a commitment, attended with no gift, or registered but didn't attend). A general email would arrive alongside those letters and say less than they do.

---

## G1 · 88 days before · Tue Dec 8, 2026

**Subject:** You're on the list for March 6
**Preview:** We're glad you're coming. Here's what to hold on your calendar.

Dear [First name],

We're glad you're joining us for the LEAD Africa Vision Dinner.

**When:** Saturday, March 6, 2027. Reception at [reception time].
**Where:** The Woodland, Strongsville, OH.
**Cost:** None. Dinner is our gift to you.

As the holidays fill your calendar, please keep March 6 clear. We'll send a few short notes between now and then, and reminders with directions in dinner week.

Merry Christmas,
Clint Bieri
LEAD

---

## G2 · 53 days before · Tue Jan 12, 2027

**Subject:** What the evening holds
**Preview:** Dinner, a few real stories, and a clear look at what God is doing.

Dear [First name],

You're registered for the LEAD Africa Vision Dinner on Saturday, March 6, at The Woodland in Strongsville. We want you to know what the evening holds.

**A good meal, with good company.** Dinner is our gift to you.

**A clear look at what God is doing.** You'll hear how LEAD equips ministry leaders in East Africa and here at home, through short stories and a few real voices.

**An invitation.** Near the end, everyone will be invited to partner with LEAD in prayer and giving. There is no obligation. We simply want you to hear the opportunity.

The whole evening is done by [end time].

We look forward to meeting you,
Clint Bieri
LEAD

---

## G3 · 39 days before · Tue Jan 26, 2027

**Subject:** Why we equip leaders
**Preview:** Before March 6, a short look at who LEAD is.

Dear [First name],

Before you join us on March 6, we'd like to introduce ourselves.

LEAD equips ministry leaders. Many have been leading alone, often with little training and few people walking beside them. Through a cohort-based journey, we help them grow in their walk with God, in Spirit-led leadership, and in making disciples who make disciples.

[A short, true story of one leader in East Africa, with permission. Or leave this paragraph out.]

On March 6 you'll hear more stories like this, and where we believe God is leading next.

See you soon,
Clint Bieri
LEAD

---

## G4 · 25 days before · Tue Feb 9, 2027

**Subject:** Know someone who should be there?
**Preview:** There's still room. Here's how a friend can join you.

Dear [First name],

There is still room at the LEAD Africa Vision Dinner on March 6, and some of the best guests are friends of guests.

If someone comes to mind who would enjoy the evening, invite them to come along. They can register at **leadcma.org/dinner**. When the form asks where they'd like to sit, they can choose the Table Leader who invited you, so you're seated together.

Every guest needs their own email address, so they'll get their own confirmation and reminders.

Thank you,
Clint Bieri
LEAD

---

## G5 · 18 days before · Tue Feb 16, 2027

**Subject:** A few details before March 6
**Preview:** Attire, dietary needs, and how to reach us.

Dear [First name],

The dinner is two weeks from Saturday. A few details to help you plan:

**When:** Saturday, March 6. Reception at [reception time]. Done by [end time].
**Where:** The Woodland, [street address], Strongsville, OH. [Parking note]
**Attire:** Business attire is strongly suggested: Men – Suit or Jacket/Tie. Women – Dress or Pants Outfit.
**Dietary or accessibility needs:** if you didn't mention them when you registered, reply to this email by [day, date] and we'll let the kitchen know.
**Can't come after all?** Please let your Table Leader know, or reply to this email, so we can plan the meal well.

We'll send reminders with directions in dinner week.

See you soon,
Clint Bieri
LEAD

---

## Before turning on each automation, check

- [ ] Every [square bracket] is filled in or removed
- [ ] The email makes sense to someone who registered today
- [ ] No emoji
- [ ] No invented stories, quotes, figures or dates
- [ ] "Gifts" and "partners," not "donations" and "donors"; "opportunity," not "need"
- [ ] No tickets and no selling tables
