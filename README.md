# GTAW WordPress Bridge Plugin

The GTAW WordPress Bridge Plugin integrates GTA:W OAuth authentication with WordPress, allowing GTA:W users to create character-based WordPress accounts. Now built with a modular architecture, the plugin’s core file remains lean while additional functionalities (such as OAuth and Discord integration) are encapsulated in separate module files. This design makes it easy to extend and maintain the plugin without altering the core.

Demo available here: [https://vommoda.com/](https://vommoda.com/)

## Plugin Structure

The plugin is organized into distinct folders and modules:

```
GTAW-Bridge/
+- gtaw-bridge.php            // Main plugin file: loads core functionality, registers assets, checks dependencies, and auto-loads modules.
+- modules/
¦  +- gtaw-oauth.php          // Handles GTA:W OAuth authentication and account management.
¦  +- gtaw-discord.php        // Manages Discord integration for linking/unlinking Discord accounts with WooCommerce accounts.
+- assets/
¦  +- css/
¦  ¦  +- gtaw-style.css       // Plugin styles.
¦  +- js/
¦     +- gtaw-script.js       // Plugin scripts.
```

## Installation

### Upload and Activate the Plugin

1. [Download the latest ZIP file](https://github.com/Botticena/gtaw-bridge/releases/latest).
2. In your WordPress admin dashboard, go to **Plugins > Add New**.
3. Click **Upload Plugin**, select the ZIP file, and then click **Install Now**.
4. Once installed, click **Activate**.

### Verify Dependencies

- **WooCommerce:** Ensure that WooCommerce is installed and activated. If not, the plugin will display an admin notice.

## Configuration

After activation, configure the plugin settings for each module via the GTAW Bridge menu in your admin dashboard.

### GTA:W OAuth Settings (gtaw-oauth Module)

1. Click on **GTA:W Bridge** in the WordPress sidebar.
2. Select the **GTA:W OAuth** tab.
3. Fill in the following fields:
   - **OAuth Client ID:** Your GTA:W Client ID from the [GTA:W UCP Developers section](https://ucp.gta.world/developers/oauth).
   - **OAuth Client Secret:** Your GTA:W Client Secret.
   - **OAuth Callback/Redirect URL:** Auto-generated (e.g., `https://yoursite.com/?gta_oauth=callback`). Ensure that this matches the URL set in your GTA:W UCP Developers section.
4. Click **Save Changes**.

### Discord Settings (gtaw-discord Module)

1. In the **GTA:W Bridge** menu, select the **Discord Settings** tab.
2. Enable the Discord module by checking the "Activate Discord Module" checkbox.
3. Enter your Discord credentials:
   - **Discord Client ID**
   - **Discord Client Secret**
   - **Discord Bot Token**
4. The **Discord OAuth Redirect URI** is auto-generated (e.g., `https://yoursite.com/?discord_oauth=callback`). Configure this URI in your Discord Developer Portal.
5. Click **Save Changes**.

## Modular Architecture

The modular design allows you to extend the plugin without modifying the core file:

- **Core File (`gtaw-bridge.php`):**  
  - Handles basic plugin initialization, dependency checks, asset enqueuing, and module auto-loading.
- **Modules (`modules/` folder):**  
  - **gtaw-oauth.php:** Contains all code related to GTA:W OAuth and account creation/login.  
  - **gtaw-discord.php:** Adds Discord integration, allowing WooCommerce users to link/unlink their Discord accounts.
- **Assets (`assets/` folder):**  
  - Organizes CSS and JavaScript files separately for cleaner maintenance.

To add new functionality, simply create a new module file in the `modules/` folder. The core will automatically load it on activation.

## Usage

### Using the GTA:W OAuth Login

- **Shortcode:**  
  Embed the login link anywhere on your site using: `[gtaw_login]`
- **User Flow:**  
  When users click the login link, they are redirected to the GTA:W OAuth page. After authenticating, they are redirected back to your website, and the plugin stores the GTA:W API response (user data and characters) in a cookie. Users can then choose a character to create a new account or log in.

### Account Management

- **First Login:**  
  New users are prompted via a modal to select a character from the GTA:W API response. The plugin creates a WordPress account for the chosen character.
- **Returning Users:**  
  Users with existing accounts can choose which connected character to log in with, or register a new character.

### Discord Integration (WooCommerce)

- **My Account Integration:**  
  In the WooCommerce My Account area, a new **Discord Settings** endpoint is available where users can link or unlink their Discord account.
- **Shortcode:**  
  Use `[gtaw_discord_buttons]` to display a link for linking/unlinking a Discord account.
- **OAuth Flow:**  
  The Discord module uses OAuth (with the `identify` scope) to retrieve the user's Discord ID.

## Troubleshooting

- **Cookies:**  
  Ensure that your site allows cookies, as the plugin uses cookies (named `gtaw_user_data`) to store API responses.
- **OAuth Callback:**  
  Verify that the OAuth Callback/Redirect URLs in the plugin settings match those configured in the GTA:W and Discord developer portals.
- **WooCommerce Dependency:**  
  Confirm that WooCommerce is active; otherwise, the plugin will notify you.
- **Cache:**  
  Clear your browser and server cache after making changes to ensure that the latest scripts and settings are loaded.

## Final Notes

The modular architecture of the GTAW WordPress Bridge Plugin streamlines development and maintenance. New modules can be added without altering the core, keeping your code clean and extendable.

For any questions or issues, feel free to reach out on the GTA:W forums:
- **Contact:** [Lena on GTA:W Forums](https://forum.gta.world/en/profile/56418-lena/)
- **Plugin Thread:** [GTA:W OAuth WordPress Plugin Guide](https://forum.gta.world/en/topic/141314-guide-gtaw-oauth-wordpress-plugin/)