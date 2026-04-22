# GTA:W WordPress Bridge v1.2.1

[![Version](https://img.shields.io/badge/version-1.2.1-blue.svg)](https://github.com/Botticena/GTAW-Bridge)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](LICENSE)

**GTA:W WordPress Bridge v1.2.1** seamlessly integrates your WordPress site with the GTA:World Roleplay platform. This modular plugin provides comprehensive integration including OAuth authentication, Discord synchronization, and Fleeca Bank payment processing.

---

## 🚀 Quick Start

1. **Install** the plugin in `/wp-content/plugins/`
2. **Activate** GTA:W Bridge in WordPress
3. **Configure** modules in the admin dashboard
4. **Enable** the features you need
5. **Use** `[gtaw_login]` shortcode anywhere

---

## ✨ Features Overview

### 🔐 **OAuth Module** (Default: Enabled)
- GTA:W single sign-on authentication
- Character-based WordPress account creation
- Seamless character switching
- Secure token management

### 💬 **Discord Module** (Default: Disabled)
- Discord OAuth integration
- Automatic role mapping and synchronization
- Real-time notifications for events
- Member management and verification

### 💳 **Fleeca Bank Module** (Default: Disabled)
- WooCommerce payment gateway integration
- Payment processing (Merchant API v2)
- Automatic order status updates
- Comprehensive transaction logging

### 🛠️ **Enhanced Platform Features**
- **Modular Architecture** - Enable/disable modules independently
- **Advanced Logging** - Database-backed with filtering and CSV export
- **Modern Dashboard** - Beautiful admin interface with activity monitoring
- **Performance Optimized** - Intelligent caching and lazy loading
- **Enhanced Security** - Improved nonce handling and capability checks
- **GitHub Updater** - Automatic updates from repository

---

## 📋 Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| **WordPress** | 5.0+ | Recommended: Latest version |
| **PHP** | 7.4+ | Recommended for optimal performance |
| **WooCommerce** | Latest | Required for Fleeca Bank module |
| **Database** | MySQL 5.6+ / MariaDB 10.1+ | For enhanced logging features |

---

## 🔧 Installation & Setup

### Step 1: Install the Plugin
1. Upload the `gtaw-bridge` folder to `/wp-content/plugins/`
2. Activate **GTA:W Bridge** in WordPress admin

### Step 2: Configure Modules
Navigate to **GTA:W Bridge** in WordPress admin to:
- ✅ Enable/disable modules based on your needs
- ⚙️ Configure OAuth settings (Client ID, Client Secret, Callback URL)
- 💬 Set up Discord integration (optional)
- 💳 Configure Fleeca Bank payment gateway (optional)

### Step 3: Module-Specific Setup

#### 🔐 OAuth Module
- Configure your GTA:W UCP Developer credentials
- Set up OAuth Client ID and Secret
- Verify callback URL matches your site

#### 💬 Discord Module
- Create Discord application in Developer Portal
- Set up Discord Bot Token
- Configure Guild ID and invite settings

#### 💳 Fleeca Bank Module
- Obtain Fleeca Bank API key from GTA:W UCP
- Configure payment gateway settings
- Set up callback URLs for payment processing

### Step 4: Embed Login Button
Use the `[gtaw_login]` shortcode in pages or posts to display the GTA:W login link.

---

## 📖 Usage Guide

### 🔐 OAuth Authentication
When users click the GTA:W login link, they're redirected to authenticate via GTA:W. Upon successful authentication, the plugin automatically creates or links their WordPress account based on the selected character.

### 💬 Discord Integration
- **Account Linking:** Users can link Discord accounts to WordPress accounts
- **Role Mapping:** Automatically assign Discord roles based on WordPress user roles
- **Notifications:** Send Discord notifications for posts, purchases, and events
- **Member Management:** Sync Discord server members with WordPress users

### 💳 Fleeca Bank Payments
- **Payment Processing:** Process WooCommerce payments through GTA:W's Fleeca Bank
- **Order Management:** Automatic order status updates based on payment completion
- **Transaction Logging:** Comprehensive logging of all payment transactions

### 🎛️ Advanced Features
- **Module Management:** Enable/disable modules independently through admin dashboard
- **Activity Monitoring:** View real-time logs and activity from all modules
- **Performance Monitoring:** Built-in performance tracking and optimization
- **Data Export:** Export logs and data in CSV format for analysis

---

## 🏗️ Module Architecture

The plugin uses a modular architecture allowing you to enable only the features you need:

```
GTA:W Bridge v1.2.1
├── 🔐 OAuth Module (Default: Enabled)
│   ├── Authentication & Token Management
│   ├── Character-based Account Creation
│   └── Character Switching
├── 💬 Discord Module (Default: Disabled)
│   ├── OAuth Integration
│   ├── Role Mapping & Sync
│   ├── Notification System
│   └── Member Management
└── 💳 Fleeca Bank Module (Default: Disabled)
    ├── WooCommerce Gateway
    ├── Payment Processing
    ├── Order Management
    └── Transaction Logging
```

---

## 📊 Changelog

### **v1.2.1** - Polish & block checkout
- **🛒 WooCommerce** – Smoother GTA:W-style address and checkout handling in one place; fewer duplicate field tweaks
- **💳 Fleeca** – Optional **block (modern) checkout** support; snappier gateway load and clearer payment UI
- **🎛️ Admin** – Notices from WordPress are easier to see on the bridge page; you can **dismiss** the “block cart/checkout” reminder
- **🔧 General** – Lighter utilities and Discord post-notification code, updates checker tuned; overall stability and maintainability

### **v1.2** - Fleeca Merchant API v2 & polish
- **💳 Fleeca v2** – Hosted checkout, HMAC webhooks, browser return with `payment_id`; **Callback** / **Return** URLs for the Merchant Center; optional rewrite flush when permalinks change
- **🛒 WooCommerce** – Clearer checkout notices; Fleeca metadata on orders; admin reconcile / API view tools; WooCommerce admin nag **only** if Fleeca is enabled
- **🔐 OAuth & Discord** – Shortcodes register on `init` (block themes); safe placeholders when a module is off (no raw `[shortcode]` text)
- **🔒 Fixes** – Log-clear AJAX nonce; OAuth callback skipped when OAuth is disabled; settings forms read nested options; Discord role-sync cron cleared on deactivate; one-time removal of legacy Fleeca v1 keys

### **v1.1** - Major Update 🎉
- **🏗️ Modular architecture** – OAuth, Discord, and Fleeca as separate modules you can turn on or off
- **💬 Discord** – OAuth linking, role mapping, notifications (posts, orders, store), member UI
- **💳 Fleeca** – WooCommerce payment gateway
- **📊 Logging & dashboard** – Database logs (filter, paginate, export CSV); central bridge dashboard
- **⚙️ Settings & API** – Consolidated options with legacy compatibility; shared HTTP helpers, caching, and tighter permission/sanitization checks

### **v1.0** - Initial Release
- **🔐 GTA:W OAuth** – Login via UCP; modal for new vs returning users; pick a character to create a WP user; link multiple characters and switch between them
- **🛒 WooCommerce** – Works alongside WooCommerce-powered sites
- **⚙️ Admin & updates** – OAuth credentials in WP admin; **`[gtaw_login]`** shortcode; updates from GitHub

---

## 🆘 Support & Documentation

### 📚 Built-in Documentation
- **📖 Module Guides** - Each module has a **Guide** tab (setup, testing, help)
- **🎛️ Admin Dashboard** - Turn features on and open settings from one place
- **📊 Activity Logs** - See what happened if something needs checking

### 🆘 Getting Help
- **🐛 Issues & Bugs:** [GitHub Issues](https://github.com/Botticena/GTAW-Bridge/issues)
- **💬 Contact:** [GTA:W Forums](https://forum.gta.world/en/profile/56418-lena/)
- **📖 Plugin Thread:** [GTA:W Plugin Discussion](https://forum.gta.world/en/topic/141314-guide-gtaw-oauth-wordpress-plugin/)

### 🔧 Troubleshooting
1. **Check Module Guides** - Visit the "Guide" tab in each module for detailed setup instructions
2. **Review Activity Logs** - Check the "Logs" tab in each module for error information
3. **Verify Dependencies** - Ensure all required plugins are installed and active
4. **Check Credentials** - Verify API credentials are correctly configured
5. **Debug Mode** - Enable WordPress debug logging for additional error details

---

## 🤝 Contributing

Contributions are welcome! Please fork the repository and open a pull request with your improvements.

- 🐛 **Bug Reports** - Help us identify and fix issues
- 💡 **Feature Requests** - Suggest new functionality
- 📝 **Documentation** - Improve guides and instructions
- 🔧 **Code Contributions** - Submit improvements and optimizations

---

## 📄 License

This project is licensed under the [GPL-2.0](LICENSE) license.

---

<div align="center">

**Created with ❤️ by [Lena](https://forum.gta.world/en/profile/56418-lena/) for the GTA:W Community**

[![GitHub](https://img.shields.io/badge/GitHub-Repository-black?style=for-the-badge&logo=github)](https://github.com/Botticena/GTAW-Bridge)
[![GTA:W Forums](https://img.shields.io/badge/GTA:W-Forums-blue?style=for-the-badge)](https://forum.gta.world/)

</div>