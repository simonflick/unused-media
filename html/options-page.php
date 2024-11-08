<?php

namespace UnusedMedia;

include_once __DIR__ . '/../src/util.php';
include_once __DIR__ . '/../src/db.php';
include_once __DIR__ . '/../src/search.php';

if (!current_user_can('manage_options')) {
    die("You are not allowed to access this page.");
}

define('MEDIA_PATH_REGEX', '/wp-content\\\\?\/uploads\\\\?\/([^\s\'"])+/');
define('UPLOAD_DIR', wp_upload_dir()['path'] . '/');
define('SITE_HOST', parse_url(get_site_url())['host']);

$image_extensions = array(
    'gif',
    'jpg',
    'jpeg',
    'png',
    'webp',
    'avif',
    'svg',
    'ico',
    'apng',
    'bmp',
    'tiff',
    'tif',
    'jxr',
    'hdp',
    'wdp',
    'heic',
    'heif',
    'jp2',
    'j2k',
    'jpf',
    'jpx',
    'jpm',
    'mj2',    
);

$missing = 0;
$unused = 0;
$missing_thumbs = 0;
$invalid_paths = 0;

$db = new DB();
$admin_url = get_admin_url();
$post_content_matches = [];
$post_meta_matches = [];
$term_meta_matches = [];
$term_taxonomy_matches = [];
$options_matches = [];

// Find media used in post content
foreach ($db->posts as $post) {
    search_post_entry($post, $db, $post_content_matches);
}

// Find media used in post meta (e.g. thumbnails and custom fields)
foreach ($db->post_meta as $meta) {
    search_post_meta_entry($meta, $db, $post_meta_matches);
}

// Find media used in terms meta
foreach ($db->term_meta as $meta) {
    search_term_meta_entry($meta, $db, $term_meta_matches);
}

// Find media used in wp_term_taxonomy
foreach ($db->term_taxonomies as $term) {
    search_term_taxonomy_entry($term, $db, $term_taxonomy_matches);
}

// Find media used in options
foreach ($db->options as $option) {
    search_option_entry($option, $db, $options_matches);
}

foreach ($db->media as $m) {
    $meta_value_fragments = parse_url($m->meta_value);
    $m->file_exists = file_exists(UPLOAD_DIR . $m->meta_value);
    $m->can_have_thumbnails = (wp_attachment_is_image($m->ID) && !empty(wp_get_attachment_metadata($m->ID)));
    $m->missing_sizes = $m->can_have_thumbnails ? array_keys(wp_get_missing_image_subsizes($m->ID)) : [];
    $m->not_relative = isset($meta_value_fragments['host']);
    $m->different_host = $m->not_relative && $meta_value_fragments['host'] !== $site_host;
    $m->uses_in_content = $post_content_matches[$m->meta_value] ?? null;
    $m->uses_in_meta = $post_meta_matches[$m->meta_value] ?? null;
    $m->uses_in_options = $options_matches[$m->meta_value] ?? null;
    $m->uses_in_term_meta = $term_meta_matches[$m->meta_value] ?? null;
    $m->uses_in_term_taxonomy = $term_taxonomy_matches[$m->meta_value] ?? null;
    $m->used =
        $m->uses_in_content !== null || 
        $m->uses_in_meta !== null || 
        $m->uses_in_options !== null || 
        $m->uses_in_term_meta !== null || 
        $m->uses_in_term_taxonomy !== null;

    if (!$m->file_exists) 
        $missing++;

    if (!$m->used) 
        $unused++;

    if ($m->not_relative || $m->different_host) 
        $invalid_paths++;

    $missing_thumbs += count($m->missing_sizes);
}

?>
<div class="wrap">
    <h1><?= esc_html(get_admin_page_title()); ?></h1>
    <br>
    <table class="widefat striped table-summary">
        <tr>
            <th>Attachments</th>
            <td><?= count($db->media) ?></td>
        </tr>
        <tr>
            <th>Invalid paths</th>
            <td class="<?= $invalid_paths > 0 ? 'summary-error' : '' ?>">
                <?= $invalid_paths ?>
            </td>
        </td>
        <tr>
            <th>Files missing</th>
            <td class="<?= $missing > 0 ? 'summary-error' : '' ?>">
                <?= $missing ?>
            </td>
        </tr>
        <tr>
            <th>Thumbnails missing</th>
            <td class="<?= $missing_thumbs > 0 ? 'summary-warning' : '' ?>">
                <?= $missing_thumbs ?>
            </td>
        </td>
        <tr>
            <th>Likely unused</th>
            <td class="<?= $unused > 0 ? 'summary-warning' : '' ?>">
                <?= $unused ?> (<?= round($unused / count($db->media) * 100, 2) ?>%)
            </td>
        </td>
    </table>
    <br>
    <table class="widefat striped sortable table-media">
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Valid path</th>
            <th>File exists</th>
            <th>Thumbnails exist</th>
            <th>Path</th>
            <th>Used</th>
            <th>Uses in post content</th>
            <th>Uses in post meta</th>
            <th>Uses in term meta</th>
            <th>Uses in term taxonomy</th>
            <th>Uses in options</th>
        </tr>
        <?php foreach ($db->media as $m) : ?>
            <tr>
                <td>
                    <a href="<?= $admin_url ?>post.php?post=<?= $m->ID ?>&action=edit">
                        <?= $m->ID ?>
                    </a>
                </td>
                <td>
                    <?= $m->post_title ?>
                </td>
                <td>
                    <?= empty($m->missing_sizes) 
                        ? "✔️" 
                        : "❌ (missing <code>" . implode('</code>, <code>', $m->missing_sizes) . "</code>)"; ?>
                </td>
                <td>
                    <?= $m->file_exists ? "✔️" : "❌"; ?>
                </td>
                <td>
                    <?php $err = [];
                    if ($m->not_relative && !$m->different_host) 
                        $err[] = "<u title='Full URLs might break in the future if the site url changes. Use relative URLs instead'>full url</u>";
                    if ($m->different_host) 
                        $err[] = "<u title='Image URL leads to a different host. This might be unintentional'>different host</u>";
                    echo empty($err) 
                        ? "✔️" 
                        : "❌ (" . implode(', ', $err) . ")"; ?>
                </td>
                <td>
                    <?php
                    $extension = pathinfo($m->meta_value, PATHINFO_EXTENSION);
                    $class = in_array($extension, $image_extensions) ? 'image-link' : '';
                    ?>
                    <?php if ($m->file_exists): ?><a class="<?= $class ?>" href="<?= $m->attachment_url ?>"><?php endif ?>
                    <?= $m->meta_value ?>
                    <?php if ($m->file_exists): ?></a><?php endif ?>
                </td>
                <td>
                    <?= $m->used ? "✔️" : "❌"; ?>
                </td>
                <td>
                    <?php if ($m->uses_in_content !== null) {
                        $i = 0;
                        foreach ($m->uses_in_content as $id) {
                            $post_info = $db->posts_by_id[$id] ?? null;
                            $post_status = $post_info->post_status ?? null;
                            $post_type = $post_info->post_type ?? null;
                            $post_title = htmlspecialchars($post_info->post_title ?? '');
                            $class = "class=\"post-status-$post_status\"";
                            $query = http_build_query([
                                'post' => $id,
                                'action' => 'edit'
                            ]);
                            $href = 'href="' . $admin_url . 'post.php?' . $query . '"';
                            $data_tooltip = "data-tooltip=\"$post_title<p><code>$post_type</code> (<em>$post_status</em>)</p>\"";
                            echo (
                                (++$i > 1 ? ', ' : '') .
                                "<a $class $href $data_tooltip>$id</a>"
                            );
                        }
                    } ?>
                </td>
                <td>
                    <?php if ($m->uses_in_meta !== null) {
                        $i = 0;
                        foreach ($m->uses_in_meta as $meta) {
                            $post_info = $db->posts_by_id[$meta['parent_id']] ?? null;
                            $post_status = $post_info->post_status ?? null;
                            $post_type = $post_info->post_type ?? null;
                            $post_title = htmlspecialchars($post_info->post_title ?? '');
                            $class = "class=\"post-status-$post_status\"";
                            $query = http_build_query([
                                'post' => $meta['parent_id'],
                                'action' => 'edit'
                            ]);
                            $href = 'href="' . $admin_url . 'post.php?' . $query . '"';
                            $data_tooltip = "data-tooltip=\"$post_title<p><code>$post_type</code> (<em>$post_status</em>)</p><p>{$meta['field_name']}</p>\"";
                            echo (
                                (++$i > 1 ? ', ' : '') .
                                "<a $class $href $data_tooltip>{$meta['parent_id']}</a>"
                            );
                        }
                    } ?>
                </td>
                <td>
                    <?php if ($m->uses_in_term_meta !== null) {
                        $i = 0;
                        foreach ($m->uses_in_term_meta as $meta) {
                            $term = $db->terms_by_id[$meta['parent_id']] ?? null;
                            $term_name = $term->name ?? null;
                            $term_taxonomy = $db->term_taxonomies_by_term_id[$meta['parent_id']] ?? null;
                            $term_taxonomy_name = $term_taxonomy->taxonomy ?? null;
                            $query = http_build_query([
                                'taxonomy' => $term_taxonomy->taxonomy,
                                'tag_ID' => $meta['parent_id']
                            ]);
                            $href = 'href="' . $admin_url . 'term.php?' . $query . '"';
                            $data_tooltip = "data-tooltip=\"$term_name<p><code>$term_taxonomy_name</code></p><p>{$meta['field_name']}</p>\"";
                            echo (
                                (++$i > 1 ? ', ' : '') .
                                "<a $href $data_tooltip>{$meta['parent_id']}</a>"
                            );
                        }
                    } ?>
                </td>
                <td>
                    <?php if ($m->uses_in_term_taxonomy !== null) {
                        $i = 0;
                        foreach ($m->uses_in_term_taxonomy as $taxonomy) {
                            $query = http_build_query([
                                'taxonomy' => $taxonomy['taxonomy'],
                                'tag_ID' => $taxonomy['term_id']
                            ]);
                            $href = 'href="' . $admin_url . 'term.php?' . $query . '"';
                            $data_tooltip = 'data-tooltip="<code>' . $taxonomy['taxonomy'] . '</code>"';
                            echo (
                                (++$i > 1 ? ', ' : '') .
                                "<a $href $data_tooltip>{$taxonomy['term_id']}</a>"
                            );
                        }
                    } ?>
                </td>
                <td>
                    <?php if ($m->uses_in_options !== null): ?>
                        <code><?= implode('</code>, <code>', $m->uses_in_options) ?></code>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>