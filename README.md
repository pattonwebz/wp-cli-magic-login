# wp-cli-magic-login

Generate expiring, single-use magic login links for local WordPress testing,
via WP-CLI. Built for agent-driven browser testing (Claude Code +
claude-in-chrome and similar) so logging in as a user never means resetting
their real password.

Inspired by [`aaemnnosttv/wp-cli-login-server`](https://github.com/aaemnnosttv/wp-cli-login-server),
with a few deliberate differences:

- **One package, not two.** No separate "server" plugin to find/install/activate
  — the WP-CLI command installs its own tiny mu-plugin automatically on first
  use, into `wp-content/mu-plugins/`, which WordPress always loads regardless
  of the normal plugins screen.
- **Transient-backed tokens**, not a custom database table — nothing to
  migrate or clean up beyond WordPress's own transient expiry.
- **Self-updating mu-plugin.** Every `create` call re-copies the bundled
  mu-plugin if it's missing or stale (md5 check), so upgrading the composer
  package propagates to already-set-up sites without a manual reinstall step.

## Install

Published on [Packagist](https://packagist.org/packages/pattonwebz/wp-cli-magic-login).

### Option A — as a global WP-CLI package (recommended)

```bash
wp package install pattonwebz/wp-cli-magic-login
```

That's it — `wp magic-login` is now available on every site this WP-CLI
install touches. No plugin activation step needed; the mu-plugin installs
itself into `wp-content/mu-plugins/` the first time you run
`wp magic-login create`.

To update later:

```bash
wp package update
```

To remove it:

```bash
wp package uninstall pattonwebz/wp-cli-magic-login
```

### Option B — per-project, via Composer

```bash
composer require --dev pattonwebz/wp-cli-magic-login
```

Then tell WP-CLI to load it, either via `wp-cli.yml`:

```yaml
require:
  - vendor/pattonwebz/wp-cli-magic-login/command.php
```

or per-invocation:

```bash
wp --require=vendor/pattonwebz/wp-cli-magic-login/command.php magic-login create admin
```

## Usage

```bash
# Log in as admin, land on the dashboard, link expires in 5 minutes.
wp magic-login create admin

# Land on a specific admin screen, short-lived link, script-friendly output.
wp magic-login create qa_admin \
  --redirect=/wp-admin/edit.php?post_type=edbs_meeting \
  --expires=60 \
  --porcelain

# Revoke all outstanding unused links.
wp magic-login invalidate
```

`create` accepts a user ID, `user_login`, or email address.

Each link works exactly once: visiting it logs the matched user in and
deletes the token immediately, whether or not the login succeeds, so a
leaked or guessed URL can't be retried.

## Uninstall

Delete `wp-content/mu-plugins/wp-cli-magic-login.php` and remove the composer
package. Nothing else is written to the database besides the transients
(which expire on their own).
