# SilverStripe Model Annotations Task

This module adds a dev task which generates annotations for your data object models. The problem is if you are working a lot with silver stripe models you will learn that no IDE can handle the DB fields as properties and/or collections as methods.

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Usage](#usage)
   1. [Web](#web)
   2. [Terminal](#terminal)
   3. [Optional params](#optional-params)
4. [Options](#options)
5. [Example](#example)
6. [Documentation](#documentation)
7. [License](#license)
8. [Maintainers](#maintainers)
9. [Bugtracker](#bugtracker)
10. [Development and contribution](#development-and-contribution)

## Requirements

* PHP 7.3 - PHP 8.0
* PHP-AST extension
* SilverStripe ^4.10

## Installation

```
composer require --dev csoellinger/silverstripe-model-annotations-task
```

## Usage

Run dev build to load the task and after you can execute it in your browser or cli.

### Web

```
http://localhost/tasks/ModelAnnotationsTask
```

### Terminal

```bash
vendor/bin/sake dev/tasks/ModelAnnotationsTask
```

### Optional params
* dryRun=0/1
* quiet=0/1
* createBackupFile=0/1
* addUseStatements=0/1

Take a look at options section below to get more information about the params.

## Options

All optional params from above can be set as silverstripe config at the ModelAnnotationsTask. Only difference is that the config is set as boolean var and not as integer. By default dryRun is set to true. If you want write the files with this task you need to set the config or set the param to false.

### dryRun (default: true)

Only print the changes inside your browser or terminal and don't write any file.

### quiet (default: false)

No output.

### createBackupFile (default: false)

Create a backup file before writing the model. Only if dryRun is false.

### addUseStatements (default: false)

Collect data types which are not declared as use statement and add them to the file. If this config is true it also shortens the data types.

## Example

Here you see a small example how it will look your model file after using the task.

Your Input
```php
<?php

class Player extends DataObject
{
    private static $db = [
        'Name' => 'Varchar(255)',
    ];
    private static $has_many = [
        'Jobs' => 'Job',
    ];
}
```

Task would write this file:
```php
<?php

/**
 * @property string $Name Name...
 *
 * @method HasManyList Jobs() Get jobs
 */
class Player extends DataObject
{
    private static $db = [
        'Name' => 'Varchar(255)',
    ];
    private static $has_many = [
        'Jobs' => 'Job',
    ];
}
```

## Documentation
 * [Documentation readme](docs/en/index.md)

## License
See [License](LICENSE.md)

## Maintainers
 * CSoellinger <christopher.soellinger@gmail.com>

## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over
existing issues to ensure yours is unique.

If the issue does look like a new bug:

 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version,
 Operating System, any installed SilverStripe modules.

Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.

## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
