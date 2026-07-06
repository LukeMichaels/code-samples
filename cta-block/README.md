# Call to Action (A/B) block

A reusable WordPress call-to-action block that can run a weighted A/B test
across any number of variants.

I wrote this sample for my Alley application. It reproduces, as a self-contained
block, the pattern behind a reusable, A/B-ready CTA I built and maintained
across high-traffic campaigns in my most recent role, rewritten here without any
campaign-specific or proprietary code.

## What it does

An editor adds one or more variants to the block, each with a button label, a
destination URL, a style, and a relative weight, plus an optional experiment ID.
Every variant is rendered into the page, and each visitor is shown exactly one.

## The interesting part: staying cache-safe

On a heavily cached site, a page cache or CDN returns the same HTML to everyone.
That means you cannot choose the variant in PHP per request without either
breaking the cache or varying it by cookie, both of which are costly at scale.

So `render.php` prints every variant into the response and marks all but the
first as `hidden`. `view.js` then assigns one variant per visitor on the client,
stores the choice in a cookie so it stays sticky across visits, reveals the
assigned variant, and reports an exposure (and later a click) to whatever
analytics layer is present. With JavaScript off, the first variant stays visible
and fully works, so crawlers and no-JS visitors are never left with an empty
call to action.

## Why I am proud of it

- It solves a real constraint, caching versus per-visitor variation, simply,
  instead of reaching for cookie-varied caching or a heavyweight third-party
  testing script.
- Everything from the editor is sanitized on the way in and escaped on the way
  out.
- The field group is registered in code, so the block's configuration is
  versioned alongside the block rather than living only in the database.
- It degrades gracefully without JavaScript and honors `prefers-reduced-motion`.

## Files

- `lm-cta-block.php` registers the block, declares its ACF fields in code, and
  loads the front-end assets.
- `render.php` is the server-side template: every variant, fully escaped.
- `view.js` does the cache-safe weighted assignment, sticky cookie, and
  vendor-agnostic analytics events.
- `style.css` handles variant visibility, focus-visible styling, and a
  reduced-motion-safe entrance animation.

## Notes

Requires WordPress and Advanced Custom Fields, so the editor UI is ACF's field
group and there is no custom editor JavaScript to read through. In production I
would add PHPUnit coverage for the variant normalization in `render.php` and a
unit test for the `weightedChoice` distribution in `view.js`.
