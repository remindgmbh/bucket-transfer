# Bucket Transfer

[travis-img]: https://img.shields.io/travis/remindgmbh/bucket-transfer.svg?style=flat-square
[codecov-img]: https://img.shields.io/codecov/c/github/remindgmbh/bucket-transfer.svg?style=flat-square
[php-v-img]: https://img.shields.io/packagist/php-v/remind/bucket-transfer?style=flat-square
[github-issues-img]: https://img.shields.io/github/issues/remindgmbh/bucket-transfer.svg?style=flat-square
[contrib-welcome-img]: https://img.shields.io/badge/contributions-welcome-blue.svg?style=flat-square
[license-img]: https://img.shields.io/github/license/remindgmbh/bucket-transfer.svg?style=flat-square
[styleci-img]: https://styleci.io/repos/344791945/shield

[![travis-img]](https://travis-ci.com/github/remindgmbh/bucket-transfer)
[![codecov-img]](https://codecov.io/gh/remindgmbh/bucket-transfer)
[![styleci-img]](https://github.styleci.io/repos/344791945)
[![php-v-img]](https://packagist.org/packages/remind/bucket-transfer)
[![github-issues-img]](https://github.com/remindgmbh/bucket-transfer/issues)
[![contrib-welcome-img]](https://github.com/remindgmbh/bucket-transfer/blob/master/CONTRIBUTING.md)
[![license-img]](https://github.com/remindgmbh/bucket-transfer/blob/master/LICENSE)

Lets you transfer local files to an Amazon S3 Bucket.

--------------------------------------------------------------------------------

## Installation

```shell
# Either git clone
git clone https://github.com/remindgmbh/bucket-transfer.git

# or use composer
composer.phar create-project remind/bucket-transfer

# Enter the project directory you just created
cd bucket-transfer

# Create a local version of the config params
cp .env .env.local

# Edit file and set the parameters to your values
vim .env.local
```

--------------------------------------------------------------------------------

## Usage

```shell
# Show help for the run command
bin/buckettransfer help run

# Run the transfer with the given parameters
bin/buckettransfer run --local-path /path/to/dir --remote-path folder

# If you want more verbose information you can run the command with -v or -vv
bin/buckettransfer run --local-path /path/to/dir --remote-path folder -vv
```

--------------------------------------------------------------------------------

## Contribute

For contributing please read the [CONTRIBUTING.md](CONTRIBUTING.md) file.

--------------------------------------------------------------------------------

## Versioning

We use [SemVer](http://semver.org/) for versioning.
For the versions available, see the [tags on this repository](https://github.com/remindgmbh/bucket-transfer/tags).

--------------------------------------------------------------------------------

## License

This project is licensed under the GPL-3.0-or-later - see the [LICENSE.md](LICENSE.md) file for details

--------------------------------------------------------------------------------

## Authors

- Hauke Schulz - <h.schulz@remind.de>
