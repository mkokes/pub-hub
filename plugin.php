<?php
/*
 * Plugin Name: Pub Hub
 * Plugin URI: http://martykokes.com/ph
 * Description: Provides support for a 'Publication' taxonomy, provides icml file generation and syndication via json rest api
 * Version: 1.0.0
 * Text Domain: pub-hub
 * Author: Marty Kokes
 * Author URI: http://martykokes.com
 */

/************************
**Plugin Initialization
*************************/

//plugin activation and deactivation routines
class pht_init
{
	public static function plugin_activated(){
		$folder = WP_CONTENT_DIR . '/incopy-export';
		if (!file_exists($folder)) {
			wp_mkdir_p( $folder );
		}
	}
	public static function plugin_deactivated(){
		$folder = WP_CONTENT_DIR . '/incopy-export';
		if (!file_exists($folder)) {
			wp_mkdir_p( $folder );
		}
	}
}
register_activation_hook( __FILE__, array('pht_init', 'plugin_activated' ));
register_deactivation_hook( __FILE__, array('pht_init', 'plugin_deactivated' ));

/************************
**TAXONOMY FILTERING
*************************/

//add taxonomy filter to admin posts screen
add_action('restrict_manage_posts', 'pht_posts_by_taxonomy');
function pht_posts_by_taxonomy() {
	global $typenow;
	$post_type = 'post'; // change to your post type
	$taxonomy  = 'publication'; // change to your taxonomy
	if ($typenow == $post_type) {
		$selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
		$info_taxonomy = get_taxonomy($taxonomy);
		wp_dropdown_categories(array(
				'show_option_all' => __("Show All {$info_taxonomy->label}"),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => false,
				'hide_empty'      => true,
			));
	};
}
add_filter('parse_query', 'pht_add_taxonomy_filter');
function pht_add_taxonomy_filter($query) {
	global $pagenow;
	$post_type = 'post'; // change to your post type
	$taxonomy  = 'publication'; // change to your taxonomy
	$q_vars    = &$query->query_vars;
	if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {
		$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
		$q_vars[$taxonomy] = $term->slug;
	}
}
/************************
**INCOPY FILE CREATION
*************************/
//Line ending cleanup function
function normalize($s) {
    // Normalize line endings
    // Convert all line-endings to UNIX format
    $s = str_replace("\r\n", "\n", $s);
    $s = str_replace("\r", "\n", $s);
    // Don't allow out-of-control blank lines
    $s = preg_replace("/\n{2,}/", "\n\n", $s);
    return $s;
}
//Create an incopy file when a post is published or updated
function pht_write_xml( $post) {
	global $post;
	if( ! ( wp_is_post_revision( $post) && wp_is_post_autosave( $post ) ) ) {
		global $post;
		//Set Upload Directory
		$upload_dir = WP_CONTENT_DIR."/incopy-export/";
		//Set File Names
		$icfile = $upload_dir . $post->post_name."-ARTICLE.xml";
		//import incopy snippets
		$plugindir = plugins_url( '' , __FILE__ );
		$ictop = file_get_contents($plugindir.'/includes/article-top.txt');
		$icbottom = file_get_contents($plugindir.'/includes/article-bottom.txt');
		//Add the title and content to variable
		$content = $post->post_title."\r\n\r\n".$post->post_content;
		//Process story content strip out html and replace with icml friendly tags
		$search = array('<p>','</p>');
		$replace = array('<content>','</content>');
		$excludetags = '<p><br><br /><h1><h2>';
		$content = strip_shortcodes($content);
		$content = strip_tags($content,$excludetags);
		$content = nl2br($content);
		$content = str_replace($search,$replace,$content);
		$content = "<content>" . implode( "</content>\n\n<content>", preg_split( '/\n(?:\s*\n)+/', $content ) ) . "</content>";
		//process content
		
		//wrap content with indesign template
		$content = $ictop."\r\n".$content."\r\n".$icbottom;
		$content = normalize($content);
		//process xml here
		file_put_contents($icfile, $content,LOCK_EX);
	}
}
add_action('admin_head', 'pht_write_xml',10,2);
add_action('save_post', 'pht_write_xml',10,2);
/************************
**IMAGE FILE CREATION
*************************/
//Create an image file when a post is published or updated and contains images
function pht_write_txt( $post) {
	if( ! ( wp_is_post_revision( $post) && wp_is_post_autosave( $post ) ) ) {
		global $post;
		//Set Upload Directory
		$upload_dir = WP_CONTENT_DIR."/incopy-export/";
		//Set File Names
		$imgfile = $upload_dir . $post->post_name."-IMAGES.txt";
		//Add the title and content to variable
		$content = $post->post_content;
		//Collect all image tags
		preg_match_all('/<img[^>]+>/i',$content, $imgTags);
		//Collect all src attributes
		for ($i = 0; $i < count($imgTags[0]); $i++) {
			// get the source string
			preg_match('/src="([^"]+)/i',$imgTags[0][$i], $image);

			// remove opening 'src=' tag, can`t get the regex right
			$origImageSrc[] = str_ireplace( 'src="', '',  $image[0]);
		}
		//Collect all alt attributes
		for ($i = 0; $i < count($imgTags[0]); $i++) {
			// get the source string
			preg_match('/alt="([^"]+)/i',$imgTags[0][$i], $image);

			// remove opening 'src=' tag, can`t get the regex right
			$origImageAlt[] = str_ireplace( 'alt="', '',  $image[0]);
		}
		//Wipe fille if it exists
		file_put_contents($imgfile,"",LOCK_EX);
		//insert links into txt file
		foreach(array_combine($origImageSrc, $origImageAlt) as $origImageSrc => $origImageAlt) {
			$match = "IMAGE = ".$origImageSrc."\r\n";
			$match = $match."CAPTION = ".$origImageAlt."\r\n\r\n";
			file_put_contents($imgfile, $match,FILE_APPEND|LOCK_EX);
		}
	}
}
//add_action('admin_head', 'pht_write_txt',10,2);
add_action('save_post', 'pht_write_txt',10,2);
