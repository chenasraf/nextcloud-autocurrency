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
repo_path=chenasraf/nextcloud-$(app_name)
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_intermediate_directory=$(CURDIR)/build/artifacts/intermediate-source
source_package_name=$(source_build_directory)/$(app_name)
app_intermediate_directory=$(CURDIR)/build/artifacts/intermediate/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
pnpm=$(shell which pnpm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)
composer_phar=build/tools/composer.phar

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
	mv composer.phar $(composer_phar)
endif
ifneq ("$(wildcard vendor)","")
	@echo "Vendor directory already exists, skipping composer install"
else
	@echo "Installing composer dependencies..."
	$(if $(composer),$(composer),php $(composer_phar)) install --prefer-dist
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
	rsync -vtr \
		--exclude="**/.git/**/*" \
		--exclude="build" \
		--exclude="tests" \
		--exclude="src" \
		--exclude="js/node_modules" \
		--exclude="node_modules" \
		--exclude="*.log" \
		--exclude="dist/js/*.log" \
		$(CURDIR)/ $(source_intermediate_directory)
	cd $(source_intermediate_directory) && \
	tar czf $(source_package_name).tar.gz ../$(app_name)

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
		--exclude="dist/js/node_modules" \
		--exclude="dist/js/tests" \
		--exclude="dist/js/test" \
		--exclude="dist/js/*.log" \
		--exclude="dist/js/package.json" \
		--exclude="dist/js/bower.json" \
		--exclude="dist/js/karma.*" \
		--exclude="dist/js/protractor.*" \
		--exclude="package.json" \
		--exclude="bower.json" \
		--exclude="karma.*" \
		--exclude="protractor\.*" \
		--exclude=".*" \
		--exclude="dist/js/.*" \
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
	$(composer_phar) run lint

.PHONY: php-cs-fixer
php-cs-fixer:
	@echo "\x1b[33mFixing PHP files...\x1b[0m"
	@FILES=$$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$$' | grep -v '^gen/'); \
	if [ -z "$$FILES" ]; then \
		echo "No PHP files staged."; \
	else \
		echo "Running CS fixer on:" $$FILES; \
		php -l $$FILES || exit 1; \
		PHP_CS_FIXER_IGNORE_ENV=true php vendor-bin/cs-fixer/vendor/php-cs-fixer/shim/php-cs-fixer.phar --config=.php-cs-fixer.dist.php fix $$FILES || exit 1; \
	fi

.PHONY: format
format:
	pnpm format
	PHP_CS_FIXER_IGNORE_ENV=true $(composer_phar) run cs:fix

.PHONY: openapi
openapi:
	@echo "\x1b[33mGenerating OpenAPI documentation...\x1b[0m"
	$(if $(composer),$(composer),php $(composer_phar)) run openapi
	@echo "\x1b[32mOpenAPI documentation generated at build/openapi/openapi.json\x1b[0m"

.PHONY: sign
sign:
	@VERSION="$$(cat version.txt)"; \
	TMPF="$$(mktemp)"; \
	echo "\x1b[33mSigning version $${VERSION}\x1b[0m"; \
	echo "\x1b[33mDownloading archive...\x1b[0m"; \
	curl -L https://github.com/chenasraf/nextcloud-$(app_name)/releases/download/v$${VERSION}/$(app_name)-v$${VERSION}.tar.gz -o $${TMPF}; \
	FILESIZE=$$(stat -f%z "$${TMPF}" 2>/dev/null || stat -c%s "$${TMPF}"); \
	if [ "$${FILESIZE}" -lt 10240 ]; then \
		echo "\x1b[31mError: Downloaded file is too small (<10KB, actual: $${FILESIZE} bytes)\x1b[0m"; \
		rm -rf $${TMPF}; \
		exit 1; \
	fi; \
	echo "\x1b[33mSigning with key $(app_name).key\x1b[0m"; \
	echo; \
	echo "\x1b[32mDownload URL:\x1b[0m https://github.com/chenasraf/nextcloud-$(app_name)/releases/download/v$${VERSION}/$(app_name)-v$${VERSION}.tar.gz"; \
	echo "\x1b[32mSignature:\x1b[0m"; \
	openssl dgst -sha512 -sign ~/.nextcloud/certificates/$(app_name).key $${TMPF} | openssl base64; \
	rm -rf $${TMPF}

.PHONY: release
release:
	@VERSION="$$(cat version.txt)"; \
	if [ -z "$$NEXTCLOUD_API_TOKEN" ]; then \
		printf "\x1b[33mNEXTCLOUD_API_TOKEN not set. Enter token: \x1b[0m"; \
		read -r NEXTCLOUD_API_TOKEN; \
	fi; \
	if [ -n "$$NEXTCLOUD_API_TOKEN" ]; then \
		echo "\x1b[32m✅ Using provided NEXTCLOUD_API_TOKEN"; \
	else \
		echo "\x1b[31m❌ Error: NEXTCLOUD_API_TOKEN is missing"; \
	fi; \
	TMPF="$$(mktemp)"; \
	DOWNLOAD_URL="https://github.com/chenasraf/nextcloud-$(app_name)/releases/download/v$${VERSION}/$(app_name)-v$${VERSION}.tar.gz"; \
	echo "\x1b[33mDownloading archive for version $${VERSION}...\x1b[0m"; \
	curl -L "$${DOWNLOAD_URL}" -o "$${TMPF}"; \
	FILESIZE=$$(stat -f%z "$${TMPF}" 2>/dev/null || stat -c%s "$${TMPF}"); \
	if [ "$${FILESIZE}" -lt 10240 ]; then \
		echo "\x1b[31mError: Downloaded file is too small (<10KB, actual: $${FILESIZE} bytes)\x1b[0m"; \
		rm -f "$${TMPF}"; \
		exit 1; \
	fi; \
	echo "\x1b[33mSigning with key $(app_name).key\x1b[0m"; \
	echo; \
	SIGNATURE="$$(openssl dgst -sha512 -sign ~/.nextcloud/certificates/$(app_name).key "$${TMPF}" | openssl base64 | tr -d '\n')"; \
	rm -f "$${TMPF}"; \
	echo "\x1b[32mReleasing to Nextcloud App Store...\x1b[0m"; \
	curl -X POST \
	  -H "Authorization: Token $$NEXTCLOUD_API_TOKEN" \
	  -H "Content-Type: application/json" \
	  -d "{\"download\":\"$${DOWNLOAD_URL}\", \"signature\":\"$${SIGNATURE}\"}" \
	  https://apps.nextcloud.com/api/v1/apps/releases; \
	if [ $$? -ne 0 ]; then \
		echo "\x1b[31m❌ Error: Failed to release to Nextcloud App Store\x1b[0m"; \
		exit 1; \
	fi; \
	echo "\x1b[32m🎉 Release successful!\x1b[0m";

