# Appointment Store

Appointment Store adds **CalDAV-backed appointment booking** to **Concrete CMS 9+** and **Community Store 2.6+**.

The CalDAV server remains the source of truth for appointment events. Appointment Store synchronizes bookable events into a local Doctrine ORM cache, presents them on Community Store product pages, temporarily reserves a slot when it is added to the cart, and writes the final booking back to CalDAV.

mailcow/SOGo is a tested target, but any standards-compatible CalDAV server can be used.

## Requirements

- Concrete CMS 9.0+
- Community Store 2.6+
- PHP 7.4+
- PHP Sodium extension
- A CalDAV account with permission to read and update the appointment calendar(s)

No additional Composer installation is required when installing the packaged ZIP. Concrete CMS 9 already provides the HTTP and iCalendar libraries used by Appointment Store.

## Recommended first setup

For the simplest initial configuration, use:

```text
Booking mode:             Categories in one calendar
Available category:       AVAILABLE
Booked category:          BOOKED
Sync horizon:             60 days
Frontend display:         Automatic
Cart reservation:         30 minutes
Minimum booking notice:   24 hours
Final booking trigger:    Order placed
Release on cancellation:  Enabled
Synchronization:          Approximately every 5 minutes
```

This setup keeps free and booked appointments in one calendar and changes only their category.

---

# Setup

## 1. Install Appointment Store

Copy the package directory to:

```text
packages/appointment_store/
```

The package root should contain files such as:

```text
packages/
└── appointment_store/
    ├── controller.php
    ├── composer.json
    ├── src/
    ├── blocks/
    ├── single_pages/
    └── ...
```

Do not keep an additional version directory around the package, for example:

```text
packages/appointment_store-0.1.19/appointment_store/
```

Then open:

**Dashboard → Extend Concrete**

and install **Appointment Store**.

Community Store should already be installed.

## 2. Create a CalDAV account

Open:

**Dashboard → Store → Appointments → Accounts**

Create a CalDAV account with:

- **Name** – an internal label, for example `Appointments`
- **CalDAV Base URL** – the DAV root or user principal URL
- **Username**
- **Password / App Password**
- **Enabled** – must be enabled for synchronization

For a typical SOGo/mailcow installation, the base URL may look like:

```text
https://mail.example.com/SOGo/dav/booking@example.com/
```

Example:

```text
Name:                Appointments
CalDAV Base URL:     https://mail.example.com/SOGo/dav/booking@example.com/
Username:            booking@example.com
Password / App Password: ********
Enabled:             Yes
```

Save the account, then click **Test**.

A successful test returns:

```text
CalDAV connection successful.
```

Do not continue until the account test succeeds.

> CalDAV credentials are encrypted before being stored in the database.

## 3. Choose a booking mode

Appointment Store supports two booking strategies.

### Option A: Categories in one calendar

This is the recommended starting point.

Free and booked appointments remain in the same calendar. Their state is represented by categories.

A free event contains the configured available category, for example:

```text
CATEGORIES:AVAILABLE
```

When booked, Appointment Store removes `AVAILABLE` and adds the configured booked category:

```text
CATEGORIES:BOOKED
```

If the order is cancelled and **Release appointment when order is cancelled** is enabled, the change is reversed.

Only events containing the configured available category are imported as free slots in category mode.

### Option B: Two calendars

This mode separates availability physically:

```text
Appointments Available
Appointments Booked
```

Free events are read from the available calendar. When booked, Appointment Store moves the CalDAV resource into the booked calendar.

On cancellation, it can move the event back when automatic release is enabled.

The configured CalDAV account must have permission to create and delete events in both calendars.

## 4. Create a Calendar Source

Open:

**Dashboard → Store → Appointments → Calendars**

Create a new **Calendar Source**.

A Calendar Source defines where Appointment Store finds appointment slots and how a successful booking is written back to CalDAV.

### Example: category mode

```text
CalDAV Account:          Appointments
Name:                    Consultation appointments
Available Calendar URL: Calendar/personal/
Booking Mode:            Categories in one calendar
Available Category:      AVAILABLE
Booked Category:         BOOKED
Timezone:                Europe/Berlin
Sync Horizon (days):     60
Enabled:                 Yes
```

If the account base URL is:

```text
https://mail.example.com/SOGo/dav/booking@example.com/
```

a relative calendar path can be used:

```text
Calendar/personal/
```

An absolute calendar URL is also accepted:

```text
https://mail.example.com/SOGo/dav/booking@example.com/Calendar/personal/
```

### Example: two-calendar mode

```text
CalDAV Account:          Appointments
Name:                    Consultation appointments
Available Calendar URL: Calendar/available/
Booking Mode:            Two calendars (free → booked)
Booked Calendar URL:     Calendar/booked/
Timezone:                Europe/Berlin
Sync Horizon (days):     60
Enabled:                 Yes
```

The booked calendar must already exist.

Save the Calendar Source and click **Test**.

A successful test returns:

```text
Calendar connection successful.
```

### Timezone

The Calendar Source timezone controls customer-facing appointment formatting and should match the timezone in which the appointments are sold.

For example:

```text
Europe/Berlin
America/El_Salvador
UTC
```

Use a valid PHP/IANA timezone identifier.

### Sync horizon

**Sync Horizon (days)** determines how far into the future Appointment Store asks CalDAV for appointment events.

For example, a value of `60` imports qualifying events starting between now and 60 days from now.

## 5. Create appointment events in CalDAV

Each bookable appointment must currently be a **separate timed VEVENT**.

Example:

```text
Date:     10 September 2026
Start:    14:00
End:      15:00
Title:    Consultation
Category: AVAILABLE
```

Equivalent simplified iCalendar data:

```text
BEGIN:VEVENT
UID:appointment-123@example.com
DTSTART:20260910T140000
DTEND:20260910T150000
SUMMARY:Consultation
CATEGORIES:AVAILABLE
END:VEVENT
```

In category mode, the event must contain the configured **Available Category**.

In two-calendar mode, qualifying events in the configured available calendar are treated as available; no `AVAILABLE` category is required.

### Current slot limitations

Appointment Store deliberately ignores:

- all-day events
- recurring master events containing `RRULE`
- CalDAV resources containing more than one `VEVENT`
- events without a valid start and end time/duration
- malformed events whose end time is not later than the start time

Do not create one recurring event such as:

```text
Every Monday at 14:00
```

Create individual appointment events instead:

```text
Monday 7 September 14:00
Monday 14 September 14:00
Monday 21 September 14:00
```

This prevents a single customer booking from accidentally changing an entire recurring series.

## 6. Run the first synchronization

Open:

**Dashboard → Store → Appointments**

Click **Open Tasks**, then run:

**Synchronize Appointment Slots**

The task handle is:

```text
appointment_store_sync
```

The task:

- releases expired cart reservations
- synchronizes all enabled Calendar Sources whose CalDAV account is enabled
- creates new local slots
- updates existing local slots
- marks disappeared free slots unavailable
- stores synchronization status and errors

After a successful sync, the Appointment Store dashboard shows counts for:

```text
Available
Reserved
Booked
Unavailable
```

For one valid test event, you should normally see:

```text
Available: 1
Reserved: 0
Booked: 0
Unavailable: 0
```

The dashboard also shows the last synchronization time and errors for each Calendar Source.

## 7. Run synchronization regularly

Appointment Store should synchronize regularly so external calendar changes appear on the storefront.

For frequently changing calendars, an interval of approximately **5 minutes** is a reasonable starting point.

The synchronization interval is not the protection against double booking. Appointment Store also uses:

- atomic database locking when a slot is added to the cart
- a unique reservation per slot
- server-side availability validation
- synchronized CalDAV ETags for final writes

Therefore, two customers cannot safely acquire the same appointment merely because the next calendar synchronization has not run yet.

## 8. Create the Community Store product

Create the Community Store product normally.

Example:

```text
Name:  60 Minute Consultation
Price: 99.00 EUR
```

Do **not** manually create an appointment product option.

Appointment Store creates and manages the required Community Store text Product Option automatically.

## 9. Map the product to the Calendar Source

Open:

**Dashboard → Store → Appointments → Products**

Choose:

- **Community Store Product**
- **Calendar Source**
- **Frontend Display**
- **Cart Reservation (minutes)**
- **Minimum booking notice**
- **Final Booking Trigger**
- **Release appointment when order is cancelled**

Example:

```text
Community Store Product: 60 Minute Consultation
Calendar Source:         Consultation appointments
Frontend Display:        Automatic
Cart Reservation:        30 minutes
Minimum booking notice:  24 hours
Final Booking Trigger:   Order placed
Release on cancellation: Yes
```

When the mapping is saved, Appointment Store creates a required Community Store **text Product Option** with handle:

```text
appointment_slot
```

Internally, Community Store posts this option as:

```text
pt<OPTION_ID>
```

The normal Community Store cart and order flow is retained. Appointment Store does not use a custom cart proxy.

Appointment Store also disables the normal product quantity selector for mapped appointment products because one selected appointment represents exactly one bookable unit. The original quantity setting is restored when Appointment Store is disabled for the product or the package is uninstalled.

## 10. Choose the frontend display mode

Each appointment product supports three display modes.

### Automatic

Recommended default.

- 1–3 visible appointment dates: native select
- 4 or more visible appointment dates: date picker with time buttons

### Select

Always use the native appointment select.

### Date picker and time buttons

Always use the Flatpickr date picker, even when only one date is available.

Flatpickr 4.6.13 is bundled with the package. No frontend CDN request is required.

## 11. Configure the cart reservation

**Cart Reservation (minutes)** controls how long an appointment is temporarily held after it has been successfully added to a Community Store cart.

The default is:

```text
30 minutes
```

Important behavior:

1. Opening the product page does **not** reserve an appointment.
2. Selecting a date does **not** reserve an appointment.
3. Selecting a time does **not** reserve an appointment.
4. The reservation is first created atomically when Community Store processes **Add to Cart**.
5. While the cart remains active, the reservation can be refreshed.
6. Removing or clearing the cart releases the reservation.
7. Expired cart reservations are also removed by the synchronization task.

If another browser visits a temporarily held appointment, Appointment Store reports it as being in an active booking process rather than incorrectly claiming that it is already booked.

The customer may see:

> **Appointment currently being booked**  
> This appointment is currently reserved as part of an active booking process. If you already added it to your cart, you can continue your booking there. Otherwise, it may become available again if the current booking is not completed.

The same neutral message also covers the case where the visitor is returning to an appointment already held by their own cart.

In the date picker, dates whose valid appointments are temporarily reserved remain visible with a separate marker, but the held times are not selectable.

## 12. Configure the minimum booking notice

**Minimum booking notice** is a rolling cutoff before the appointment start time.

Available values are:

```text
No minimum notice
1 hour
2 hours
4 hours
6 hours
8 hours
12 hours
24 hours
36 hours
48 hours
72 hours
```

The default is:

```text
24 hours
```

For example, if the current time is Monday at 10:00 and the minimum notice is 24 hours, an appointment starting before Tuesday at 10:00 cannot be selected or reserved.

The rule is enforced both in the storefront and inside the server-side reservation transaction, so manipulating the browser request cannot bypass it.

## 13. Choose the final booking trigger

Appointment Store supports two final booking triggers.

### Order placed

This is the default and simplest choice.

```text
Available
↓
Added to cart
↓
Reserved
↓
Order placed
↓
Booked
```

The final CalDAV booking happens when Community Store creates the order.

### Payment complete

Use this when the appointment should become permanently booked only after Community Store reports successful payment.

```text
Available
↓
Added to cart
↓
Reserved
↓
Order created
↓
Reservation bound to order
↓
Payment complete
↓
Booked
```

An order-bound reservation is intentionally not released by normal cart-expiry cleanup.

If the payment method can leave orders unpaid indefinitely, abandoned orders must eventually be cancelled so their appointment reservation can be released.

## 14. Configure cancellation behavior

Enable:

**Release appointment when order is cancelled**

when a cancelled order should make the appointment available again.

In category mode:

```text
BOOKED → AVAILABLE
```

In two-calendar mode:

```text
Booked calendar → Available calendar
```

If this option is disabled, cancellation does not automatically reopen an already booked appointment.

## 15. Apply the Appointment Store product template

This step is required for the packaged appointment picker.

Locate the **Community Product** block used on the product page and choose:

**Design & Custom Template → Appointment Store**

The package provides:

```text
blocks/community_product/templates/appointment_store/
├── view.php
└── view.js
```

Nothing needs to be copied into `application/blocks`.

The custom template is based on Community Store's complete product view. It only replaces rendering of the Product Option with handle:

```text
appointment_slot
```

All other Community Store product options and normal cart behavior remain native.

The server-rendered select remains a usable no-JavaScript fallback.

---

# End-to-end test before production

Use at least one real CalDAV appointment to verify the complete lifecycle.

## Availability test

1. Create one separate timed event in the available calendar.
2. In category mode, add the `AVAILABLE` category.
3. Make sure the event starts later than the configured minimum booking notice.
4. Run **Synchronize Appointment Slots**.
5. Confirm **Dashboard → Store → Appointments** shows the slot as **Available**.
6. Open the mapped Community Store product.
7. Confirm the date/time appears in the appointment picker.

## Reservation test

1. Add the appointment to the cart.
2. Confirm the dashboard shows it as **Reserved**.
3. Open the same product in another browser or private window.
4. Confirm the appointment cannot be selected.
5. Confirm the storefront explains that it is temporarily in a booking process.
6. Remove the appointment from the first cart.
7. Reload the second browser and confirm the appointment becomes available again.

## Booking test

1. Add the appointment to the cart again.
2. Complete the order according to the configured final booking trigger.
3. Confirm the dashboard shows the slot as **Booked**.
4. Check CalDAV.

In category mode, confirm:

```text
AVAILABLE → BOOKED
```

In two-calendar mode, confirm the event moved from the available calendar to the booked calendar.

## Cancellation test

If **Release appointment when order is cancelled** is enabled:

1. Cancel the Community Store order.
2. Confirm the appointment is returned to the available state in CalDAV.
3. Run synchronization if necessary.
4. Confirm the appointment becomes selectable again on the storefront.

Do not go live until this complete test succeeds.

---

# Booking lifecycle and conflict protection

Appointment Store deliberately separates browsing, temporary reservation, and final booking.

1. The customer browses available dates and times. This is read-only.
2. A read-only preflight validates the selected slot immediately before normal Community Store cart submission.
3. `on_community_store_cart_pre_add` acquires a database lock and creates the temporary reservation atomically.
4. Only one active reservation can exist for a slot.
5. The reservation is refreshed while appropriate cart activity continues.
6. Cart removal/clearing releases a cart-bound reservation.
7. The configured order/payment event triggers final booking.
8. Final CalDAV writes use the synchronized ETag.
9. If the external calendar event changed after synchronization, Appointment Store reports a conflict instead of silently overwriting it.
10. Order cancellation can release pending reservations and reopen completed bookings when configured.

A successful frontend preflight is never treated as authoritative. If another request wins the slot between preflight and cart submission, the database reservation transaction still rejects the second request.

---

# CalDAV write behavior

## Category mode

Appointment Store reads the event using its synchronized ETag and updates the configured categories in the same CalDAV resource.

A normal booking changes:

```text
AVAILABLE → BOOKED
```

Other unrelated categories are retained.

## Two-calendar mode

Booking is implemented as:

1. GET the source event using its synchronized ETag
2. PUT the event into the booked collection using `If-None-Match: *`
3. DELETE the original source resource using `If-Match`

If deletion fails after creating the destination resource, Appointment Store attempts to roll back the newly created destination resource.

Cancellation performs the reverse operation when automatic release is enabled.

---

# Troubleshooting

## The account test fails

Check:

- CalDAV base URL
- username
- password/app password
- TLS certificate validity
- whether the account has DAV access
- whether the server permits `PROPFIND`

For SOGo/mailcow, verify that the URL points to the DAV user root, for example:

```text
https://mail.example.com/SOGo/dav/user@example.com/
```

## The Calendar Source test fails

The account may work while the configured calendar path is wrong.

Check the **Available Calendar URL** separately, for example:

```text
Calendar/personal/
```

or use the full absolute URL.

In two-calendar mode, also verify that the booked calendar exists and is writable.

## No appointments appear after synchronization

Check all of the following:

- CalDAV account is enabled
- Calendar Source is enabled
- event is inside the configured sync horizon
- event starts in the future
- event is a timed event, not all-day
- event is not a recurring `RRULE` master
- resource contains exactly one `VEVENT`
- end time is later than start time
- in category mode, the event contains the configured available category
- product is mapped to the correct Calendar Source
- appointment is outside the configured minimum booking notice
- **Appointment Store** custom template is applied to the Community Product block

## A date says the appointment is currently being booked

The appointment has an active temporary reservation. This normally means it is currently present in a cart or has been bound to an order that has not yet reached its final booking trigger.

It may also be the current customer's own cart reservation.

The appointment becomes available again when the hold is legitimately released, for example after cart removal, expiry, or order cancellation according to the configured lifecycle.

## A calendar event was changed manually after synchronization

Appointment Store uses the synchronized ETag for final CalDAV writes. If the event changed externally, the final operation can be rejected as a conflict rather than overwriting the newer calendar version.

Run synchronization again and review the resulting slot state before retrying.

---

# Architecture

The CalDAV server remains the source of truth. Appointment Store keeps a local Doctrine ORM cache for availability and transactional locking.

The package creates these ORM entities:

- `CalDavAccount` – encrypted CalDAV credentials
- `CalendarSource` – calendar configuration and booking strategy
- `AppointmentProductConfig` – Community Store product-to-calendar mapping
- `AppointmentSlot` – synchronized local slot cache
- `AppointmentReservation` – temporary cart/order reservation
- `AppointmentBooking` – permanent order/slot association

Database tables are created and upgraded through Concrete's package Doctrine ORM provider. The package contains no raw `CREATE TABLE` SQL.

# Security

- CalDAV passwords/app passwords are encrypted with Sodium `secretbox` before storage.
- The encryption key is generated locally and stored in Concrete configuration under the `appointment_store` namespace.
- Frontend slot IDs are never trusted by themselves.
- Product mapping, source, slot state, minimum notice, and reservation ownership are revalidated server-side.
- Temporary reservations are protected with database locking and a unique-per-slot reservation constraint.
- Final CalDAV modifications use conditional requests such as `If-Match` and `If-None-Match` where applicable.

# Localization

Source strings are English.

The package includes translations for:

- German (`de_DE`)
- French (`fr_FR`)
- Italian (`it_IT`)

# Development notes

Appointment Store targets the APIs available in Concrete CMS 9+ and Community Store 2.6+.

Legacy Concrete Jobs are not used. Periodic synchronization is implemented as a Concrete Automated Task.

With JavaScript enabled, Appointment Store keeps the internal slot ID separate from the customer-facing Community Store option value. Cart, checkout, order details, and receipt emails therefore show the appointment date/time in the configured calendar timezone instead of exposing an internal numeric slot ID. Existing orders created with early 0.1.x releases remain supported by the package's compatibility paths.
