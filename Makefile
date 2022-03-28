.PHONY: install lint test

install:
	docker exec takeatoll_web_1 /bin/sh -c "composer install"

lint:
	docker exec takeatoll_web_1 /bin/sh -c "composer run-script lint"

test:
	docker exec takeatoll_web_1 /bin/sh -c "composer run-script test"
