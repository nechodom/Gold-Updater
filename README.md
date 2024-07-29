# Gold Price Updater

Gold Price Updater is a WordPress plugin designed to automatically update the gold price and adjust the prices of selected products with an added margin. 

## Features

- **Automatic Gold Price Update**: Fetches the latest gold prices from an external API and updates product prices daily.
- **Admin Settings**: Configure product IDs and margins via the WordPress admin interface.
- **Manual Update**: Option to manually trigger an update.
- **Price Adjustment**: Adjust product prices based on weight and specified margins.
- **Logging**: Keeps logs of updates and changes for troubleshooting and verification.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/gold-price-updater` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your product IDs and margins in the plugin settings under 'Gold Price Updater'.

## Usage

### Admin Menu

- Navigate to `Gold Price Updater` in the WordPress admin menu to access settings.
- Configure up to four product IDs and their respective margin percentages.
- View and manage logs for update activities.

### Automatic Updates

- The plugin schedules a daily event to update the gold prices and adjust product prices accordingly.
- Activation and deactivation hooks manage the scheduling and unscheduling of the daily event.

### Manual Updates

- Use the "Update Now" button in the settings page to manually trigger an update.

## Settings

- **Product ID**: Set the WooCommerce product IDs for gold products.
- **Margin %**: Define the margin percentage to be added to the gold price for each product.
- **Last Update**: Displays the last time the gold price was updated.
- **Next Update**: Displays the scheduled time for the next update.
- **Current Gold Price**: Shows the latest gold price in CZK per gram.
- **Manual Update**: Button to manually trigger an update.

## Developer Notes

- Ensure WooCommerce is installed and active.
- The plugin uses a secure method to fetch gold prices from an external API.
- Logging is implemented to help with troubleshooting and to keep track of updates.

**Note**: API keys should be securely stored and not hardcoded in the script. The provided code does not include actual API keys.

**API Source**: The gold price data is fetched from [Gold API](https://www.goldapi.io).

---

### Example Code

```php
// Activation and scheduling daily event
register_activation_hook(__FILE__, 'gpu_activation');
add_action('gpu_daily_event', 'gpu_update_gold_prices');

// Deactivation and unscheduling daily event
register_deactivation_hook(__FILE__, 'gpu_deactivation');

// AJAX handler for manual update
add_action('wp_ajax_gpu_manual_update', 'gpu_manual_update');

function gpu_update_gold_prices() {
    $apiKey = "your_api_key_here"; // Replace with your actual API key
    $symbol = "XAU";
    $curr = "CZK";

    // API request and handling code
}

function ppu_update_product_price($product_id, $new_price) {
    // WooCommerce product price update code
}
