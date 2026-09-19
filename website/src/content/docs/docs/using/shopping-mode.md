---
title: Shopping Mode
description:
  Start a trip from your lists, walk your stores, shop it together with the house, and finish with a
  priced review.
sidebar:
  order: 5
---

## What it does

### 1. Starting a trip

From any list (or **All Lists**) you open **Start shopping**. You pick:

- **Which checklists** to shop (pre-selected from where you launched).
- **Which items** the trip covers. The **Items** tile opens a picker holding everything still to buy
  on the selected lists, grouped by category: tick items one at a time, a whole category from its
  header, or use **All**, **None** and **Invert**. The tile sums up what you chose — _"All 23
  items"_, _"9 of 23 items"_. A trip covers everything unless you narrow it, so items that appear on
  the lists after you picked are shopped too; leaving nothing at all is not a trip you can start.
- **Which stores** you'll visit, and in what order — toggle stores on/off and drag to arrange the
  route. Only stores that actually have unchecked items on the selected lists are offered.
- Whether to **include items not assigned to any store** (buy-anywhere items).

You shop one trip at a time. If you already have a trip running (even in another house), you'll be
offered **Resume** or **End previous trip** instead of silently starting a second one; if you are
out on a housemate's trip, **Resume** or **Leave trip**.

<img width="669" alt="Start shopping screen: checklist selection, an ordered store route, and the include-unassigned toggle" src="/docs/start-screen.png" />

### 2. Shopping

The trip view is a dense, single-column list of everything still to buy, grouped under sticky
category headers, narrowed to the **active store**. Tapping a row checks the item off — it drops out
of the list and into a **Done** drawer at the bottom (tap again to undo). A sticky bar up top shows
your store sequence and progress; tap a store to jump to it, or use the floating action button to
move to the **next store** / **finish**.

The list refreshes about once a minute (and immediately when you return to the tab), so if a
housemate checks something off, it disappears from yours too. A trip has a single shared check log,
so on a trip you share with a housemate their ticks land in your **Done** drawer rather than
silently vanishing from your buy list.

Categories are grouped in the order you walk the store you're in — see
[per-store category order](#per-store-category-order) below.

<img width="965" alt="Dense shopping view: sticky store bar with progress, category-grouped items, and the next-store action" src="/docs/shopping-view.png" />

### 3. Shopping together

While you shop, your session sends a lightweight heartbeat. Housemates' avatars appear on the store
bar next to the store each person is currently shopping, so nobody double-shops. Presence is
approximate and forgiving (it fades after ~15 minutes of no activity, and pauses when your tab is
backgrounded). If you'd rather not be seen, flip the **shop privately** toggle in the store bar —
your trip is hidden from housemates' presence (and later from their history).

When somebody is out shopping, a banner on your lists says so — _"Alice is shopping at
Supermarket"_, or _"Alice and 2 others are shopping"_ once a trip has several shoppers — with a
**Join** button that puts you on their trip instead of starting one of your own:

- You shop the same items against the same check log. Whatever any of you ticks off drops out of
  everyone's buy list.
- The store bar attributes the whole trip to its active store, so every shopper on it shows there.
- Whoever finishes the trip finishes it for everybody; the rest are told and sent back to their
  lists.
- **Leave trip**, from the start screen, steps you out without ending it for the others. The shopper
  who started a trip cannot leave it — closing it is their way out.

Joining while you have a live trip of your own offers to end and save yours first. A private trip
never surfaces for housemates to join.

A trip belongs to the shopper who started it, so that is whose history it lands in. A trip you
joined shows up under the **house** view of [history](#6-history) rather than your own.

### 4. Reminders

Households can define their own prompts — e.g. _"Bring reusable bags"_, _"Check the freezer aisle"_
— that pop up at the moment they matter: **at start**, **between shops**, or **at end**. Manage them
from **House settings → Shopping**, from the start screen, or inline while shopping. In the manager,
reminders are grouped by moment into three drag-reorderable lists; each row has an enable switch,
the text, and the moment it fires. Change a row's moment to move it between groups. When a step has
no reminders, you'll see a small empty state with an **Add reminders** button.

While shopping, enabled reminders show as a small, **non-blocking** checklist at their moment — you
can tick them off as you go, but nothing is required and the ticks are just for your own tracking
(they don't persist across a reload).

<img width="617" alt="Inline reminder block surfaced while shopping, with tick-off checkboxes" src="/docs/reminders-surfacing.png" />

<img width="920" alt="Reminders manager: prompts grouped by moment into drag-reorderable lists with enable toggles" src="/docs/reminders-manager.png" />

### 5. Finishing & review

When you finish a store or the whole trip you get a **review**: items grouped by the store you
bought them at, an estimated total per currency (from item prices, shown as a range when prices are
ranges), and a field to **enter what you actually paid** per store (or for the whole trip if you
shopped storeless). Finishing stamps the trip closed.

<img width="616" alt="Trip review grouped by store with per-currency estimates and an actual-paid field" src="/docs/review.png" />

### 6. History

Finished trips land in **Shopping history** (linked from the start screen). Each row shows the store
route, item count, duration, and total; opening one shows the same grouped review. You can view just
**your** trips or the whole **house's** (private trips stay hidden from housemates). Closed trips
render from a **snapshot** taken at close time, so they stay intact even if the underlying items are
later edited or deleted. Old trips can be auto-pruned via a configurable retention period (default:
keep forever).

Got a total wrong, or paid at the till after the trip was closed? **Edit totals**, in the review of
a trip you shopped yourself, reopens the amounts — what you paid per store, or the grand total for a
storeless trip — and the history row follows the correction. Housemates' trips stay look-only.

<img width="714" alt="Shopping history list: store route, item count, duration, and total per finished trip" src="/docs/history.png" />

## Per-store category order

Shopping mode groups items under category headers, and a shop is rarely walked in the order the
house happens to list its categories. Open the category manager and choose **Per-store order** to
arrange one: pick a store, then drag its categories into the order you walk its aisles. **Use the
shared order** drops the arrangement and hands the store back to the house-wide order.

A store names only the categories it has been arranged with. Everything else — including one created
after the arrangement — falls in after them, in the house-wide order.
