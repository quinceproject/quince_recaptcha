# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the versioning follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.3] - 2026-11-15

### Security

- **A Google answer without a score is a rejection**, not a pass. Before, it counted
  as the best score.
- **The answer must come from the shop's own domain** (the `hostname` Google reports),
  so a token obtained on another site is not accepted.
- **The secret and API keys are no longer printed into the settings page.** The fields
  stay empty and show that a key is saved; leave a field empty to keep the key.
- When Google cannot be reached, forms are still accepted, so a Google outage does not
  stop orders. This is deliberate and now documented in the code.

## [0.2.2] - 2026-11-15

### Security

- Every back-office screen of the module checks the employee's permissions. Before,
  any logged-in employee, whatever their profile, could see the reCAPTCHA keys and turn
  the protection off. The screens now belong to the module's own menu tab
  (`AdminQuinceRecaptcha`): grant it to a profile under **Advanced Parameters → Team →
  Permissions**. SuperAdmin needs nothing. A profile that had access through the hidden
  `AdminQuince` tab needs access to this tab now.

## [0.2.1] - 2026-11-10

### Changed

- Polish translation completed: the texts added since the previous translation are now translated.
- The hook title no longer uses a long dash (new installations).

## [0.2.0] - 2026-11-10

First public release.

### Added

- Google reCAPTCHA v3 score for every shop form protected by Quince Engine, checked
  on the server after the engine's own protection.
- Standard (free) and Enterprise (Google Cloud) keys.
- Refusal threshold set in the back office, 0.5 by default.
- "This site is protected by reCAPTCHA" notice with the Google Privacy Policy and
  Terms of Service links next to each protected form, in place of the badge in the
  corner of the page.
- Polish translation.
