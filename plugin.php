<?php
/*
 * Plugin Name: Filter posts by taxonomy
 * Plugin URI: http://martykokes.com/filter-posts-by-taxonomy
 * Description: Allows you to add an extra drop down to the posts screen in the wordpress admin area
 * Version: 1.0.0
 * Text Domain: filter-posts-by-taxonomy
 * Author: Marty Kokes
 * Author URI: http://martykokes.com
 */
add_filter('parse_query', 'add_taxonomy_filter');
function add_taxonomy_filter($query) {
    global $pagenow;
    $post_type = 'post'; // change to your post type
    $taxonomy  = 'publication'; // change to your taxonomy
    $q_vars    = &$query->query_vars;
    if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {
        $term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
        $q_vars[$taxonomy] = $term->slug;
    }
}