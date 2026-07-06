# Accessible modal dialog

A small, dependency-free modal built on the native `<dialog>` element.

I wrote this for my Alley application. It is a cleaned-up version of the kind of
accessible modal I have built for client and campaign sites.

## The approach

I deliberately built on `<dialog>` and `showModal()` rather than hand-rolling a
modal out of a `<div>`. The platform already handles the parts that are easy to
get wrong and tedious to maintain by hand:

- moving focus into the dialog on open,
- trapping Tab and Shift+Tab inside it,
- closing on Escape,
- rendering in the top layer above all other content,
- returning focus to the trigger on close.

This controller adds only what the platform leaves to the author: opening from a
trigger, closing on a backdrop click, locking background scroll without a layout
shift, and animating open and close while respecting `prefers-reduced-motion`.

## Why I am proud of it

- It gets accessibility right by leaning on the platform instead of
  reimplementing focus management, which means less code to maintain and fewer
  edge-case bugs.
- The background scroll lock compensates for the scrollbar width, so the page
  behind the modal does not jump when it opens.
- The open and close animations fully collapse for visitors who prefer reduced
  motion.
- Usage is declarative: authors wire it up with data attributes and correct ARIA
  labelling, with no per-modal JavaScript.

## Files

- `modal.js` is the controller.
- `modal.css` covers styling, the scroll lock, and reduced-motion handling.
- `demo.html` is a working example you can open in a browser.

## Notes

Target support is current evergreen browsers, where `<dialog>` is fully
supported. To support older browsers I would layer a focus-trap and `inert`
polyfill behind feature detection rather than change the approach.
