<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ZF_Cert_Admin {

    private ZF_Cert_DB $db;

    public function __construct( ZF_Cert_DB $db ) {
        $this->db = $db;
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_zf_cert_delete', array( $this, 'ajax_delete' ) );
    }

  

    public function register_menu(): void {
        add_menu_page(
            'ZeroFlux Certificates',
            'Certificates',
            'manage_options',
            'zf-certificates',
            array( $this, 'render_page' ),
            'dashicons-awards',
            30
        );
    }



    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'toplevel_page_zf-certificates' ) return;

        wp_enqueue_style(
            'zf-cert-admin',
            ZF_CERT_URL . 'assets/admin.css',
            array(),
            ZF_CERT_VERSION
        );
    }


    public function ajax_delete(): void {
        check_ajax_referer( 'zf_cert_delete_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        $id = absint( $_POST['id'] ?? 0 );
        wp_send_json( array( 'success' => $this->db->delete( $id ) ) );
    }

  

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $action = sanitize_key( $_GET['action'] ?? 'list' );
        $notice = '';

    
        if ( $action === 'edit' && isset( $_POST['zf_edit_cert'] ) ) {
            check_admin_referer( 'zf_edit_certificate_nonce' );
            $id     = absint( $_POST['cert_db_id'] ?? 0 );
            $data   = $this->sanitize_form();
            $result = $this->db->update( $id, $data );
            $notice = $result
                ? array( 'type' => 'success', 'msg' => 'Certificate updated successfully.' )
                : array( 'type' => 'error',   'msg' => 'Update failed. Please try again.' );
        }

        
        if ( $action === 'list' && isset( $_POST['zf_add_cert'] ) ) {
            check_admin_referer( 'zf_add_certificate_nonce' );
            $data   = $this->sanitize_form();
            $result = $this->db->insert( $data );
            if ( $result === true ) {
                $notice = array( 'type' => 'success', 'msg' => 'Certificate added successfully.' );
            } elseif ( $result === 'duplicate' ) {
                $notice = array( 'type' => 'error', 'msg' => 'Certificate ID already exists.' );
            } else {
                $notice = array( 'type' => 'error', 'msg' => 'Failed to add certificate.' );
            }
        }

        echo '<div class="wrap zf-wrap">';
        $this->render_header();

        if ( $notice ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $notice['type'] ),
                esc_html( $notice['msg'] )
            );
        }

        if ( $action === 'edit' ) {
            $id   = absint( $_GET['id'] ?? 0 );
            $cert = $this->db->get_by_id( $id );
            if ( $cert ) $this->render_edit_form( $cert );
            else echo '<p>Certificate not found.</p>';
        } else {
            $this->render_add_form();
            $this->render_list();
        }

        echo '</div>';
        $this->inline_js();
    }

   

    private function sanitize_form(): array {
        return array(
            'certificate_id' => strtoupper( sanitize_text_field( $_POST['certificate_id'] ?? '' ) ),
            'full_name'      => sanitize_text_field( $_POST['full_name'] ?? '' ),
            'role_name'      => sanitize_text_field( $_POST['role_name'] ?? '' ),
            'issue_date'     => sanitize_text_field( $_POST['issue_date'] ?? '' ),
            'duration'       => sanitize_text_field( $_POST['duration'] ?? '' ),
            'status'         => in_array( $_POST['status'] ?? '', array( 'valid', 'revoked' ) ) ? $_POST['status'] : 'valid',
            'pdf_url'        => esc_url_raw( $_POST['pdf_url'] ?? '', array( 'http', 'https' ) ),
        );
    }

    
    private function render_header(): void {
        echo '<div class="zf-header">
            <span class="zf-logo" aria-hidden="true"></span>
            <h1>Certificate System</h1>
        </div>';
    }


    private function render_add_form(): void {
        ?>
        <div class="zf-card">
            <h2 class="zf-card-title">➕ Add Certificate</h2>
            <form method="POST" class="zf-form">
                <?php wp_nonce_field( 'zf_add_certificate_nonce' ); ?>
                <?php $this->render_form_fields(); ?>
                <div class="zf-form-actions">
                    <button type="submit" name="zf_add_cert" class="zf-btn zf-btn-primary">Add Certificate</button>
                </div>
            </form>
        </div>
        <?php
    }

    private function render_edit_form( object $cert ): void {
        ?>
        <div class="zf-card">
            <h2 class="zf-card-title">✏️ Edit Certificate — <code><?php echo esc_html( $cert->certificate_id ); ?></code></h2>
            <form method="POST" class="zf-form">
                <?php wp_nonce_field( 'zf_edit_certificate_nonce' ); ?>
                <input type="hidden" name="cert_db_id" value="<?php echo esc_attr( $cert->id ); ?>">
                <?php $this->render_form_fields( $cert ); ?>
                <div class="zf-form-actions">
                    <button type="submit" name="zf_edit_cert" class="zf-btn zf-btn-primary">Save Changes</button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=zf-certificates' ) ); ?>" class="zf-btn zf-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    }


    private function render_form_fields( ?object $cert = null ): void {
        $v = fn( $key ) => esc_attr( $cert->$key ?? '' );
        $statuses = array( 'valid' => 'Valid', 'revoked' => 'Revoked' );
        ?>
        <div class="zf-grid-2">

            <div class="zf-field">
                <label>Certificate ID</label>
                <input type="text" name="certificate_id" value="<?php echo $v('certificate_id'); ?>"
                       placeholder="ZF-2024-001" required <?php echo $cert ? 'readonly' : ''; ?>>
                <?php if ( ! $cert ) echo '<span class="zf-hint">Auto-uppercased. Must be unique.</span>'; ?>
            </div>

            <div class="zf-field">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?php echo $v('full_name'); ?>" placeholder="Jane Doe" required>
            </div>

            <div class="zf-field">
                <label>Role / Position</label>
                <input type="text" name="role_name" value="<?php echo $v('role_name'); ?>" placeholder="Frontend Developer Intern" required>
            </div>

            <div class="zf-field">
                <label>Issue Date</label>
                <input type="date" name="issue_date" value="<?php echo $v('issue_date'); ?>" required>
            </div>

            <div class="zf-field">
                <label>Duration</label>
                <input type="text" name="duration" value="<?php echo $v('duration'); ?>" placeholder="3 Months" required>
            </div>

            <div class="zf-field">
                <label>Status</label>
                <select name="status">
                    <?php foreach ( $statuses as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cert->status ?? 'valid', $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="zf-field zf-field-full">
                <label>Certificate PDF URL <span class="zf-optional">(optional)</span></label>
                <input type="url" name="pdf_url" value="<?php echo esc_url( $cert->pdf_url ?? '' ); ?>" placeholder="https://example.com/cert.pdf">
            </div>

        </div>
        <?php
    }

    private function render_list(): void {
        $page    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $search  = sanitize_text_field( $_GET['s'] ?? '' );
        $results = $search ? array( 'rows' => $this->db->search( $search ), 'total' => 0, 'per_page' => 50, 'page' => 1 )
                           : $this->db->get_all( $page );

        $total_pages = $results['per_page'] > 0 ? ceil( $results['total'] / $results['per_page'] ) : 1;
        ?>
        <div class="zf-card">
            <div class="zf-list-header">
                <h2 class="zf-card-title">📋 All Certificates <span class="zf-badge"><?php echo esc_html( $results['total'] ); ?></span></h2>
                <form method="GET" class="zf-search-form">
                    <input type="hidden" name="page" value="zf-certificates">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, ID, role…">
                    <button type="submit" class="zf-btn zf-btn-sm">Search</button>
                    <?php if ( $search ) echo '<a href="' . esc_url( admin_url( 'admin.php?page=zf-certificates' ) ) . '" class="zf-btn zf-btn-sm zf-btn-ghost">Clear</a>'; ?>
                </form>
            </div>

            <?php if ( $results['rows'] ) : ?>
            <div class="zf-table-wrap">
                <table class="zf-table">
                    <thead>
                        <tr>
                            <th>Certificate ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Issued</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $results['rows'] as $cert ) :
                        $edit_url = admin_url( 'admin.php?page=zf-certificates&action=edit&id=' . $cert->id );
                        $status_class = $cert->status === 'valid' ? 'zf-status-valid' : 'zf-status-revoked';
                    ?>
                        <tr>
                            <td><code><?php echo esc_html( $cert->certificate_id ); ?></code></td>
                            <td><?php echo esc_html( $cert->full_name ); ?></td>
                            <td><?php echo esc_html( $cert->role_name ); ?></td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $cert->issue_date ) ) ); ?></td>
                            <td><?php echo esc_html( $cert->duration ); ?></td>
                            <td><span class="zf-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $cert->status ) ); ?></span></td>
                            <td class="zf-actions-cell">
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="zf-btn zf-btn-sm">Edit</a>
                                <button class="zf-btn zf-btn-sm zf-btn-danger zf-delete-btn"
                                        data-id="<?php echo esc_attr( $cert->id ); ?>"
                                        data-name="<?php echo esc_attr( $cert->full_name ); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'zf_cert_delete_nonce' ) ); ?>">
                                    Delete
                                </button>
                                <?php if ( ! empty( $cert->pdf_url ) ) : ?>
                                    <a href="<?php echo esc_url( $cert->pdf_url ); ?>" target="_blank" rel="noopener" class="zf-btn zf-btn-sm zf-btn-ghost">PDF</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( ! $search && $total_pages > 1 ) : ?>
                <div class="zf-pagination">
                    <?php
                    echo paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'current'   => $page,
                        'total'     => $total_pages,
                        'prev_text' => '← Prev',
                        'next_text' => 'Next →',
                    ) );
                    ?>
                </div>
            <?php endif; ?>

            <?php else : ?>
                <p class="zf-empty"><?php echo $search ? 'No results found for "' . esc_html( $search ) . '".' : 'No certificates yet. Add one above.'; ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

      private function inline_js(): void {
        ?>
        <script>
        document.querySelectorAll('.zf-delete-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const name = this.dataset.name;
                if ( ! confirm(`Delete certificate for "${name}"? This cannot be undone.`) ) return;
                const id    = this.dataset.id;
                const nonce = this.dataset.nonce;
                const row   = this.closest('tr');
                row.style.opacity = '0.5';
                fetch(ajaxurl, {
                    method : 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body   : `action=zf_cert_delete&id=${id}&nonce=${nonce}`
                })
                .then( r => r.json() )
                .then( data => {
                    if ( data.success ) row.remove();
                    else { row.style.opacity = '1'; alert('Delete failed.'); }
                });
            });
        });
        </script>
        <?php
    }
}
