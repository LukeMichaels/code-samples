# Campaign Action Migration Command

A WP-CLI command that migrates legacy campaign action records, petition and action signups from an old platform, into WordPress as a custom post type.

I wrote this sample for my Alley application. My production WordPress migration work sits in employers' private repos I no longer have access to, so this reproduces the pattern behind that work: importing a large, messy CSV export into WordPress in a way that survives being interrupted and can be safely re-run, without any client-specific or proprietary code.

## What it does

Given a CSV export and a campaign_action post type already registered, the command validates the file's columns, then reads and imports each row: title, status, campaign slug, and signature count become post fields, meta, and a taxonomy term. A progress bar tracks the run, and a summary at the end reports how many rows were created, updated, or failed.

## The interesting part: safe to run twice

Migrations at this scale rarely go perfectly on the first attempt. A network drop, a bad row, or someone kicking off the same import twice by accident are all normal, so the command is built around one rule: re-running it against the same file should never create duplicates.

Each row carries a legacy ID, stored in post meta rather than derived from the title or slug, since titles get edited after import and legacy IDs don't. Every import looks up that legacy ID first and updates the matching post if one exists, so the same file can run today, get interrupted, and run again tomorrow with the same result. A --dry-run flag validates and reports without writing anything, and by default a bad row logs a warning and lets the rest of the file keep processing rather than aborting a ten-thousand-row import over one malformed line.

## Why I am proud of it

- It treats resumability as the actual design constraint, not an afterthought bolted on after something failed midway through a real import.
- Row-level failures are isolated and reported individually, so partial success is visible instead of an all-or-nothing outcome.
- Term counting and cache invalidation are deferred for the duration of the run and restored afterward, which matters once the file is large enough to be worth optimizing for.
- --dry-run makes it possible to validate an unfamiliar export before committing to it.

## Files

- 'class-campaign-action-migration-command.php' registers the wp campaign-migrate import-actions command and contains the full import, matching, and reporting logic.

## Notes

Requires WP-CLI and a campaign_action post type with a campaign taxonomy registered. In production I would add PHPUnit coverage for map_status()'s status mapping and an integration test asserting that a second run against an already-imported file produces zero new posts.
