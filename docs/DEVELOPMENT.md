# Contributing to the OneMedia as a Developer

Code contributions, bug reports, and feature requests are welcome! The following sections provide guidelines for contributing to this project, as well as information about development processes and testing.

## Table of Contents

- [Contributing to the OneMedia as a Developer](#contributing-to-the-onemedia-as-a-developer)
  - [Table of Contents](#table-of-contents)
  - [Directory Structure](#directory-structure)
  - [Local setup](#local-setup)
    - [Prerequisites](#prerequisites)
    - [Building OneMedia Packages](#building-onemedia-packages)
  - [Code Contributions (Pull Requests)](#code-contributions-pull-requests)
    - [Workflow](#workflow)
    - [Code Quality / Code Standards](#code-quality--code-standards)
      - [PHPCS (PHP CodeSniffer)](#phpcs-php-codesniffer)
      - [ESLint](#eslint)
      - [Run all linters](#run-all-linters)
  - [Changesets](#changesets)
  - [Releasing](#releasing)
    - [Release Commands](#release-commands)

## Directory Structure

<details>
<summary> Click to expand </summary>

```bash
.
├── assets
│   └── src
│       ├── admin
│       │   ├── media-sharing
│       │   │   ├── browser-uploader.js
│       │   │   ├── index.js
│       │   │   └── syncIcon.js
│       │   ├── plugin
│       │   │   └── index.js
│       │   └── settings
│       │       └── index.js
│       ├── components
│       │   ├── api.js
│       │   ├── brand-settings
│       │   │   └── BrandSiteSettings.js
│       │   ├── constants.js
│       │   └── governing-settings
│       │       ├── ShareMediaModal.js
│       │       ├── SiteModal.js
│       │       └── SiteTable.js
│       ├── css
│       │   ├── admin.scss
│       │   ├── editor.scss
│       │   ├── main.scss
│       │   └── media-taxonomy.scss
│       ├── images
│       │   ├── banner.png
│       │   ├── fallback-image.svg
│       │   └── logo.svg
│       └── js
│           ├── admin.js
│           ├── editor.js
│           ├── main.js
│           ├── media-frame.js
│           ├── media-sync-filter.js
│           └── utils.js
├── babel.config.js
├── bin
│   └── phpcbf.sh
├── composer.json
├── composer.lock
├── docs
│   ├── CODE_OF_CONDUCT.md
│   ├── CONTRIBUTING.md
│   ├── DEVELOPMENT.md
│   ├── INSTALLATION.md
│   └── SECURITY.md
├── inc
│   ├── classes
│   │   ├── admin
│   │   │   └── class-media-taxonomy.php
│   │   ├── brand-site
│   │   │   └── class-admin-hooks.php
│   │   ├── class-admin.php
│   │   ├── class-assets.php
│   │   ├── class-hooks.php
│   │   ├── class-plugin.php
│   │   ├── class-rest.php
│   │   ├── class-settings.php
│   │   ├── class-utils.php
│   │   ├── plugin-configs
│   │   │   ├── class-constants.php
│   │   │   └── class-secret-key.php
│   │   └── rest
│   │       ├── class-basic-options.php
│   │       └── class-media-sharing.php
│   ├── helpers
│   │   ├── custom-functions.php
│   │   └── custom-hooks.php
│   ├── templates
│   │   ├── brand-site
│   │   │   └── sync-status.php
│   │   ├── help
│   │   │   ├── best-practices.php
│   │   │   ├── how-to-share.php
│   │   │   ├── overview.php
│   │   │   └── sharing-modes.php
│   │   └── notices
│   │       └── no-build-assets.php
│   └── traits
│       └── trait-singleton.php
├── languages
│   └── OneMedia.pot
├── LICENSE
├── onemedia.php
├── package-lock.json
├── package.json
├── phpcs.xml.dist
├── README.md
├── readme.txt
├── uninstall.php
└── webpack.config.js
```

</details>

## Local setup

To set up locally, clone the repository into plugins directory of your WordPress installation:

### Prerequisites

- [Node.js](https://nodejs.org/) v20+
- npm or yarn
- PHP (recommended: 7.4+)
- Composer
- WordPress (recommended: 6.8+) (local install)

### Building OneMedia Packages

Install dependencies:

```bash
  # Navigate to the plugin directory
  composer install
  npm install
```

Start the development build process:

```bash
  npm start
```

Create a production-ready build:

```bash
  npm run build:prod
```

## Code Contributions (Pull Requests)

### Workflow

The `develop` branch is used for active development, while `main` contains the current stable release. Always create a new branch from `develop` when working on a new feature or bug fix.

Branches should be prefixed with the type of change (e.g. `feat`, `chore`, `tests`, `fix`, etc.) followed by a short description of the change. For example, a branch for a new feature called "Add new feature" could be named `feat/add-new-feature`.

### Code Quality / Code Standards

This project uses several tools to ensure code quality and standards are maintained:

#### PHPCS (PHP CodeSniffer)

This project uses [PHP CodeSniffer (PHPCS)](https://github.com/squizlabs/PHP_CodeSniffer) to ensure that the PHP code adheres to a set of coding standards.

You can run PHPCS using the following command:

```bash
  npm run lint:php
```

PHPCS can automatically fix some issues. To fix issues automatically, run:

```bash
  npm run lint:php:fix
```

#### ESLint

This project uses [ESLint](https://eslint.org), which is a tool for identifying and reporting on patterns found in ECMAScript/JavaScript code.

You can run ESLint using the following command:

```bash
  npm run lint:js
  npm run lint:css
```

ESLint can automatically fix some issues. To fix issues automatically, run:

```bash
  npm run lint:js:fix
  npm run lint:css:fix
```

#### Run all linters

To run all linters (PHP, JS, CSS) at once, use the following command:

```bash
  npm run lint
```

Automatic fixes for all linters can be applied using:

```bash
  npm run lint:fix
```

## Changesets

Please check the [changeset documentation](../.changeset/README.md) file for details on how to create and manage changesets.

## Releasing

1. Ensure all changes are committed and tested.
2. Update changelogs and version numbers.
3. Merge to main branch.
4. Tag release and push to remote.
5. Publish packages if needed.

### Release Commands

Command to create a tag and push it:

```bash
git tag -a vx.x.x -m "Release vx.x.x"
git push --tags
```

Command to delete the tag (Locally) incase wanted to release same tag:

```bash
git tag --delete vx.x.x
```

Release will be auto generated and kept in draft once pushed a tag.
