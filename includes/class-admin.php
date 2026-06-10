<?php

namespace COURTIQ;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'CTQ Traduction', 'courtiq-translator' ),
			__( '🌐 CTQ', 'courtiq-translator' ),
			'manage_options',
			'courtiq',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		if ( isset( $_POST['ctq_clear'] ) && check_admin_referer( 'ctq_admin' ) ) {
			$sel = sanitize_text_field( $_POST['ctq_lang'] ?? 'all' );
			foreach ( ( $sel === 'all' ? ['en', 'ar'] : [$sel] ) as $l ) {
				$f = CTQ_CACHE_DIR . '/dict-' . $l . '.json';
				if ( file_exists( $f ) ) {
					unlink( $f );
				}
			}
			echo '<div class="notice notice-success"><p>✅ ' . __( 'Cache vidé.', 'courtiq-translator' ) . '</p></div>';
		}

		$stats = Helpers::get_stats();
		$key_ok = CTQ_GEMINI_KEY !== '__NOT_SET__' && strlen( CTQ_GEMINI_KEY ) > 20;
		?>
		<div class="wrap">
			<h1>🌐 CTQ v<?php echo CTQ_VERSION; ?></h1>

			<?php if ( ! $key_ok ): ?>
				<div class="notice notice-error" style="padding:14px 16px">
					<p style="font-size:14px;margin:0 0 10px"><strong>❌ Clé API non trouvée</strong></p>
					<p style="margin:0 0 8px">Ouvrez <code>wp-config.php</code> et ajoutez cette ligne <strong>avant</strong> <em>"That's all, stop editing!"</em> :</p>
					<code style="display:block;background:#f8f9fa;border:1px solid #dee2e6;padding:12px 16px;border-radius:6px;font-size:13px;margin:8px 0">
						define('CTQ_GEMINI_KEY', 'AIzaSy...');
					</code>
				</div>
			<?php endif; ?>

			<div style="display:flex;gap:14px;flex-wrap:wrap;margin:20px 0">
				<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 24px;min-width:160px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
					<div style="font-size:30px;font-weight:800;color:#1d4ed8"><?php echo $stats['en']['count']; ?></div>
					<div style="font-size:12px;color:#64748b;margin-top:3px">🇬🇧 Anglais · <?php echo $stats['en']['size']; ?></div>
				</div>
				<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 24px;min-width:160px;box-shadow:0 1px 3px rgba(0,0,0,.05)">
					<div style="font-size:30px;font-weight:800;color:#1d4ed8"><?php echo $stats['ar']['count']; ?></div>
					<div style="font-size:12px;color:#64748b;margin-top:3px">🇸🇦 Arabe · <?php echo $stats['ar']['size']; ?></div>
				</div>
			</div>

			<form method="post" style="margin-bottom:28px">
				<?php wp_nonce_field( 'ctq_admin' ); ?>
				<select name="ctq_lang" style="padding:5px 10px;margin-right:8px">
					<option value="all"><?php _e( 'Toutes les langues', 'courtiq-translator' ); ?></option>
					<option value="en">Anglais</option>
					<option value="ar">Arabe</option>
				</select>
				<button type="submit" name="ctq_clear" class="button button-secondary" onclick="return confirm('Vider ?')">🗑️ Vider le cache</button>
			</form>

			<table class="widefat" style="max-width:560px">
				<tr><td width="160"><b>Modèle</b></td><td><code><?php echo CTQ_GEMINI_MODEL; ?></code></td></tr>
				<tr><td><b>Version</b></td><td><code><?php echo CTQ_VERSION; ?></code></td></tr>
				<tr><td><b>Cache</b></td><td><code><?php echo CTQ_CACHE_DIR; ?></code></td></tr>
				<tr><td><b>Clé API</b></td><td><?php echo $key_ok ? '<span style="color:#059669;font-weight:600">✅ Configurée</span>' : '<span style="color:#dc2626;font-weight:600">❌ Absente</span>'; ?></td></tr>
			</table>
		</div>
		<?php
	}
}
