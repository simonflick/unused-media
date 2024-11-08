<?php

namespace UnusedMedia;

function search_post_entry($post, $db, &$results)
{
    // skip uncomplete entries
    if (empty($post->post_content)) return;

    preg_match_all(MEDIA_PATH_REGEX, $post->post_content, $matches);

    foreach ($matches[0] as $match) {
        $results[normalize_media_path($match)][$post->ID] = $post->ID;
    }
}

function search_post_meta_entry($post_meta, $db, &$results) 
{
    // skip meta entries that are not found in the posts list. This filters out references to attachments, revisions and nonexistent posts
    if (!isset($db->posts_by_id[$post_meta->post_id])) return;

    $meta = new \stdClass();
    $meta->parent_id = $post_meta->post_id;
    $meta->meta_key = $post_meta->meta_key;
    $meta->meta_value = $post_meta->meta_value;

    search_meta_entry($meta, $db, $results, $db->posts_by_id, $db->post_meta_by_post_id);
}

function search_term_meta_entry($term_meta, $db, &$results)
{
    // skip meta entries that are not found in the terms list. This filters out references to attachments, revisions and nonexistent posts
    if (!isset($db->terms_by_id[$term_meta->term_id])) return;

    $meta = new \stdClass();
    $meta->parent_id = $term_meta->term_id;
    $meta->meta_key = $term_meta->meta_key;
    $meta->meta_value = $term_meta->meta_value;

    search_meta_entry($meta, $db, $results, $db->terms_by_id, $db->term_meta_by_term_id);
}

function search_meta_entry($meta, $db, &$results, &$parents_by_id, &$meta_by_parent_id)
{
    // skip uncomplete entries
    if (empty($meta->parent_id) || empty($meta->meta_key) || empty($meta->meta_value)) return;

    // handle builtin thumbnails
    if ($meta->meta_key === '_thumbnail_id') {
        // skip if the referenced parent post doesn't exist
        if (!isset($parents_by_id[$meta->parent_id])) return;
        
        // skip if the referenced attachment doesn't exist
        if (!isset($db->media_by_id[$meta->meta_value])) return;

        $media_path = normalize_media_path($db->media_by_id[$meta->meta_value]->attachment_url);
        $results[$media_path][] = [
            'parent_id' => $meta->parent_id,
            'field_name' => 'Thumbnail'
        ];
    // handle ACF fields which typically start with underscores
    } else if (!str_starts_with($meta->meta_key, '_')) {
        // search for accompanied meta field that has the same name but starts with underscore
        $all_post_meta = $meta_by_parent_id[$meta->parent_id];
        
        

        $acf_field_definitions = array_filter($all_post_meta, function($m) use ($meta) {
            return $m->meta_key === '_' . $meta->meta_key;
        });

        

        $acf_field_definition = array_pop($acf_field_definitions);
        
        // skip if the field definition doesn't exist which means that it can't be an ACF field
        if ($acf_field_definition === null) return;
        
        // skip if the name doesn't start with _field which means that it can't be an ACF field
        if (!str_starts_with($acf_field_definition->meta_value, 'field_')) return;

        // take the meta_value and use it to search for the acf field post
        if (!isset($db->posts_by_post_name[$acf_field_definition->meta_value])) return;

        $acf_field_post = $db->posts_by_post_name[$acf_field_definition->meta_value];
        
        // unserialize the post content and retrieve the type of the extra field
        $acf_field_data = unserialize($acf_field_post->post_content);

        // skip if data could not be unserialized or if the type is not present
        if ($acf_field_data === false || !isset($acf_field_data['type'])) return;

        $acf_field_type = $acf_field_data['type'];

        // handle the meta field content depending on the field type
        switch ($acf_field_type) {
            case 'repeater': // skip these as they are not needed to get to the sub fields
            case 'flexible_content':
                break;

            case 'image': // stored as media post id
            case 'gallery': // contains serialized array of media post ids
            case 'post_object': // contains the post id
            case 'page_link': // contains the post id
            case 'relationship': // contains serialized array of post ids
                $media_ids = @unserialize($meta->meta_value);
                
                // if it failed to unserialize, assume it is a single id
                if ($media_ids === false) {
                    $media_ids = [$meta->meta_value];
                }

                foreach ($media_ids as $media_id) {
                    if (!isset($db->media_by_id[$media_id])) return;

                    $media_path = normalize_media_path($db->media_by_id[$media_id]->attachment_url);
                    $results[$media_path][] = [
                        'parent_id' => $meta->parent_id,
                        'field_name' => "ACF {$acf_field_type}"
                    ];
                }

                break;

            case 'icon_picker': // contains serialized data of selected media object
                $data = @unserialize($meta->meta_value);
                if ($data === false || !isset($data['type']) || !isset($data['value'])) break;

                switch($data['type']) {
                    case 'media_library':
                        $media_path = normalize_media_path($db->media_by_id[$data['value']]->attachment_url);
                        $results[$media_path][] = [
                            'parent_id' => $meta->parent_id,
                            'field_name' => "ACF {$acf_field_type}"
                        ];

                        break;
                    case 'url':
                        preg_match_all(MEDIA_PATH_REGEX, $data['value'], $matches);
                
                        foreach ($matches[0] as $match) {
                            $results[normalize_media_path($match)][] = [
                                'parent_id' => $meta->parent_id,
                                'field_name' => "ACF {$acf_field_type}"
                            ];
                        }

                        break;
                }

                break;

            default: // search for image urls for all other field types
                preg_match_all(MEDIA_PATH_REGEX, $meta->meta_value, $matches);
                
                foreach ($matches[0] as $match) {
                    $results[normalize_media_path($match)][] = [
                        'parent_id' => $meta->parent_id,
                        'field_name' => "ACF {$acf_field_type}"
                    ];
                }

                break;
        }
    // fallback text search for paths
    } else {
        preg_match_all(MEDIA_PATH_REGEX, $meta->meta_value, $matches);
                
        foreach ($matches[0] as $match) {
            $results[normalize_media_path($match)][$post->ID] = [
                'parent_id' => $meta->parent_id,
                'field_name' => "unknown field type <code>{$meta->meta_key}</code>"
            ];
        }
    }
}

function search_term_taxonomy_entry($taxonomy, $db, &$results)
{
    // skip uncomplete entries
    if (empty($taxonomy->taxonomy) || empty($taxonomy->description)) return;

    preg_match_all(MEDIA_PATH_REGEX, $taxonomy->description, $matches);

    foreach ($matches[0] as $match) {
        $results[normalize_media_path($match)][] = [
            'term_id' => $taxonomy->term_id,
            'taxonomy' => $taxonomy->taxonomy
        ];
    }
}

function search_option_entry($option, $db, &$results)
{
    if (empty($option->option_value)) return;

    preg_match_all(MEDIA_PATH_REGEX, $option->option_value, $matches);

    foreach ($matches[0] as $match) {
        $results[normalize_media_path($match)][$option->option_name] = $option->option_name;
    }
}