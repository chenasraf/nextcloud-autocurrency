<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Auto Currency for Cospend

This NextCloud app automatically fetches currency information for your Cospend projects, and fills
them up using the main currency as base. No more manually updating exchange rates!

It will automatically run once a day by default and use your currency names to fetch the correct
rate.

Interval for fetch may be adjusted in the admin settings "Auto Currency" section.

**Note**: This is a companion app to Cospend. Without Cospend, this app will not work.

## Preparing currency data

For both main and additional currencies, you must include in the currency name, one of the
following:

- Currency symbol (`$`, `€`, `£`, etc.)
- Currency code (USD, EUR, GBP, etc.) - case insensitive

Using one of these will be enough to fetch the correct rate.

For a full list of available currencies, see [symbols.json](lib/Service/symbols.json).

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

### Building the app

The app can be built by using the provided Makefile by running:

    make

### Running tests

You can use the provided Makefile to run all tests by using:

    make test
