# WordPress Plugin Deployment & Publishing Guide

This guide explains how to deploy the AfconWave WooCommerce Gateway. There are two primary methods depending on your target audience.

## Method 1: Manual ZIP Distribution (Direct to Merchants)
If you want to manually hand out the plugin to your merchants or host it on your own website:

1. **Prepare the Folder:**
   Ensure you are inside the `plugins/` directory.

2. **Zip the Folder:**
   Compress the entire `wordpress` directory into a file named `afconwave-woocommerce-gateway.zip`.
   *Warning: Ensure the root of the ZIP file contains `afconwave-secure-gateway.php`, not another nested folder.*

3. **Distribute:**
   Merchants can simply go to their WordPress Admin > Plugins > Add New > Upload Plugin, and upload the `.zip` file.

---

## Method 2: Publishing to WordPress.org Plugin Directory
If you want the plugin to be officially searchable from within the WordPress Admin panel (e.g., millions of users can find it).

### Prerequisites
1. Register for an account on [WordPress.org](https://login.wordpress.org/register).
2. Submit your plugin for review via the [Add Your Plugin page](https://wordpress.org/plugins/developers/add/).
3. Wait for approval (usually takes 1-3 weeks).

### Publishing (SVN)
Once approved, WordPress.org provides you with an SVN repository (e.g., `https://plugins.svn.wordpress.org/afconwave-secure-gateway`).

1. **Checkout the SVN Repo:**
   ```bash
   svn co https://plugins.svn.wordpress.org/afconwave-secure-gateway my-local-svn
   ```

2. **Copy Files:**
   Copy the contents of this `plugins/wordpress/` directory into the `/trunk/` folder of the SVN repository.

3. **Commit the Release:**
   ```bash
   cd my-local-svn
   svn add trunk/*
   svn ci -m "Initial release of AfconWave Gateway version 1.0.0"
   ```

4. **Tag the Release:**
   ```bash
   svn cp trunk tags/1.0.0
   svn ci -m "Tagging version 1.0.0"
   ```

The plugin will instantly go live on the official WordPress Plugin Directory!
