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
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
app_intermediate_directory=$(CURDIR)/build/artifacts/intermediate/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
pnpm=$(shell which pnpm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

all: build

# Fetches the PHP and JS dependencies and compiles the JS. If no composer.json
# is present, the composer step is skipped, if no package.json or js/package.json
# is present, the pnpm step is skipped
.PHONY: build
build:
ifneq (,$(wildcard $(CURDIR)/composer.json))
	make composer
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	make pnpm
endif
ifneq (,$(wildcard $(CURDIR)/js/package.json))
	make pnpm
endif

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
	tar czf $(source_package_name).tar.gz \
		--exclude="**/.git/**/*" \
		--exclude="../$(app_name)/build" \
		--exclude="../$(app_name)/tests" \
		--exclude="../$(app_name)/src" \
		--exclude="../$(app_name)/js/node_modules" \
		--exclude="../$(app_name)/node_modules" \
		--exclude="../$(app_name)/*.log" \
		--exclude="../$(app_name)/js/*.log" \
		--exclude="../$(source_build_directory)" \
		../$(app_name)

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(app_intermediate_directory)
	mkdir -p $(appstore_build_directory)
	rm -rf $(appstore_package_name).tar.gz
	rsync -vtr \
		--exclude="**/.git/**/*" \
		--exclude="**/.github/**/*" \
		--exclude="build" \
		--exclude="tests" \
		--exclude="Makefile" \
		--exclude="*.log" \
		--exclude="phpunit*xml" \
		--exclude="composer.*" \
		--exclude="node_modules" \
		--exclude="js/node_modules" \
		--exclude="js/tests" \
		--exclude="js/test" \
		--exclude="js/*.log" \
		--exclude="js/package.json" \
		--exclude="js/bower.json" \
		--exclude="js/karma.*" \
		--exclude="js/protractor.*" \
		--exclude="package.json" \
		--exclude="bower.json" \
		--exclude="karma.*" \
		--exclude="protractor\.*" \
		--exclude=".*" \
		--exclude="js/.*" \
		--exclude="src" \
		$(CURDIR)/ $(app_intermediate_directory)
	cd $(app_intermediate_directory) && \
	tar czf $(appstore_package_name).tar.gz ../$(app_name)

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
	echo "\x1b[33mSigning version $${VERSION}\x1b[0m"; \
	echo "\x1b[33mDownloading archive...\x1b[0m"; \
	curl -L https://github.com/chenasraf/nextcloud-autocurrency/releases/download/v$${VERSION}/autocurrency-v$${VERSION}.tar.gz -o $${TMPF}; \
	echo "\x1b[33mSigning with key $$(app_name).key\x1b[0m"; \
	echo; \
	echo "\x1b[32mDownload URL:\x1b[0m https://github.com/chenasraf/nextcloud-autocurrency/releases/download/v$${VERSION}/autocurrency-v$${VERSION}.tar.gz"; \
	echo "\x1b[32mSignature:\x1b[0m"; \
	openssl dgst -sha512 -sign ~/.nextcloud/certificates/$(app_name).key $${TMPF} | openssl base64; \
	rm -rf $${TMPF}
