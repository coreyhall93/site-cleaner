# Site Cleaner

Current version: 0.3.0

<p align="center">
  <a href="https://coreyhall93.github.io/site-cleaner/instruction_manual.html">
    <img src="https://img.shields.io/badge/View%20Instructions-Here-0f766e?style=for-the-badge" alt="View instructions here">
  </a>
</p>

Site Cleaner is a small WordPress admin tool for clearing a test or staging site after the finished client site has already been moved to production with All-in-One WP Migration.

It is designed for quick test-domain cleanup. WordPress stays installed, users stay in place, and protected connection tools like ManageWP Worker are preserved.

## When To Use It

Use Site Cleaner after:

- The client site has been migrated from the test domain to the production domain.
- The production site has been checked and approved.
- Nobody needs the old client build on the test domain anymore.
- You are logged directly into the test or staging site's WordPress admin.

Do not run Site Cleaner on the production site.

## What It Does

- Walks the user through a cleanup wizard.
- Includes a dry run before the final clean.
- Requires the confirmation phrase `CLEAN TEST SITE` before a real clean.
- Deletes selected site content:
  - Pages
  - Posts
  - Media library attachments
  - Navigation menus
  - Comments
  - Widgets and sidebars
  - Public custom post type content
- Selects most non-protected plugins and themes for deletion by default.
- Deactivates selected active plugins before deleting them.
- Protects Site Cleaner, ManageWP Worker, and network-active plugins.
- Protects Twenty Twenty-Five.
- Keeps WordPress users intact.
- Can install and activate Twenty Twenty-Five.
- Can update the site title.
- Can clear the tagline.
- Can remove the site icon.
- Can reset permalinks to the WordPress default.
- Creates a clean block-theme blog home template with centered ready text and no header or footer template parts.

## What It Does Not Do

- It does not delete WordPress users.
- It does not replace the whole WordPress database.
- It does not remove the site from ManageWP.
- It does not delete a plugin after you uncheck that plugin.
- It does not delete a theme after you uncheck that theme.
- It does not delete ManageWP Worker.
- It does not delete Site Cleaner.
- It does not delete Twenty Twenty-Five.
- It does not require a WPX reset because WordPress remains in place on the test domain.

## Install The Plugin

1. Log into the test or staging WordPress admin directly with `/wp-admin`.
2. Confirm the browser address bar shows the test domain, not the production domain.
3. Go to `Plugins -> Add New -> Upload Plugin`.
4. Upload `site-cleaner.zip`.
5. Activate `Site Cleaner`.
6. Go to `Tools -> Site Cleaner`.

## Run The Wizard

1. Confirm you are on the test or staging domain.
2. Confirm the finished site has already been moved to production.
3. Review the content cleanup options. The defaults are set for a full cleanup.
4. Review the site identity and ready screen settings.
5. Review the checked plugin and theme deletion rows.
6. Uncheck anything the test site should keep.
7. Click `Run dry run`.
8. Read the dry run output carefully.
9. If the plan looks correct, type `CLEAN TEST SITE`.
10. Click `Clean test site`.

## After Cleanup

After Site Cleaner finishes:

1. Open the test domain in a browser.
2. Confirm the old client site is gone.
3. Confirm the ready screen appears.
4. Log back into WordPress admin.
5. Check `Pages`, `Posts`, and `Media` to confirm old client content is gone.
6. Check `Plugins` and `Themes` to confirm only the items you wanted to keep remain.
7. If the test domain is tracked in ManageWP, confirm ManageWP opens the test domain's WordPress admin.

## ManageWP Notes

ManageWP usually should not need to be reconnected after Site Cleaner runs. Site Cleaner protects ManageWP Worker and does not replace the WordPress install.

If ManageWP opens the wrong site or cannot open the test site:

1. Stop using ManageWP one-click login for that site.
2. Log into the test site directly with `/wp-admin`.
3. Confirm ManageWP Worker is still installed and active.
4. In ManageWP, confirm the saved site URL is the test domain.
5. Reconnect the test domain only if ManageWP still cannot sync or open the correct WordPress admin.

## Safety Reminders

- Always check the browser address bar before running the final clean.
- Always run the dry run first.
- Keep Site Cleaner, ManageWP Worker, network-active plugins, and Twenty Twenty-Five protected.
- When unsure, uncheck a plugin or theme before the final clean.
- When something looks wrong in the dry run, stop and fix the selection before continuing.
