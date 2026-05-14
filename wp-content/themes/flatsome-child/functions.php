<?php
/**
 * Deligo Auto Multilingual URL + Auto Detect Browser/IP + Hidden Google Translate UI
 */

if (!defined('ABSPATH')) {
    exit;
}

function dlml_languages() {
    return [
        'vi' => ['name' => 'Tiếng Việt', 'google' => 'vi'],
        'en' => ['name' => 'English',    'google' => 'en'],
    ];
}

function dlml_default_lang() {
    return 'en';
}

function dlml_flag_emoji($lang) {
    $flags = ['vi' => '🇻🇳', 'en' => '🇺🇸'];
    return $flags[$lang] ?? '🏳️';
}

function dlml_valid_lang($lang) {
    return isset(dlml_languages()[$lang]);
}

function dlml_google_code($lang) {
    $langs = dlml_languages();
    return $langs[$lang]['google'] ?? dlml_default_lang();
}

function dlml_normalize_lang($lang) {
    $lang = strtolower(trim((string) $lang));
    return str_replace('_', '-', $lang);
}

function dlml_lang_regex() {
    return implode('|', array_map(function ($lang) {
        return preg_quote($lang, '#');
    }, array_keys(dlml_languages())));
}

function dlml_current_lang() {
    $query_lang = get_query_var('dlml_lang');
    if ($query_lang && dlml_valid_lang($query_lang)) {
        return $query_lang;
    }
    $path  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    $first = $parts[0] ?? '';
    if ($first && dlml_valid_lang($first)) {
        return $first;
    }
    return dlml_default_lang();
}

function dlml_is_auth_path($path) {
    $path   = trim((string) $path, '/');
    $parts  = explode('/', $path);
    $first  = $parts[0] ?? '';
    $second = $parts[1] ?? '';
    $auth_slugs = ['wp-login.php', 'wp-admin', 'login-deligo', 'admin'];
    if (in_array($first, $auth_slugs, true)) return true;
    if ($first && dlml_valid_lang($first) && in_array($second, $auth_slugs, true)) return true;
    return false;
}

function dlml_detect_browser_language() {
    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return '';
    $header = sanitize_text_field($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $items  = explode(',', $header);
    $langs  = [];
    foreach ($items as $item) {
        $parts = explode(';q=', trim($item));
        $code  = dlml_normalize_lang($parts[0] ?? '');
        $q     = isset($parts[1]) ? (float) $parts[1] : 1.0;
        if (!$code) continue;
        $langs[] = ['code' => $code, 'q' => $q];
    }
    usort($langs, function ($a, $b) { return $b['q'] <=> $a['q']; });
    foreach ($langs as $item) {
        $code = $item['code'];
        if (dlml_valid_lang($code)) return $code;
        if (substr($code, 0, 2) === 'en') return 'en';
        if (substr($code, 0, 2) === 'vi') return 'vi';
    }
    return '';
}

function dlml_detect_cloudflare_ip_language() {
    if (empty($_SERVER['HTTP_CF_IPCOUNTRY'])) return '';
    $country = strtoupper(sanitize_text_field($_SERVER['HTTP_CF_IPCOUNTRY']));
    return $country === 'VN' ? 'vi' : 'en';
}

function dlml_detect_target_language() {
    return dlml_default_lang();
}

add_action('init', function () {
    $regex = dlml_lang_regex();
    add_rewrite_tag('%dlml_lang%', '(' . $regex . ')');
    add_rewrite_tag('%dlml_path%', '(.+)');
    $front_page_id = (int) get_option('page_on_front');
    if (get_option('show_on_front') === 'page' && $front_page_id) {
        add_rewrite_rule('^(' . $regex . ')/?$', 'index.php?dlml_lang=$matches[1]&page_id=' . $front_page_id, 'top');
    } else {
        add_rewrite_rule('^(' . $regex . ')/?$', 'index.php?dlml_lang=$matches[1]', 'top');
    }
    add_rewrite_rule('^(' . $regex . ')/(.+?)/?$', 'index.php?dlml_lang=$matches[1]&dlml_path=$matches[2]', 'top');
}, 20);

add_filter('query_vars', function ($vars) {
    $vars[] = 'dlml_lang';
    $vars[] = 'dlml_path';
    return $vars;
});

add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    $path  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    $first = $parts[0] ?? '';
    if ($first && dlml_valid_lang($first)) return false;
    return $redirect_url;
}, 10, 2);

add_filter('request', function ($query_vars) {
    if (!empty($query_vars['dlml_lang']) && empty($query_vars['dlml_path'])) {
        $front_page_id = (int) get_option('page_on_front');
        if (get_option('show_on_front') === 'page' && $front_page_id) {
            $query_vars['page_id'] = $front_page_id;
        }
        return $query_vars;
    }
    if (empty($query_vars['dlml_path'])) return $query_vars;
    $path = trim($query_vars['dlml_path'], '/');
    unset($query_vars['dlml_path']);
    $page = get_page_by_path($path, OBJECT, 'page');
    if ($page && $page->post_status === 'publish') {
        $query_vars['pagename'] = $path;
        return $query_vars;
    }
    $post_id = url_to_postid(home_url('/' . $path . '/'));
    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            if ($post->post_type === 'page') {
                $query_vars['page_id'] = $post_id;
            } else {
                $query_vars['name']      = $post->post_name;
                $query_vars['post_type'] = $post->post_type;
            }
            return $query_vars;
        }
    }
    $slug       = basename($path);
    $post_types = get_post_types(['public' => true], 'names');
    unset($post_types['attachment']);
    $posts = get_posts([
        'name'             => $slug,
        'post_type'        => array_values($post_types),
        'post_status'      => 'publish',
        'numberposts'      => 1,
        'suppress_filters' => false,
    ]);
    if (!empty($posts)) {
        $query_vars['name']      = $posts[0]->post_name;
        $query_vars['post_type'] = $posts[0]->post_type;
    }
    return $query_vars;
});

function dlml_should_skip_redirect($path, $first) {
    if (dlml_is_auth_path($path)) return true;
    $skip_prefixes = ['wp-admin','wp-login.php','wp-json','wp-content','wp-includes','xmlrpc.php','feed','robots.txt','sitemap.xml','sitemap_index.xml','favicon.ico','wp-cron.php'];
    if (in_array($first, $skip_prefixes, true)) return true;
    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|pdf|zip|xml|txt|json|woff|woff2|ttf|eot|map)$/i', $path)) return true;
    return false;
}

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || is_preview()) return;
    if (!isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) return;
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path        = trim(parse_url($request_uri, PHP_URL_PATH), '/');
    $query       = parse_url($request_uri, PHP_URL_QUERY);
    $parts       = explode('/', $path);
    $first       = $parts[0] ?? '';
    if ($first && dlml_valid_lang($first) && dlml_is_auth_path($path)) {
        $path_parts = explode('/', $path);
        array_shift($path_parts);
        $clean_path = implode('/', array_filter($path_parts));
        $target_url = home_url('/' . $clean_path . '/');
        if (!empty($query)) $target_url .= '?' . $query;
        wp_safe_redirect($target_url, 302);
        exit;
    }
    if (dlml_is_auth_path($path)) return;
    if ($first && dlml_valid_lang($first)) {
        setcookie('site_lang', sanitize_text_field($first), time() + 365 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        return;
    }
    if (dlml_should_skip_redirect($path, $first)) return;
    $target_lang = dlml_detect_target_language();
    if (!$target_lang || !dlml_valid_lang($target_lang)) $target_lang = dlml_default_lang();
    $target_path = '/' . $target_lang . '/';
    if (!empty($path)) $target_path .= $path . '/';
    $target_url = home_url($target_path);
    if (!empty($query)) $target_url .= '?' . $query;
    setcookie('site_lang', sanitize_text_field($target_lang), time() + 365 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
    nocache_headers();
    wp_safe_redirect($target_url, 302);
    exit;
}, 0);

function dlml_url_for_lang($lang) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path        = trim(parse_url($request_uri, PHP_URL_PATH), '/');
    $query       = parse_url($request_uri, PHP_URL_QUERY);
    $parts       = explode('/', $path);
    if (!empty($parts[0]) && dlml_valid_lang($parts[0])) array_shift($parts);
    $new_path    = '/' . $lang . '/';
    $clean_parts = array_filter($parts);
    if (!empty($clean_parts)) $new_path .= implode('/', $clean_parts) . '/';
    $url = home_url($new_path);
    if (!empty($query)) $url .= '?' . $query;
    return $url;
}

function dlml_prefix_internal_url($url) {
    if (is_admin()) return $url;
    $current_path  = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $current_parts = explode('/', $current_path);
    $current_first = $current_parts[0] ?? '';
    if (!$current_first || !dlml_valid_lang($current_first)) return $url;
    $lang = $current_first;
    $home = rtrim(home_url('/'), '/');
    if (strpos($url, $home) !== 0) return $url;
    $relative = ltrim(substr($url, strlen($home)), '/');
    if (dlml_is_auth_path($relative)) return $url;
    if ($relative === '') return trailingslashit($home . '/' . $lang);
    $parts = explode('/', $relative);
    if (!empty($parts[0]) && dlml_valid_lang($parts[0])) return $url;
    return trailingslashit($home . '/' . $lang . '/' . $relative);
}

add_filter('page_link',      'dlml_prefix_internal_url', 10, 1);
add_filter('post_link',      'dlml_prefix_internal_url', 10, 1);
add_filter('post_type_link', 'dlml_prefix_internal_url', 10, 1);

/**
 * Frontend UI.
 */
add_action('wp_footer', function () {
    if (is_admin()) return;
    $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if (dlml_is_auth_path($current_path)) return;

    $current_lang   = dlml_current_lang();
    $current_google = dlml_google_code($current_lang);
    $default_lang   = dlml_default_lang();
    $default_google = 'vi';
    $langs          = dlml_languages();
    ?>

    <style>
        #dlml_translate_element,
.goog-te-banner-frame,
iframe.goog-te-banner-frame,
.goog-te-gadget,
.goog-te-gadget-simple,
.goog-te-gadget-icon,
.goog-te-menu-value,
.goog-te-balloon-frame,
#goog-gt-tt,
.VIpgJd-ZVi9od-ORHb-OEVmcd,
.VIpgJd-ZVi9od-aZ2wEe-wOHMyf,
.VIpgJd-yAWNEb-L7lbkb {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    height: 0 !important;
    width: 0 !important;
    overflow: hidden !important;
    pointer-events: none !important;
}

html,
body {
    top: 0 !important;
    margin-top: 0 !important;
}

body {
    position: static !important;
}

/* ================================
   DELIGO LANGUAGE SWITCHER FIX
================================ */

#dlml_lang_switcher,
#dlml_lang_switcher * {
    box-sizing: border-box !important;
}

#dlml_lang_switcher {
    position: fixed !important;
    left: 20px !important;
    bottom: 20px !important;
    z-index: 2147483647 !important;
    width: 48px !important;
    height: 48px !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
}

/* Reset button theme / Elementor */
#dlml_lang_switcher button {
    appearance: none !important;
    -webkit-appearance: none !important;
    font: inherit !important;
    text-transform: none !important;
    letter-spacing: 0 !important;
    min-width: 0 !important;
    min-height: 0 !important;
    max-width: none !important;
    max-height: none !important;
    margin: 0 !important;
    border: 0 !important;
    outline: none !important;
    box-shadow: none !important;
    background: transparent !important;
}

/* Main circle button */
#dlml_lang_switcher .dlml_toggle {
    width: 48px !important;
    height: 48px !important;
    min-width: 48px !important;
    min-height: 48px !important;
    max-width: 48px !important;
    max-height: 48px !important;
    padding: 0 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    background: #ffffff !important;
    border: 1px solid rgba(15, 23, 42, 0.12) !important;
    border-radius: 999px !important;
    cursor: pointer !important;

    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.16) !important;
    overflow: hidden !important;
    line-height: 1 !important;
    transition: transform .16s ease, box-shadow .16s ease !important;
}

#dlml_lang_switcher .dlml_toggle:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.22) !important;
}

#dlml_lang_switcher .dlml_toggle:active {
    transform: scale(.96) !important;
}

#dlml_lang_switcher .dlml_toggle span,
#dlml_lang_switcher .dlml_item_flag {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 24px !important;
    line-height: 1 !important;
}

/* Fix WordPress emoji image */
#dlml_lang_switcher img.emoji,
#dlml_lang_switcher img.wp-smiley {
    width: 24px !important;
    height: 24px !important;
    max-width: 24px !important;
    max-height: 24px !important;
    margin: 0 !important;
    padding: 0 !important;
    vertical-align: middle !important;
}

/* Dropdown */
#dlml_lang_switcher .dlml_menu {
    position: absolute !important;
    left: 50% !important;
    bottom: calc(100% + 10px) !important;

    width: 56px !important;
    padding: 6px !important;

    display: flex !important;
    flex-direction: column !important;
    gap: 4px !important;

    background: rgba(255, 255, 255, 0.96) !important;
    border: 1px solid rgba(15, 23, 42, 0.10) !important;
    border-radius: 18px !important;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.20) !important;
    backdrop-filter: blur(14px) !important;
    -webkit-backdrop-filter: blur(14px) !important;

    opacity: 0 !important;
    pointer-events: none !important;
    overflow: hidden !important;

    transform: translateX(-50%) translateY(8px) scale(.96) !important;
    transform-origin: bottom center !important;
    transition: opacity .18s ease, transform .18s ease !important;
}

#dlml_lang_switcher.is-open .dlml_menu {
    opacity: 1 !important;
    pointer-events: auto !important;
    transform: translateX(-50%) translateY(0) scale(1) !important;
}

/* Dropdown item */
#dlml_lang_switcher .dlml_item {
    position: relative !important;

    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
    min-height: 44px !important;
    max-width: 44px !important;
    max-height: 44px !important;

    padding: 0 !important;
    margin: 0 !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    border-radius: 14px !important;
    cursor: pointer !important;
    background: transparent !important;
    overflow: hidden !important;
    line-height: 1 !important;
    transition: background .15s ease, transform .15s ease !important;
}

#dlml_lang_switcher .dlml_item:hover {
    background: rgba(83, 74, 183, 0.08) !important;
}

#dlml_lang_switcher .dlml_item.is-active {
    background: rgba(83, 74, 183, 0.12) !important;
}

#dlml_lang_switcher .dlml_item_check {
    position: absolute !important;
    right: 5px !important;
    bottom: 5px !important;

    width: 14px !important;
    height: 14px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    background: #534AB7 !important;
    color: #ffffff !important;
    border-radius: 999px !important;

    font-size: 9px !important;
    line-height: 1 !important;
    font-weight: 800 !important;

    opacity: 0 !important;
}

#dlml_lang_switcher .dlml_item.is-active .dlml_item_check {
    opacity: 1 !important;
}

@media (max-width: 767px) {
    #dlml_lang_switcher {
        left: 14px !important;
        bottom: 14px !important;
    }
}
    </style>

    <div id="dlml_lang_switcher">
        <div class="dlml_menu" id="dlmlMenu" role="menu">
            <?php foreach ($langs as $code => $data): ?>
                <button
                    type="button"
                    class="dlml_item <?php echo $code === $current_lang ? 'is-active' : ''; ?>"
                    role="menuitem"
                    data-lang="<?php echo esc_attr($code); ?>"
                    data-google="<?php echo esc_attr($data['google']); ?>"
                    data-url="<?php echo esc_url(dlml_url_for_lang($code)); ?>"
                >
                    <span class="dlml_item_flag"><?php echo esc_html(dlml_flag_emoji($code)); ?></span>
                    <span class="dlml_item_check" aria-hidden="true">✓</span>
                </button>
            <?php endforeach; ?>
        </div>

        <button
            type="button"
            class="dlml_toggle"
            id="dlmlToggle"
            aria-haspopup="true"
            aria-expanded="false"
            aria-label="Select language"
        >
            <span id="dlmlCurrentFlag"><?php echo esc_html(dlml_flag_emoji($current_lang)); ?></span>
        </button>
    </div>

    <script>
        window.DLML_CURRENT_LANG        = "<?php echo esc_js($current_lang); ?>";
        window.DLML_CURRENT_GOOGLE_LANG = "<?php echo esc_js($current_google); ?>";
        window.DLML_DEFAULT_LANG        = "<?php echo esc_js($default_lang); ?>";
        window.DLML_DEFAULT_GOOGLE_LANG = "<?php echo esc_js($default_google); ?>";
        window.DLML_LANG_CODES          = <?php echo wp_json_encode(array_keys(dlml_languages())); ?>;

        window.dlmlSetCookie = function(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + encodeURIComponent(value || "") + expires + "; path=/";
        };

        window.dlmlSetRawCookie = function(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + value + expires + "; path=/";
            var host = window.location.hostname;
            document.cookie = name + "=" + value + expires + "; path=/; domain=" + host;
            var parts = host.split(".");
            if (parts.length >= 2) {
                var rootDomain = "." + parts.slice(-2).join(".");
                document.cookie = name + "=" + value + expires + "; path=/; domain=" + rootDomain;
            }
        };

        window.dlmlHideGoogleTranslateUI = function() {
            document.documentElement.style.top = "0px";
            if (document.body) {
                document.body.style.top = "0px";
                document.body.style.marginTop = "0px";
            }
            var selectors = [
                ".goog-te-banner-frame", "iframe.goog-te-banner-frame",
                ".goog-te-gadget", ".goog-te-balloon-frame", "#goog-gt-tt",
                ".VIpgJd-ZVi9od-ORHb-OEVmcd", ".VIpgJd-ZVi9od-aZ2wEe-wOHMyf",
                ".VIpgJd-yAWNEb-L7lbkb"
            ];
            selectors.forEach(function(selector) {
                document.querySelectorAll(selector).forEach(function(el) {
                    el.style.cssText = "display:none!important;visibility:hidden!important;opacity:0!important;height:0!important;width:0!important;overflow:hidden!important;pointer-events:none!important;";
                });
            });
        };

        window.dlmlPrefixInternalLinks = function() {
            var path  = window.location.pathname.replace(/^\/+/, "");
            var first = path.split("/")[0];
            if (window.DLML_LANG_CODES.indexOf(first) === -1) return;
            document.querySelectorAll("a[href]").forEach(function(link) {
                var href = link.getAttribute("href");
                if (!href || href.indexOf("#") === 0 || href.indexOf("mailto:") === 0 ||
                    href.indexOf("tel:") === 0 || href.indexOf("javascript:") === 0) return;
                var url;
                try { url = new URL(href, window.location.origin); } catch(e) { return; }
                if (url.origin !== window.location.origin) return;
                var linkPath  = url.pathname.replace(/^\/+/, "");
                var linkFirst = linkPath.split("/")[0];
                if (window.DLML_LANG_CODES.indexOf(linkFirst) !== -1 ||
                    linkFirst === "wp-admin" || linkFirst === "wp-login.php" ||
                    linkFirst === "login-deligo" || linkFirst === "admin" ||
                    linkFirst === "wp-content" || linkFirst === "wp-includes" ||
                    linkFirst === "wp-json") return;
                url.pathname = "/" + first + "/" + linkPath;
                link.setAttribute("href", url.toString());
            });
        };

        window.dlmlInitSwitcher = function() {
            var wrapper = document.getElementById("dlml_lang_switcher");
            var toggle  = document.getElementById("dlmlToggle");
            var menu    = document.getElementById("dlmlMenu");
            if (!wrapper || wrapper.getAttribute("data-ready") === "1") return;
            wrapper.setAttribute("data-ready", "1");

            function openMenu()  { wrapper.classList.add("is-open");    toggle.setAttribute("aria-expanded", "true"); }
            function closeMenu() { wrapper.classList.remove("is-open"); toggle.setAttribute("aria-expanded", "false"); }

            toggle.addEventListener("click", function(e) {
                e.stopPropagation();
                wrapper.classList.contains("is-open") ? closeMenu() : openMenu();
            });
            document.addEventListener("click", function(e) {
                if (!wrapper.contains(e.target)) closeMenu();
            });
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") closeMenu();
            });

            menu.addEventListener("click", function(e) {
                var item = e.target.closest(".dlml_item");
                if (!item) return;
                e.preventDefault();
                window.dlmlSetCookie("site_lang", item.getAttribute("data-lang"), 365);
                window.location.href = item.getAttribute("data-url");
            });
        };

        document.addEventListener("DOMContentLoaded", function() {
            window.dlmlPrefixInternalLinks();
            window.dlmlHideGoogleTranslateUI();
            window.dlmlInitSwitcher();
            new MutationObserver(function() {
                window.dlmlHideGoogleTranslateUI();
            }).observe(document.documentElement, { childList: true, subtree: true });
        });

        window.addEventListener("load", function() {
            window.dlmlInitSwitcher();
            window.dlmlHideGoogleTranslateUI();
        });
    </script>
    
    
    <?php
});

add_action('wp_head', function () {

    if (is_admin()) {
        return;
    }

    if (dlml_current_lang() !== 'en') {
        return;
    }

?>
<div id="dlml_translate_element" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;"></div>

<style id="dlml-preload-style">
html.dlml-translating body {
    overflow: hidden !important;
}

html.dlml-translating body > *:not(#dlmlPageLoader):not(script):not(style):not(link):not(meta) {
    opacity: 0 !important;
    visibility: hidden !important;
}

#dlmlPageLoader {
    position: fixed !important;
    inset: 0 !important;
    z-index: 2147483647 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #fafaf8 !important;
    transition: opacity .35s ease, visibility .35s ease !important;
}

#dlmlPageLoader.is-hide {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.dlml-loader-card {
    width: min(320px, calc(100vw - 48px));
    padding: 28px 24px;
    border-radius: 28px;
    background: rgba(255,255,255,.82);
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 24px 70px rgba(15,23,42,.14);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    text-align: center;
    font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.dlml-loader-logo {
    width: 54px;
    height: 54px;
    margin: 0 auto 14px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #534AB7;
    color: #fff;
    font-weight: 900;
    font-size: 18px;
    letter-spacing: -.04em;
    box-shadow: 0 16px 36px rgba(83,74,183,.28);
}

.dlml-loader-title {
    margin: 0;
    color: #101114;
    font-size: 17px;
    line-height: 1.35;
    font-weight: 800;
}

.dlml-loader-text {
    margin: 8px 0 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.dlml-loader-bar {
    position: relative;
    height: 5px;
    margin-top: 18px;
    border-radius: 999px;
    overflow: hidden;
    background: rgba(15,23,42,.08);
}

.dlml-loader-bar::before {
    content: "";
    position: absolute;
    inset: 0;
    width: 42%;
    border-radius: inherit;
    background: linear-gradient(90deg, #534AB7, #F5B544);
    animation: dlmlLoading 1s ease-in-out infinite;
}

@keyframes dlmlLoading {
    0% {
        transform: translateX(-120%);
    }
    100% {
        transform: translateX(260%);
    }
}

/* Hide Google Translate top banner / injected UI */
iframe.goog-te-banner-frame,
.goog-te-banner-frame,
.skiptranslate,
#goog-gt-tt,
.goog-tooltip,
.goog-tooltip:hover,
.VIpgJd-ZVi9od-ORHb-OEVmcd,
.VIpgJd-ZVi9od-aZ2wEe-wOHMyf,
.VIpgJd-yAWNEb-L7lbkb {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    height: 0 !important;
    width: 0 !important;
    max-height: 0 !important;
    overflow: hidden !important;
    pointer-events: none !important;
}

html,
body {
    top: 0 !important;
    margin-top: 0 !important;
}
</style>

<script>
(function () {
    var MAX_WAIT = 2200;
    var EXTRA_DELAY = 250;

    document.documentElement.classList.add("dlml-translating");

    document.write(
    '<div id="dlmlPageLoader">' +
        '<div class="dlml-loader-card">' +
            '<div class="dlml-loader-logo">DT</div>' +
            '<p class="dlml-loader-title">Loading content</p>' +
            '<p class="dlml-loader-text">Please wait a moment.</p>' +
            '<div class="dlml-loader-bar"></div>' +
        '</div>' +
    '</div>'
);

    function setTranslateCookie() {
        var value = "/vi/en";
        var host = location.hostname;

        document.cookie = "googtrans=" + value + ";path=/";
        document.cookie = "googtrans=" + value + ";path=/;domain=" + host;

        var parts = host.split(".");
        if (parts.length >= 2) {
            var root = "." + parts.slice(-2).join(".");
            document.cookie = "googtrans=" + value + ";path=/;domain=" + root;
        }
    }

    setTranslateCookie();

    var revealed = false;

    function hideLoader() {
        var loader = document.getElementById("dlmlPageLoader");
        if (loader) {
            loader.classList.add("is-hide");
            setTimeout(function () {
                if (loader && loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
            }, 400);
        }
    }

    function cleanup() {
        document.documentElement.classList.remove("dlml-translating");


        if (window.dlmlHideGoogleTranslateUI) {
            window.dlmlHideGoogleTranslateUI();
        }

        hideLoader();
    }

    function revealPage() {
        if (revealed) return;
        revealed = true;
        cleanup();
    }

    function hasGoogleTranslatedClass() {
        var cls = document.documentElement.className || "";
        return cls.indexOf("translated-ltr") !== -1 || cls.indexOf("translated-rtl") !== -1;
    }

    function hasVietnameseText() {
        if (!document.body) return false;

        var text = document.body.innerText || "";

        var viTexts = [
            "Trang chủ",
            "Dịch Vụ",
            "Công Nghệ",
            "Liên Hệ",
            "Khám Phá",
            "Hệ Sinh Thái",
            "Nền tảng",
            "Tư Vấn"
        ];

        for (var i = 0; i < viTexts.length; i++) {
            if (text.indexOf(viTexts[i]) !== -1) return true;
        }

        return false;
    }

    function hasEnglishText() {
        if (!document.body) return false;

        var text = document.body.innerText || "";

        var enTexts = [
            "Home",
            "Services",
            "Contact",
            "Explore",
            "Technology",
            "Digital",
            "Business",
            "Ecosystem",
            "Platform",
            "Solution"
        ];

        for (var i = 0; i < enTexts.length; i++) {
            if (text.indexOf(enTexts[i]) !== -1) return true;
        }

        return false;
    }

    function checkTranslated() {
        if (hasGoogleTranslatedClass()) {
            setTimeout(revealPage, EXTRA_DELAY);
            return true;
        }

        if (hasEnglishText() && !hasVietnameseText()) {
            setTimeout(revealPage, EXTRA_DELAY);
            return true;
        }

        return false;
    }

    var observer = new MutationObserver(function () {
        if (checkTranslated()) {
            observer.disconnect();
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        characterData: true
    });

    var interval = setInterval(function () {
        if (checkTranslated()) {
            clearInterval(interval);
            observer.disconnect();
        }
    }, 120);

    setTimeout(function () {
        clearInterval(interval);
        observer.disconnect();
        revealPage();
    }, MAX_WAIT);
})();
</script>

<script>
function deligoGoogleTranslateInit() {
    function init() {
        var el = document.getElementById("dlml_translate_element");

        if (
            el &&
            typeof google !== "undefined" &&
            google.translate &&
            google.translate.TranslateElement
        ) {
            new google.translate.TranslateElement({
                pageLanguage: "vi",
                includedLanguages: "en,vi",
                autoDisplay: false
            }, "dlml_translate_element");

            return true;
        }

        return false;
    }

    if (init()) return;

    var tries = 0;
    var timer = setInterval(function () {
        tries++;

        if (init() || tries > 25) {
            clearInterval(timer);
        }
    }, 100);
}
</script>

<script src="https://translate.google.com/translate_a/element.js?cb=deligoGoogleTranslateInit"></script>

<?php

}, -9999);


/**
 * Hreflang
 */
add_action('wp_head', function () {
    if (is_admin()) return;
    foreach (['vi', 'en'] as $lang) {
        echo '<link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url(dlml_url_for_lang($lang)) . '">' . "\n";
    }
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url(home_url('/')) . '">' . "\n";
});