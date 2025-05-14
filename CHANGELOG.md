# Changelog

## Unreleased
- profile form - photo upload fix
- foto live upload fix

## [1.9.1] - 2020-07-01

### Fixed
- photo upload in highlineForm
  - max size set to 2MB
  - max width set to 2500px
  - final image optimization
- highlines coordinates in database (every highline has at least one coordinate set, so is visible in map)

### Removed
- thumb upload in highlineForm

## [1.9.0] - 2020-05-07

### Fixed
- login, registration, password resetting
- profile edit form - changing personal info and photo
- highline form - map rendering

### Changed
Visual
- font switched to Ubuntu
- container width enlarged to 1540px
- homepage news listing responsive design 
 
Functional

- highline book - map as default view
- highline book - map shows all highlines from database
- highline detail - map is visible at the end of info tab
- highline detail - breadcrumbs are shown instead of side nav, highline info has more space

### Added
- basic responsive layout
- password resetting form and forum have Google reCaptcha v2
- TinyMCE editor in admin forms upgraded to last version (bug fix) 
- composer.json added to root of the project as main and only dependency config
- highline book - map state (zoom) is saved until user closes browser tab
- highline detail - last attempts listing
- font awesome icons everywhere :-)

### Removed
- highline book - all tabs except map
- first attempts form
- unused very old code snippets

## [1.0.0] - 2007

### Added
- everything :-)