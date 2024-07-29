<?php
/*
Plugin Name: Aktualizace ceny zlata
Description: Automaticky aktualizuje cenu zlata a nastaví novou cenu s marží přiděleným produktům.
Version: 1.6
Author: Matěj Kevin Nechodom
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

init_plugin();

function init_plugin() {
    add_action('admin_menu', 'gpu_add_admin_menu');
    add_action('admin_init', 'gpu_settings_init');
    add_action('apply_price_changes', 'apply');
    add_action('action_change_prices', 'change_prices', 10, 5);
    add_action('action_remove_prices', 'remove_prices', 10, 5);
    if (!class_exists('WP_List_Table')) {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
    }

    // Activation and scheduling daily event
    register_activation_hook(__FILE__, 'gpu_activation');
    add_action('gpu_daily_event', 'gpu_update_gold_prices');

    // Deactivation and unscheduling daily event
    register_deactivation_hook(__FILE__, 'gpu_deactivation');

    // AJAX handler for manual update
    add_action('wp_ajax_gpu_manual_update', 'gpu_manual_update');
}

function gpu_add_admin_menu() {
    add_menu_page('Gold Price Updater', 'Gold Price Updater', 'manage_options', 'gold_price_updater', 'gpu_options_page');
}

function gpu_settings_init() {
    register_setting('pluginPage', 'gpu_settings', 'gpu_settings_validate');

    add_settings_section(
        'gpu_pluginPage_section',
        __('Configure your gold products and margin', 'wordpress'),
        'gpu_settings_section_callback',
        'pluginPage'
    );

    for ($i = 1; $i <= 4; $i++) {
        add_settings_field(
            'gpu_product_id_' . $i,
            __('Product ID ' . $i, 'wordpress'),
            'gpu_product_id_render',
            'pluginPage',
            'gpu_pluginPage_section',
            ['id' => $i]
        );

        add_settings_field(
            'gpu_margin_' . $i,
            __('Margin % ' . $i, 'wordpress'),
            'gpu_margin_render',
            'pluginPage',
            'gpu_pluginPage_section',
            ['id' => $i]
        );
    }

    add_settings_field(
        'gpu_last_update',
        __('Last Update', 'wordpress'),
        'gpu_last_update_render',
        'pluginPage',
        'gpu_pluginPage_section'
    );

    add_settings_field(
        'gpu_next_update',
        __('Next Update', 'wordpress'),
        'gpu_next_update_render',
        'pluginPage',
        'gpu_pluginPage_section'
    );

    add_settings_field(
        'gpu_gold_price',
        __('Current Gold Price (CZK/gram)', 'wordpress'),
        'gpu_gold_price_render',
        'pluginPage',
        'gpu_pluginPage_section'
    );

    add_settings_field(
        'gpu_manual_update',
        __('Manual Update', 'wordpress'),
        'gpu_manual_update_render',
        'pluginPage',
        'gpu_pluginPage_section'
    );
}

function gpu_settings_validate($input) {
    for ($i = 1; $i <= 4; $i++) {
        if (!isset($input['gpu_product_id_' . $i]) || !is_numeric($input['gpu_product_id_' . $i])) {
            $input['gpu_product_id_' . $i] = '';
        }
        if (!isset($input['gpu_margin_' . $i]) || !is_numeric($input['gpu_margin_' . $i])) {
            $input['gpu_margin_' . $i] = '';
        }
    }
    add_settings_error(
        'gpu_messages',
        'gpu_message',
        __('Settings Saved', 'wordpress'),
        'updated'
    );
    return $input;
}

function gpu_product_id_render($args) {
    $options = get_option('gpu_settings');
    ?>
    <input type='number' name='gpu_settings[gpu_product_id_<?php echo $args['id']; ?>]' value='<?php echo isset($options['gpu_product_id_' . $args['id']]) ? $options['gpu_product_id_' . $args['id']] : ''; ?>'>
    <?php
}

function gpu_margin_render($args) {
    $options = get_option('gpu_settings');
    ?>
    <input type='number' name='gpu_settings[gpu_margin_<?php echo $args['id']; ?>]' value='<?php echo isset($options['gpu_margin_' . $args['id']]) ? $options['gpu_margin_' . $args['id']] : ''; ?>'>
    <?php
}

function gpu_last_update_render() {
    $last_update = get_option('gpu_last_update');
    echo $last_update ? date('Y-m-d H:i:s', $last_update) : 'Never';
}

function gpu_next_update_render() {
    $next_update = wp_next_scheduled('gpu_daily_event');
    echo $next_update ? date('Y-m-d H:i:s', $next_update) : 'Never';
}

function gpu_gold_price_render() {
    $gold_price = get_option('gpu_gold_price_24k');
    echo $gold_price ? number_format($gold_price, 2) . ' CZK/gram' : 'Not available';
}

function gpu_manual_update_render() {
    ?>
    <button id="gpu_manual_update_button" class="button button-primary">Update Now</button>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#gpu_manual_update_button').on('click', function(e) {
            e.preventDefault();
            $.post(ajaxurl, { action: 'gpu_manual_update' }, function(response) {
                alert('Update started. Please check logs for details.');
            });
        });
    });
    </script>
    <?php
}

function gpu_settings_section_callback() {
    echo __('Enter the WooCommerce product IDs and the margin percentage for each product.', 'wordpress');
}

function gpu_options_page() {
    ?>
    <h2>Gold Price Updater</h2>
    <h2 class="nav-tab-wrapper">
        <a href="?page=gold_price_updater" class="nav-tab <?php echo !isset($_GET['tab']) ? 'nav-tab-active' : ''; ?>">Settings</a>
        <a href="?page=gold_price_updater&tab=logs" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'logs' ? 'nav-tab-active' : ''; ?>">Logs</a>
    </h2>
    <?php
    if (isset($_GET['tab']) && $_GET['tab'] == 'logs') {
        gpu_display_logs();
    } else {
        ?>
        <form action='options.php' method='post'>
            <?php
            settings_fields('pluginPage');
            do_settings_sections('pluginPage');
            submit_button();
            ?>
        </form>
        <?php
        if (isset($_GET['settings-updated'])) {
            add_settings_error('gpu_messages', 'gpu_message', __('Settings Saved', 'wordpress'), 'updated');
            settings_errors('gpu_messages');
        }
    }
}

function gpu_display_logs() {
    $logs = get_option('gpu_logs', []);
    ?>
    <div class="wrap">
        <h2>Log Messages</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($logs)) {
                    foreach (array_reverse($logs) as $log) {
                        echo '<tr>';
                        echo '<td>' . esc_html($log['date']) . '</td>';
                        echo '<td>' . esc_html($log['message']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr>';
                    echo '<td colspan="2">No log messages found.</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

function gpu_activation() {
    if (!wp_next_scheduled('gpu_daily_event')) {
        wp_schedule_event(time(), 'daily', 'gpu_daily_event');
    }
    gpu_log("Plugin activated and daily event scheduled.");
}

function gpu_deactivation() {
    wp_clear_scheduled_hook('gpu_daily_event');
    gpu_log("Plugin deactivated and daily event unscheduled.");
}

function gpu_log($message) {
    $logs = get_option('gpu_logs', []);
    $logs[] = [
        'date' => current_time('mysql'),
        'message' => $message
    ];
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100); // Keep only the last 100 messages
    }
    update_option('gpu_logs', $logs);
}

function gpu_update_gold_prices() {
    $apiKey = "goldapi-b9i0slxjd1yd4-io";
    $symbol = "XAU";
    $curr = "CZK";

    $myHeaders = array(
        'x-access-token: ' . $apiKey,
        'Content-Type: application/json'
    );

    $curl = curl_init();

    $url = "https://www.goldapi.io/api/{$symbol}/{$curr}";

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $myHeaders
    ));

    $response = curl_exec($curl);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        gpu_log('Gold Price Updater Error: ' . $error);
        return;
    }

    $data = json_decode($response, true);
    $price_gram_24k = isset($data['price_gram_24k']) ? $data['price_gram_24k'] : null;

    if (!empty($price_gram_24k)) {
        update_option('gpu_gold_price_24k', $price_gram_24k);
        update_option('gpu_last_update', time());
        gpu_log("Gold price updated: $price_gram_24k CZK/gram");

        gpu_update_product_prices($price_gram_24k);
    } else {
        gpu_log('Gold Price Updater Error: Invalid price data');
    }
}

function gpu_update_product_prices($price_gram_24k) {
    $options = get_option('gpu_settings');
    gpu_log("Starting product price update...");

    for ($i = 1; $i <= 4; $i++) {
        if (isset($options['gpu_product_id_' . $i]) && !empty($options['gpu_product_id_' . $i]) &&
            isset($options['gpu_margin_' . $i]) && $options['gpu_margin_' . $i] !== '') {
            $product_id = $options['gpu_product_id_' . $i];
            $margin = $options['gpu_margin_' . $i];

            $product = wc_get_product($product_id);
            if ($product) {
                $weight = $product->get_weight();
                gpu_log("Product ID $product_id weight: $weight grams");
                if ($weight) {
                    $new_price = $price_gram_24k * $weight * (1 + ($margin / 100));
                    gpu_log("Calculated new price for product ID $product_id: $new_price");
                    ppu_update_product_price($product_id, $new_price);
                    gpu_log("Updated product ID $product_id with new price $new_price");

                    // Verifying that the price has been updated
                    $updated_product = wc_get_product($product_id);
                    if ($updated_product && $updated_product->get_price() == $new_price) {
                        gpu_log("Price verification successful for product ID $product_id: $new_price");
                    } else {
                        gpu_log("Price verification failed for product ID $product_id. Expected: $new_price, Actual: " . $updated_product->get_price());
                    }
                } else {
                    gpu_log("Gold Price Updater Error: Product ID $product_id has no weight set");
                }
            } else {
                gpu_log("Gold Price Updater Error: Invalid product ID $product_id");
            }
        } else {
            gpu_log("Gold Price Updater Error: Missing or invalid settings for product ID $i");
            gpu_log("gpu_product_id_$i: " . (isset($options['gpu_product_id_' . $i]) ? $options['gpu_product_id_' . $i] : 'Not set'));
            gpu_log("gpu_margin_$i: " . (isset($options['gpu_margin_' . $i]) ? $options['gpu_margin_' . $i] : 'Not set'));
        }
    }
}

// Function to update the product price
function ppu_update_product_price($product_id, $new_price) {
    if (class_exists('WooCommerce')) {
        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_price($new_price);
            $product->set_regular_price($new_price);
            $product->save();
            gpu_log("Price updated successfully for product ID $product_id to $new_price.");

            // Invalidate WooCommerce cache
            wc_delete_product_transients($product_id);
            clean_post_cache($product_id);
            wp_cache_flush();  // Clear entire cache if necessary
        } else {
            gpu_log("Invalid product ID $product_id.");
        }
    } else {
        gpu_log("WooCommerce is not active.");
    }
}

function gpu_manual_update() {
    if (current_user_can('manage_options')) {
        gpu_update_gold_prices();
        wp_send_json_success('Gold prices updated.');
    } else {
        wp_send_json_error('Unauthorized.');
    }
}

function change_prices($ids, $choice, $value, $operation, $enable_translations = false) {
    gpu_log("Changing prices for products: " . implode(", ", $ids));
    foreach ($ids as $product) {
        $product_retrieved = wc_get_product($product);
        $new_price = calculate_final_price((float)$product_retrieved->get_regular_price(), $choice, $value, $operation);
        set_prices($product_retrieved, $new_price, $choice, $enable_translations);
        gpu_log("Set new price for product ID $product: $new_price");
    }
}

function set_prices($product, $new_price, $choice, $enable_translations = false) {
    if ($enable_translations) {
        $wpml_trid = apply_filters('wpml_element_trid', '', $product->get_id());
        $wpml_product_translations = apply_filters('wpml_get_element_translations', '', $wpml_trid);

        foreach ($wpml_product_translations as $translation) {
            $product_translation = wc_get_product($translation->element_id);
            if ($choice == 'inc') {
                $product_translation->set_price($new_price);
                $product_translation->set_regular_price($new_price);
            } else {
                $product_translation->set_sale_price($new_price);
            }
            $product_translation->save();
            gpu_log("Updated translated product ID " . $translation->element_id . " with new price $new_price");
        }
    } else {
        if ($choice == 'inc') {
            $product->set_price($new_price);
            $product->set_regular_price($new_price);
        } else {
            $product->set_sale_price($new_price);
        }
        $product->save();
        gpu_log("Updated product ID " . $product->get_id() . " with new price $new_price");
    }

    // Clear the object cache for the product
    wc_delete_product_transients($product->get_id());
    clean_post_cache($product->get_id());
    wp_cache_flush();  // Clear entire cache if necessary
}

function remove_prices($ids, $choice, $value, $operation, $enable_translations = false) {
    gpu_log("Removing price changes for products: " . implode(", ", $ids));
    foreach ($ids as $product) {
        $product_retrieved = wc_get_product($product);
        $product_retrieved_price = (float)$product_retrieved->get_regular_price();

        if ($enable_translations) {
            $wpml_trid = apply_filters('wpml_element_trid', '', $product_retrieved->get_id());
            $wpml_product_translations = apply_filters('wpml_get_element_translations', '', $wpml_trid);

            foreach ($wpml_product_translations as $translation) {
                $product_translation = wc_get_product($translation->element_id);
                if ($choice == 'inc') {
                    if ($operation == 'percentage') {
                        $product_translation->set_price(sprintf("%.2f", ($product_retrieved_price / (1 + ($value / 100)))));
                        $product_translation->set_regular_price(sprintf("%.2f", ($product_retrieved_price / (1 + ($value / 100)))));
                    } else {
                        $product_translation->set_price(sprintf("%.2f", $product_retrieved_price - $value));
                        $product_translation->set_regular_price(sprintf("%.2f", $product_retrieved_price - $value));
                    }
                } else {
                    $product_translation->set_sale_price('');
                }
                $product_translation->save();
                gpu_log("Reverted translated product ID " . $translation->element_id . " to original price");
            }
        } else {
            if ($choice == 'inc') {
                if ($operation == 'percentage') {
                    $product_retrieved->set_price(sprintf("%.2f", ($product_retrieved_price / (1 + ($value / 100)))));
                    $product_retrieved->set_regular_price(sprintf("%.2f", ($product_retrieved_price / (1 + ($value / 100)))));
                } else {
                    $product_retrieved->set_price(sprintf("%.2f", $product_retrieved_price - $value));
                    $product_retrieved->set_regular_price(sprintf("%.2f", $product_retrieved_price - $value));
                }
            } else {
                $product_retrieved->set_sale_price('');
            }
            $product_retrieved->save();
            gpu_log("Reverted product ID " . $product->get_id() . " to original price");
        }

        // Clear the object cache for the product
        wc_delete_product_transients($product_retrieved->get_id());
        clean_post_cache($product_retrieved->get_id());
        wp_cache_flush();  // Clear entire cache if necessary
    }
}

function calculate_final_price($price, $choice, $value, $operation) {
    if ($operation == 'percentage') {
        $value = ($price / 100) * $value;
    }
    if ($choice == 'inc') {
        return sprintf("%.2f", $price + $value);
    } else {
        return sprintf("%.2f", $price - $value);
    }
}

?>
