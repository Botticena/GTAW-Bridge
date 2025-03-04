# GTA:W Bridge

**Version 1.1**

## Description

GTA:W Bridge connects your WordPress website with the GTA World roleplay platform, enabling seamless account integration, Discord synchronization, and Fleeca Bank payments through WooCommerce. This plugin is perfect for roleplay business owners who want to extend their in-game businesses to the web.

## Major Features

### 1. GTA:W OAuth Integration (Core)
- **Single Sign-On:** Allow users to log in with their existing GTA:W accounts
- **Character-Based Accounts:** Create separate WordPress accounts for each GTA:W character
- **Account Management:** Easy interface for users to switch between character accounts
- **WooCommerce Integration:** Character information appears in user accounts

### 2. Discord Integration
- **Account Linking:** Users can connect their Discord accounts to their WordPress accounts
- **Role Mapping:** Synchronize Discord roles with WordPress user roles (both directions)
- **Order Notifications:** Send order status updates to customers via Discord
- **Store Notifications:** Receive detailed order alerts in your Discord server
- **Member Card:** Display users' Discord roles on their profile
- **Discord Checkout:** Require Discord membership for checkout (optional)

### 3. Fleeca Bank WooCommerce Integration
- **Custom Payment Gateway:** Accept payments through GTA:W's Fleeca Bank system
- **Secure Transactions:** All payments are processed through the official GTA:W banking system
- **Order Status Updates:** Automatic order completion upon payment
- **Sandbox Testing:** Test your store without real currency transfers

### 4. Comprehensive Logging
- **Detailed Activity Logs:** Track authentication, Discord interactions, and payments
- **Troubleshooting Tools:** Debug information for all modules
- **Security Monitoring:** Track account creations and payment attempts

## Requirements

- WordPress 5.0 or higher
- WooCommerce 6.0 or higher
- GTA:W Developer API credentials
- Discord Developer Application
- Fleeca Bank API key

## Installation

1. Upload the `gtaw-bridge` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the modules through the GTA:W Bridge menu in your admin dashboard

## Module Configuration

### OAuth Module
- Obtain OAuth credentials from the [GTA:W UCP Developers section](https://ucp.gta.world/developers/oauth)
- Configure your Client ID, Client Secret, and Callback URL
- Add login buttons to your site using the provided shortcodes

### Discord Module
- Create a [Discord Application](https://discord.com/developers/applications)
- Configure the Bot Token, Client ID, Client Secret, and Guild ID
- Set up role mappings and notification templates
- Customize notification settings for different order statuses

### Fleeca Bank Module
- Request a Fleeca Bank API key from GTA:W UCP Developers
- Configure the payment gateway settings
- Test transactions using the sandbox mode

## Shortcodes

### OAuth Shortcodes
- `[gtaw_login]` - Display a basic login link
- `[gtaw_login_button]` - Display a styled button with custom text
- `[gtaw_user_info]` - Show information about the logged-in character
- `[gtaw_character_info]` - Display detailed character information
- `[gtaw_if_logged_in]` and `[gtaw_if_not_logged_in]` - Conditional content display

### Discord Shortcodes
- `[gtaw_discord_buttons]` - Display link/unlink Discord buttons

## Upgrade Notes from v1.0

If you're upgrading from version 1.0, here's what's new:

1. **Discord Integration** - Complete Discord module with role mapping, notifications, and member cards
2. **Fleeca Bank Gateway** - New WooCommerce payment gateway for in-game currency transactions
3. **Enhanced Logging** - Comprehensive logging system across all modules
4. **Improved UI** - Better admin interfaces with detailed guides
5. **Two-Way Role Sync** - Synchronize roles between WordPress and Discord in both directions
6. **Module Activation** - All modules can be activated or desactivated depending on your needs.

## Frequently Asked Questions

### Is this an official GTA World plugin?
No, this is a third-party plugin. While it integrates with GTA World's API, it is not officially created or maintained by GTA World.

### Do users need to create new accounts to use my site?
No, users log in with their existing GTA:W accounts, and a WordPress account is automatically created for their characters.

### How secure are the Fleeca Bank payments?
All payments are processed directly through GTA:W's official banking system. This plugin simply facilitates the connection between your store and their payment system.

## Support and Documentation

For more detailed documentation, please refer to the guide tabs within each module's settings page in your WordPress admin area.

For support, please open an issue on the [GitHub repository](https://github.com/Botticena/gtaw-bridge/).

## Changelog

### 1.1
- Added Discord integration module with role mapping
- Added Discord notifications for customers and store owners
- Added Fleeca Bank WooCommerce payment gateway
- Added comprehensive logging system
- Improved admin UI with detailed guides
- Added two-way role synchronization with Discord
- Added Discord member card display
- Added various configuration options for all modules

### 1.0
- Initial release with GTA:W OAuth integration
- Character-based account system
- Basic account management features

## Credits

Developed by [Lena](https://forum.gta.world/en/profile/56418-lena/)

## License

This plugin is licensed under the GPL v2 or later.