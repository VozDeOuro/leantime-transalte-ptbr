VERSION := $(shell grep "appVersion" ./app/Core/AppSettings.php |awk -F' = ' '{print substr($$2,2,length($$2)-3)}')
TARGET_DIR:= ./target/leantime
DOCS_DIR:= ./builddocs
DOCS_REPO:= git@github.com:Leantime/docs.git
RUNNING_DOCKER_CONTAINERS:= $(shell docker ps -a -q)
RUNNING_DOCKER_VOLUMES:= $(shell docker volume ls -q)

install-deps-dev:
	npm install --only=dev
	composer install --optimize-autoloader

install-deps:
	npm install
	composer install --no-dev --optimize-autoloader

build: install-deps
	npx mix --production

build-dev: install-deps-dev
	npx mix --production

package: clean build
	mkdir -p $(TARGET_DIR)
	cp -R ./app $(TARGET_DIR)
	cp -R ./bin $(TARGET_DIR)
	mkdir -p $(TARGET_DIR)/config
	cp ./config/configuration.sample.php $(TARGET_DIR)/config
	cp ./config/sample.env $(TARGET_DIR)/config
	mkdir -p $(TARGET_DIR)/logs
	mkdir -p $(TARGET_DIR)/cache
	mkdir -p $(TARGET_DIR)/cache/avatars
	mkdir -p $(TARGET_DIR)/cache/views
	touch $(TARGET_DIR)/logs/.gitkeep
	cp -R ./public $(TARGET_DIR)
	rm -rf $(TARGET_DIR)/public/assets
	mkdir -p $(TARGET_DIR)/userfiles
	touch   $(TARGET_DIR)/userfiles/.gitkeep
	cp -R ./vendor $(TARGET_DIR)
	cp  ./.htaccess $(TARGET_DIR)
	cp  ./LICENSE $(TARGET_DIR)
	cp  ./nginx*.conf $(TARGET_DIR)
	cp  ./web.config.sample $(TARGET_DIR)
	cp  ./updateLeantime.sh $(TARGET_DIR)

	rm -f $(TARGET_DIR)/config/configuration.php
	#Remove font for QR code generator (not needed if no label is used)
	rm -f $(TARGET_DIR)/vendor/endroid/qr-code/assets/fonts/noto_sans.otf

	#Remove DeepL.com and mltranslate engine (not needed in production)
	rm -rf $(TARGET_DIR)/vendor/mpdf/mpdf/ttfonts
	rm -rf $(TARGET_DIR)/vendor/lasserafn/php-initial-avatar-generator/src/fonts
	rm -rf $(TARGET_DIR)/vendor/lasserafn/php-initial-avatar-generator/tests/fonts

	#Remove local configuration, if any
	rm -rf $(TARGET_DIR)/custom/*/*
	rm -rf $(TARGET_DIR)/public/theme/*/css/custom.css

	#Remove userfiles
	rm -rf $(TARGET_DIR)/userfiles/*
	rm -rf $(TARGET_DIR)/public/userfiles/*

	#Removing unneeded items for release
	rm -rf $(TARGET_DIR)/public/dist/images/Screenshots

	#removing js directories
	find  $(TARGET_DIR)/app/domain/ -depth -maxdepth 2 -name "js" -exec rm -rf {} \;

	#removing uncompiled js files
	find $(TARGET_DIR)/public/dist/js/ -depth -mindepth 1 ! -name "*compiled*" -exec rm -rf {} \;

	#create zip files
	cd target && zip -r -X "Leantime-v$(VERSION)$$1.zip" leantime
	cd target && tar -zcvf "Leantime-v$(VERSION)$$1.tar.gz" leantime

gendocs: # Requires github CLI (brew install gh)
	# Delete the temporary docs directory if exists
	rm -rf $(DOCS_DIR)

	# Make a temporary directory for docs
	mkdir -p $(DOCS_DIR)

	# Clone the docs
	git clone $(DOCS_REPO) $(DOCS_DIR)

	# Generate the docs
	phpDocumentor
	vendor/bin/leantime-documentor parse app --format=markdown --template=templates/markdown.php --output=builddocs/technical/hooks.md --memory-limit=-1

	# create pull request
	cd $(DOCS_DIR) && git switch -c "release/$(VERSION)
	cd $(DOCS_DIR) && git add -A
	cd $(DOCS_DIR) && git commit -m "Generated docs release $(VERSION)
	cd $(DOCS_DIR) && git push --set-upstream origin "release/$(VERSION)
	cd $(DOCS_DIR) && gh pr create --title "release/$(VERSION) --body ""

	# Delete the temporary docs directory
	rm -rf $(DOCS_DIR)


clean:
	rm -rf $(TARGET_DIR)

run-dev: build-dev
	cd .dev && docker-compose up --build --remove-orphans

Acceptance-test: build-dev
	php vendor/bin/codecept run Acceptance --steps

Acceptance-test-ci: build-dev
	php vendor/bin/codecept build
ifeq ($(strip $(RUNNING_DOCKER_CONTAINERS)),)
	@echo "No running docker containers found"
else
	docker rm -f $(RUNNING_DOCKER_CONTAINERS)
endif
ifeq ($(strip $(RUNNING_DOCKER_VOLUMES)),)
	@echo "No running docker volumes found"
else
	docker volume rm $(RUNNING_DOCKER_VOLUMES)
endif
	php vendor/bin/codecept run Acceptance --steps

.PHONY: install-deps build package clean run-dev

