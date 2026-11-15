# Imagination Nature Studio — Multilingual Website

Languages enabled for the public website:
- English (`en`)
- Kinyarwanda (`rw`)
- Français (`fr`)

## How it works

Visitors can choose the language from the public website header. The selected language is saved in a cookie and PHP session, so navigation to other pages keeps the selected language.

The implementation is centralized in:
- `includes/language.php`
- `includes/functions.php`
- `includes/header.php`
- `includes/footer.php`

The existing booking, admin, security, photo-selection, assets, uploads and other systems are kept in place.

## Deployment

Upload the contents of this package into the existing website root, preserving the existing folder structure. Take a full server backup first.

Do not upload the old ZIP backup files from the source project.
