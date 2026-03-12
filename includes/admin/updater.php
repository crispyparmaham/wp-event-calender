<?php
defined( 'ABSPATH' ) || exit;

/**
 * TC_Plugin_Updater
 *
 * Nutzt den WordPress 5.8+ Update-URI-Mechanismus:
 *   - Plugin-Header "Update URI" zeigt auf das GitHub-Repository
 *   - WordPress feuert den Filter "update_plugins_github.com"
 *   - Dieser Filter fragt die GitHub Releases API ab und liefert
 *     die Update-Daten direkt an WordPress zurück.
 *
 * Konfigurationsparameter:
 *   github_user     – GitHub-Benutzername oder Organisation
 *   github_repo     – Name des Repositories
 *   plugin_file     – Absoluter Pfad zur Haupt-Plugin-Datei (__FILE__)
 *   current_version – Aktuelle Versionsnummer
 *   access_token    – Optional: Personal Access Token für private Repos
 */
class TC_Plugin_Updater {

    const TRANSIENT_KEY  = 'tc_github_update_check';
    const LAST_CHECK_KEY = 'tc_github_update_last_checked';

    /** @var array */
    private $config;

    /** @var string  z.B. "training-calendar/functions.php" */
    private $plugin_slug;

    /** @var string  z.B. "training-calendar" */
    private $plugin_folder;

    // ─────────────────────────────────────────────
    // Konstruktor
    // ─────────────────────────────────────────────
    public function __construct( array $config ) {
        $this->config = wp_parse_args( $config, array(
            'github_user'     => '',
            'github_repo'     => '',
            'plugin_file'     => '',
            'current_version' => '0.0.0',
            'access_token'    => '',
        ) );

        $this->plugin_slug   = plugin_basename( $this->config['plugin_file'] );
        $this->plugin_folder = basename( dirname( $this->config['plugin_file'] ) );

        // WordPress 5.8+: Update URI Hook (Domain = github.com)
        add_filter( 'update_plugins_github.com',     array( $this, 'check_for_update' ), 10, 4 );
        add_filter( 'plugins_api',                   array( $this, 'plugin_info'      ), 20, 3 );
        add_action( 'upgrader_process_complete',     array( $this, 'after_update'     ), 10, 2 );
        add_filter( 'upgrader_source_selection',     array( $this, 'fix_folder_name'  ), 10, 3 );
    }

    // ─────────────────────────────────────────────
    // GitHub API: neueste Release-Daten abrufen
    // ─────────────────────────────────────────────

    /**
     * Gibt das neueste Release-Objekt der GitHub API zurück.
     * Ergebnis wird 12 Stunden per Transient gecacht.
     *
     * @param bool $force  true = Cache ignorieren, frisch abfragen.
     * @return object|false
     */
    public function get_release_data( bool $force = false ) {
        if ( ! $force ) {
            $cached = get_transient( self::TRANSIENT_KEY );
            if ( $cached !== false ) {
                return $cached;
            }
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode( $this->config['github_user'] ),
            rawurlencode( $this->config['github_repo'] )
        );

        $args = array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
                'Accept'     => 'application/vnd.github+json',
            ),
        );

        if ( ! empty( $this->config['access_token'] ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->config['access_token'];
        }

        $response = wp_remote_get( $api_url, $args );

        update_option( self::LAST_CHECK_KEY, time(), false );

        if ( is_wp_error( $response ) ) {
            update_option( 'tc_github_update_last_status', 'wp_error:' . $response->get_error_message(), false );
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        update_option( 'tc_github_update_last_status', (string) $code, false );

        if ( $code !== 200 ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( empty( $data->tag_name ) ) {
            update_option( 'tc_github_update_last_status', 'no_tag', false );
            return false;
        }

        set_transient( self::TRANSIENT_KEY, $data, 12 * HOUR_IN_SECONDS );

        return $data;
    }

    // ─────────────────────────────────────────────
    // Download-URL aus Release ermitteln
    // ─────────────────────────────────────────────

    private function get_download_url( $release ): string {
        // 1. Explizit hochgeladene ZIP-Assets bevorzugen
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                $is_zip = (
                    ( isset( $asset->content_type ) && $asset->content_type === 'application/zip' ) ||
                    ( isset( $asset->name )         && str_ends_with( $asset->name, '.zip' ) )
                );
                if ( $is_zip && ! empty( $asset->browser_download_url ) ) {
                    return (string) $asset->browser_download_url;
                }
            }
        }

        // 2. Fallback: automatisch generiertes Quell-ZIP von GitHub
        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/%s.zip',
            rawurlencode( $this->config['github_user'] ),
            rawurlencode( $this->config['github_repo'] ),
            rawurlencode( $release->tag_name )
        );
    }

    // ─────────────────────────────────────────────
    // Hook: update_plugins_github.com
    //
    // WordPress 5.8+ feuert diesen Filter für alle Plugins,
    // deren "Update URI" Header auf github.com zeigt.
    // ─────────────────────────────────────────────

    public function check_for_update( $update, $plugin_data, $plugin_file, $locales ) {
        // Nur für unser Plugin reagieren
        if ( $plugin_file !== $this->plugin_slug ) {
            return $update;
        }

        $release = $this->get_release_data();
        if ( ! $release ) {
            return $update;
        }

        $latest = ltrim( $release->tag_name, 'v' );

        if ( ! version_compare( $latest, $this->config['current_version'], '>' ) ) {
            return $update;
        }

        return (object) array(
            'id'            => $this->plugin_slug,
            'slug'          => $this->plugin_folder,
            'plugin'        => $this->plugin_slug,
            'version'       => $latest,
            'url'           => 'https://github.com/' . $this->config['github_user'] . '/' . $this->config['github_repo'],
            'package'       => $this->get_download_url( $release ),
            'icons'         => array(),
            'banners'       => array(),
            'tested'        => '6.7',
            'requires_php'  => '7.4',
            'compatibility' => new stdClass(),
        );
    }

    // ─────────────────────────────────────────────
    // Hook: plugins_api (Plugin-Info-Popup)
    // ─────────────────────────────────────────────

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( empty( $args->slug ) || $args->slug !== $this->plugin_folder ) {
            return $result;
        }

        $release = $this->get_release_data();
        if ( ! $release ) {
            return $result;
        }

        $latest       = ltrim( $release->tag_name, 'v' );
        $download_url = $this->get_download_url( $release );
        $published    = ! empty( $release->published_at )
            ? date_i18n( get_option( 'date_format' ), strtotime( $release->published_at ) )
            : '';

        return (object) array(
            'name'          => 'Drag &amp; Drop Event Calendar',
            'slug'          => $this->plugin_folder,
            'version'       => $latest,
            'author'        => '<a href="https://www.morethanads.de">Lucas Dühr | more than ads</a>',
            'homepage'      => 'https://github.com/' . $this->config['github_user'] . '/' . $this->config['github_repo'],
            'download_link' => $download_url,
            'last_updated'  => $published,
            'requires'      => '5.8',
            'tested'        => '6.7',
            'requires_php'  => '7.4',
            'sections'      => array(
                'description' => '<p>Kalenderübersicht für Trainings &amp; Seminare mit Drag &amp; Drop Funktion.</p>',
                'changelog'   => $this->format_changelog( (string) ( $release->body ?? '' ) ),
            ),
        );
    }

    private function format_changelog( string $body ): string {
        if ( $body === '' ) {
            return '<p>Keine Changelog-Informationen vorhanden.</p>';
        }

        $body = esc_html( $body );
        $body = preg_replace( '/^### (.+)$/m',  '<h4>$1</h4>', $body );
        $body = preg_replace( '/^## (.+)$/m',   '<h3>$1</h3>', $body );
        $body = preg_replace( '/^# (.+)$/m',    '<h2>$1</h2>', $body );
        $body = preg_replace( '/^[-*] (.+)$/m', '<li>$1</li>', $body );
        $body = preg_replace( '/(<li>[^<]*<\/li>\n?)+/', '<ul>$0</ul>', $body );
        $body = wpautop( $body );

        return $body;
    }

    // ─────────────────────────────────────────────
    // Hook: upgrader_process_complete
    // ─────────────────────────────────────────────

    public function after_update( $upgrader, $options ) {
        if (
            empty( $options['action'] ) || $options['action'] !== 'update' ||
            empty( $options['type'] )   || $options['type']   !== 'plugin'
        ) {
            return;
        }

        if ( in_array( $this->plugin_slug, (array) ( $options['plugins'] ?? array() ), true ) ) {
            delete_transient( self::TRANSIENT_KEY );
        }
    }

    // ─────────────────────────────────────────────
    // Hook: upgrader_source_selection
    //
    // GitHub entpackt ZIPs als "{repo}-{tag}/" — WordPress erwartet
    // aber den Original-Ordnernamen "training-calendar/".
    // Dieser Hook benennt den Ordner vor der Installation um.
    // ─────────────────────────────────────────────

    public function fix_folder_name( $source, $remote_source, $upgrader ) {
        global $wp_filesystem;

        $info = $upgrader->skin->plugin_info ?? null;
        if ( ! $info ) {
            return $source;
        }
        if ( ( $info['TextDomain'] ?? '' ) !== 'training-calendar' ) {
            return $source;
        }

        $expected = trailingslashit( $remote_source ) . $this->plugin_folder . '/';

        if ( trailingslashit( $source ) === $expected ) {
            return $source;
        }

        if ( ! $wp_filesystem || ! $wp_filesystem->exists( $source ) ) {
            return $source;
        }

        if ( $wp_filesystem->move( $source, $expected ) ) {
            return $expected;
        }

        return $source;
    }
}

// ─────────────────────────────────────────────
// Standalone-Helper: aktuelles GitHub-Release
// Wird von der Settings-Seite direkt aufgerufen
// ─────────────────────────────────────────────
function tc_fetch_latest_release( bool $force = false ) {
    if ( ! defined( 'TC_GITHUB_USER' ) || ! defined( 'TC_GITHUB_REPO' ) ) {
        update_option( 'tc_github_update_last_status', 'config_missing', false );
        return false;
    }

    if ( ! $force ) {
        $cached = get_transient( TC_Plugin_Updater::TRANSIENT_KEY );
        if ( $cached !== false ) {
            return $cached;
        }
    }

    $api_url = sprintf(
        'https://api.github.com/repos/%s/%s/releases/latest',
        rawurlencode( TC_GITHUB_USER ),
        rawurlencode( TC_GITHUB_REPO )
    );

    $response = wp_remote_get( $api_url, array(
        'timeout' => 10,
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            'Accept'     => 'application/vnd.github+json',
        ),
    ) );

    update_option( TC_Plugin_Updater::LAST_CHECK_KEY, time(), false );

    if ( is_wp_error( $response ) ) {
        update_option( 'tc_github_update_last_status', 'wp_error:' . $response->get_error_message(), false );
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    update_option( 'tc_github_update_last_status', (string) $code, false );

    if ( $code !== 200 ) {
        return false;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ) );

    if ( empty( $data->tag_name ) ) {
        update_option( 'tc_github_update_last_status', 'no_tag', false );
        return false;
    }

    set_transient( TC_Plugin_Updater::TRANSIENT_KEY, $data, 12 * HOUR_IN_SECONDS );

    return $data;
}
