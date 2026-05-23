<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ZF_Cert_Shortcode {

    private ZF_Cert_DB $db;

    public function __construct( ZF_Cert_DB $db ) {
        $this->db = $db;
        add_shortcode( 'zf_verify', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets(): void {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'zf_verify' ) ) {
            wp_enqueue_style(
                'zf-cert-public',
                ZF_CERT_URL . 'assets/public.css',
                array(),
                ZF_CERT_VERSION
            );
        }
    }


    public function render( array $atts ): string {

        $atts = shortcode_atts( array(
            'title'       => 'Certificate Verification',
            'placeholder' => 'Enter Certificate ID (e.g. ZF-2024-001)',
            'btn_text'    => 'Verify',
            'accent'      => '#FF6B00', 
        ), $atts, 'zf_verify' );


        $title       = esc_html( $atts['title'] );
        $placeholder = esc_attr( $atts['placeholder'] );
        $btn_text    = esc_html( $atts['btn_text'] );
        $accent      = sanitize_hex_color( $atts['accent'] ) ?: '#FF6B00';

        $result_html = '';
        $input_val   = '';

    
        if ( isset( $_POST['zf_verify_id'] ) ) {

            if ( ! isset( $_POST['zf_verify_nonce'] ) || ! wp_verify_nonce( $_POST['zf_verify_nonce'], 'zf_verify_certificate' ) ) {
                $result_html = $this->result_html( 'error', 'Security check failed. Please refresh and try again.' );
            } else {
                $cert_id    = strtoupper( sanitize_text_field( $_POST['zf_verify_id'] ) );
                $input_val  = esc_attr( $cert_id );
                $cert       = $this->db->find_by_cert_id( $cert_id );
                $result_html = $cert ? $this->cert_found_html( $cert ) : $this->result_html( 'error', 'No certificate found for ID: ' . esc_html( $cert_id ) );
            }
        }

        ob_start();
        ?>
        {/* Fixed inline variables to properly communicate with the layout engines */}
        <div class="zf-verify-wrap" style="--zf-orange:<?php echo esc_attr( $accent ); ?>; --zf-orange-ring:<?php echo esc_attr( $accent ); ?>28;">

            <div class="zf-verify-box">

                <div class="zf-verify-inner">
                    <div class="zf-verify-icon">🏅</div>
                    <h2 class="zf-verify-title"><?php echo $title; ?></h2>
                    <p class="zf-verify-sub">Enter a certificate ID to instantly verify its authenticity.</p>

                    <form method="POST" class="zf-verify-form" novalidate>
                        <?php wp_nonce_field( 'zf_verify_certificate', 'zf_verify_nonce' ); ?>
                        <div class="zf-verify-input-row">
                            <input
                                type="text"
                                name="zf_verify_id"
                                value="<?php echo $input_val; ?>"
                                placeholder="<?php echo $placeholder; ?>"
                                autocomplete="off"
                                spellcheck="false"
                                required
                            >
                            <button type="submit"><?php echo $btn_text; ?></button>
                        </div>
                    </form>
                </div>

                <?php echo $result_html; ?>

                <div class="zf-verify-footer">
                    <span class="zf-footer-mark" aria-hidden="true"></span>
                    Secured by <strong>lybernet</strong> &middot; Real-time verification
                </div>

            </div>

        </div>
        <?php
        return ob_get_clean();
    }


    private function cert_found_html( object $cert ): string {
        if ( $cert->status !== 'valid' ) {
            return $this->result_html( 'revoked', 'This certificate has been revoked.' );
        }

        $formatted_date = esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cert->issue_date ) ) );

        $pdf_link = ! empty( $cert->pdf_url )
            ? '<a href="' . esc_url( $cert->pdf_url ) . '" target="_blank" rel="noopener" class="zf-cert-pdf-btn">⬇ Download Certificate PDF</a>'
            : '';

        return '
        <div class="zf-result zf-result-valid">
            <div class="zf-result-header">
                <span class="zf-result-icon">✓</span>
                <span class="zf-result-status">Certificate Verified</span>
            </div>
            <dl class="zf-cert-details">
                <div class="zf-detail-row">
                    <dt>Name</dt>
                    <dd>' . esc_html( $cert->full_name ) . '</dd>
                </div>
                <div class="zf-detail-row">
                    <dt>Role</dt>
                    <dd>' . esc_html( $cert->role_name ) . '</dd>
                </div>
                <div class="zf-detail-row">
                    <dt>Duration</dt>
                    <dd>' . esc_html( $cert->duration ) . '</dd>
                </div>
                <div class="zf-detail-row">
                    <dt>Issued</dt>
                    <dd>' . $formatted_date . '</dd>
                </div>
                <div class="zf-detail-row">
                    <dt>Certificate ID</dt>
                    <dd><code>' . esc_html( $cert->certificate_id ) . '</code></dd>
                </div>
            </dl>
            ' . $pdf_link . '
        </div>';
    }



    private function result_html( string $type, string $message ): string {
        $icons = array( 'error' => '✕', 'revoked' => '⊘', 'warning' => '⚠' );
        $icon  = $icons[ $type ] ?? '✕';
        return sprintf(
            '<div class="zf-result zf-result-%s"><div class="zf-result-header"><span class="zf-result-icon">%s</span><span class="zf-result-status">%s</span></div></div>',
            esc_attr( $type ),
            esc_html( $icon ),
            esc_html( $message )
        );
    }
}
