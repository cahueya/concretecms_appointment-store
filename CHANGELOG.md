# Changelog

## 0.1.19

- Keep temporary cart reservations as the authoritative protection against double booking, but expose them to the storefront as a distinct `reserved` availability state.
- Replace misleading “just booked” messaging for temporary holds with an explicit **Appointment currently being booked** explanation.
- Explain that a held appointment may already be in the current customer's cart and may become available again if the active booking is not completed.
- Return `available`, `reserved`, `minimum_notice` and `unavailable` reasons from the read-only availability/preflight flow instead of collapsing every conflict into a generic unavailable response.
- Keep temporarily reserved dates visible in the Flatpickr calendar with a distinct hollow marker; selecting such a date explains the active booking hold without allowing the slot to be selected.
- Show a booking-process notice in the select fallback when one or more appointments are temporarily reserved.
- Add/update German, French and Italian translations for all new customer-facing messages.
- No database schema changes.

## 0.1.18

- Change reservation timing so browsing dates and selecting time slots never blocks an appointment.
- Make the product-page availability precheck strictly read-only.
- Create the temporary reservation only inside Community Store `CART_PRE_ADD`, after the customer submits **Add to Cart**.
- Keep the atomic database lock and unique-per-slot reservation protection at the cart boundary.
- Keep the friendly product-page conflict check without mutating slot state.
- Release the newly created cart reservation if Community Store rejects the item later in the add-to-cart flow.

## 0.1.17

- Fixed reservation preflight failures after upgrading from earlier package versions.
- Replaced Doctrine `repositoryClass` metadata with a normal injectable ORM repository service, avoiding stale metadata-cache issues.
- Reservation lookups now use an explicit ORM join on the slot association and support pessimistic locking inside the active transaction.

## 0.1.16

- Fixed duplicate reservation INSERTs during the preflight → Community Store cart hand-off.
- Added an ORM repository lookup using the slot association identifier directly, with pessimistic locking inside reservation transactions.
- Existing reservations owned by the same reservation token are now reliably reused and only have their expiry extended.
- Expired reservations are flushed as DELETEs before a replacement reservation is inserted, preventing unique-slot constraint collisions.
- Applied the same slot-ID reservation lookup to cart refresh, release, order binding, and final booking paths.

## 0.1.15

- Fix cart refresh reservation lookup to query the Doctrine slot association with an entity reference.
- Make reservation TTL refresh non-fatal so Community Store cart rendering can never fail because of a touch error.
- Add cart pre-add acceptance diagnostics to the Concrete log.

## 0.1.14

- Replace the fragile session-based preflight/cart hand-off with an opaque per-reservation bearer token.
- Carry the reservation token as an internal Community Store cart attribute and use it for cart refresh, removal, clearing, and order binding.
- Release a preflight reservation automatically when Community Store rejects the cart item after CART_PRE_ADD.
- Keep legacy/no-JavaScript session ownership only as a fallback.

## 0.1.13

- Fixed cart reservation ownership by using a stable Appointment Store owner token stored through Concrete's Session facade instead of raw PHP/Symfony session IDs.
- Preflight and Community Store cart requests now identify the same browser reservation reliably.
- Failed cart hand-offs release their own preflight reservation immediately instead of leaving the appointment blocked until timeout.
- Removed unsafe integer casts of human-readable appointment values in cart/order fallbacks. Legacy numeric appointment values remain supported.

## 0.1.12

- Fix the preflight-to-cart hand-off when Community Store does not transport the internal slot field.
- Treat the signed reservation proof as the authoritative source for the slot ID.
- Never cast a human-readable appointment value such as `2026-09-27 · 05:00–15:00` to an integer slot ID.
- Add the internal machine field name directly in the rendered HTML instead of relying only on JavaScript.

## 0.1.11

- Fix the reservation hand-off between the AJAX preflight and Community Store add-to-cart.
- Use a signed reservation proof instead of relying on identical session IDs across the two requests.
- Keep the existing atomic reservation/race protection while preventing false “no longer available” cart errors.

## 0.1.10

- Fixed an availability regression introduced in 0.1.9 where a Doctrine field-to-field datetime comparison could filter out all otherwise valid free slots.
- Malformed slots whose end time is not after their start time are now filtered in PHP instead, preserving compatibility across Concrete CMS 9 database/Doctrine combinations.

## 0.1.9

- Make `/availability/days` and `/availability/slots` use the same free-slot query and the same local-date calculation.
- Fix cases where a day was shown as available but its slot endpoint returned an empty result because of timezone/database date-boundary handling.
- Ignore malformed CalDAV events whose end time is not after their start time.

## 0.1.8

- Handle appointment availability races as a normal product-page booking conflict instead of exposing Community Store's generic error page.
- Add a CSRF-protected reservation preflight before the normal Community Store add-to-cart handler.
- Refresh the product page after a conflict so the picker immediately reflects current availability.
- Show a Bootstrap warning above the picker with a direct **Choose another appointment** action.
- Add German, Italian and French translations for the new conflict flow.

## 0.1.7

- Keep the saved product configuration selected after saving so the dashboard form shows the persisted frontend display mode instead of resetting visually to the default `Automatic` state.
- Show the stored frontend display mode and minimum booking notice directly in the product mappings table.

## 0.1.6

- Bundle Flatpickr 4.6.13 locally with the package and remove all frontend CDN requests.
- Add a per-product **Minimum booking notice** setting with selectable hour offsets from 0 to 72 hours.
- Default new appointment product mappings to a 24-hour minimum notice.
- Filter too-soon appointments from the fallback select, Flatpickr days and availability API.
- Enforce the same minimum notice inside the server-side reservation transaction so it cannot be bypassed by manipulated requests.

## 0.1.5

- Replace the browser-native date picker with Flatpickr 4.6.13.
- Enable only dates that currently have synchronized free appointment slots.
- Add a small visual marker to available dates and keep unavailable days disabled.
- Keep Bootstrap time-slot buttons after date selection.
- Preserve the native Community Store select as a no-JavaScript/asset-load fallback.
- Localize weekday and month names from the document locale using `Intl.DateTimeFormat`.

## 0.1.4

- Move the package dashboard section below **Store → Appointments**.
- Use generic CalDAV terminology throughout the dashboard; mailcow/SOGo remains a tested server rather than an interface assumption.
- Replace the custom month calendar with the browser-native HTML5 date picker plus Bootstrap time-slot buttons.
- Keep the native Community Store select as the fallback and for products configured in Select mode.
- Add German, Italian and French package translations.

## 0.1.3

- Show the selected appointment as a localized date/time value in the Community Store cart, order details and receipts instead of exposing the internal slot ID.
- Keep the internal slot ID as a separate cart-only machine field and bind it to Appointment Store reservations when Community Store creates the order.
- Resolve final bookings from the order-bound reservation, with backward compatibility for 0.1.0–0.1.2 orders that stored the numeric slot ID as the visible option value.
- Use one shared slot formatter for customer-facing appointment labels in the configured calendar timezone.

## 0.1.2

- Bundled the Community Product custom template directly in the package.
- Moved picker JavaScript into the custom template `view.js`, loaded automatically by Concrete.
- Removed global Appointment Store frontend asset registration.
- Added a compact month calendar with only available dates clickable.
- Kept the native Community Store select as a no-JavaScript fallback.
- Applied Community Store/Bootstrap standard classes to the fallback select.
- Made calendar initialization robust both before and after `DOMContentLoaded`.

## 0.1.1

- Fixed product configuration error handling when Community Store option creation fails.
- Initialize the selected calendar source before re-rendering the product form.
- Made product configuration calendar-source rendering null-safe so the original error is no longer masked by a secondary return-type error.

## 0.1.0

- Initial installable release for Concrete CMS 9+ and Community Store 2.6+.
- Doctrine ORM data model for CalDAV accounts, calendar sources, product mappings, slots, reservations and bookings.
- Encrypted CalDAV credentials using Sodium secretbox.
- mailcow/SOGo-oriented CalDAV synchronization with ETag conflict protection.
- Category and two-calendar booking strategies.
- Native Community Store text Product Option integration (`appointment_slot`).
- Cart reservation locking and order/payment booking lifecycle.
- Concrete Automated Task for slot synchronization and expired cart-reservation cleanup.
- Dashboard management for accounts, calendar sources and product mappings.
- German translation included; source strings remain English.
- Recurring/multi-VEVENT CalDAV resources intentionally excluded from the first release.
