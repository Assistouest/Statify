<?php
/**
 * Référents connus — source de vérité unique.
 *
 * Chaque entrée :
 *   'pattern' → regex PHP (sans délimiteurs, insensible à la casse automatiquement)
 *               CONVENTION : préfixer avec (^|\.) pour éviter les faux positifs
 *               Ex: (^|\.)twitter\. matche twitter.com et www.twitter.com
 *                   mais PAS copilot.microsoft.com
 *   'cat'     → 'search' | 'social' | 'ai' | 'site'
 *   'label'   → nom affiché dans l'interface
 *   'color'   → couleur hexadécimale de la marque
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * POUR AJOUTER UNE SOURCE :
 *   1. Choisir la bonne section (search / social / ai / site)
 *   2. Copier-coller une ligne existante et modifier les 4 champs
 *   3. Sauvegarder — pris en compte immédiatement, pas de cache à vider
 * ──────────────────────────────────────────────────────────────────────────────
 *
 * @package Always_Analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(

    // =========================================================================
    // MOTEURS DE RECHERCHE
    // =========================================================================

    array( 'pattern' => '(^|\.)google\.',       'cat' => 'search', 'label' => 'Google',      'color' => '#4285F4' ),
    array( 'pattern' => '(^|\.)bing\.',          'cat' => 'search', 'label' => 'Bing',         'color' => '#00897B' ),
    array( 'pattern' => '(^|\.)yahoo\.',         'cat' => 'search', 'label' => 'Yahoo',        'color' => '#720E9E' ),
    array( 'pattern' => '(^|\.)duckduckgo\.',    'cat' => 'search', 'label' => 'DuckDuckGo',   'color' => '#DE5833' ),
    array( 'pattern' => '(^|\.)qwant\.',         'cat' => 'search', 'label' => 'Qwant',        'color' => '#9963EA' ),
    array( 'pattern' => '(^|\.)ecosia\.',        'cat' => 'search', 'label' => 'Ecosia',       'color' => '#2E7D32' ),
    array( 'pattern' => '(^|\.)yandex\.',        'cat' => 'search', 'label' => 'Yandex',       'color' => '#FF0000' ),
    array( 'pattern' => '(^|\.)baidu\.',         'cat' => 'search', 'label' => 'Baidu',        'color' => '#2932E1' ),
    array( 'pattern' => '(^|\.)naver\.',         'cat' => 'search', 'label' => 'Naver',        'color' => '#03C75A' ),
    array( 'pattern' => '(^|\.)brave\.com',      'cat' => 'search', 'label' => 'Brave Search', 'color' => '#FB542B' ),
    array( 'pattern' => '(^|\.)startpage\.',     'cat' => 'search', 'label' => 'Startpage',    'color' => '#5CB85C' ),
    array( 'pattern' => '(^|\.)ask\.',           'cat' => 'search', 'label' => 'Ask',          'color' => '#E65100' ),
    array( 'pattern' => '(^|\.)seznam\.',        'cat' => 'search', 'label' => 'Seznam',       'color' => '#CC0000' ),
    array( 'pattern' => '(^|\.)swisscows\.',     'cat' => 'search', 'label' => 'Swisscows',    'color' => '#9C27B0' ),
    array( 'pattern' => '(^|\.)lilo\.',          'cat' => 'search', 'label' => 'Lilo',         'color' => '#FF6B35' ),
    array( 'pattern' => '(^|\.)sogou\.',         'cat' => 'search', 'label' => 'Sogou',        'color' => '#E74C3C' ),
    array( 'pattern' => '(^|\.)so\.com',         'cat' => 'search', 'label' => '360 Search',   'color' => '#1ABC9C' ),

    // =========================================================================
    // RÉSEAUX SOCIAUX
    // =========================================================================

    array( 'pattern' => '(^|\.)facebook\.',     'cat' => 'social', 'label' => 'Facebook',    'color' => '#1877F2' ),
    array( 'pattern' => '^l\.facebook\.',        'cat' => 'social', 'label' => 'Facebook',    'color' => '#1877F2' ),
    array( 'pattern' => '^fb\.com',              'cat' => 'social', 'label' => 'Facebook',    'color' => '#1877F2' ),
    array( 'pattern' => '(^|\.)instagram\.',     'cat' => 'social', 'label' => 'Instagram',   'color' => '#E1306C' ),
    array( 'pattern' => '(^|\.)twitter\.',       'cat' => 'social', 'label' => 'Twitter / X', 'color' => '#000000' ),
    array( 'pattern' => '^t\.co$',               'cat' => 'social', 'label' => 'Twitter / X', 'color' => '#000000' ),
    array( 'pattern' => '^x\.com',               'cat' => 'social', 'label' => 'X',           'color' => '#000000' ),
    array( 'pattern' => '(^|\.)linkedin\.',      'cat' => 'social', 'label' => 'LinkedIn',    'color' => '#0A66C2' ),
    array( 'pattern' => '(^|\.)pinterest\.',     'cat' => 'social', 'label' => 'Pinterest',   'color' => '#E60023' ),
    array( 'pattern' => '(^|\.)tiktok\.',        'cat' => 'social', 'label' => 'TikTok',      'color' => '#010101' ),
    array( 'pattern' => '(^|\.)youtube\.',       'cat' => 'social', 'label' => 'YouTube',     'color' => '#FF0000' ),
    array( 'pattern' => '^youtu\.be',            'cat' => 'social', 'label' => 'YouTube',     'color' => '#FF0000' ),
    array( 'pattern' => '(^|\.)reddit\.',        'cat' => 'social', 'label' => 'Reddit',      'color' => '#FF4500' ),
    array( 'pattern' => '(^|\.)discord\.',       'cat' => 'social', 'label' => 'Discord',     'color' => '#5865F2' ),
    array( 'pattern' => '(^|\.)snapchat\.',      'cat' => 'social', 'label' => 'Snapchat',    'color' => '#FFFC00' ),
    array( 'pattern' => '(^|\.)whatsapp\.',      'cat' => 'social', 'label' => 'WhatsApp',    'color' => '#25D366' ),
    array( 'pattern' => '(^|\.)telegram\.',      'cat' => 'social', 'label' => 'Telegram',    'color' => '#2AABEE' ),
    array( 'pattern' => '(^|\.)mastodon\.',      'cat' => 'social', 'label' => 'Mastodon',    'color' => '#6364FF' ),
    array( 'pattern' => '(^|\.)bsky\.',          'cat' => 'social', 'label' => 'Bluesky',     'color' => '#0085FF' ),
    array( 'pattern' => '(^|\.)bluesky\.',       'cat' => 'social', 'label' => 'Bluesky',     'color' => '#0085FF' ),
    array( 'pattern' => '(^|\.)threads\.net',    'cat' => 'social', 'label' => 'Threads',     'color' => '#000000' ),
    array( 'pattern' => '(^|\.)vk\.com',         'cat' => 'social', 'label' => 'VKontakte',   'color' => '#0077FF' ),
    array( 'pattern' => '(^|\.)tumblr\.',        'cat' => 'social', 'label' => 'Tumblr',      'color' => '#35465C' ),
    array( 'pattern' => '(^|\.)twitch\.',        'cat' => 'social', 'label' => 'Twitch',      'color' => '#9146FF' ),
    array( 'pattern' => '(^|\.)quora\.',         'cat' => 'social', 'label' => 'Quora',       'color' => '#B92B27' ),
    array( 'pattern' => '(^|\.)medium\.',        'cat' => 'social', 'label' => 'Medium',      'color' => '#000000' ),
    array( 'pattern' => '(^|\.)substack\.',      'cat' => 'social', 'label' => 'Substack',    'color' => '#FF6719' ),
    array( 'pattern' => '(^|\.)producthunt\.',   'cat' => 'social', 'label' => 'Product Hunt','color' => '#DA552F' ),
    array( 'pattern' => 'news\.ycombinator\.',   'cat' => 'social', 'label' => 'Hacker News', 'color' => '#FF6600' ),

    // =========================================================================
    // INTELLIGENCE ARTIFICIELLE & ASSISTANTS
    // Copilot AVANT bing — évite que (^|\.)bing\. matche copilot.microsoft.com
    // =========================================================================

    array( 'pattern' => '(^|\.)copilot\.microsoft\.', 'cat' => 'ai', 'label' => 'Copilot',     'color' => '#0078D4' ),
    array( 'pattern' => 'chat\.openai\.',              'cat' => 'ai', 'label' => 'ChatGPT',     'color' => '#10A37F' ),
    array( 'pattern' => '(^|\.)chatgpt\.',             'cat' => 'ai', 'label' => 'ChatGPT',     'color' => '#10A37F' ),
    array( 'pattern' => '(^|\.)openai\.',              'cat' => 'ai', 'label' => 'OpenAI',      'color' => '#10A37F' ),
    array( 'pattern' => '(^|\.)claude\.ai',            'cat' => 'ai', 'label' => 'Claude',      'color' => '#D97706' ),
    array( 'pattern' => '(^|\.)anthropic\.',           'cat' => 'ai', 'label' => 'Claude',      'color' => '#D97706' ),
    array( 'pattern' => 'gemini\.google\.',            'cat' => 'ai', 'label' => 'Gemini',      'color' => '#4285F4' ),
    array( 'pattern' => '(^|\.)bard\.',                'cat' => 'ai', 'label' => 'Gemini',      'color' => '#4285F4' ),
    array( 'pattern' => '(^|\.)perplexity\.',          'cat' => 'ai', 'label' => 'Perplexity',  'color' => '#20B2AA' ),
    array( 'pattern' => '^you\.com',                   'cat' => 'ai', 'label' => 'You.com',     'color' => '#8B5CF6' ),
    array( 'pattern' => '(^|\.)mistral\.',             'cat' => 'ai', 'label' => 'Mistral',     'color' => '#FF7000' ),
    array( 'pattern' => '(^|\.)huggingface\.',         'cat' => 'ai', 'label' => 'HuggingFace', 'color' => '#FFD21E' ),
    array( 'pattern' => '(^|\.)grok\.',                'cat' => 'ai', 'label' => 'Grok',        'color' => '#000000' ),
    array( 'pattern' => '(^|\.)x\.ai',                'cat' => 'ai', 'label' => 'Grok',        'color' => '#000000' ),
    array( 'pattern' => '^meta\.ai',                   'cat' => 'ai', 'label' => 'Meta AI',     'color' => '#0082FB' ),
    array( 'pattern' => '(^|\.)phind\.',               'cat' => 'ai', 'label' => 'Phind',       'color' => '#7C3AED' ),
    array( 'pattern' => '^poe\.com',                   'cat' => 'ai', 'label' => 'Poe',         'color' => '#6366F1' ),
    array( 'pattern' => '(^|\.)deepseek\.',            'cat' => 'ai', 'label' => 'DeepSeek',    'color' => '#1E88E5' ),
    array( 'pattern' => '(^|\.)kagi\.',                'cat' => 'ai', 'label' => 'Kagi',        'color' => '#FF4F64' ),

    // =========================================================================
    // SITES — ajouter ici les domaines spécifiques à reconnaître
    // (tout ce qui ne matche aucune règle ci-dessus tombe dans 'site' par défaut)
    // =========================================================================

    // array( 'pattern' => '(^|\.)monsite\.fr', 'cat' => 'site', 'label' => 'Mon Site', 'color' => '#3498DB' ),

);
