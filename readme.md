# Vatomi

* Contributors: nko
* Tags: envato, license, activation, support, oauth
* Requires at least: 4.8.0
* Tested up to: 5.3
* Requires PHP: 5.4
* Stable tag: 1.0.3
* License: GPLv2 or later
* License URI: <http://www.gnu.org/licenses/gpl-2.0.html>

Envato oAuth registration. Support Envato customers users with AwesomeSupport plugin.

## Description

### Features

* Envato oAuth button in registration form
* AwesomeSupport integration for Envato products
* Activation API for themes and plugins developers

### Activation themes/plugins for developers

1. Fill settings in **wp_admin > Vatomi > Settings > Envato Settings**
1. Create Licenses page in **wp_admin > Vatomi > Settings > Licenses**
1. Use these links to let users activate your theme/plugin:
    * Activate

            <a href="<?php echo esc_attr( 'https://{YOUR_SITE}/licenses/?vatomi_item_id={ITEM_ID}&vatomi_action=activate&vatomi_site=' . urlencode( home_url( '/' ) ) . '&vatomi_redirect=' . urlencode( admin_url( 'admin.php?page={YOUR_THEME_PAGE}' ) ) ); ?>" class="button button-primary">Activate</a>

        After button clicked, user will be redirected back to their site on the page `admin_url( 'admin.php?page={YOUR_THEME_PAGE}' )` with available GET variables, that you can use:

        * **vatomi_action** (activate, deactivate)
        * **vatomi_item_id** (item ID)
        * **vatomi_license_code** (Envato purchase code)

    * Deactivate

            <a href="<?php echo esc_attr( 'https://{YOUR_SITE}/licenses/?vatomi_item_id={ITEM_ID}&vatomi_action=deactivate&vatomi_license={PURCHASE_CODE}&vatomi_redirect=' . urlencode( admin_url( 'admin.php?page={YOUR_THEME_PAGE}' ) ) ); ?>" class="button button-primary">Deactivate</a>

        After button clicked, user will be redirected back to their site on the page `admin_url( 'admin.php?page={YOUR_THEME_PAGE}' )` with available GET variables, that you can use:

        * vatomi_action (activate, deactivate)
        * vatomi_item_id (item ID)
        * vatomi_license_code (Envato purchase code)

After theme/plugin activated, you will be able to use Vatomi API:

* Get URL to ZIP file:

        https://{YOUR_SITE}/wp-json/vatomi/v1/envato/item_wp_url/{ITEM_ID}?license={PURCHASE_CODE}&site={ACTIVATED_SITE_ADDRESS}

* Get item current version number:

        https://{YOUR_SITE}/wp-json/vatomi/v1/envato/item_version/{ITEM_ID}

* Check valid purchase code (if user purchased item from you):

        https://{YOUR_SITE}/wp-json/vatomi/v1/envato/check_license/{PURCHASE_CODE}

## Installation

#### Automatic installation

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of Vatomi, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type Vatomi and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

#### Manual installation

The manual installation method involves downloading our Vatomi plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

== Changelog ==

= 1.0.3 =

* improved user search code
* changed logs prune to transient instead of cron
* removed permission callback from rest endpoints because called twice
* fixed license deactivation redirection when not enough data (always redirect if we have redirection url and item id)

= 1.0.2 =

* added transient to Envato products api method
* updated buttons on licenses page
* fixed redirect urls while license activation

= 1.0.1 =

* fixed ajax purchase verification
* added plugin version in enqueued assets

= 1.0.0 =

* Initial Release
