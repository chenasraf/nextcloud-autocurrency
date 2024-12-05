# SPDX-FileCopyrightText: Bernhard Posselt <dev@bernhard-posselt.com>
# SPDX-License-Identifier: AGPL-3.0-or-later

# Generic Makefile for building and packaging a Nextcloud app which uses pnpm and
# Composer.
#
# Dependencies:
# * make
# * which
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive
# * pnpm: for building and testing everything JS
#
# If no composer.json is in the app root directory, the Composer step
# will be skipped. The same goes for the package.json which can be located in
# the app root or the js/ directory.
#
# The pnpm command by launches the pnpm build script:
#
#    pnpm build
#
# The pnpm test command launches the pnpm test script:
#
#    pnpm test
#
# The idea behind this is to be completely testing and build tool agnostic. All
# build tools and additional package managers should be installed locally in
# your project, since this won't pollute people's global namespace.
#
# The following pnpm scripts in your package.json install and update the bower
# and pnpm dependencies and use gulp as build system (notice how everything is
# run from the node_modules folder):
#
#    "scripts": {
#        "test": "node node_modules/gulp-cli/bin/gulp.js karma",
#        "prebuild": "pnpm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
#        "build": "node node_modules/gulp-cli/bin/gulp.js"
#    },

app_name=autocurrency
dir_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
pnpm=$(shell which pnpm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)
ifeq ($(shell uname),Darwin)
	tar=gtar
else
	tar=tar
endif

all: build

# Fetches the PHP and JS dependencies and compiles the JS. If no composer.json
# is present, the composer step is skipped, if no package.json or js/package.json
# is present, the pnpm step is skipped
.PHONY: build
build:
	make deps
	make composer
	make pnpm

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
else
	composer install --prefer-dist
endif

# Installs pnpm dependencies
.PHONY: pnpm
pnpm:
	pnpm install --frozen-lockfile
ifeq (,$(wildcard $(CURDIR)/package.json))
	cd js && $(pnpm) build
else
	pnpm build
endif

.PHONY: deps
deps:
	@echo "Checking dependencies"
# on mac, install gnu-tar via brew
ifeq ($(shell uname),Darwin)
	@which gtar; \
	if [ $$? -ne 0 ]; then \
		brew install gnu-tar; \
	else \
		echo "gtar already installed"; \
	fi
else
	@which tar; \
	if [ $$? -ne 0 ]; then \
		echo "Please install tar"; \
		exit 1; \
	else \
		echo "tar already installed"; \
	fi
endif

# Removes the appstore build
.PHONY: clean
clean:
	rm -rf ./build

# Same as clean but also removes dependencies installed by composer, bower and
# pnpm
.PHONY: distclean
distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js/vendor
	rm -rf js/node_modules

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

# Builds the source package
.PHONY: source
source:
	rm -rf $(source_build_directory)
	mkdir -p $(source_build_directory)
	rm -rf $(appstore_package_name).tar.gz
	$(tar) czf $(source_package_name).tar.gz \
		--transform "s|^../$(dir_name)|$(app_name)|" \
		--exclude="**/.git/**/*" \
		--exclude="../$(dir_name)/build" \
		--exclude="../$(dir_name)/tests" \
		--exclude="../$(dir_name)/src" \
		--exclude="../$(dir_name)/js/node_modules" \
		--exclude="../$(dir_name)/node_modules" \
		--exclude="../$(dir_name)/*.log" \
		--exclude="../$(dir_name)/js/*.log" \
		--exclude="../$(source_build_directory)" \
		../$(dir_name)

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	rm -rf $(appstore_package_name).tar.gz
	$(tar) czf $(appstore_package_name).tar.gz \
		--transform "s|^../$(dir_name)|$(app_name)|" \
		--exclude="**/.git/**/*" \
		--exclude="../$(dir_name)/build" \
		--exclude="../$(dir_name)/tests" \
		--exclude="../$(dir_name)/Makefile" \
		--exclude="../$(dir_name)/*.log" \
		--exclude="../$(dir_name)/phpunit*xml" \
		--exclude="../$(dir_name)/composer.*" \
		--exclude="../$(dir_name)/js/node_modules" \
		--exclude="../$(dir_name)/js/tests" \
		--exclude="../$(dir_name)/js/test" \
		--exclude="../$(dir_name)/js/*.log" \
		--exclude="../$(dir_name)/js/package.json" \
		--exclude="../$(dir_name)/js/bower.json" \
		--exclude="../$(dir_name)/js/karma.*" \
		--exclude="../$(dir_name)/js/protractor.*" \
		--exclude="../$(dir_name)/package.json" \
		--exclude="../$(dir_name)/bower.json" \
		--exclude="../$(dir_name)/karma.*" \
		--exclude="../$(dir_name)/protractor\.*" \
		--exclude="../$(dir_name)/.*" \
		--exclude="../$(dir_name)/js/.*" \
		--exclude="../$(dir_name)/src" \
		--exclude="../$(source_build_directory)" \
		--exclude="../$(appstore_build_directory)" \
		../$(dir_name)

.PHONY: test
test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c tests/phpunit.xml
	( test ! -f tests/phpunit.integration.xml ) || $(CURDIR)/vendor/phpunit/phpunit/phpunit -c tests/phpunit.integration.xml

.PHONY: lint
lint:
	pnpm lint
	build/tools/composer.phar run lint

.PHONY: format
format:
	pnpm format
	PHP_CS_FIXER_IGNORE_ENV=true build/tools/composer.phar run cs:fix

.PHONY: sign
sign:
	VERSION="$$(cat version.txt)"; \
	TMPF="$$(mktemp)"; \
	curl -L https://github.com/chenasraf/nextcloud-autocurrency/releases/download/v$${VERSION}/autocurrency.tar.gz -o $${TMPF} &&
	echo &&
	openssl dgst -sha512 -sign ~/.nextcloud/certificates/$(appname).key $${TMPF} | openssl base64
