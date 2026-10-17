# Hostinger update notes

Use this checklist when uploading the current SureSell files to Hostinger.

## Category images and layout

- Upload the full `assets/img` directory, including `electronics.jpg`, `farm produce.jpg`, `house hold goods.jpg`, and `rolls royce.jpg`.
- The homepage chooses local category images by the category slug. The current mapping is `electronics`, `farm-produce`, `household-goods`, and `vehicles-parts`.
- If a Hostinger database uses different category slugs, update the `$categoryImages` map in `index.php` to match those slugs.
- Keep `assets/css/style.css` and `index.php` in the same release. The category tiles are responsive: four columns on desktop and two on narrow mobile screens.
- Upload `assets/css/style.css` with the page files. The stylesheet URL includes a file modification timestamp to refresh browser caches.

## SMS configuration

- Keep `.env` out of public uploads and source control. Configure `APP_ENV=production`, `APP_URL`, database settings, `SOMAME_API_BASE`, and `SOMAME_API_KEY` in Hostinger's private environment configuration or a protected `.env` file.
- Enable PHP cURL in the Hostinger PHP extensions.
- The Somame account/key must have OTP send and verify access and support Ghanaian destination numbers. A key being present does not prove delivery; verify with a real controlled signup/reset after deployment.
- Ensure the configured host is reachable over HTTPS. Current development network could not connect to `smsapi.somame.net`, so provider credentials and deliverability remain unverified.
- Do not paste API or secret keys into support tickets, screenshots, chat, or committed files. Rotate the key if it has been exposed.

## Release consistency

Upload the repository's application files together, including PHP pages, `includes/`, `config/`, `assets/`, and `database/`. Preserve Hostinger's production `.env` and uploaded user listing images. Back up the database and existing site files before replacing them.

After upload, check the homepage at desktop and mobile widths, click each category tile, inspect the PHP error log, and complete a controlled SMS signup and reset to verify both OTP send and OTP verification.
