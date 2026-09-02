<?php

/*
 * Points this clone's Git at the tracked .githooks directory.
 *
 * `core.hooksPath` is per clone and cannot be committed — Git deliberately
 * refuses to let a repository configure itself — so Composer runs this after
 * every install. It is PHP rather than a shell one-liner because the same
 * Composer scripts run on Windows in CI, where `|| true` is not a command.
 */

$root = dirname(__DIR__);

// A tarball install or a Docker build has no repository to configure.
if (! file_exists($root . '/.git')) {
    exit(0);
}

chdir($root);

exec('git config core.hooksPath .githooks 2>&1', $output, $status);

if ($status !== 0) {
    fwrite(STDERR, "Could not set core.hooksPath, so the pre-push hook will not run.\n");
    fwrite(STDERR, "Set it by hand with: git config core.hooksPath .githooks\n");
}
