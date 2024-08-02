# 📜 Kurt Plugin

## 📝 Description

The Kurt Plugin is a minimalistic WordPress plugin designed to generate a JSON file containing links present in each page, post, CPT, or sitemap, along with the URLs where each link was found. It also checks if each URL is available, adding a ✅ true or ❌ false value to indicate its availability.

## 🌟 Features

- 🕵️ Crawls all posts, pages, and custom post types.
- 🗂️ Checks the robots.txt file for sitemaps.
- 🔍 Crawls hypothetical sitemap URLs if they exist.
- 🚫 Ignores irrelevant links such as `mailto:`, `javascript:`, `tel:`, `whatsapp:`, and Google Maps links.
- 📊 Outputs a JSON file with the links found, where they were discovered, and their availability.

## 📥 Installation

1. Upload the plugin to the `wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

## 🚀 Usage

To generate the JSON file with the links, access your site with the URL parameter `?generate_links_json`, e.g., `https://yoursite.com/?generate_links_json`.

## 📄 JSON Structure

The generated JSON file will have the following structure:

```json
{
    "https://example.com/somepage": {
        "found_in": [
            "https://example.com/otherexample",
            "https://example.com/someotherexample",
            "https://example.com/sitemap.xml"
        ],
        "available": true
    },
    ...
}
```
- https://example.com/somepage: The URL of the link.
- found_in: An array of URLs where the link was discovered.
- available: A boolean indicating whether the link is available.

📜 License

This plugin is licensed under the GPL v2 or later.
