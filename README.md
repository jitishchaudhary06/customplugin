# Custom Post Type

WordPress plugin that registers a real custom post type, with the same admin screen and database storage as normal posts.

Posts are saved in `wp_posts`, extra fields in `wp_postmeta`, and categories/tags in the WordPress term tables.

- Admin menu **Custom Post Type** uses the normal post editor, list, categories, and tags.
- Each published post has its own frontend URL, plus an archive at `/custom-post/`.
- Shortcode: `[custom_posts]`
- Deleting the plugin from the Plugins screen removes these posts, categories, and tags.
