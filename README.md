# GTA:W WordPress Bridge

GTA:W WordPress Bridge seamlessly integrates your WordPress site with the GTA:World Roleplay platform. Using OAuth authentication, it allows users to log in with their GTA:W credentials while automatically creating or linking their WordPress accounts based on selected in-game characters.

## Features

- **OAuth Integration:** Enables users to authenticate via GTA:W.
- **Automatic Account Creation/Linking:** Automatically creates or links WordPress accounts based on the user's selected GTA:W character.
- **WooCommerce Compatibility:** Requires WooCommerce to be installed and activated.
- **Shortcode Support:** Easily embed the GTA:W login button using the `[gtaw_login]` shortcode.
- **GitHub Updater:** Checks for plugin updates directly from the GitHub repository.

## Requirements

- WordPress (version 5.0 or higher recommended)
- WooCommerce (must be installed and active)
- PHP 7.0+

## Installation

1. **Upload the Plugin:**  
   Upload the `gtaw-bridge` folder to the `/wp-content/plugins/` directory.

2. **Activate the Plugin:**  
   Go to the **Plugins** menu in WordPress and activate **GTA:W WordPress Bridge**.

3. **Configure Settings:**  
   Navigate to **GTA:W Bridge Settings** in the WordPress admin area to configure your OAuth settings:
   - **OAuth Client ID**
   - **OAuth Client Secret**
   - **OAuth Callback URL**

4. **Embed the Login Button:**  
   Use the `[gtaw_login]` shortcode in your pages or posts to display the GTA:W login link.

## Usage

When users click the GTA:W login link, they are redirected to authenticate via GTA:W. Upon successful authentication, the plugin automatically creates or links their WordPress account based on the selected in-game character.

The plugin also provides AJAX endpoints for:
- Checking if an account exists for a GTA:W user.
- Creating a new account if needed.
- Logging into an existing account based on character selection.

## Changelog

- **1.0.0**
  - Initial release.
  - OAuth-based login and account management.
  - WooCommerce integration.
  - GitHub updater for seamless updates.

## Contributing

Contributions are welcome! Please fork the repository and open a pull request with your improvements.

## Support

If you encounter any issues or have suggestions, please open an issue on [GitHub Issues](https://github.com/Botticena/gtaw-bridge/issues).

You can also [contact](https://forum.gta.world/en/profile/56418-lena/) me on the GTA:W Forums or ask for help on the [plugin's thread](https://forum.gta.world/en/topic/141314-guide-gtaw-oauth-wordpress-plugin/).

## License

This project is licensed under the [GPL-2.0](LICENSE) license.
