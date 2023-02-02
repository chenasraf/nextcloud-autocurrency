<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Auto Currency

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