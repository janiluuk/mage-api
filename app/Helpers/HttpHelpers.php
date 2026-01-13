<?php

if (!function_exists('parse_http_headers')) {
    /**
     * Parse HTTP headers to collapse internal arrays.
     * Guzzle and some HTTP clients return headers as arrays where each header
     * value is an array. This helper collapses single-element arrays to strings.
     *
     * @param array $headers
     * @return array
     */
    function parse_http_headers(array $headers): array
    {
        return collect($headers)->map(function ($item) {
            return is_array($item) && count($item) === 1 ? $item[0] : $item;
        })->toArray();
    }
}
