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

	private static $attachment = false;

    public static function hooks() {
        add_action('admin_enqueue_scripts',array(__CLASS__,'enqueue_scripts'));
		add_action('add_meta_boxes',array(__CLASS__,'add_the_box'));
		add_action('admin_footer-post.php',array(__CLASS__,'heartbeat_footer_js'),20);
		add_action('heartbeat_received',array(__CLASS__,'heartbeat_received'),10,3);
    }

	public static function enqueue_scripts($hook) {
		if (!in_array($hook,array('post-new.php','post.php'))) return;
		wp_enqueue_script('attached-imgs',plugin_dir_url(__FILE__) . 'admin.js',array('jquery','heartbeat'),'init');
		wp_enqueue_style('attached-imgs',plugin_dir_url(__FILE__) . 'admin.css','','init');
	}

	public static function add_the_box() {
		global $post;
		if (!is_object($post)) return;

		if ('attachment' === get_post_type($post)) {
			self::$attachment = true;
			$metadata = wp_get_attachment_metadata($post->ID);
			if (array_key_exists('sizes',$metadata) && is_array($metadata['sizes']) && count($metadata['sizes'])) {
				self::$imgs = array();
				foreach ($metadata['sizes'] as $size => $array) {
					$image = new stdClass();
					$image->width = $array['width'];
					$image->height = $array['height'];
					$image->size = $size;
					$temp = wp_get_attachment_image_src($post->ID,$size);
					$image->src = $temp[0];
					self::$imgs[] = $image;
				}
			}
		} else {
			$images = new WP_Query(array(
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'post_status' => 'any',
				'post_parent' => $post->ID,
				'posts_per_page' => -1,
				'orderby' => 'menu_order',
				'order' => 'asc',
			));
			if ($images->have_posts()) {
				foreach ($images->posts as $img) {
					$temp = wp_get_attachment_image_src($img->ID,'thumbnail');
					$image = new stdClass();
					$image->width = $temp[1];
					$image->height = $temp[2];
					$image->src = $temp[0];
					$image->id = $img->ID;
					self::$imgs[] = $image;
				}
				self::$coords['all'] = $images->found_posts;
			}
		}

		add_meta_box('cpmb-attachimgs',(true === self::$attachment ? 'Image Sizes' : 'Attached Images') . '<span class="spinner"></span>',array(__CLASS__,'the_box'),'','side');
	}

	public static function the_box($post) {
		global $wp_version;
		$orig = $post;

        $num = count(self::$imgs);

        if (4 > $num) $cols = 2;
        else if (3 < $num && 9 > $num) $cols = 3;
        else if (16 > $num) $cols = 4;
        else if (16 <= $num) $cols = 5;

		echo '<ul data-cols="' . $cols . '" data-posttype="' . get_post_type($post) . '">';
			if (is_array(self::$imgs) && count(self::$imgs)) {
                echo self::num();
				$image_id = $post->ID;
				foreach (self::$imgs as $img) {
					if (isset($img->id)) {
						$image_id = $img->id;
						$image_link = get_edit_post_link($img->id);
					}
					else if (isset($img->size)) {
						$image = wp_get_attachment_image_src($post->ID,$img->size);
						$image_link = $image[0];
					}
					echo '<li data-orientation="' . ($img->width >= $img->height ? 'landscape' : 'portrait') . '">' .
						'<a href="' . $image_link . '" target="_blank">' .
							'<img src="' . $img->src . '" alt="' . get_the_title($image_id) . '" width="' . $img->width . '" height="' . $img->height . '" />' .
						'</a>' .
					'</li>';
				}
			} else {
                $openheadtag = version_compare($wp_version,'4.4-alpha','>=') ? 'h2 style="font-weight: bold;"' : 'h3';
				$closeheadtag = version_compare($wp_version,'4.4-alpha','>=') ? 'h2' : 'h3';
                echo '<li class="no-imgs"><' . $openheadtag . ' class="hndle">No Attached Images<span class="spinner"></span></' . $closeheadtag . '></li>';
            }
		echo '</ul>';
	}

		public static function num() {
			return '<li class="count">' .
				'<span>' .
					'<span>' .
						'<span class="num-images">' .
							'<span class="num">' . count(self::$imgs) . '</span><br />' .
							(true === self::$attachment ? 'Size' : 'Image') . (1 == count(self::$imgs) ? '' : 's') .
						'</span>' .
						'<span class="move">Move</span>' .
						'<span class="spinner"></span>' .
					'</span>' .
				'</span>' .
			'</li>';
		}

		public static function heartbeat_footer_js() {
			?>

			<script>
				(function($){

					$(document).on('heartbeat-send',function(e,data) {
						$("#cpmb-attachimgs").addClass('refreshing');
						$("#cpmb-attachimgs .spinner").addClass('is-active');
						var post_id = $.QueryString['post'];
						data['postid_heartbeat'] = post_id;
					});

					$(document).on('heartbeat-tick',function(e,data) {

						$("#cpmb-attachimgs.refreshing").removeClass('refreshing');
						$("#cpmb-attachimgs .spinner.is-active").removeClass('is-active');

						if (!data['css-cpmb-attachimgs'] || "none" == data['css-cpmb-attachimgs']) {
							$("#cpmb-attachimgs > .hndle").hide();

							if (!$("#cpmb-attachimgs div.inside > ul > li.no-imgs").length)
								$("#cpmb-attachimgs div.inside > ul").html('<li class="no-imgs"><h2 class="hndle">No Attached Images<span class="spinner"></span></h2></li>');

							if ('function' === typeof HBMonitor)
								HBMonitor('No Attached Images');

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
						} else {
							$("#cpmb-attachimgs > .hndle").hide();
							$("#cpmb-attachimgs div.inside > ul").html('<li class="no-imgs"><h2 class="hndle">No Attached Images<span class="spinner"></span></h2></li>');
						}

						if ('function' === typeof HBMonitor)
							HBMonitor('Attached Images: ' + (imgs.length - 1));
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

			if ('attachment' === get_post_type($post_id)) {
				self::$attachment = true;
				$metadata = wp_get_attachment_metadata($post_id);
				if (array_key_exists('sizes',$metadata) && is_array($metadata['sizes']) && count($metadata['sizes'])) {
					self::$imgs = array();
					foreach ($metadata['sizes'] as $size => $array) {
						$image = new stdClass();
						$image->width = $array['width'];
						$image->height = $array['height'];
						$image->size = $size;
						$temp = wp_get_attachment_image_src($post_id,$size);
						$image->src = $temp[0];
						self::$imgs[] = $image;
					}
				}
			} else {
				$images = new WP_Query(array(
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'post_status' => 'any',
					'post_parent' => $post_id,
					'posts_per_page' => -1,
					'orderby' => 'menu_order',
					'order' => 'asc',
				));
				if ($images->have_posts()) {
					foreach ($images->posts as $img) {
						$temp = wp_get_attachment_image_src($img->ID,'thumbnail');
						$image = new stdClass();
						$image->width = $temp[1];
						$image->height = $temp[2];
						$image->src = $temp[0];
						$image->id = $img->ID;
						self::$imgs[] = $image;
					}
					self::$coords['all'] = $images->found_posts;
				}
			}

			if (false === self::$imgs || !count(self::$imgs)) {
				$response['css-cpmb-attachimgs'] = 'none';
				return $response;
			}

			$responses['css-cpmb-attachimgs'] = 'has-images';

			$return = array(self::num());
			if (is_array(self::$imgs) && count(self::$imgs)) {
				$image_id = $post->ID;
				foreach (self::$imgs as $img) {
					if (isset($img->id)) {
						$image_id = $img->id;
						$image_link = get_edit_post_link($img->id);
					}
					else if (isset($img->size)) {
						$image = wp_get_attachment_image_src($post->ID,$img->size);
						$image_link = $image[0];
					}
					$return[] = '<li data-orientation="' . ($img->width >= $img->height ? 'landscape' : 'portrait') . '">' .
						'<a href="' . $image_link . '" target="_blank">' .
							'<img src="' . $img->src . '" alt="' . get_the_title($image_id) . '" width="' . $img->width . '" height="' . $img->height . '" />' .
						'</a>' .
					'</li>';
				}
			} else {
                $openheadtag = version_compare($wp_version,'4.4-alpha','>=') ? 'h2 style="font-weight: bold;"' : 'h3';
				$closeheadtag = version_compare($wp_version,'4.4-alpha','>=') ? 'h2' : 'h3';
                $return[] = '<li class="no-imgs"><' . $openheadtag . ' class="hndle">No Attached Images<span class="spinner"></span></' . $closeheadtag . '></li>';
            }
			wp_reset_postdata();

			$response['css-cpmb-attachimgs'] = json_encode($return);

			return $response;
		}

}

?>
