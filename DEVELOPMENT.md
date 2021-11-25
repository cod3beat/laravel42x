## Development Guide

### Testing
First, please run this command to build docker image.
```sh
make docker-build
```

Then, install all dependencies
```sh
make composer-install
```

Last, run the tests
```sh
make composer-test
```

### Testing on PHPStorm
- Open Settings dialog
- Open ... on PHP > CLI Interpreter
- Add new interpreter (From docker ...)
- Choose Docker with image l42x:latest
- Back to Settings
- Open ... on PHP > Docker Container
- On Volume Bindings, set host to current path and `/usr/src/myapp` to container path

### Laravel App Setup
Change this value on your `composer.json` laravel app
```json
{
  "requires": {
    "laravel/framework": "dev-branch-name as 4.2.x-dev"
  },
  "repositories": [
    {
      "type": "path",
      "url": "/var/www/laravel42x",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then, run this command:

```sh
composer update laravel/framework --prefer-source -W
```

Add this volume binding to `docker-compose.yml`
```yaml
version: '3.9'
services:
  web:
    volumes:
      - "/path/to/laravel42x:/var/www/laravel42x"
```