<?php
/*
Plugin Name: OnePress Demo Importer
Plugin URI: http://www.famethemes.com/
Description: One click to import demo content for OnePress theme.
Author: famethemes
Author URI:  http://www.famethemes.com/
Version: 1.0.4
Text Domain: onepress-importer
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


class OnePress_Demo_Import {
    public $dir;
    public $url;
    function __construct( ){

        $this->url = trailingslashit( plugins_url('', __FILE__) );
        $this->dir = trailingslashit( plugin_dir_path( __FILE__) );

        require_once $this->dir.'inc/ft-content.php';

        add_action( 'wp_ajax_ft_demo_import_content', array( $this, 'ajax_import' ) );

        add_action( 'onepress_admin_more_tabs', array( $this, 'add_tab' ), 66 );
        add_action( 'onepress_more_tabs_details', array( $this, 'tab_details' ), 66 );
        add_action( 'ft_import_after_imported', array( $this, 'setup_demo' ), 66 );
    }

    function ajax_import(){
        $nonce = $_REQUEST['_nonce'];
        if ( ! wp_verify_nonce( $nonce, 'ft_demo_import' ) ) {
            die( 'Security check' );
        } else {
            // Do stuff here.
        }
        if ( class_exists( 'OnePress_PLus' ) ) {
            $import = new FT_Demo_Content(array(
                'xml' => $this->dir.'data/onepress-plus/dummy-data.xml',
                'customize' => $this->dir.'data/onepress-plus/customize.json',
                'widget' => $this->dir.'data/onepress-plus/widgets.json',
                'option' => '',
                'option_key' => '',
            ) );

            $import->import();
        } else {
            $import = new FT_Demo_Content(array(
                'xml' => $this->dir.'data/onepress/dummy-data.xml',
                'customize' => $this->dir.'data/onepress/customize.json',
                'widget' => $this->dir.'data/onepress/widgets.json',
                'option' => '',
                'option_key' => '',
            ) );

            $import->import();
        }


        update_option( 'ft_demo_imported', 1 );

        die( 'done' );
    }
    function setup_demo( $processed_posts  = array() ){

        // Try to set home page
        // This may return another "Home" page.
        // maybe someone already added Home page before import
        $page = get_page_by_title( 'Home' );
        if ( get_post_type( $page ) == 'page' ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $page->ID );

            //Make sure home page is using front page template
            update_post_meta( $page->ID, '_wp_page_template', 'template-frontpage.php' );

            // Try to set blog page
            $blog_page = get_page_by_title( 'News' );
            if ( get_post_type( $page ) == 'page' ) {
                update_option('page_for_posts', $blog_page->ID);
            }
        }

        // Setup demo menu
        $menu = get_term_by( 'name', 'Primary', 'nav_menu' );
        if ( $menu ) {
            // Getting new theme's theme location settings
            $nav_menu_locations = get_theme_mod( 'nav_menu_locations' );
            $nav_menu_locations[ 'primary' ] = $menu->term_id;
            set_theme_mod( 'nav_menu_locations', $nav_menu_locations );
        }

        // Setup service
        $services =  $this->resetup_repeater_page_ids( 'onepress_services', 'content_page', $processed_posts );
        if ( $services ) {
            set_theme_mod( 'onepress_services',  json_encode( $services ) );
        }

        // Setup about
        $abouts =  $this->resetup_repeater_page_ids( 'onepress_about_boxes', 'content_page', $processed_posts );
        if ( $abouts ) {
            set_theme_mod( 'onepress_about_boxes',  json_encode( $abouts ) );
        }

        // Setup Team
        $teams =  $this->resetup_repeater_page_ids( 'onepress_team_members', array( 'user_id', 'id' ), $processed_posts );
        if ( $abouts ) {
            set_theme_mod( 'onepress_team_members',  json_encode( $teams ) );
        }

        // If OnePress_PLus Activated
        if ( class_exists( 'OnePress_PLus' ) ) {

        }

    }

    function resetup_repeater_page_ids( $theme_mod_name, $key, $processed_posts = array() ){
        // Setup service
        $data = get_theme_mod( $theme_mod_name );
        if ( is_string( $data ) ) {
            $data = json_decode( $data, true );
        }
        if ( ! is_array( $data ) ) {
            return false;
        }
        if ( ! is_array( $processed_posts ) ) {
            return false;
        }

        if ( ! is_array( $key ) ) {
            if ( ! empty( $data ) && is_array( $data ) ) {
                foreach ( $data as $k => $v ) {
                    if ( isset( $v[ $key ] ) && isset ( $processed_posts[ $v[ $key ] ] ) ) {
                        $data[ $k ][ $key ] =  $processed_posts[ $v[ $key ] ];
                    }
                }
            }
        } else if ( count( $key ) > 1 ) {
            $main_key = isset( $key[ 0 ] ) ? $key[ 0 ] :  false;
            $sub_key = isset( $key[ 1 ] ) ? $key[ 1 ] :  false;
            if ( $main_key && $sub_key ) {
                if ( ! empty( $data ) && is_array( $data ) ) {
                    foreach ( $data as $k => $v ) {
                        if ( isset( $v[ $main_key ] ) && is_array( $v[ $main_key ] ) ) {
                            if ( isset ( $v[ $main_key ][ $sub_key ] ) ) {
                                $data[ $k ][ $main_key ][ $sub_key ] =  $processed_posts[ $v[ $main_key ][ $sub_key ] ];
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    function add_tab(){
        // Check for current viewing tab
        $tab = null;
        if ( isset( $_GET['tab'] ) ) {
            $tab = $_GET['tab'];
        } else {
            $tab = null;
        }
        ?>
        <a href="?page=ft_onepress&tab=demo_content" class="nav-tab<?php echo $tab == 'demo_content' ? ' nav-tab-active' : null; ?>"><?php esc_html_e( 'Demo Content', 'onepress-import' ); ?></a>
        <?php
    }

    function tab_details( $details ){
        $tab = null;
        if ( isset( $_GET['tab'] ) ) {
            $tab = $_GET['tab'];
        } else {
            $tab = null;
        }

        if ( $tab != 'demo_content' ) {
            return ;
        }

        $show_export = false;
        if ( isset( $_REQUEST['export'] ) && $_REQUEST['export'] == 1 ) {
            $show_export = true;
        }

        ?>

        <div class="theme_info info-tab-content">
            <?php if ( $show_export ) { ?>
            <?php /*
            <div style="margin-bottom: 25px;">
                <h3>Options Export</h3>
                <textarea readonly="true" style="width: 100%;" rows="10"><?php echo esc_attr( stripslashes_deep( FT_Demo_Content::generate_options_export_data( 'sidebars_widgets' ) ) );  ?></textarea>
            </div>
            */ ?>

            <div style="margin-bottom: 25px;">
                <h3>Widget Export</h3>
                <textarea readonly="true" style="width: 100%;" rows="10"><?php echo esc_textarea( FT_Demo_Content::generate_widgets_export_data() );  ?></textarea>
            </div>

            <div style="margin-bottom: 25px;">
                <h3>Customize Export</h3>
                <textarea readonly="true" style="width: 100%;" rows="10"><?php echo esc_textarea( FT_Demo_Content::generate_theme_mods_export_data( ) );  ?></textarea>
            </div>
            <?php } ?>

            <div class="action-required">
                <p class="tie_message_hint">Importing demo data (post, pages, images, theme settings, ...) is the easiest way to setup your theme. It will
                    allow you to quickly edit everything instead of creating content from scratch. When you import the data following things will happen:</p>

                <ul style="padding-left: 20px;list-style-position: inside;list-style-type: square;}">
                    <li>No existing posts, pages, categories, images, custom post types or any other data will be deleted or modified .</li>
                    <li>No WordPress settings will be modified .</li>
                    <li>Posts, pages, some images, some widgets and menus will get imported .</li>
                    <li>Images will be downloaded from our server, these images are copyrighted and are for demo use only .</li>
                    <li>Please click import only once and wait, it can take a couple of minutes</li>
                </ul>
            </div>

            <div class="action-required"><p class="tie_message_hint">Before you begin, make sure all the required plugins are activated.</p></div>
            <?php if ( get_option( 'ft_demo_imported' ) == 1 ) { ?>
                <div class="action-required" style="border-left-color: #a1d3a2; clear:both;">
                    <p><?php _e('Demo already imported', 'radium'); ?></p>
                </div>
            <?php } ?>

            <p>
                <a href="#" class="ft_demo_import button-primary" name="demo_import" /><?php

                if( get_option( 'ft_demo_imported' ) == 1 ) {
                    _e('Import Again', 'onepress-import');
                } else {
                    _e('Import Demo Data', 'onepress-import');
                }

                ?></a>
                <span class="spinner" style="float: none; margin-left: 0px; margin-top: -2px;"></span>
            </p>

        </div>
        <script type="text/javascript">
            jQuery( document).ready( function( $ ){
                $( '.ft_demo_import').on( 'click', function( e ){
                    e.preventDefault();
                    var btn = $(this);
                    if ( btn.hasClass( 'disabled' ) ) {
                        return false;
                    }
                    //var c = confirm( "<?php echo esc_attr( 'Are you sure want to import demo content ?' ); ?>" );
                    var c = true;
                    if ( c ) {

                        btn.addClass('disabled');

                        $('.spinner', btn.parent() ).css('visibility', 'visible');

                        var params = {
                            'action': 'ft_demo_import_content',
                            '_nonce': '<?php echo wp_create_nonce( 'ft_demo_import' ); ?>'
                        };

                        $.post( window.ajaxurl, params, function ($data) {
                            btn.removeClass('disabled');
                            $('.spinner', btn.parent() ).css('visibility', 'hidden');

                            window.location = '<?php echo admin_url( 'themes.php?page=ft_onepress&tab=demo_content&imported=1' ); ?>';

                        });
                    }

                } );
            } );
        </script>
        <?php
    }


}

 //remove_theme_mods();

if ( is_admin() ) {
    function pnepress_demo_import_init(){
        new OnePress_Demo_Import();
    }
    add_action( 'plugins_loaded', 'pnepress_demo_import_init' );
}