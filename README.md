<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Auto Currency for Cospend

This NextCloud app automatically fetches currency information for your Cospend projects, and fills
them up using the main currency as base. No more manually updating exchange rates!

It will automatically run once a day and use your currency names to fetch the correct rate.

The name will be fetched using the first 3-uppercase-letter appearance in the name.

For example:

- USD
- $ USD
- USD $
- United States Dollars (USD)

Will all be considered "USD" for conversion purposes.

This rule applies to main and additional currencies.

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

This requires the following things to be present:

- make
- which
- tar: for building the archive
- curl: used if phpunit and composer are not installed to fetch them from the web
- npm: for building and testing everything JS, only required if a package.json is placed inside the
  **js/** folder

The make command will install or update Composer dependencies if a composer.json is present and also
**npm run build** if a package.json is present in the **js/** folder. The npm **build** script
should use local paths for build systems and package managers, so people that simply want to build
the app won't need to install npm libraries globally, e.g.:

**package.json**:

```json
"scripts": {
    "test": "node node_modules/gulp-cli/bin/gulp.js karma",
    "prebuild": "npm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
    "build": "node node_modules/gulp-cli/bin/gulp.js"
}
```

### Publish to App Store

First get an account for the [App Store](http://apps.nextcloud.com/) then run:

    make && make appstore

The archive is located in build/artifacts/appstore and can then be uploaded to the App Store.

### Running tests

You can use the provided Makefile to run all tests by using:

    make test

This will run the PHP unit and integration tests and if a package.json is present in the **js/**
folder will execute **npm run test**

Of course you can also install [PHPUnit](http://phpunit.de/getting-started.html) and use the
configurations directly:

    phpunit -c phpunit.xml

or:

    phpunit -c phpunit.integration.xml

for integration tests
