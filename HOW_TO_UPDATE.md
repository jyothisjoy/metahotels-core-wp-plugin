# How to Remotely Update the MetaHotels Core Plugin

The `metahotels-core` plugin is configured to receive automatic updates directly from its GitHub repository using the `plugin-update-checker` library.

**Repository:** `https://github.com/jyothisjoy/metahotels-core-wp-plugin`

## Prerequisites
- You must have push access to the GitHub repository.
- The plugin code on your local machine should be up-to-date.

## Step-by-Step Update Process

Follow these steps whenever you want to push a new version to all users:

### 1. Update Version Number
Open the main plugin file: `metahotels-core.php`.
Update the `Version:` header to the new version number (e.g., change `2.9.0` to `2.9.1`).

```php
/*
Plugin Name: MetaHotels - Core
...
Version: 2.9.1
...
*/
```

### 2. Commit and Push Changes
Commit your changes to the `main` (or `master`) branch.

```bash
git add .
git commit -m "Bump version to 2.9.1"
git push origin main
```

### 3. Create a GitHub Release
The update checker looks for **GitHub Releases** to identify new versions.

1. Go to the [Releases page](https://github.com/jyothisjoy/metahotels-core-wp-plugin/releases) of your repository.
2. Click **"Draft a new release"**.
3. **Tag version:** Create a new tag that matches your version number exactly (e.g., `v2.9.1`).
4. **Target:** Select `main` (or your default branch).
5. **Release title:** Enter the version number or a descriptive title (e.g., `Version 2.9.1 - Security Fixes`).
6. **Description:** Add a changelog describing what's new. This will be visible in the WordPress Admin dashboard when users click "View version details".
7. Click **"Publish release"**.

### 4. Verification
Once the release is published:
1. Go to a WordPress site where an older version of the plugin is installed.
2. Navigate to **Dashboard > Updates** or the **Plugins** page.
3. Click "Check Again" to force a refresh.
4. You should see an update available for "MetaHotels - Core".

## Troubleshooting
- **Update not showing?** WordPress caches update data for up to 12 hours. You can force a check by going to **Dashboard > Updates > Check Again**.
- **Download failed?** Ensure the repository is Public. If it is Private, you must configure an Authentication Token in the plugin code (currently typically set up for public repos).
