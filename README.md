# vision-dinner-countdown

The task countdown for LEAD's Vision Dinners. It is the source of truth for the countdown board each dinner gets in Monday.com.

The tasks follow the Fundraising Masterminds Perfect Vision Dinner model (21 weeks out through the week after), adjusted to LEAD's settled standards. Examples of those standards: Table Leaders (not Table Hosts), a 10–12% no-show rate, 5 / 3 / 1 / STOP time cards, the save-the-date mailed 12 weeks out, and LEAD's honorarium amounts. Registration runs through LEAD's own page at leadcma.org/dinner and Monday.com, not FundEasy.

## Files

| File | What it holds |
|---|---|
| `countdown.json` | The task template. It is the same for every dinner: 138 tasks, each with an `id`, name, `weeks_out`, area, roles and details. It contains no dates. |
| `dinners/YYYY-MM.json` | One file per dinner: the dinner date, any schedule adjustments, and the Monday.com IDs for that dinner's workspace, folder, boards (countdown, guests, Table Leaders), columns and groups. |
| `wordpress/` | The registration page at leadcma.org/dinner, a WordPress plugin that writes to the guest and Table Leader boards. See `wordpress/README.md`. |
| `LEAD Africa Vision Dinner designs.zip` | The design handoff the registration page was built from. |

## How due dates work

Every task is placed relative to the dinner date:

```
due date = dinner_date + offset_days
```

Most tasks have no `offset_days`. For those, `offset_days = -7 × weeks_out`, so a Week 12 task is due 84 days before the dinner. Tasks that fall on a specific day (the dinner-week reminder emails, the dinner itself, the week after) have an explicit `offset_days`.

`weeks_out` also decides the group on the board:

| `weeks_out` | Group title |
|---|---|
| 21 … 1 | `Week N · <date>`, using dinner_date − 7N days |
| 0 | `Dinner Week` |
| −1 | `Week After` |

If the dinner date moves, change `dinner_date` in that dinner's file and every due date and group title can be recalculated from it.

## Adjustments

A dinner file can change the template's schedule without editing the template. The only type so far is `off_week`: a week with no tasks, where the listed tasks move to another week.

March 2027 example: Christmas falls in Week 10 (Dec 26, 2026), so Week 10 is an off week and its three tasks move up to Week 13 (Dec 5), which the course leaves empty.

```json
{
  "type": "off_week",
  "week": 10,
  "reason": "Christmas (Dec 26, 2026)",
  "move_tasks_to_week": 13,
  "tasks": ["produce-the-ministry-update-video-3-min", "plan-the-creative-pieces", "plan-the-reception"]
}
```

On the board, moved tasks carry their new week in the Weeks Out column, and their Details note says they were moved.

## The Monday.com board

Each dinner gets a folder in the **Fundraising** workspace (for example, "Vision Dinner – March 2027") with a board named **Vision Dinner Countdown – \<Month Year\>**. The guest and Table Leader boards for registration (**Vision Dinner Guests – \<Month Year\>** and **Table Leaders – \<Month Year\>**) go in the same folder; their columns are described in `wordpress/README.md`.

| Column | Type | Notes |
|---|---|---|
| Status | Status | Blank = not started |
| Owner | People | Filled in once each role is recruited |
| Role | Dropdown | Executive Director, Overall Dinner Coordinator, Table Leader Coordinator, Registration Coordinator, D.O.V.E., Appeal Speaker, Calling Team |
| Due Date | Date | Calculated from the dinner date |
| Weeks Out | Number | 0 = dinner week, −1 = week after |
| Area | Status | Strategy & Team, Venue & Catering, Table Leaders, Program & Speakers, Giving & Appeal, Marketing & Mail, Registration & Tech, Night-of, Gratitude & Follow-up |
| Details | Long text | The playbook specifics for the task |

Column and group IDs differ for every board, which is why each dinner file records its own.

## Setting up the next dinner

1. Copy the latest file in `dinners/` to a new file named for the new dinner's year and month (for example, `dinners/2028-03.json`).
2. Set `dinner`, `label` and `dinner_date`. Clear the `monday` section.
3. Check the 21 weeks for holidays (Christmas, Thanksgiving, Easter) and add any `off_week` adjustments.
4. Ask Claude: "Set up the [Month Year] Vision Dinner." Claude creates the folder, board, columns, groups and tasks from `countdown.json` and the new dinner file, then writes the new IDs into that file's `monday` section.
5. Commit the updated dinner file.

## Changing the template

Edit `countdown.json` when LEAD changes how it runs every dinner, not just one. Keep each task's `id` stable, since dinner files refer to tasks by `id`. A change to the template affects future boards only; existing boards are updated by hand or on request.
