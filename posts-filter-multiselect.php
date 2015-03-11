<?php
/*
 Plugin Name: Posts filter multiselect
 Plugin URI: http://elearn.jp/wpman/column/posts-filter-multiselect.html
 Description: Pull-down list in the posts filter of the single choice is changed to multi-select.
 Author: tmatsuur
 Version: 1.0.1
 Author URI: http://12net.jp/
 */

/*
 Copyright (C) 2015 tmatsuur (Email: takenori dot matsuura at 12net dot jp)
 This program is licensed under the GNU GPL Version 2.
 */

define( 'POSTS_FILTER_MULTISELECT_DOMAIN', 'posts-filter-multiselect' );
define( 'POSTS_FILTER_MULTISELECT_DB_VERSION_NAME', 'posts-filter-multiselect-db-version' );
define( 'POSTS_FILTER_MULTISELECT_DB_VERSION', '1.0.1' );

$plugin_posts_filter_multiselect = new posts_filter_multiselect();

class posts_filter_multiselect {
	const PROPERTIES_NAME = '-properties';
	var $get_params = array();
	var $font_weight_normal = true;
	var $ui_theme = 'redmond';	// see jquery ui themes

	function __construct() {
		register_activation_hook( __FILE__ , array( &$this , 'activation' ) );
		register_deactivation_hook( __FILE__ , array( &$this , 'deactivation' ) );

		global $pagenow;
		if ( isset( $pagenow ) && in_array( $pagenow, array( 'edit.php' ) ) ) {
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_head', array( $this, 'admin_head' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		}
	}
	function activation() {
		if ( get_option( POSTS_FILTER_MULTISELECT_DB_VERSION_NAME ) != POSTS_FILTER_MULTISELECT_DB_VERSION ) {
			update_option( POSTS_FILTER_MULTISELECT_DB_VERSION_NAME, POSTS_FILTER_MULTISELECT_DB_VERSION );
		}
	}
	function deactivation() {
		delete_option( POSTS_FILTER_MULTISELECT_DB_VERSION_NAME );
	}

	function pre_get_posts( $query ) {
		$this->get_params = array();
		foreach ( array_keys( $_GET ) as $key ) {
			if ( !in_array( $key, array( 's','post_status','post_type', 'action', 'action2','filter_action','paged','mode' ) ) ) {
				$this->get_params[$key] = explode( ',', $_GET[$key] );
				if ( count( $this->get_params[$key] ) > 1 ) {
					if ( $key == 'm' ) {
						$date_query = array( 'relation'=>'OR' );
						foreach ( $this->get_params[$key] as $yyyymm ) {
							if ( preg_match( '/^[0-9]+$/u', $yyyymm ) ) {
								$yyyy = intval( substr( $yyyymm, 0, 4 ) );
								if ( strlen( $yyyymm ) > 5 ) {
									$mm2 = $mm = intval( substr( $yyyymm, 4, 2 ) );
								} else {
									$mm = 1;
									$mm2 = 12;
								}
								if ( strlen( $yyyymm ) > 7 ) {
									$date_query[] = array( 'year'=>$yyyy, 'month'=>$mm, 'day'=>intval( substr( $yyyymm, 6, 2 ) ) );
								} else {
									$date_query[] = array(
										'compare'=>'BETWEEN',
										'inclusive'=>true,
										'after'=>$yyyy.'/'.$mm.'/1',
										'before'	=>date( 'Y/m/d H:i:s', strtotime( '+1 month '.$yyyy.'/'.$mm2.'/1' )-1 ) );
								}
							}
						}
						if ( count( $date_query ) > 1 ) {
							$query->set( 'm', '' );
							$query->set( 'date_query', $date_query );
						}
					} else {
						$query->set( $key, $_GET[$key] );
					}
				} else if ( $_GET[$key] == '0' ) {
					$query->set( $key, '' );
				}
			}
		}
	}
	function admin_enqueue_scripts( $hook_suffix ) {
		wp_enqueue_style( 'jquery-ui-css', plugins_url( basename( dirname( __FILE__ ) ).'/css/themes/'.$this->ui_theme.'/jquery-ui.min.css' ) );
		wp_enqueue_style( POSTS_FILTER_MULTISELECT_DOMAIN.'-style', plugins_url( basename( dirname( __FILE__ ) ).'/css/jquery.multiselect.css' ) );
		wp_enqueue_script( POSTS_FILTER_MULTISELECT_DOMAIN.'-script', plugins_url( basename( dirname( __FILE__ ) ).'/js/jquery.multiselect.min.js' ), array( 'jquery-ui-widget' ) );
	}
	function admin_head() {
?>
<style type="text/css">
button.ui-multiselect { line-height: 1.55em; position: relative; }
button.ui-multiselect span.ui-icon-triangle-2-n-s { position: absolute; right: 0; }
button.ui-multiselect span { white-space: nowrap; }
.ui-widget-content { background-image: none; }
<?php if ( $this->font_weight_normal ) { ?>
.ui-state-default, .ui-widget-content .ui-state-default, .ui-widget-header .ui-state-default { font-weight: normal; }
.ui-state-active, .ui-widget-content .ui-state-active, .ui-widget-header .ui-state-active { font-weight: normal; }
.ui-state-hover, .ui-widget-content .ui-state-hover, .ui-widget-header .ui-state-hover, .ui-state-focus, .ui-widget-content .ui-state-focus, .ui-widget-header .ui-state-focus { font-weight: normal; }
<?php } ?>
</style>
<?php
	}
	function admin_footer() {
?>
<script type="text/javascript">
//<![CDATA[
( function ( $ ) {
	$(document).ready( function () {
		var get_filter = $.parseJSON( '<?php echo json_encode( $this->get_params ); ?>' );
		$( '#posts-filter input[name=filter_action]' ).siblings( 'select' ).each( function () {
			first_text = $(this).find(':first' ).text();
			$(this).multiselect( {
				checkAllText: '<?php _e( 'Select all' ); ?>',
				uncheckAllText: '<?php _e( 'Deselect' ); ?>',
				noneSelectedText: first_text,
				selectedText: function ( numChecked, numTotal, checkedItems ) {
					text = '';
					if ( numChecked > 0 ) {
						for ( var key in checkedItems ) {
							if ( text != '' ) text += ',';
							text += $( checkedItems[key] ).attr( 'title' );
						}
					}
					return text;
				}
			} );
			$(this).on( 'multiselectclick', function( event, ui ) {
				if ( ui.checked ) $(this).multiselect( 'widget' ).find( '[value'+( ( ui.value == 0 )? '!=': '=' )+'0]:checked' ).click();
			} );
			selectedVal = get_filter[$(this).attr('name')];
			if ( Array.isArray( selectedVal ) && selectedVal.length > 1 ) {
				for ( var key in selectedVal ) {
					$(this).multiselect( 'widget' ).find( '[value='+selectedVal[key]+']' ).each( function () {
						if ( !$(this).is( ':checked' ) ) $(this).click();
					} );
				}
			}
	} );
		$( '#posts-filter' ).submit( function () {
			$( '#posts-filter input[name=filter_action]' ).siblings( 'select' ).each( function () {
				selected = $(this).multiselect( 'getChecked' ).map( function () { return this.value; } ).get().join();
				if ( selected == '' ) selected = '0';
				$(this).find( 'option:selected' ).val( selected );
			} );
			return true;
		} );
	} );
} )( jQuery );
//]]>
</script>
<?php
	}
}