# SMS Providers for EspoCRM

An installable extension.


## Supported SMS Providers

* Twilio
* Spryng
* sms77
* smstools
* [SerwerSms](https://dev.serwersms.pl/en/https-api-v2/introduction)

## Setting up

1. Install the extension.
2. At Administration > SMS, select the needed SMS provider. Specify From Number (if needed). 
3. At Administration > Integration, open the needed SMS provider and entered required credentials.


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


## Configuring IDE

You need to set the following paths to be ignored in your IDE:

* `build`
* `site/build`
* `site/custom/Espo/Modules/SmsProviders`
* `site/tests/unit/Espo/Modules/SmsProviders`
* `site/tests/integration/Espo/Modules/SmsProviders`

## License

Change a license in `LICENSE` file. The current license is intended for scripts of this repository. It's not supposed to be used for code of your extension.
