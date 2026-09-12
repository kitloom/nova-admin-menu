# Changelog

All notable changes to this package are documented here.

## v1.0.0

The screen is what it should be, and the storage format is settled: entries are
keyed by class name, then path, then label, and that is the shape this package
intends to keep.

- Create your own entries: sections, group headings and links, internal or external
- Reset button: drops every override after a confirmation and reloads
- Save reloads the page, since Nova hands the sidebar to a page as props on a
  full load and the result of a save was otherwise invisible until a reload
- The screen is drawn the way Nova draws itself: rows as cards, a drag handle,
  an icon for what each entry is, and icon buttons for edit, delete and hide
- A stylesheet of its own, for the utilities Nova's own does not carry. Several
  of them were being used already and silently doing nothing - hidden entries
  were not actually struck through, and the drop indicators never appeared
- A test that fails on a class neither stylesheet defines, so that cannot
  happen quietly again

Released through v0.9.0 to v0.9.2 while the screen was being finished.

## v0.1.0

Initial release.

- Reorder the Nova main menu by dragging, nested entries included
- Move entries between sections
- Rename entries, set the icon of top level sections
- Hide entries, or restrict them to named permissions
- Overrides kept in one table, behind a swappable repository contract
