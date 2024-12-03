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

## Preparing currency data

To make this work, a 3-letter currency code must be in the name of the currency.

Currencies of different code lengths are not currently supported (but are planned).

The currency code will be fetched using the first 3-uppercase-letter occurrence in the name defined
on Cospend.

For example, to properly set USD as currency, set the name to one of (but not limited to):

- USD
- $ USD
- USD $
- United States Dollars (USD)

Will all be considered "USD" for conversion purposes.

This rule applies to **main** and **additional currencies**.

To see the full list of available currencies, visit
[this page](https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies.json) and
refer to the keys of the JSON response (not the values).

## Installation

Place this app in **nextcloud/apps/** or **nextcloud/custom_apps/**

> App Store link coming once app has been a bit more matured.

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
