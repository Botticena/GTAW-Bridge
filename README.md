# GTAW WordPress Bridge Plugin
The GTA:W Bridge plugin integrates GTA:W OAuth authentication with WordPress, allowing GTA:W users to create character-based WordPress accounts. Each character the user selects from the GTA:W API becomes its own WP account, and returning users can choose which character account to log in as.

Demo available here: https://vommoda.com/

## Installation

#### Upload and Activate the Plugin
- [Download the plugin](https://github.com/Botticena/gtaw-bridge/releases/latest) ZIP file.
- In your WordPress admin dashboard, navigate to Plugins > Add New.
- Click Upload Plugin, choose the ZIP file, and then click Install Now.
- Once installed, click Activate.

#### Verify Dependencies
- Make sure WooCommerce is installed and activated. If it isn’t, the plugin will display an admin notice.

## Configuration
After activation, you’ll need to configure the plugin settings.

### GTA:W oAuth Settings

#### Access the Settings Page
- In the WordPress admin sidebar, you should now see a top-level menu item called GTA:W Bridge. Click on it.

#### GTA:W oAuth Tab
The settings page displays three tabs at the top:
- GTA:W oAuth
- Fleeca API (Coming Soon)
- Discord Sync (Coming Soon)

![Settings Page](https://i.imgur.com/SAxIz4F.png)

In the GTA:W oAuth tab, you’ll see the following fields:
- OAuth Client ID: Enter your GTA:W Client ID from the [GTA:W UCP Developers section](https://ucp.gta.world/developers/oauth).
- OAuth Client Secret: Enter your GTA:W Client Secret from the GTA:W UCP Developers section.
- OAuth Callback/Redirect URL: This field is auto-generated (e.g., https://yoursite.com/?gta_oauth=callback). Ensure that the callback URL configured in your GTA:W UCP Developers section matches this URL.

After filling in these fields, click the Save Changes button.

#### Future Settings Tabs
The Fleeca API and Discord Sync tabs currently display “Coming Soon.” These tabs will be updated as new features are developed.

### Removing Residual Sensitive Data
All sensitive credentials (Client ID and Secret) are stored as plugin options and are not hard-coded in the plugin files.

## Usage

### Using the OAuth Login Link

#### Embedding the Link
You can embed the login link anywhere on your site using the shortcode: [gtaw_login]

Alternatively, you can use the link directly (as generated and shown in the settings page).
#### User Flow via GTA:W
When a user clicks the login link, they are redirected to the GTA:W OAuth page.

After authenticating on GTA:W, they are redirected back to your website. The plugin saves the GTA:W API response (user data and list of characters) in a cookie.

### Account Creation – First Login
![Account Creation](https://i.imgur.com/hqBhzoW.png)
#### First Login Modal
If the plugin determines that there is no existing WordPress account linked to the GTA:W user, a modal appears on the site prompting the user to select a character from the GTA:W API response. The modal displays a dropdown list of all characters. When the user selects a character and clicks Create Account & Login, the plugin:
- Creates a new WP account using the selected character’s details.
  - Username: Generated as (firstname)_(lastname) (all lower-case and sanitized). If the username already exists, a timestamp is appended.
  - Email: Set as firstname.lastname@mail.sa
  - First Name/Last Name: Set to the character’s first and last names.
- Saves the GTA:W user ID in user meta (key: gtaw_user_id).
- Saves the selected character as both the connected character (in gtaw_characters) and as the active character (active_gtaw_character).
- Logs the user in using WordPress authentication cookies.

### Returning User Flow – Logging in and Switching Characters
![Account Login](https://i.imgur.com/C5hwkbK.png)
#### When an Account Exists
The plugin checks for existing WP accounts using the GTA:W user ID stored in user meta.A modal appears with two sections:
- Login with an Existing Account:
Displays a dropdown list of accounts (each representing a connected character). Clicking Login calls an AJAX endpoint that updates the active character and logs the user in.

- Register a New Character: Displays a dropdown list of GTA:W characters that are not yet connected to any WP account. Clicking Register New Account creates a new WP account for that character.

#### Switching Active Character
If a user is already logged in and wants to switch the active character, they can do so from the returning user modal. This updates both the user meta and the WP profile (first name, last name, display name, etc.) to reflect the new character. The plugin logs the user in with the updated profile.

## Troubleshooting
### Cookie Issues
Ensure that your site isn’t blocking cookies, as the plugin relies on cookies (named gtaw_user_data) to store the GTA:W API response.

### OAuth Callback
If users are not being redirected back properly after logging into GTA:W, double-check that the OAuth Callback/Redirect URL in your plugin settings matches what’s configured on the GTA:W UCP Developers section.

### WooCommerce Dependency
Make sure WooCommerce is active. The plugin shows an admin notice if WooCommerce isn’t installed or activated.

### Cache
After making changes to the plugin, clear your browser cache (and any server-side cache) to ensure that the latest JavaScript and settings are loaded.

## Final Notes
If you have any questions or run into any errors, feel free to contact me on GTA:W forums.
- Contact me here: https://forum.gta.world/en/profile/56418-lena/
- Plugin thread: https://forum.gta.world/en/topic/141314-guide-gtaw-oauth-wordpress-plugin/
