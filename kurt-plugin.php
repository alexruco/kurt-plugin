<?php
/**
 * Plugin Name: Kurt Plugin
 * Description: A minimal internal crawler for WP websites.
 * Version: 1.4
 * Author: Alex Ruco
 */

// Hook to generate JSON when accessing a specific URL
add_action('init', 'minimal_link_crawler_init');

function minimal_link_crawler_init() {
    if (isset($_GET['generate_links_json'])) {
        $crawler = new Minimal_Link_Crawler();
        $crawler->generate_links_json();
    }
}

class Minimal_Link_Crawler {
    private $found_links = [];
    private $visited_sitemaps = [];

    public function generate_links_json() {
        // Get all posts, pages, and CPTs
        $all_posts = get_posts(array(
            'post_type' => 'any',
            'numberposts' => -1
        ));

        foreach ($all_posts as $post) {
            $url = get_permalink($post->ID);
            $html = $this->fetch_page($url);
            if ($html !== false) {
                $links = $this->extract_links($html, $url);
                $this->record_links($links, $url);
            }
        }

        // Check robots.txt for sitemaps
        $robots_txt_url = home_url('/robots.txt');
        $this->check_robots_txt($robots_txt_url);

        // Check hypothetical sitemap URLs
        $initial_sitemap_urls = [
            home_url('/sitemap_index.xml'),
            home_url('/sitemap.xml'),
            home_url('/sitemap-pt-post-2024-08.xml'),
            home_url('/sitemap-posttype-post.xml'),
            home_url('/sitemap-posttype-page.xml')
        ];

        foreach ($initial_sitemap_urls as $sitemap_url) {
            if ($this->sitemap_exists($sitemap_url)) {
                $this->crawl_sitemap($sitemap_url, 'Initial sitemap list');
            }
        }

        // Output the JSON
        header('Content-Type: application/json');
        echo json_encode($this->found_links);
        exit;
    }

    private function fetch_page($url) {
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    private function extract_links($html, $base_url) {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        $links = [];
        foreach ($dom->getElementsByTagName('a') as $node) {
            $href = $node->getAttribute('href');
            $href = $this->resolve_url($href, $base_url);

            if ($href !== null && $this->is_relevant_link($href)) {
                $links[] = $href;
            }
        }

        return $links;
    }

    private function resolve_url($relative_url, $base_url) {
        // Remove anchors
        $relative_url = strtok($relative_url, '#');

        // Ignore irrelevant links
        if (!$this->is_relevant_link($relative_url)) {
            return null;
        }

        // Resolve relative URL
        $base_url_parts = parse_url($base_url);
        $relative_url_parts = parse_url($relative_url);

        if (!isset($relative_url_parts['scheme'])) {
            $relative_url = $base_url_parts['scheme'] . '://' . $base_url_parts['host'] . '/' . ltrim($relative_url, '/');
        }

        return $relative_url;
    }

    private function is_relevant_link($url) {
        $irrelevant_schemes = ['mailto:', 'javascript:', 'tel:', 'whatsapp:'];
        foreach ($irrelevant_schemes as $scheme) {
            if (strpos($url, $scheme) === 0) {
                return false;
            }
        }

        if (strpos($url, 'maps.google') !== false || strpos($url, 'google.com/maps') !== false) {
            return false;
        }

        return true;
    }

    private function record_links($links, $url) {
        foreach ($links as $link) {
            if (!isset($this->found_links[$link])) {
                $this->found_links[$link] = [
                    'found_in' => [],
                    'available' => $this->url_exists($link)
                ];
            }
            if (!in_array($url, $this->found_links[$link]['found_in'])) {
                $this->found_links[$link]['found_in'][] = $url;
            }
        }
    }

    private function crawl_sitemap($sitemap_url, $origin) {
        if (in_array($sitemap_url, $this->visited_sitemaps)) {
            return;
        }

        $this->visited_sitemaps[] = $sitemap_url;
        $this->record_links([$sitemap_url], $origin);

        $html = $this->fetch_page($sitemap_url);
        if ($html === false) {
            return;
        }

        $dom = new DOMDocument;
        @$dom->loadXML($html);

        foreach ($dom->getElementsByTagName('sitemap') as $sitemap) {
            $loc = $sitemap->getElementsByTagName('loc')->item(0)->nodeValue;
            $this->crawl_sitemap($loc, $sitemap_url);
        }

        foreach ($dom->getElementsByTagName('url') as $url) {
            $loc = $url->getElementsByTagName('loc')->item(0)->nodeValue;
            $html = $this->fetch_page($loc);
            if ($html !== false) {
                $links = $this->extract_links($html, $loc);
                $this->record_links($links, $loc);
            }
        }
    }

    private function sitemap_exists($sitemap_url) {
        $response = wp_remote_head($sitemap_url);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200;
    }

    private function url_exists($url) {
        $response = wp_remote_head($url);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200;
    }

    private function check_robots_txt($robots_txt_url) {
        $content = $this->fetch_page($robots_txt_url);
        if ($content === false) {
            return;
        }

        // Parse robots.txt for Sitemap directives
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (stripos($line, 'Sitemap:') === 0) {
                $sitemap_url = trim(substr($line, 8));
                $this->crawl_sitemap($sitemap_url, $robots_txt_url);
            }
        }
    }
}
?>
