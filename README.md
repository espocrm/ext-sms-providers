# SMS Providers for EspoCRM

An installable extension.


## Supported SMS Providers

* Twillio


## Configuration

Create `config.json` file in the root directory. You can copy `config-default.json` and rename it to `config.json`.

When reading, this config will be merged with `config-default.json`. You can override default parameters in the created config.

Parameters:

* espocrm.repository - from what repository to fetch EspoCRM;
* espocrm.branch - what branch to fetch (`stable` is set by default); you can specify version number instead (e.g. `5.9.2`);
* database - credentials of the dev database;
* install.siteUrl - site url of the dev instance;
* install.defaultOwner - a webserver owner (important to be set right);
* install.defaultGroup - a webserver group (important to be set right).


## Config for EspoCRM instance

You can override EspoCRM config. Create `config.php` in the root directory of the repository. This file will be applied after EspoCRM intallation (when building).

Example:

```php
<?php
return [
    'useCacheInDeveloperMode' => true,
];
```

## Building

After building, EspoCRM instance with installed extension will be available at `site` directory. You will be able to access it with credentials:

* Username: admin
* Password: 1

### Preparation

1. You need to have *node*, *npm*, *composer* installed.
2. Run `npm install`.
3. Create a database. The database name is set in the config file.

### Full EspoCRM instance building

It will download EspoCRM (from the repository specified in the config), then build and install it. Then it will install the extension.

Command:

```
node build --all
```

Note: It will remove a previously installed EspoCRM instance, but keep the database intact.

### Copying extension files to EspoCRM instance

You need to run this command every time you make changes in `src` directory and you want to try these changes on Espo instance.

Command:

```
node build --copy
```

### Running after-install script

AfterInstall.php will be applied for EspoCRM instance.

Command:

```
node build --after-install
```

### Extension package building

Command:

```
node build --extension
```

The package will be created in `build` directory.

Note: The version number is taken from `package.json`.

## Development workflow

1. Do development in `src` dir.
2. Run `node build --copy`.
3. Test changes in EspoCRM instance at `site` dir.

## Versioning

The version number is stored in `package.json` and `package-lock.json`.

Bumping version:

```
npm version patch
npm version minor
npm version major
```

## Tests

Prepare:

1. `node build --copy`
2. `cd site`
3. `grunt test`

### Unit

Command to run unit tests:

```
vendor/bin/phpunit tests/unit/Espo/Modules/SmsProviders
```

### Integration

You need to create a config file `tests/integration/config.php`:

```php
<?php

return [
    'database' => [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'charset' => 'utf8mb4',
        'dbname' => 'TEST_DB_NAME',
        'user' => 'YOUR_DB_USER',
        'password' => 'YOUR_DB_PASSWORD',
    ],
];
```
The file should exist before you run `node build --copy`.

Command to run integration tests:

```
vendor/bin/phpunit tests/integration/Espo/Modules/SmsProviders
```

## Configuring IDE

You need to set the following paths to be ignored in your IDE:

* `build`
* `site/build`
* `site/custom/Espo/Modules/SmsProviders`
* `site/tests/unit/Espo/Modules/SmsProviders`
* `site/tests/integration/Espo/Modules/SmsProviders`

## License

Change a license in `LICENSE` file. The current license is intended for scripts of this repository. It's not supposed to be used for code of your extension.
