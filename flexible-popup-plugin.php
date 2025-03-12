<?php
/*
Plugin Name: Flexible Popup Plugin
Description: Display flexible popups with scheduling, multilingual support, advanced display conditions, and secure admin actions.
Version: 1.0.1
Author: Adi Kica
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =======================
   DATABASE INSTALLATION & UPDATE
========================== */
register_activation_hook( __FILE__, 'fpp_install' );
function fpp_install() {
    global $wpdb;
    $table = $wpdb->prefix . 'fpp_popups';
    $charset_collate = $wpdb->get_charset_collate();

    // Base schema with languages field included.
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        content longtext NOT NULL,
        display_pages text NOT NULL,
        display_once tinyint(1) NOT NULL DEFAULT 0,
        device_targeting varchar(20) NOT NULL DEFAULT 'both',
        trigger_type varchar(20) NOT NULL DEFAULT 'immediate',
        trigger_value varchar(50) DEFAULT '',
        show_animation varchar(50) DEFAULT 'zoom-in',
        hide_animation varchar(50) DEFAULT 'zoom-out',
        positioning varchar(50) DEFAULT 'center',
        start_datetime datetime DEFAULT NULL,
        end_datetime datetime DEFAULT NULL,
        display_always tinyint(1) NOT NULL DEFAULT 0,
        languages text NOT NULL,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Update table to add new columns if missing.
    fpp_update_table_columns();
}

function fpp_update_table_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'fpp_popups';

    // Retrieve existing columns.
    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM $table", 0 );

    // Define new columns and their SQL definitions.
    $new_columns = array(
        'start_datetime'  => "datetime DEFAULT NULL",
        'end_datetime'    => "datetime DEFAULT NULL",
        'display_always'  => "tinyint(1) NOT NULL DEFAULT 0",
        'languages'       => "text NOT NULL"
    );

    // Loop and add missing columns.
    foreach ( $new_columns as $column => $definition ) {
        if ( ! in_array( $column, $existing_columns ) ) {
            $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
            $wpdb->query( $sql );
        }
    }
}

/* =======================
       ADMIN INTERFACE
========================== */
add_action( 'admin_menu', 'fpp_admin_menu' );
function fpp_admin_menu() {
    add_menu_page(
        'Flexible Popups',
        'Flexible Popups',
        'manage_options',
        'fpp_popups',
        'fpp_render_admin_page',
        'dashicons-format-gallery',
        20
    );
}

function fpp_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'fpp_popups';

    // Process form submissions.
    if ( isset( $_POST['fpp_action'] ) ) {
        // Verify nonce.
        if ( ! isset( $_POST['fpp_nonce'] ) || ! wp_verify_nonce( $_POST['fpp_nonce'], 'fpp_nonce_action' ) ) {
            echo '<div class="error"><p>Security check failed. Please try again.</p></div>';
            return;
        }
        $action = sanitize_text_field( $_POST['fpp_action'] );

        if ( 'delete' === $action && ! empty( $_POST['fpp_id'] ) ) {
            $wpdb->delete( $table, array( 'id' => intval( $_POST['fpp_id'] ) ) );
            echo '<div class="updated"><p>Popup deleted.</p></div>';
        } elseif ( 'save' === $action ) {
            // Get languages array from the checkboxes.
            $languages = isset($_POST['fpp_languages']) ? array_map('sanitize_text_field', $_POST['fpp_languages']) : array();
            // Default to "all" if nothing is selected.
            if ( empty( $languages ) ) {
                $languages = array('all');
            }
            $data = array(
                'title'            => sanitize_text_field( $_POST['fpp_title'] ),
                'content'          => wp_unslash( $_POST['fpp_content'] ),
                'display_pages'    => sanitize_text_field( $_POST['fpp_display_pages'] ),
                'display_once'     => isset( $_POST['fpp_display_once'] ) ? 1 : 0,
                'device_targeting' => sanitize_text_field( $_POST['fpp_device_targeting'] ),
                'trigger_type'     => sanitize_text_field( $_POST['fpp_trigger_type'] ),
                'trigger_value'    => sanitize_text_field( $_POST['fpp_trigger_value'] ),
                'show_animation'   => sanitize_text_field( $_POST['fpp_show_animation'] ),
                'hide_animation'   => sanitize_text_field( $_POST['fpp_hide_animation'] ),
                'positioning'      => sanitize_text_field( $_POST['fpp_positioning'] ),
                'start_datetime'   => ! empty( $_POST['fpp_start_datetime'] ) ? sanitize_text_field( $_POST['fpp_start_datetime'] ) : null,
                'end_datetime'     => ! empty( $_POST['fpp_end_datetime'] ) ? sanitize_text_field( $_POST['fpp_end_datetime'] ) : null,
                'display_always'   => isset( $_POST['fpp_display_always'] ) ? 1 : 0,
                'languages'        => maybe_serialize( $languages ),
                'active'           => isset( $_POST['fpp_active'] ) ? 1 : 0,
            );
            if ( ! empty( $_POST['fpp_id'] ) ) {
                $wpdb->update( $table, $data, array( 'id' => intval( $_POST['fpp_id'] ) ) );
                echo '<div class="updated"><p>Popup updated.</p></div>';
            } else {
                $wpdb->insert( $table, $data );
                echo '<div class="updated"><p>Popup created.</p></div>';
            }
        } elseif ( 'toggle' === $action && ! empty( $_POST['fpp_id'] ) ) {
            $id = intval( $_POST['fpp_id'] );
            $popup = $wpdb->get_row( $wpdb->prepare( "SELECT active FROM $table WHERE id = %d", $id ) );
            $new_status = $popup->active ? 0 : 1;
            $wpdb->update( $table, array( 'active' => $new_status ), array( 'id' => $id ) );
            echo '<div class="updated"><p>Popup status toggled.</p></div>';
        }
    }
    
    // Check if editing a popup.
    $edit_popup = null;
    if ( isset( $_GET['edit'] ) ) {
        $id = intval( $_GET['edit'] );
        $edit_popup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
    }
    ?>
    <div class="wrap">
      <h1>Flexible Popups</h1>
      <h2><?php echo $edit_popup ? 'Edit Popup' : 'Add New Popup'; ?></h2>
      <form method="post">
        <?php wp_nonce_field( 'fpp_nonce_action', 'fpp_nonce' ); ?>
        <input type="hidden" name="fpp_action" value="save">
        <input type="hidden" name="fpp_id" value="<?php echo $edit_popup ? esc_attr( $edit_popup->id ) : ''; ?>">
        <table class="form-table">
          <tr>
            <th scope="row"><label for="fpp_title">Title</label></th>
            <td><input name="fpp_title" type="text" id="fpp_title" value="<?php echo $edit_popup ? esc_attr( $edit_popup->title ) : ''; ?>" class="regular-text" required></td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_content">Content</label></th>
            <td><?php
                wp_editor( $edit_popup ? wp_unslash( $edit_popup->content ) : '', 'fpp_content', array( 'textarea_name' => 'fpp_content' ) );
            ?></td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_display_pages">Display Pages</label></th>
            <td>
              <input name="fpp_display_pages" type="text" id="fpp_display_pages" value="<?php echo $edit_popup ? esc_attr( $edit_popup->display_pages ) : 'all'; ?>" class="regular-text">
              <p class="description">Enter "all" for all pages, "homepage" for homepage only, or comma separated page IDs.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Display Once Per Session</th>
            <td>
              <label><input name="fpp_display_once" type="checkbox" <?php echo $edit_popup && $edit_popup->display_once ? 'checked' : ''; ?>> Yes</label>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_device_targeting">Device Targeting</label></th>
            <td>
              <select name="fpp_device_targeting" id="fpp_device_targeting">
                <option value="both" <?php selected( $edit_popup ? $edit_popup->device_targeting : '', 'both' ); ?>>Both</option>
                <option value="desktop" <?php selected( $edit_popup ? $edit_popup->device_targeting : '', 'desktop' ); ?>>Desktop Only</option>
                <option value="mobile" <?php selected( $edit_popup ? $edit_popup->device_targeting : '', 'mobile' ); ?>>Mobile Only</option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_trigger_type">Trigger Type</label></th>
            <td>
              <select name="fpp_trigger_type" id="fpp_trigger_type">
                <option value="immediate" <?php selected( $edit_popup ? $edit_popup->trigger_type : '', 'immediate' ); ?>>Immediate</option>
                <option value="delay" <?php selected( $edit_popup ? $edit_popup->trigger_type : '', 'delay' ); ?>>Delay</option>
                <option value="click" <?php selected( $edit_popup ? $edit_popup->trigger_type : '', 'click' ); ?>>Click</option>
              </select>
              <p class="description">If Delay, set seconds; if Click, set trigger element ID.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_trigger_value">Trigger Value</label></th>
            <td>
              <input name="fpp_trigger_value" type="text" id="fpp_trigger_value" value="<?php echo $edit_popup ? esc_attr( $edit_popup->trigger_value ) : ''; ?>" class="regular-text">
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_show_animation">Show Animation</label></th>
            <td>
              <select name="fpp_show_animation" id="fpp_show_animation">
                <option value="zoom-in" <?php selected( $edit_popup ? $edit_popup->show_animation : '', 'zoom-in' ); ?>>Zoom In</option>
                <option value="slide-left" <?php selected( $edit_popup ? $edit_popup->show_animation : '', 'slide-left' ); ?>>Slide Left</option>
                <option value="slide-top" <?php selected( $edit_popup ? $edit_popup->show_animation : '', 'slide-top' ); ?>>Slide Top</option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_hide_animation">Hide Animation</label></th>
            <td>
              <select name="fpp_hide_animation" id="fpp_hide_animation">
                <option value="zoom-out" <?php selected( $edit_popup ? $edit_popup->hide_animation : '', 'zoom-out' ); ?>>Zoom Out</option>
                <option value="slide-left" <?php selected( $edit_popup ? $edit_popup->hide_animation : '', 'slide-left' ); ?>>Slide Left</option>
                <option value="slide-top" <?php selected( $edit_popup ? $edit_popup->hide_animation : '', 'slide-top' ); ?>>Slide Top</option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_positioning">Positioning</label></th>
            <td>
              <select name="fpp_positioning" id="fpp_positioning">
                <option value="center" <?php selected( $edit_popup ? $edit_popup->positioning : '', 'center' ); ?>>Center</option>
                <option value="left" <?php selected( $edit_popup ? $edit_popup->positioning : '', 'left' ); ?>>Left</option>
                <option value="right" <?php selected( $edit_popup ? $edit_popup->positioning : '', 'right' ); ?>>Right</option>
                <option value="top" <?php selected( $edit_popup ? $edit_popup->positioning : '', 'top' ); ?>>Top</option>
                <option value="bottom" <?php selected( $edit_popup ? $edit_popup->positioning : '', 'bottom' ); ?>>Bottom</option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_start_datetime">Start Date &amp; Time</label></th>
            <td>
              <input type="datetime-local" name="fpp_start_datetime" id="fpp_start_datetime" value="<?php echo $edit_popup && $edit_popup->start_datetime ? date('Y-m-d\TH:i', strtotime($edit_popup->start_datetime)) : ''; ?>" class="regular-text">
              <p class="description">Optional: When the popup should start displaying.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="fpp_end_datetime">End Date &amp; Time</label></th>
            <td>
              <input type="datetime-local" name="fpp_end_datetime" id="fpp_end_datetime" value="<?php echo $edit_popup && $edit_popup->end_datetime ? date('Y-m-d\TH:i', strtotime($edit_popup->end_datetime)) : ''; ?>" class="regular-text">
              <p class="description">Optional: When the popup should stop displaying.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Display Always</th>
            <td>
              <label><input type="checkbox" name="fpp_display_always" <?php echo $edit_popup && $edit_popup->display_always ? 'checked' : ''; ?>> Always display regardless of start/end time</label>
            </td>
          </tr>
          <tr>
            <th scope="row">Display in Languages</th>
            <td>
              <?php
              // You can auto-detect languages from a multilingual plugin.
              // Here, we define a fixed array for demonstration.
              $available_languages = array(
                  'all' => 'All Languages',
                  'en'  => 'English',
                  'fr'  => 'French',
                  'it'  => 'Italian',
                  'de'  => 'German'
              );
              // Get selected languages if editing.
              $selected_languages = $edit_popup && ! empty($edit_popup->languages) ? maybe_unserialize($edit_popup->languages) : array('all');
              foreach ($available_languages as $lang_code => $lang_name) {
                  $checked = in_array($lang_code, $selected_languages) ? 'checked' : '';
                  echo '<label style="margin-right:10px;"><input type="checkbox" name="fpp_languages[]" value="' . esc_attr($lang_code) . '" ' . $checked . '> ' . esc_html($lang_name) . '</label>';
              }
              ?>
            </td>
          </tr>
          <tr>
            <th scope="row">Active</th>
            <td>
              <label><input name="fpp_active" type="checkbox" <?php echo $edit_popup && $edit_popup->active ? 'checked' : ''; ?>> Yes</label>
            </td>
          </tr>
        </table>
        <?php submit_button( $edit_popup ? 'Update Popup' : 'Create Popup' ); ?>
      </form>
      <h2>Existing Popups</h2>
      <?php
         $popups = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
         if ( $popups ) :
      ?>
      <table class="widefat fixed striped">
         <thead>
            <tr>
               <th>ID</th>
               <th>Title</th>
               <th>Display Pages</th>
               <th>Device</th>
               <th>Trigger</th>
               <th>Schedule</th>
               <th>Always</th>
               <th>Languages</th>
               <th>Active</th>
               <th>Actions</th>
            </tr>
         </thead>
         <tbody>
         <?php foreach ( $popups as $popup ) : ?>
            <tr>
               <td><?php echo esc_html( $popup->id ); ?></td>
               <td><?php echo esc_html( $popup->title ); ?></td>
               <td><?php echo esc_html( $popup->display_pages ); ?></td>
               <td><?php echo esc_html( $popup->device_targeting ); ?></td>
               <td><?php echo esc_html( $popup->trigger_type ) . ( $popup->trigger_value ? " (" . esc_html( $popup->trigger_value ) . ")" : '' ); ?></td>
               <td>
                 <?php
                   echo $popup->start_datetime ? esc_html( $popup->start_datetime ) : 'N/A';
                   echo ' to ';
                   echo $popup->end_datetime ? esc_html( $popup->end_datetime ) : 'N/A';
                 ?>
               </td>
               <td><?php echo $popup->display_always ? 'Yes' : 'No'; ?></td>
               <td>
                 <?php
                 $langs = maybe_unserialize($popup->languages);
                 echo is_array($langs) ? implode(', ', $langs) : '';
                 ?>
               </td>
               <td><?php echo $popup->active ? 'Yes' : 'No'; ?></td>
               <td>
                  <a href="<?php echo admin_url( 'admin.php?page=fpp_popups&edit=' . $popup->id ); ?>">Edit</a> |
                  <form style="display:inline;" method="post" onsubmit="return confirm('Delete this popup?');">
                     <?php wp_nonce_field( 'fpp_nonce_action', 'fpp_nonce' ); ?>
                     <input type="hidden" name="fpp_action" value="delete">
                     <input type="hidden" name="fpp_id" value="<?php echo esc_attr( $popup->id ); ?>">
                     <button type="submit" style="background:none;border:none;color:red;">Delete</button>
                  </form> |
                  <form style="display:inline;" method="post">
                     <?php wp_nonce_field( 'fpp_nonce_action', 'fpp_nonce' ); ?>
                     <input type="hidden" name="fpp_action" value="toggle">
                     <input type="hidden" name="fpp_id" value="<?php echo esc_attr( $popup->id ); ?>">
                     <button type="submit" style="background:none;border:none;color:blue;"><?php echo $popup->active ? 'Deactivate' : 'Activate'; ?></button>
                  </form>
               </td>
            </tr>
         <?php endforeach; ?>
         </tbody>
      </table>
      <?php else: ?>
         <p>No popups found.</p>
      <?php endif; ?>
    </div>
    <?php
}

/* =======================
       FRONTEND OUTPUT
========================== */
add_action( 'wp_footer', 'fpp_render_popups' );
function fpp_render_popups() {
    if ( is_admin() ) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'fpp_popups';
    $popups = $wpdb->get_results( "SELECT * FROM $table WHERE active = 1" );
    if ( ! $popups ) {
        return;
    }
    $current_page_id = get_the_ID();
    $is_homepage     = is_front_page() || is_home();
    $current_datetime = current_time( 'mysql' );
    // Use get_locale() or a multilingual plugin function if available.
    $current_lang = get_locale();
    ?>
    <style>
    .fpp-popup-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    .fpp-popup-content {
      background: #fff;
      padding: 20px;
      position: relative;
      border-radius: 5px;
      height: 100%;
      max-width: 90%;
      max-height: 90%;
     /* overflow-y: auto;*/
    }
    .fpp-popup-close {
      position: absolute;
      top: 0;
      right: 0;
      background: #cccccc00;
      border: none;
      padding: 5px;
      cursor: pointer;
    }
    .fpp-popup-inner-content,
    .fpp-popup-inner-content img{
      height: 100%;
    }
    /* Positioning classes */
    .fpp-position-center { margin: auto; }
    .fpp-position-left { margin: auto auto auto 0; }
    .fpp-position-right { margin: auto 0 auto auto; }
    .fpp-position-top { align-self: flex-start; }
    .fpp-position-bottom { align-self: flex-end; }
    /* Animations */
    @keyframes fpp-zoom-in {
      from { transform: scale(0.5); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    @keyframes fpp-zoom-out {
      from { transform: scale(1); opacity: 1; }
      to { transform: scale(0.5); opacity: 0; }
    }
    @keyframes fpp-slide-left {
      from { transform: translateX(-100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fpp-slide-top {
      from { transform: translateY(-100%); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    .fpp-show-zoom-in { animation: fpp-zoom-in 0.5s forwards; }
    .fpp-show-slide-left { animation: fpp-slide-left 0.5s forwards; }
    .fpp-show-slide-top { animation: fpp-slide-top 0.5s forwards; }
    .fpp-hide-zoom-out { animation: fpp-zoom-out 0.5s forwards; }
    </style>
    <?php
    // Loop through each active popup and check display conditions.
    foreach ( $popups as $popup ) {

        // Check display_pages condition.
        $display = false;
        $pages   = trim( $popup->display_pages );
        if ( 'all' === $pages ) {
            $display = true;
        } elseif ( 'homepage' === $pages && $is_homepage ) {
            $display = true;
        } elseif ( is_numeric( $pages ) ) {
            if ( $current_page_id == intval( $pages ) ) {
                $display = true;
            }
        } else {
            // Assume comma separated page IDs.
            $page_ids = array_map( 'trim', explode( ',', $pages ) );
            if ( in_array( $current_page_id, $page_ids ) ) {
                $display = true;
            }
        }
        if ( ! $display ) {
            continue;
        }
        
        // Device targeting conditions.
        if ( 'mobile' === $popup->device_targeting && ! wp_is_mobile() ) {
            continue;
        }
        if ( 'desktop' === $popup->device_targeting && wp_is_mobile() ) {
            continue;
        }
        
        // Check scheduling: if not "display always", ensure current time is within the range.
        if ( ! $popup->display_always ) {
            if ( $popup->start_datetime && strtotime($current_datetime) < strtotime($popup->start_datetime) ) {
                continue;
            }
            if ( $popup->end_datetime && strtotime($current_datetime) > strtotime($popup->end_datetime) ) {
                continue;
            }
        }
        
        // Check language conditions.
        $popup_languages = maybe_unserialize($popup->languages);
        // If "all" is selected, display on any language.
        if ( ! in_array( 'all', $popup_languages ) ) {
            if ( ! in_array( $current_lang, $popup_languages ) ) {
                continue;
            }
        }
        
        // Prepare data attributes for JavaScript.
        $data_trigger_type  = esc_attr( $popup->trigger_type );
        $data_trigger_value = esc_attr( $popup->trigger_value );
        $data_show_animation = 'fpp-show-' . esc_attr( $popup->show_animation );
        $data_hide_animation = 'fpp-hide-' . esc_attr( $popup->hide_animation );
        $data_display_once   = intval( $popup->display_once );
        $data_popup_id       = esc_attr( $popup->id );
        $position_class      = 'fpp-position-' . esc_attr( $popup->positioning );
        ?>
        <div class="fpp-popup-overlay" id="fpp-popup-overlay-<?php echo $popup->id; ?>"
             data-trigger-type="<?php echo $data_trigger_type; ?>"
             data-trigger-value="<?php echo $data_trigger_value; ?>"
             data-show-animation="<?php echo $data_show_animation; ?>"
             data-hide-animation="<?php echo $data_hide_animation; ?>"
             data-display-once="<?php echo $data_display_once; ?>"
             data-popup-id="<?php echo $data_popup_id; ?>">
          <div class="fpp-popup-content <?php echo $position_class; ?>">
            <button class="fpp-popup-close">X</button>
            <div class="fpp-popup-inner-content">
              <?php echo wp_kses_post( $popup->content ); ?>
            </div>
          </div>
        </div>
        <?php
    }
    // JavaScript to handle popup display, triggers, animations, and close events.
    ?>
    <script>
    (function(){
      // Helper functions to get/set cookie (for display once per session)
      function getCookie(name) {
          let matches = document.cookie.match(new RegExp(
              "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
          ));
          return matches ? decodeURIComponent(matches[1]) : undefined;
      }
      function setCookie(name, value) {
          let d = new Date();
          d.setTime(d.getTime() + (24*60*60*1000));
          document.cookie = name + "=" + value + ";expires=" + d.toUTCString() + ";path=/";
      }
      
      // Show popup function (adds the show animation class)
      function showPopup(overlay) {
          var popupId = overlay.getAttribute('data-popup-id');
          if (overlay.getAttribute('data-display-once') == "1" && getCookie("fpp_popup_shown_" + popupId)) {
              return;
          }
          overlay.style.display = "flex";
          var showAnim = overlay.getAttribute('data-show-animation');
          var content = overlay.querySelector('.fpp-popup-content');
          if (showAnim) {
              content.classList.add(showAnim);
          }
      }
      
      // Hide popup function (adds the hide animation class and then hides the overlay)
      function hidePopup(overlay) {
          var hideAnim = overlay.getAttribute('data-hide-animation');
          var content = overlay.querySelector('.fpp-popup-content');
          if (hideAnim) {
              content.classList.remove(overlay.getAttribute('data-show-animation'));
              content.classList.add(hideAnim);
              setTimeout(function(){
                  overlay.style.display = "none";
                  content.classList.remove(hideAnim);
              }, 500);
          } else {
              overlay.style.display = "none";
          }
          var popupId = overlay.getAttribute('data-popup-id');
          if (overlay.getAttribute('data-display-once') == "1") {
              setCookie("fpp_popup_shown_" + popupId, "1");
          }
      }
      
      // Process each popup overlay based on its trigger type.
      document.querySelectorAll('.fpp-popup-overlay').forEach(function(overlay){
          var triggerType = overlay.getAttribute('data-trigger-type');
          var triggerValue = overlay.getAttribute('data-trigger-value');
          
          if (triggerType === 'immediate') {
              showPopup(overlay);
          } else if (triggerType === 'delay') {
              var delay = parseInt(triggerValue) || 0;
              setTimeout(function(){
                  showPopup(overlay);
              }, delay * 1000);
          } else if (triggerType === 'click') {
              if (triggerValue) {
                  var triggerElem = document.getElementById(triggerValue);
                  if (triggerElem) {
                      triggerElem.addEventListener('click', function(){
                          showPopup(overlay);
                      });
                  }
              }
          }
          
          // Close events: click on close button or on overlay (if clicked outside popup content)
          overlay.querySelector('.fpp-popup-close').addEventListener('click', function(e){
              e.stopPropagation();
              hidePopup(overlay);
          });
          overlay.addEventListener('click', function(e){
              if (e.target === overlay) {
                  hidePopup(overlay);
              }
          });
      });
    })();
    </script>
    <?php
}
