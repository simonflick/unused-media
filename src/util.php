<?php

// remove base url, escaped slashes and beginning slash
function normalize_media_path($path) {
    $tmp = str_replace('\/', '/', $path);
    $tmp = str_replace(['https://', SITE_HOST, 'wp-content/uploads/'], '', $tmp);
    return ltrim($tmp, '/');
};