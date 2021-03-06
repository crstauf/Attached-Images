<?php
/*
Plugin Name: Attached Images
Plugin URI: http://www.calebstauffer.com
Description: Adds meta box to see images attached to post
Version: 0.0.1
Author: Caleb Stauffer
*/

if (!defined('ABSPATH') || !function_exists('add_filter')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

add_action('admin_init',array('css_attachimgs','hooks'));

class css_attachimgs {

    public static $imgs = false;
	public static $coords = array('num' => 1,'all' => 7);

    public static function hooks() {
        add_action('admin_enqueue_scripts',array(__CLASS__,'enqueue_scripts'));
		add_action('add_meta_boxes',array(__CLASS__,'add_the_box'));
		add_action('admin_footer-post.php',array(__CLASS__,'heartbeat_footer_js'),20);
		add_action('heartbeat_received',array(__CLASS__,'heartbeat_received'),10,3);
    }

	public static function enqueue_scripts($hook) {
		if (!in_array($hook,array('post-new.php','post.php'))) return;
		wp_enqueue_script('attached-imgs',plugin_dir_url(__FILE__) . 'admin.js',array('jquery','heartbeat'));
		wp_enqueue_style('attached-imgs',plugin_dir_url(__FILE__) . 'admin.css');
	}

	public static function add_the_box() {
		global $post;
		if (!is_object($post)) return;

		$query = array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'post_status' => 'any',
			'post_parent' => $post->ID,
			'posts_per_page' => -1,
			'orderby' => 'menu_order',
			'order' => 'asc',
		);

		$images = new WP_Query($query);
		self::$imgs = $images;

		self::$coords['all'] = self::$imgs->found_posts;

		if ('attachment' !== get_post_type($post))
			add_meta_box('cpmb-attachimgs','Attached Images',array(__CLASS__,'the_box'),'','side');
	}

	public static function the_box($post) {
		global $wp_version;
		$orig = $post;

        $num = self::$imgs->found_posts;
        if (4 > $num) $cols = 2;
        else if (3 < $num && 9 > $num) $cols = 3;
        else if (16 > $num) $cols = 4;
        else if (16 <= $num) $cols = 5;

		echo '<ul data-cols="' . $cols . '">';
			if (false !== self::$imgs && self::$imgs->have_posts()) {
                echo self::num();
				foreach (self::$imgs->posts as $img) {
					$thumb = wp_get_attachment_image_src($img->ID,'thumbnail');
					$large = wp_get_attachment_image_src($img->ID,'large');
					echo '<li><a href="' . $large[0] . '" target="_blank"><img src="' . $thumb[0] . '" alt="' . get_the_title($img->ID) . '" width="' . $thumb[1] . '" height="' . $thumb[2] . '" /></a></li>';
				}
			} else {
                $headtag = version_compare($wp_version,'4.4-alpha','>=') ? 'h2 style="font-weight: bold;"' : 'h3';
                echo '<li class="no-imgs"><' . $headtag . ' class="hndle">No Attached Images</' . $headtag . '></li>';
            }
		echo '</ul><style type="text/css">#cpmb-attachimgs > .hndle { width: ' . (100 / $cols) . '%; }</style>';
	}

		public static function num() {
			return '<li class="count"><span><span><span class="num-images"><span class="num">' . self::$imgs->found_posts . '</span><br />Image' . (1 == self::$imgs->found_posts ? '' : 's') . '</abbr></span><span class="move">Move</span></span></span></li>';
		}

		public static function all() {
			if (self::$coords['all'] != (self::$imgs->current_post + 1)) return;

			$text = 'Add<br />Image(s)';
			if (self::$imgs->found_posts > 10) $text = 'Add &amp;<br />View All<br />Images';

			return '<li class="viewall"><span>' . $text . '</span></li>';
		}

		public static function heartbeat_footer_js() {
			?>

			<script>
				(function($){

					$(document).on('heartbeat-send',function(e,data) {
						var post_id = $.QueryString['post'];
						data['postid_heartbeat'] = post_id;
					});

					$(document).on('heartbeat-tick',function(e,data) {

						if (!data['css-cpmb-attachimgs'] || "none" == data['css-cpmb-attachimgs']) {
							$("#cpmb-attachimgs > .hndle").hide();
							if (!$("#cpmb-attachimgs div.inside > ul > li.no-imgs").length)
								$("#cpmb-attachimgs div.inside > ul").html('<li class="no-imgs"><h2 class="hndle">No Attached Images</h2></li>');

							if ('function' === typeof HBMonitor)
								HBMonitor('AIMGS (no imgs)');

							return;
						}

						var imgs = eval(data['css-cpmb-attachimgs']);

						var output = '';
						if (imgs.length) {
							imgs.forEach(function(li) {
								output += li;
							});
                            var img_count = imgs.length - 1;
                            var cols = 2;
                            if (4 > img_count) cols = 2;
                            else if (3 < img_count && 9 > img_count) cols = 3;
                            else if (16 > img_count) cols = 4;
                            else if (16 <= img_count) cols = 5;
                            $("#cpmb-attachimgs > .hndle").attr('data-cols',cols).show();
							$("#cpmb-attachimgs div.inside > ul").attr('data-cols',cols).html(output);

							$("#cpmb-attachimgs li.viewall").on('mouseover',function() {
								$(this).animate({color: 'red'},200);
							}).on('mouseout',function() {
								$(this).animate({color: '#666'},200);
							}).on('click',function() {
								$("#insert-media-button").click();
							});
						} else {
							$("#cpmb-attachimgs > .hndle").hide();
							$("#cpmb-attachimgs div.inside > ul").html('<li class="no-imgs"><h2 class="hndle">No Attached Images</h2></li>');
						}

						if ('function' === typeof HBMonitor)
							HBMonitor('AIMGS (' + (imgs.length - 1) + ' imgs)');
					});
				}(jQuery));
			</script>

			<?php
		}

		public static function heartbeat_received($response,$data,$screen_id) {
			$response['css-cpmb-attachimgs'] = 'i got in';
			if (!isset($data['postid_heartbeat'])) {
				$response['css-cpmb-attachimgs'] = 'no-post-id';
				return $response;
			}

			$response['css-cpmb-attachimgs'] = 'has-post-id';
			$post_id = $data['postid_heartbeat'];

			$query = array(
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'post_status' => 'any',
				'posts_per_page' => -1,
				'post_parent' => $post_id,
				'orderby' => 'menu_order',
				'order' => 'asc'
			);

			$images = new WP_Query($query);

			if (!$images->have_posts()) {
				$response['css-cpmb-attachimgs'] = 'none';
				return $response;
			}

			self::$imgs = $images;

			$responses['css-cpmb-attachimgs'] = 'has-images';

			$return = array(self::num());
			while ($images->have_posts()) {
				$images->the_post();
				$thumb = wp_get_attachment_image_src(get_the_ID(),'thumbnail');
				$return[] = '<li><a href="' . get_edit_post_link(get_the_ID()) . '" target="_blank"><img src="' . $thumb[0] . '" alt="' . get_the_title() . '" width="' . $thumb[1] . '" height="' . $thumb[2] . '" /></a></li>';
				if (0 && null != ($temp = self::all())) {
					$return[] = $temp;
					unset($temp);
				}
			}
			wp_reset_postdata();

			$response['css-cpmb-attachimgs'] = json_encode($return);

			return $response;
		}

}

?>
