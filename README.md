<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Auto Currency for Cospend

This NextCloud app automatically fetches currency information for your Cospend projects, and fills
them up using the main currency as base. No more manually updating exchange rates!

You can also view the history of currency rates fetched in the past, and see how they changed over
time.

![Nextcloud Auto Currency](/promo.png)

It will automatically run once a day by default and use your currency names to fetch the correct
rate.

Interval for fetch may be adjusted in the admin settings "Auto Currency" section.

> **Note**: This is a companion app to [Cospend](https://apps.nextcloud.com/apps/cospend).
>
> Without Cospend, this app will not work.

## Preparing currency data

To make sure your currencies are found for the rates to be updated, please ensure your currencies
are named appropriately.

For both main and additional currencies, Currency names must contain **at least one of**:

- Currency symbol (`$`, `€`, `£`, etc.)
- Currency code (USD, EUR, GBP, etc.) - case insensitive

Using one of these will be enough to fetch the correct rate.

Example working names:

- ✅ `$`
- ✅ `USD`
- ✅ `$ USD`
- ❌ `US Dollar`
- ❌ `United States Dollar`

For a full list of available currencies, see [symbols.json](lib/Service/symbols.json), or refer to
the table in the app's settings screen.

## Installation

Download the app from [Nextcloud's App Store](https://apps.nextcloud.com/apps/autocurrency) through
your Nextcloud instance.

If you prefer to download manually, you can download the latest version from GitHub and install
directly:

1. Place this app in **nextcloud/apps/** or **nextcloud/custom_apps/**

2. Here is a quick installation script you can use as base. Modify the first variable lines to match
   your setup:

   ```bash
   pushd "/path/to/root/of/nextcloud/custom_apps"

   APPVER=$(curl -s https://api.github.com/repos/chenasraf/nextcloud-autocurrency/releases/latest | grep tag_name | grep -Eo 'v[^"]+') && \
   curl -L https://github.com/chenasraf/nextcloud-autocurrency/releases/download/${APPVER}/autocurrency-${APPVER}.tar.gz -o autocurrency.tar.gz && \
   tar xfv autocurrency.tar.gz && \
   rm -rf autocurrency.tar.gz
   ```

3. Then enable the app as you normally would from Nextcloud's Apps page.

## Contributing

I am developing this package on my free time, so any support, whether code, issues, or just stars is
very helpful to sustaining its life. If you are feeling incredibly generous and would like to donate
just a small amount to help sustain this project, I would be very very thankful!

<a href='https://ko-fi.com/casraf' target='_blank'>
  <img height='36' style='border:0px;height:36px;'
    src='https://cdn.ko-fi.com/cdn/kofi1.png?v=3'
    alt='Buy Me a Coffee at ko-fi.com' />
</a>

I welcome any issues or pull requests on GitHub. If you find a bug, or would like a new feature,
don't hesitate to open an appropriate issue and I will do my best to reply promptly.

## Development

### Automation

Most development processes are automated:

- **GitHub Actions** run tests, builds, and validations on each push or pull request.
- **Pre-commit formatting** is handled by [lint-staged](https://github.com/okonet/lint-staged),
  which automatically formats code before committing:

> 🛠️ The NPM package [husky](https://www.npmjs.com/package/husky) takes care of installing the
> pre-commit hook automatically after `pnpm install`.

---

### Manual Commands

While automation handles most workflows, the following commands are available for local development
and debugging:

#### Build the App

```bash
make
```

Installs dependencies and compiles frontend/backend assets.

#### Run Tests

```bash
make test
```

Runs unit and integration tests (if available).

#### Format & Lint

```bash
make format   # Auto-fix code style
make lint     # Check code quality
```

#### Generate OpenAPI Docs

```bash
make openapi
```

Output is saved to `build/openapi/openapi.json`.

#### Packaging for Release

```bash
make appstore    # Production build for Nextcloud app store
make source      # Full source package
make distclean   # Clean build artifacts and dependencies
```

#### Sign Releases

After uploading the archive to GitHub:

```bash
make sign
```

Downloads the `.tar.gz` release, verifies it, and prints a SHA-512 signature using your key at
`~/.nextcloud/certificates/autocurrency.key`.
