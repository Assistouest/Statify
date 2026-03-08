<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bot filter — detects and filters bot traffic.
 */
class Statify_Bot_Filter {

    /**
     * Common bot User-Agent keywords.
     */
    private static $bot_keywords = array(
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'nagios', 'wget', 'curl', 'fetch', 'scanner',
        'scraper', 'monitor', 'checker', 'archive',
        'python-requests', 'python-urllib', 'go-http-client',
        'java/', 'httpclient', 'nutch', 'phpcrawl',
        'mj12bot', 'ahrefsbot', 'semrushbot', 'dotbot',
        'rogerbot', 'yandexbot', 'baiduspider', 'duckduckbot',
        'facebookexternalhit', 'facebot', 'twitterbot',
        'linkedinbot', 'pinterestbot', 'whatsapp',
        'telegrambot', 'discordbot', 'slackbot',
        'applebot', 'petalbot', 'bytespider',
        'gptbot', 'claudebot', 'anthropic',
        'headlesschrome', 'phantomjs', 'selenium',
        'puppeteer', 'playwright',
    );

    /**
     * Strict mode: additional keywords.
     */
    private static $strict_keywords = array(
        'http://', 'https://', '.com', '.org', '.net',
        'preview', 'prerender', 'lighthouse',
        'pagespeed', 'pingdom', 'uptime',
        'statuspage', 'newrelic', 'datadog',
    );

    /**
     * Check if a User-Agent belongs to a bot.
     *
     * @param string $ua_string The User-Agent string.
     * @param string $mode      Filter mode: 'normal' or 'strict'.
     * @return bool
     */
    public static function is_bot( $ua_string, $mode = 'normal' ) {
        if ( empty( $ua_string ) ) {
            return true; // No User-Agent = likely bot
        }

        $ua_lower = strtolower( $ua_string );

        /**
         * Filter the list of bot User-Agent keywords.
         *
         * @param array $bot_list The bot keywords.
         */
        $keywords = apply_filters( 'statify_bot_user_agents', self::$bot_keywords );

        if ( 'strict' === $mode ) {
            $keywords = array_merge( $keywords, self::$strict_keywords );
        }

        foreach ( $keywords as $keyword ) {
            if ( strpos( $ua_lower, strtolower( $keyword ) ) !== false ) {
                return true;
            }
        }

        // Additional heuristic: very short UA strings are suspicious
        if ( strlen( $ua_string ) < 20 ) {
            return true;
        }

        return false;
    }

    /**
     * Get the list of known bot keywords.
     *
     * @return array
     */
    public static function get_bot_keywords() {
        return self::$bot_keywords;
    }
}
