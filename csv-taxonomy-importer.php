<?php
/**
 * Plugin Name: CSV Taxonomy Importer
 * Plugin URI: https://bryaneutsler.com/wordpress/csv-taxonomy-importer
 * Description: Import categories and tags from CSV files with ease
 * Version: 1.0.0
 * Author: Bryan Eutsler
 * Author URL: https://bryaneutsler.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CSV_Taxonomy_Importer {
    
    /**
     * Constructor - Initialize the plugin
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_csv_upload'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    /**
     * Add custom CSS for better UI
     */
    public function enqueue_admin_styles($hook) {
        if ('tools_page_csv-taxonomy-importer' !== $hook) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
            .cti-upload-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
                max-width: 600px;
            }
            .cti-upload-box h3 {
                margin-top: 0;
                color: #1d2327;
            }
            .cti-info-box {
                background: #f0f6fc;
                border-left: 4px solid #2271b1;
                padding: 12px;
                margin: 15px 0;
            }
            .cti-example {
                background: #f6f7f7;
                padding: 10px;
                border-radius: 3px;
                font-family: monospace;
                margin: 10px 0;
            }
        ');
    }
    
    /**
     * Add menu item to WordPress admin
     */
    public function add_admin_menu() {
        add_management_page(
            'CSV Taxonomy Importer',
            'CSV Importer',
            'manage_options',
            'csv-taxonomy-importer',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Display the admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>CSV Taxonomy Importer</h1>
            <p>Import categories and tags from CSV files. Make bulk taxonomy management simple and efficient.</p>
            
            <div class="cti-info-box">
                <strong>CSV Format Requirements:</strong>
                <ul style="margin: 10px 0;">
                    <li>Your CSV file should have a header row</li>
                    <li>Required column: <code>name</code></li>
                    <li>Optional columns: <code>slug</code>, <code>description</code>, <code>parent</code></li>
                    <li>For parent categories, use the parent category name or slug</li>
                </ul>
                
                <strong>Example CSV format:</strong>
                <div class="cti-example">
                    name,slug,description,parent<br>
                    Technology,technology,Tech related posts,<br>
                    Web Development,web-dev,Website development topics,Technology<br>
                    Design,design,Design and creativity posts,
                </div>
            </div>
            
            <!-- Categories Upload -->
            <div class="cti-upload-box">
                <h3>Import Categories</h3>
                <p>Upload a CSV file to import WordPress categories.</p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('csv_taxonomy_import', 'csv_taxonomy_nonce'); ?>
                    <input type="hidden" name="taxonomy_type" value="category">
                    
                    <p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </p>
                    
                    <p>
                        <input type="submit" name="import_csv" class="button button-primary" value="Import Categories">
                    </p>
                </form>
            </div>
            
            <!-- Tags Upload -->
            <div class="cti-upload-box">
                <h3>Import Tags</h3>
                <p>Upload a CSV file to import WordPress tags.</p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('csv_taxonomy_import', 'csv_taxonomy_nonce'); ?>
                    <input type="hidden" name="taxonomy_type" value="post_tag">
                    
                    <p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </p>
                    
                    <p>
                        <input type="submit" name="import_csv" class="button button-primary" value="Import Tags">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle CSV file upload and import
     */
    public function handle_csv_upload() {
        // Check if form was submitted
        if (!isset($_POST['import_csv'])) {
            return;
        }
        
        // Verify nonce for security
        if (!isset($_POST['csv_taxonomy_nonce']) || !wp_verify_nonce($_POST['csv_taxonomy_nonce'], 'csv_taxonomy_import')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
            });
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>You do not have permission to import taxonomies.</p></div>';
            });
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Error uploading file. Please try again.</p></div>';
            });
            return;
        }
        
        // Get taxonomy type
        $taxonomy_type = isset($_POST['taxonomy_type']) ? sanitize_text_field($_POST['taxonomy_type']) : 'category';
        
        // Validate taxonomy type
        if (!in_array($taxonomy_type, array('category', 'post_tag'))) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Invalid taxonomy type.</p></div>';
            });
            return;
        }
        
        // Process the CSV file
        $file_path = $_FILES['csv_file']['tmp_name'];
        $result = $this->import_csv($file_path, $taxonomy_type);
        
        // Display result message
        if ($result['success']) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Import completed successfully!</strong></p>';
                echo '<p>Created: ' . $result['created'] . ' | Skipped: ' . $result['skipped'] . ' | Errors: ' . $result['errors'] . '</p>';
                echo '</div>';
            });
        } else {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Import failed:</strong> ' . esc_html($result['message']) . '</p>';
                echo '</div>';
            });
        }
    }
    
    /**
     * Import CSV file and create taxonomy terms
     */
    private function import_csv($file_path, $taxonomy_type) {
        $created = 0;
        $skipped = 0;
        $errors = 0;
        
        // Open CSV file
        $file = fopen($file_path, 'r');
        if (!$file) {
            return array(
                'success' => false,
                'message' => 'Could not open CSV file'
            );
        }
        
        // Get header row
        $headers = fgetcsv($file);
        if (!$headers) {
            fclose($file);
            return array(
                'success' => false,
                'message' => 'CSV file is empty or invalid'
            );
        }
        
        // Normalize headers (trim and lowercase)
        $headers = array_map(function($header) {
            return strtolower(trim($header));
        }, $headers);
        
        // Check for required 'name' column
        $name_index = array_search('name', $headers);
        if ($name_index === false) {
            fclose($file);
            return array(
                'success' => false,
                'message' => 'CSV must have a "name" column'
            );
        }
        
        // Get optional column indices
        $slug_index = array_search('slug', $headers);
        $description_index = array_search('description', $headers);
        $parent_index = array_search('parent', $headers);
        
        // Store created terms for parent reference
        $created_terms = array();
        
        // Process each row
        while (($row = fgetcsv($file)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Get term data
            $name = isset($row[$name_index]) ? trim($row[$name_index]) : '';
            
            // Skip if no name
            if (empty($name)) {
                $skipped++;
                continue;
            }
            
            // Prepare term arguments
            $args = array();
            
            // Add slug if provided
            if ($slug_index !== false && !empty($row[$slug_index])) {
                $args['slug'] = sanitize_title($row[$slug_index]);
            }
            
            // Add description if provided
            if ($description_index !== false && !empty($row[$description_index])) {
                $args['description'] = sanitize_text_field($row[$description_index]);
            }
            
            // Handle parent for categories
            if ($taxonomy_type === 'category' && $parent_index !== false && !empty($row[$parent_index])) {
                $parent_name = trim($row[$parent_index]);
                
                // Try to find parent by name or slug
                $parent_term = term_exists($parent_name, $taxonomy_type);
                
                // Check in our created terms array
                if (!$parent_term && isset($created_terms[$parent_name])) {
                    $parent_term = $created_terms[$parent_name];
                }
                
                if ($parent_term) {
                    if (is_array($parent_term)) {
                        $args['parent'] = $parent_term['term_id'];
                    } else {
                        $args['parent'] = $parent_term;
                    }
                }
            }
            
            // Check if term already exists
            $existing_term = term_exists($name, $taxonomy_type);
            
            if ($existing_term) {
                $skipped++;
                // Store for parent reference
                $created_terms[$name] = $existing_term;
                continue;
            }
            
            // Insert the term
            $result = wp_insert_term($name, $taxonomy_type, $args);
            
            if (is_wp_error($result)) {
                $errors++;
            } else {
                $created++;
                // Store for parent reference
                $created_terms[$name] = $result;
            }
        }
        
        fclose($file);
        
        return array(
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        );
    }
}

// Initialize the plugin
new CSV_Taxonomy_Importer();
