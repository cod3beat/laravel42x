docker-build:
	docker build -t l42x .

composer-install:
	docker run --rm --volume "${PWD}:/usr/src/myapp" -w /usr/src/myapp -it l42x composer install

composer-test:
	docker run --rm --volume ${PWD}:/usr/src/myapp -w /usr/src/myapp -it l42x composer test