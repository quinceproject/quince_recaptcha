# Quince reCAPTCHA

**Google reCAPTCHA v3 on shop forms for PrestaShop 9.2.**

Quince reCAPTCHA adds Google's score to the form protection that Quince Engine
already has. The engine stops the obvious bots by itself, with a hidden field, a
minimum fill time and a request limit. This module asks Google how likely it is that a
person sent the form, and refuses submissions that score below the threshold set by
the shop. Customers never see a puzzle to solve.

> Quince reCAPTCHA is a free, open-source module (OSL-3.0). There is nothing to buy.
> If it saves you time, you can support development, see
> [Supporting the project](#supporting-the-project).

---

## Features

- **Every protected form, none by name.** The module checks each form that Quince
  Engine protects. In the Quince theme these are: contact, back in stock
  notification, newsletter sign-up, product question, product review, account
  registration and the first step of the step by step checkout, plus the expert
  enquiry of Quince Contacts and the quote request of Quince Offers. A form that the
  engine protects in the future is covered without changes here.
- **No puzzles.** reCAPTCHA v3 scores the visit in the background. The token is
  fetched at the moment of sending, so it does not expire on a long form.
- **A threshold you choose.** Google scores every submission from 0 (certainly a
  machine) to 1 (certainly a person). Below the score set in the back office, 0.5 by
  default, the shop refuses the submission.
- **Standard or Enterprise keys.** Use free keys from google.com/recaptcha, or the
  Enterprise version run from Google Cloud with an API key and a project id.
- **Notice instead of the badge.** Next to each protected form the shop shows "This
  site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service
  apply", which Google requires when the badge in the corner of the page is hidden.
  The notice appears only while the check actually runs.
- **Forms keep working without Google.** Until the keys are filled in, the module
  stays out of the way. If Google does not answer, the submission is decided by the
  engine's own protection alone. Uninstalling the module leaves the forms protected
  by the engine.
- **Multilingual.** English base with a full Polish translation, on PrestaShop's
  standard translation system.

### Known limitation: one page checkout

The one page checkout (the PrestaShop `ps_onepagecheckout` module) is not checked by
reCAPTCHA. Its final button sends the order straight from a click handler, without
the form submit event that the protection relies on, and adding the check there would
require changing PrestaShop core code. The step by step checkout is checked on its
first step.

## Requirements

| Requirement | Version |
|-------------|---------|
| PrestaShop  | 9.2 (tested on 9.2.0) |
| PHP         | 8.4 (tested), 8.1 or newer required |
| Quince Engine | required |
| PHP cURL extension | required |
| Google reCAPTCHA v3 keys | required for the check to run |

Quince Engine provides the form protection this module adds to and hosts the
module's settings screen. The module declares it as a dependency, so PrestaShop
refuses to install Quince reCAPTCHA without it.

## Installation

The release ZIP already contains everything the module needs. Composer and the
command line are not required.

**From the back office**

1. Download the latest **`quince_recaptcha.zip`** from the
   [**Releases**](https://github.com/quinceproject/quince_recaptcha/releases) page.
2. In the back office go to **Modules → Module Manager → Upload a module** and
   choose the ZIP. PrestaShop installs it.

**By copying the files to the server**

1. Unpack the release ZIP. It contains a `quince_recaptcha` folder.
2. Upload that folder to the `modules/` directory of the shop, for example by FTP.
3. In **Modules → Module Manager** find Quince reCAPTCHA and click **Install**.

**From Quince Engine** *(coming soon)*

The module list in Quince Engine will install the module straight from its release,
without downloading anything by hand.

**With Composer** *(coming soon)*

Installing with `composer require quinceproject/recaptcha` will work once the package
is published on Packagist.

> ⚠️ **Do not** use the green **Code → Download ZIP** button on this page. That
> archive contains the source only and is **missing the `vendor/` folder**, so the
> module will not run. Always install from a
> [**Release**](https://github.com/quinceproject/quince_recaptcha/releases).

## Configuration

Open **Quince project → reCAPTCHA** in the back office menu, or click **Configure**
next to the module in the Module Manager.

1. At google.com/recaptcha add your shop domain and pick **reCAPTCHA v3**.
2. Choose which kind of key you have: the free one, or Enterprise from Google Cloud.
3. Paste the site key and the secret key (for Enterprise: the site key, the Google
   Cloud API key and the project id).
4. Set the score below which the shop refuses a submission, switch the check on and
   save.

The keys are stored in the shop configuration only. The secret key never leaves the
shop: only the shop server asks Google with it.

## Architecture

- **`actionQuinceFormVerify`** hook of Quince Engine: the engine runs its own checks
  first and asks this module for the final word. Returning no opinion lets the
  submission through.
- **`TokenCheck`** sends the token to Google's `siteverify` endpoint or to the
  reCAPTCHA Enterprise assessments API and compares the score with the threshold.
- **`views/js/recaptcha.js`** adds a fresh token to every form that carries the
  engine's guard field, right before it is sent.
- **`displayQuinceFormNotice`** hook places the Google notice under each protected
  form.
- **Settings** are stored in PrestaShop configuration under the `QUINCE_RECAPTCHA_`
  prefix and removed on uninstall.

## Development / building from source

This repository tracks **source only**. The `vendor/` directory is git-ignored, so a
`git clone` is **not** directly installable. **Start with `composer install`**: the
development environment mounts module directories straight from disk, and without
that step a fresh clone gives a broken back office.

```bash
git clone https://github.com/quinceproject/quince_recaptcha.git
cd quince_recaptcha
composer install
```

The folder must be called `quince_recaptcha`, the same as the module. A clone of this
repository already has that name.

To build an installable archive, zip the `quince_recaptcha/` folder **with its
`vendor/` directory included**, leaving out `.git/` and `.gitignore`.

## License

Code: [Open Software License 3.0 (OSL-3.0)](LICENSE). It is a reciprocal licence:
whoever modifies and distributes this code has to publish the changes under the same
licence and keep the original copyright and author notices.

reCAPTCHA is a Google service, used under Google's own terms. The module only connects
to it.

**The name "Quince" is not covered by the code licence.** A fork may use the code; it
may not use the name.

© 2026 Marcin Gajewski

## Supporting the project

Quince modules are free downloads and there is nothing to buy. If the module is useful
to you, you can support its development by buying me a coffee:

**[buymeacoffee.com/marcingajewski](https://buymeacoffee.com/marcingajewski)**
