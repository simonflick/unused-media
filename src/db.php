<?php

namespace UnusedMedia;

class DB {
    public $media;
    public $posts;
    public $post_meta;
    public $terms;
    public $term_meta;
    public $term_taxonomies;
    public $options;

    public $media_by_id;
    public $posts_by_id;
    public $posts_by_post_name;
    public $post_meta_by_post_id;
    public $terms_by_id;
    public $term_meta_by_term_id;
    public $term_taxonomies_by_term_id;

    function __construct() 
    {
        global $wpdb;

        $this->media = $wpdb->get_results("
            SELECT * FROM (
                SELECT ID, post_title, meta_value FROM {$wpdb->prefix}posts x 
                JOIN {$wpdb->prefix}postmeta y ON x.ID = y.post_id AND y.meta_key = '_wp_attached_file'
            ) a
        ");

        $this->posts = $wpdb->get_results("
            SELECT ID, post_content, post_status, post_type, post_title, post_name
            FROM {$wpdb->prefix}posts 
            WHERE post_type not in ('revision', 'attachment')
        ");

        $this->post_meta = $wpdb->get_results("
            SELECT post_id, meta_key, meta_value
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key NOT LIKE '_oembed%' AND meta_key NOT IN ('_customize_changeset_uuid', '_wp_old_date', '_edit_lock', '_edit_last')
        ");

        $this->terms = $wpdb->get_results("
            SELECT term_id, name
            FROM {$wpdb->prefix}terms 
        ");

        $this->term_meta = $wpdb->get_results("
            SELECT term_id, meta_key, meta_value
            FROM {$wpdb->prefix}termmeta
            WHERE meta_key NOT LIKE '_oembed%' AND meta_key NOT IN ('created_by')
        ");

        $this->term_taxonomies = $wpdb->get_results("
            SELECT term_id, taxonomy, description
            FROM {$wpdb->prefix}term_taxonomy
        ");

        $this->options = $wpdb->get_results("
            SELECT option_name, option_value
            FROM {$wpdb->prefix}options 
        ");

        foreach ($this->media as $m) {
            $m->attachment_url = wp_get_attachment_url($m->ID);
        }
        
        $this->media_by_id = [];
        foreach ($this->media as $m) { $this->media_by_id[$m->ID] = $m; }
        
        $this->posts_by_id = [];
        foreach ($this->posts as $p) { $this->posts_by_id[$p->ID] = $p; }
        
        $this->posts_by_post_name = [];
        foreach ($this->posts as $p) { $this->posts_by_post_name[$p->post_name] = $p; }
        
        $this->post_meta_by_post_id = [];
        foreach ($this->post_meta as $m) { $this->post_meta_by_post_id[$m->post_id][] = $m; }
        
        $this->terms_by_id = [];
        foreach ($this->terms as $t) { $this->terms_by_id[$t->term_id] = $t; }
        
        $this->term_meta_by_term_id = [];
        foreach ($this->term_meta as $m) { $this->term_meta_by_term_id[$m->term_id][] = $m; }

        $this->term_taxonomies_by_term_id = [];
        foreach ($this->term_taxonomies as $t) { $this->term_taxonomies_by_term_id[$t->term_id] = $t; }
    }
}