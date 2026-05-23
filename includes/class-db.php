<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ZF_Cert_DB {

    private static string $table_name = '';

    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'zf_certificates';
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'zf_certificates';
    }

    public static function create_table(): void {
        global $wpdb;

        $table          = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id             BIGINT(20)   NOT NULL AUTO_INCREMENT,
            certificate_id VARCHAR(100) NOT NULL,
            full_name      VARCHAR(255) NOT NULL,
            role_name      VARCHAR(255) NOT NULL,
            issue_date     DATE         NOT NULL,
            duration       VARCHAR(100) NOT NULL,
            status         VARCHAR(20)  NOT NULL DEFAULT 'valid',
            pdf_url        TEXT,
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE  KEY  certificate_id (certificate_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }


    public function insert( array $data ): bool|string {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM " . self::table() . " WHERE certificate_id = %s", $data['certificate_id'] )
        );
        if ( $exists ) return 'duplicate';

        $result = $wpdb->insert( self::table(), $data );
        return $result !== false ? true : 'error';
    }

    public function update( int $id, array $data ): bool {
        global $wpdb;
        return $wpdb->update( self::table(), $data, array( 'id' => $id ) ) !== false;
    }

    public function delete( int $id ): bool {
        global $wpdb;
        return $wpdb->delete( self::table(), array( 'id' => $id ) ) !== false;
    }

    public function get_all( int $page = 1, int $per_page = 20 ): array {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $rows   = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset )
        );
        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );
        return compact( 'rows', 'total', 'per_page', 'page' );
    }

    public function get_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ) );
    }

    public function find_by_cert_id( string $cert_id ): ?object {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE certificate_id = %s", $cert_id )
        );
    }

    public function search( string $query ): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $query ) . '%';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE full_name LIKE %s OR certificate_id LIKE %s OR role_name LIKE %s ORDER BY id DESC LIMIT 50",
                $like, $like, $like
            )
        ) ?: [];
    }
}
