<?php
/**
 * Plugin Name: ACL Media Renamer
 * Description: Renames media files by removing the "®" symbol from filenames and updates the WordPress database accordingly.
 * Version: 1.0
 * Author: Charly Leetham
 */

// Ensure this file is accessed within WordPress
if ( ! defined( 'ABSPATH' ) ) exit;

class ACLMediaFileRenamer {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_rename_action' ) );
    }

    // Add menu for the plugin in the WordPress admin
    public function add_admin_menu() {
        add_submenu_page(
            'upload.php',
            'ACL Media Renamer',
            'File Renamer',
            'manage_options',
            'acl-file-renamer',
            array( $this, 'plugin_page' )
        );
    }

    // Plugin page output
    public function plugin_page() {
        ?>
        <div class="wrap">
            <h2>ACL Media Renamer</h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'rename_files', 'rename_files_nonce' ); ?>
                <input type="submit" name="rename_files" class="button button-primary" value="Rename Files">
            </form>
        </div>
        <?php
    }

    // Handle the renaming action
    public function handle_rename_action() {
        if ( isset( $_POST['rename_files'] ) && check_admin_referer( 'rename_files', 'rename_files_nonce' ) ) {
            $this->rename_files();
        }
    }

    // Function to rename files and update database
    public function rename_files() {
        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        );

        $attachments = get_posts( $args );

        foreach ( $attachments as $attachment ) {
            $file = get_attached_file( $attachment->ID );
            $info = pathinfo( $file );
            $new_name = str_replace( '®', '', $info['filename'] );
            $new_file = $info['dirname'] . '/' . $new_name . '.' . $info['extension'];

            if ( $file !== $new_file && file_exists( $file ) ) {
                // Rename the file on the server
                if ( rename( $file, $new_file ) ) {
                    // Update WordPress database
                    $meta = wp_get_attachment_metadata( $attachment->ID );
                    update_attached_file( $attachment->ID, $new_file );
                    
                    // Update GUID if it contains the old filename
                    if ( strpos( $attachment->guid, $info['basename'] ) !== false ) {
                        $new_guid = str_replace( $info['basename'], $new_name . '.' . $info['extension'], $attachment->guid );
                        wp_update_post( array( 'ID' => $attachment->ID, 'guid' => $new_guid ) );
                    }
                    
                    // Update metadata paths
                    if ( is_array( $meta ) ) {
                        $meta = $this->update_metadata_paths( $meta, $info['basename'], $new_name . '.' . $info['extension'] );
                        wp_update_attachment_metadata( $attachment->ID, $meta );
                    }
                    
                    echo "Renamed: {$info['basename']} to {$new_name}.{$info['extension']}<br>";
                } else {
                    echo "Failed to rename: " . $info['basename'] . "<br>";
                }
            }
        }
    }

    // Helper function to update paths in metadata
    private function update_metadata_paths( $meta, $old_name, $new_name ) {
        if ( isset( $meta['file'] ) ) {
            $meta['file'] = str_replace( $old_name, $new_name, $meta['file'] );
        }
        if ( isset( $meta['sizes'] ) ) {
            foreach ( $meta['sizes'] as &$size ) {
                if ( isset( $size['file'] ) ) {
                    $size['file'] = str_replace( $old_name, $new_name, $size['file'] );
                }
            }
        }
        return $meta;
    }
}

new ACLMediaFileRenamer();

?>