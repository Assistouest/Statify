<?php
namespace Statify;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consent banner — RGPD cookie consent management.
 * Active only when tracking_mode is 'cookie' and consent_enabled is true.
 */
class Statify_Consent {

    /**
     * Render the consent banner in the footer.
     */
    public function render_banner() {
        $options = get_option( 'statify_options', array() );

        // Only show banner if cookie mode + consent is enabled
        if ( empty( $options['consent_enabled'] ) ) {
            return;
        }

        $tracking_mode = isset( $options['tracking_mode'] ) ? $options['tracking_mode'] : 'cookieless';
        if ( 'cookie' !== $tracking_mode ) {
            return;
        }

        // Don't show to logged-in users with excluded roles
        if ( is_user_logged_in() ) {
            $excluded_roles = isset( $options['excluded_roles'] ) ? (array) $options['excluded_roles'] : array();
            $user           = wp_get_current_user();
            if ( array_intersect( $excluded_roles, $user->roles ) ) {
                return;
            }
        }

        $message     = isset( $options['consent_message'] ) ? $options['consent_message'] : __( 'Ce site utilise des cookies pour analyser le trafic. Acceptez-vous ?', 'statify' );
        $accept_text = isset( $options['consent_accept'] ) ? $options['consent_accept'] : __( 'Accepter', 'statify' );
        $decline_text = isset( $options['consent_decline'] ) ? $options['consent_decline'] : __( 'Refuser', 'statify' );
        $bg_color    = isset( $options['consent_bg_color'] ) ? $options['consent_bg_color'] : '#1a1a2e';
        $text_color  = isset( $options['consent_text_color'] ) ? $options['consent_text_color'] : '#ffffff';
        $btn_color   = isset( $options['consent_btn_color'] ) ? $options['consent_btn_color'] : '#6c63ff';
        ?>
        <div id="statify-consent-banner"
             style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:999999;padding:20px 24px;
                    background:<?php echo esc_attr( $bg_color ); ?>;color:<?php echo esc_attr( $text_color ); ?>;
                    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;
                    box-shadow:0 -4px 24px rgba(0,0,0,0.3);backdrop-filter:blur(12px);">
            <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
                <p style="margin:0;flex:1;min-width:280px;line-height:1.5;">
                    <?php echo esc_html( $message ); ?>
                </p>
                <div style="display:flex;gap:10px;flex-shrink:0;">
                    <button id="statify-consent-decline"
                            style="padding:10px 20px;border:1px solid <?php echo esc_attr( $text_color ); ?>;
                                   background:transparent;color:<?php echo esc_attr( $text_color ); ?>;
                                   border-radius:6px;cursor:pointer;font-size:14px;font-weight:500;
                                   transition:opacity .2s;">
                        <?php echo esc_html( $decline_text ); ?>
                    </button>
                    <button id="statify-consent-accept"
                            style="padding:10px 24px;border:none;
                                   background:<?php echo esc_attr( $btn_color ); ?>;color:#ffffff;
                                   border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;
                                   transition:transform .15s,box-shadow .15s;
                                   box-shadow:0 2px 8px rgba(108,99,255,0.4);">
                        <?php echo esc_html( $accept_text ); ?>
                    </button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var COOKIE_NAME = 'statify_consent';
            var banner = document.getElementById('statify-consent-banner');
            if (!banner) return;

            // Check if consent already given
            var consent = getCookie(COOKIE_NAME);
            if (consent) {
                // Consent already stored — notify tracker
                window.statifyConsentStatus = consent;
                return;
            }

            // Show banner
            banner.style.display = 'block';

            document.getElementById('statify-consent-accept').addEventListener('click', function(){
                setCookie(COOKIE_NAME, 'granted', 182);
                window.statifyConsentStatus = 'granted';
                banner.style.display = 'none';
                // Trigger tracking if it was waiting for consent
                if (window.statifyOnConsent) window.statifyOnConsent('granted');
            });

            document.getElementById('statify-consent-decline').addEventListener('click', function(){
                setCookie(COOKIE_NAME, 'denied', 182);
                window.statifyConsentStatus = 'denied';
                banner.style.display = 'none';
                if (window.statifyOnConsent) window.statifyOnConsent('denied');
            });

            function setCookie(name, value, days) {
                var d = new Date();
                d.setTime(d.getTime() + (days * 86400000));
                document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
            }

            function getCookie(name) {
                var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
                return v ? v[2] : null;
            }
        })();
        </script>
        <?php
    }

    /**
     * Enqueue consent-related assets if needed.
     */
    public function enqueue_assets() {
        // Consent styles/scripts are inline for minimal overhead — nothing to enqueue here.
    }
}
