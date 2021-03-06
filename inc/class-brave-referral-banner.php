<?php
/**
 * Class: WP_Brave_Referral_Banner
 *
 * @author Ryan Lanese (Brave Software)
 * https://github.com/brave-intl-brave-referral-banner
 */

class WP_Brave_Referral_Banner {
  // Identifiers
  private $settings_group = 'brb-settings-group';
  private $settings_slug = 'brave-referral-banner';
  private $text_domain = 'brb-text-domain';
  private $settings_hook = 'plugins_page_brave-referral-banner';
  private $invalid_ref_link = 'brb-invalid-ref-link';

  // Options
  private $options = array(
    'referral_style' => array(
      'red'     => 'Red',
      'yellow'  => 'Yellow',
      'black'   => 'Black',
      'branded' => 'Branded'
    ),
    'referral_position' => array(
      'top'    => 'Top of the Page',
      'bottom' => 'Bottom of the Page'
    )
  );

  public function __construct () {
    add_action( 'admin_menu', array( $this, 'add_plugin_settings' ) );
    add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
    add_action( 'admin_notices', array( $this, 'register_plugin_notices' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

    if ( $this->should_add_banner() ) {
      add_action( 'wp_footer', array( $this, 'site_banner_template' ) );
      add_action( 'wp_enqueue_scripts', array( $this, 'load_public_assets' ) );
    }
  }

  public function add_plugin_settings () {
    add_plugins_page(
      'Admin Settings',
      'Brave Referral Banner',
      'administrator',
      $this->settings_slug,
      array( $this, 'plugin_settings_template' )
    );
  }

  public function register_plugin_settings () {
    register_setting( $this->settings_group, 'referral_enabled' );
    register_setting( $this->settings_group, 'referral_style' );
    register_setting( $this->settings_group, 'referral_position' );
    register_setting( 
      $this->settings_group, 
      'referral_link', 
      array( $this, 'validate_referral_link' )
    );
    register_setting(
      $this->settings_group,
      'referral_name',
      array( $this, 'prepare_referral_name' )
    );
  }

  public function register_plugin_notices () {
    settings_errors( $this->invalid_ref_link );
  }

  public function load_admin_assets ($hook) {
    // These assets shouldn't load anywhere besides the plugin settings page.
    if ($hook !== $this->settings_hook) {
      return;
    }

    wp_enqueue_style(
      'plugin_settings_style',
      plugins_url( '../assets/admin/css/brave-referral-banner-admin.css', __FILE__ )
    );
    wp_enqueue_script(
      'plugin_settings_script',
      plugins_url( '../assets/admin/js/brave-referral-banner-admin.js', __FILE__ )
    );
    // This is additionally used in the admin settings for the banner preview.
    wp_enqueue_style(
      'brave_referral_banner_style',
      plugins_url( '../assets/public/css/brave-referral-banner.css', __FILE__ )
    );
  }

  public function load_public_assets () {
    // We don't need to load this again in the admin panel
    if ( ! is_admin() )  {
      wp_enqueue_style(
        'brave_referral_banner_style',
        plugins_url( '../assets/public/css/brave-referral-banner.css', __FILE__ )
      );
    }
    if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
      wp_enqueue_script( 'jquery' );
    }
    wp_enqueue_script(
      'brave_referral_banner_script',
      plugins_url( '../assets/public/js/brave-referral-banner.js', __FILE__ )
    );
  }

  public function get_option_value ( $option_name ) {
    return esc_attr( get_option( $option_name ) );
  }

  public function sanitize_option_value ( $value ) {
    return trim( sanitize_text_field( $value ) );
  }

  public function validate_referral_link ( $value ) {
    if ( preg_match( '/https:\/\/brave\.com\/[a-zA-Z0-9]{6}/', $value ) ) {
      return $this->sanitize_option_value( $value );
    }

    add_settings_error(
      $this->invalid_ref_link,
      esc_attr( 'settings_updated' ),
      __( 'Referral link invalid. Please enter a valid referral link.' ),
      'error'
    );
  }

  public function prepare_referral_name ( $value ) {
    return $this->sanitize_option_value(
      $this->get_referral_name( $value )
    );
  }

  public function get_referral_name ( $new_name=false ) {
    if ( $new_name !== false && strlen ( $new_name ) > 0 ) {
      return $new_name;
    }

    $referral_name = get_option( 'referral_name' );
    if ( strlen( $referral_name ) === 0 ||
         ( $new_name !== false  && strlen( $new_name ) === 0 ) ) {
      // If for some reason the site title is also empty, generic noun is used.
      $site_title = get_bloginfo( 'name' );
      return strlen( $site_title ) === 0
        ? __( 'this site', $this->text_domain )
        : $site_title;
    }

    return $referral_name;
  }

  public function is_banner_enabled () {
    return get_option( 'referral_enabled' ) === "1";
  }

  public function should_add_banner () {
    return $this->is_banner_enabled() &&
    strlen( get_option( 'referral_link' ) ) > 0;
  }

  public function get_display_class () {
    return $this->is_banner_enabled()
      ? ""
      : "row-hidden";
  }

  public function get_color_class () {
    $referral_style = get_option( 'referral_style' );
    $selected_color = strlen($referral_style) === 0
    ? "black"
    : $referral_style;
    return "bk-{$selected_color}";
  }

  public function do_checkbox ( $option_name ) {
?>
    <input
      value="1"
      type="checkbox"
      id="<?php echo $option_name; ?>"
      name="<?php echo $option_name; ?>"
      <?php checked( 1, get_option( $option_name )); ?>
    />
<?php
  }

  public function do_text_input ( $option_name ) {
    $value = $option_name === 'referral_name'
      ? $this->get_referral_name()
      : $this->get_option_value( $option_name );
?>
    <input
      type="text"
      id="<?php echo $option_name ;?>"
      name="<?php echo $option_name ;?>"
      value="<?php echo $value; ?>"
    />
<?php
  }

  public function do_select ( $option_name ) {
?>
    <select
      id="<?php echo $option_name; ?>"
      name="<?php echo $option_name; ?>"
    >
      <?php
        foreach ( $this->options[$option_name] as $value => $name ):
          $selected = $this->get_option_value( $option_name ) === $value
            ? "selected='selected'"
            : "";
      ?>
        <option
          value="<?php echo $value; ?>"
          <?php echo $selected; ?>
        >
          <?php echo $name; ?>
        </option>
      <?php endforeach; ?>
    </select>
<?php
  }

  public function plugin_settings_template () {
?>
    <div class="wrap">
      <h1>
        <?php echo __( 'Brave Referral Banner', $this->text_domain ); ?>
      </h1>
      <div class="brave-image">
        <img src="<?php echo plugins_url( '../assets/admin/img/brave-icon.png', __FILE__ ) ?>" />
      </div>
      <form method="post" action="options.php">
        <?php settings_fields( $this->settings_group ); ?>
        <?php do_settings_sections( $this->settings_slug ); ?>
        <table class="form-table">
          <tbody>
            <tr>
              <th scope="row">
                <label for="referral_enabled">
                  <?php echo __( 'Banner Enabled:', $this->text_domain ); ?>
                </label>
              </th>
              <td>
                <?php $this->do_checkbox( 'referral_enabled' ); ?>
              </td>
            </tr>
            <tr class="configuration <?php echo $this->get_display_class(); ?>">
              <th scope="row">
                <label for="referral_style">
                  <?php echo __( 'Banner Style:', $this->text_domain ); ?>
                </label>
              </th>
              <td>
                <?php $this->do_select( 'referral_style' ); ?>
              </td>
            </tr>
            <tr class="configuration <?php echo $this->get_display_class(); ?>">
              <th scope="row">
                <label for="referral_position">
                  <?php echo __( 'Banner Position:', $this->text_domain ); ?>
                </label>
              </th>
              <td>
                <?php $this->do_select( 'referral_position' ); ?>
              </td>
            </tr>
            <tr class="configuration <?php echo $this->get_display_class(); ?>">
              <th scope="row">
                <label for="referral_link">
                  <?php echo __( 'Brave Referral Link:', $this->text_domain ); ?>
                </label>
              </th>
              <td>
                <?php $this->do_text_input( 'referral_link' ); ?>
              </td>
            </tr>
            <tr class="configuration <?php echo $this->get_display_class(); ?>">
              <th scope="row">
                <label for="referral_link">
                  <?php echo __( 'Publisher Name:', $this->text_domain ); ?>
                </label>
                <span class="sub-label">
                  <?php echo __( '(Defaults to Site Title)', $this->text_domain ); ?>
                </span>
              </th>
              <td>
                <?php $this->do_text_input( 'referral_name' ); ?>
              </td>
            </tr>
            <tr class="configuration <?php echo $this->get_display_class(); ?>">
              <div class="preview-container">
                <?php $this->site_banner_template(); ?>
              </div>
            </tr>
          </tbody>
        </table>
        <?php submit_button( __( 'Update Banner', $this->text_domain ), 'brb-save' ); ?>
      </form>
    </div>
<?php
  }

  public function site_banner_template () {
    $referral_position = get_option( 'referral_position ' );
    $position_class = !is_admin()
      ? $referral_position === 'top'
        ? 'brb-top'
        : 'brb-bottom'
      : "";
    $hidden_class = $this->is_banner_enabled()
      ? ""
      : "preview-hidden";
?>
    <div class="preview-inner <?php echo $hidden_class; ?>">
      <?php if ( is_admin() ): ?>
        <h2 class="preview-text">
          <?php echo __( 'Banner Preview:', $this->text_domain ); ?>
        </h2>
      <?php endif; ?>
      <div class="brb-banner <?php echo $position_class; ?> <?php echo $this->get_color_class(); ?>">
        <span>
          <?php echo __( 'Switch web browsers to Brave to protect your privacy and support', $this->text_domain ); ?>
        </span>
        <span class="brb-referral-name">
          <?php echo get_option( 'referral_name' ); ?>
        </span>
        <a
          target="_blank"
          class="brb-referral-link"
          href="<?php echo $this->get_option_value( 'referral_link' ); ?>"
        >
          <?php echo __( 'Try It Today >>..', $this->text_domain ); ?>
        </a>
        <a class="brb-referral-close">X</a>
      </div>
    </div>
<?php
  }
}

