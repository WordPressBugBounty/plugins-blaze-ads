<?php
/**
 * Class Blaze_Marketing_Channel
 *
 * @package Automattic\BlazeAds
 */

namespace BlazeAds;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Marketing\MarketingChannels;
use Automattic\WooCommerce\Admin\Marketing\MarketingCampaign;
use Automattic\WooCommerce\Admin\Marketing\MarketingCampaignType;
use Automattic\WooCommerce\Admin\Marketing\MarketingChannelInterface;
use Automattic\WooCommerce\Admin\Marketing\Price;
use BlazeAds\Exceptions\Base_Exception;
use Jetpack_Options;

/**
 * Marketing Channel implementation for Blaze Campaigns.
 * This class is responsible for registering Blaze as a marketing channel and displaying Blaze campaigns
 *
 * Class Blaze_Marketing_Channel
 */
class Blaze_Marketing_Channel implements MarketingChannelInterface {

	/**
	 * The campaign types supported by Blaze.
	 *
	 * @var array
	 */
	protected array $campaign_types;

	/**
	 * MarketingChannelRegistrar constructor.
	 */
	public function __construct() {
		$this->campaign_types = array();

		if ( $this->can_register_marketing_channel() ) {
			$this->campaign_types = $this->generate_campaign_types();
		}
	}

	/**
	 * Initialize the marketing channel.
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( ! $this->can_register_marketing_channel() ) {
			return;
		}

		$wc_container       = $GLOBALS['wc_container'];
		$marketing_channels = $wc_container->get( MarketingChannels::class );
		$marketing_channels->register( $this );
	}


	/**
	 * Check if the Multichannel Marketing plugin is active.
	 *
	 * @return bool
	 */
	public function can_register_marketing_channel(): bool {
		// Check if the Multichannel Marketing plugin is active.
		if ( ! class_exists( MarketingChannels::class ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the unique identifier string for the marketing channel extension, also known as the plugin slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'woo-blaze';
	}

	/**
	 * Returns the name of the marketing channel.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Blaze Ads', 'blaze-ads' );
	}

	/**
	 * Returns the description of the marketing channel.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __(
			'Drive sales, and elevate your products to center stage, effortlessly. Witness your business flourishing in the blink of an eye.',
			'blaze-ads'
		);
	}

	/**
	 * Returns the path to the channel icon.
	 *
	 * @return string
	 */
	public function get_icon_url(): string {
		return plugins_url( 'assets/images/blaze-flame-woo.svg', BLAZEADS_PLUGIN_FILE );
	}

	/**
	 * Returns an array of marketing campaign types that the channel supports.
	 *
	 * @return MarketingCampaignType[] Array of marketing campaign type objects.
	 */
	public function get_supported_campaign_types(): array {
		return $this->campaign_types;
	}

	/**
	 * Returns the setup status of the marketing channel.
	 *
	 * @return bool
	 */
	public function is_setup_completed(): bool {
		$setup_reason = ( new Blaze_Dashboard() )->check_setup_plugin_status();

		return null === $setup_reason;
	}

	/**
	 * Returns the status of the marketing channel's product listings.
	 *
	 * @return string
	 */
	public function get_product_listings_status(): string {
		return MarketingChannelInterface::PRODUCT_LISTINGS_NOT_APPLICABLE;
	}

	/**
	 * Returns the number of channel issues/errors (e.g. account-related errors, product synchronization issues, etc.).
	 *
	 * @return int The number of issues to resolve, or 0 if there are no issues with the channel.
	 */
	public function get_errors_count(): int {
		return 0;
	}

	/**
	 * Returns the URL to the settings page, or the link to complete the setup/onboarding if the channel has not been set up yet.
	 *
	 * @return string
	 */
	public function get_setup_url(): string {
		if ( $this->is_setup_completed() ) {
			return admin_url( sprintf( 'admin.php?page=wp-blaze#!/wp-blaze/%s', $this->get_site_hostname() ) );
		}

		return admin_url( sprintf( 'admin.php?page=wp-blaze#!/wp-blaze/setup/%s', $this->get_site_hostname() ) );
	}

	/**
	 * Returns the campaign price for the given Blaze campaign.
	 *
	 * @param array $campaign Blaze campaign object.
	 *
	 * @return Price
	 */
	public function get_campaign_price( array $campaign ): Price {
		$price_amount = isset( $campaign['campaign_stats']['total_budget'] ) && is_numeric( $campaign['campaign_stats']['total_budget'] )
			? $campaign['campaign_stats']['total_budget']
			: 0;

		return new Price( $price_amount, 'USD' );
	}

	/**
	 * Returns the campaign URL for the given Blaze campaign.
	 *
	 * @param array  $campaign Blaze campaign object.
	 * @param string $site_url Site URL.
	 *
	 * @return string
	 */
	public function get_campaign_url( array $campaign, string $site_url ): string {
		return admin_url(
			sprintf(
				'admin.php?page=wp-blaze#!/wp-blaze/campaigns/%s/%s',
				$campaign['campaign_id'],
				$site_url
			)
		);
	}

	/**
	 *  Returns the site hostname.
	 *
	 * @return string
	 */
	public function get_site_hostname(): string {
		return wp_parse_url( get_site_url(), PHP_URL_HOST );
	}

	/**
	 * Returns the marketing campaigns from the Blaze campaigns information.
	 *
	 * @param array $campaigns List of Blaze campaigns.
	 *
	 * @return MarketingCampaign[] Array of marketing campaign objects.
	 */
	public function get_marketing_campaigns( array $campaigns ): array {
		$marketing_campaigns = array();
		$site_url            = wp_parse_url( get_site_url(), PHP_URL_HOST );

		foreach ( $campaigns as $campaign ) {
			$marketing_campaigns[] = new MarketingCampaign(
				$campaign['campaign_id'],
				$this->campaign_types['woo-blaze'],
				$campaign['name'],
				$this->get_campaign_url( $campaign, $this->get_site_hostname() ),
				$this->get_campaign_price( $campaign )
			);
		}

		return $marketing_campaigns;
	}

	/**
	 * Returns an array of the channel's marketing campaigns.
	 *
	 * @return MarketingCampaign[]
	 */
	public function get_campaigns(): array {
		$query_params = array(
			'order'    => 'asc',
			'order_by' => 'post_date',
			'status'   => 'active',
		);

		$blog_id = Jetpack_Options::get_option( 'id' );
		$path    = sprintf( 'v1/search/campaigns/site/%s', $blog_id );

		try {
			$response = Blaze_Ads_Utils::call_dsp_server( $blog_id, $path, 'GET', $query_params );

			if ( 200 !== $response['status'] || ! isset( $response['body'] ) || ! isset( $response['body']['campaigns'] ) ) {
				return array();
			}

			$campaigns = array_map(
				static function ( $campaign ) {
					return array(
						'campaign_id'    => $campaign['campaign_id'],
						'start_date'     => $campaign['start_date'],
						'end_date'       => $campaign['end_date'],
						'name'           => $campaign['name'],
						'campaign_stats' => $campaign['campaign_stats'],
						'audience_list'  => $campaign['audience_list'],
					);
				},
				$response['body']['campaigns']
			);

			return $this->get_marketing_campaigns( $campaigns );
		} catch ( Base_Exception $e ) {
			return array();
		}
	}

	/**
	 * Returns the URL for creating a new marketing campaign.
	 *
	 * @return mixed
	 */
	public function get_campaign_create_url(): string {
		return admin_url(
			sprintf(
				'admin.php?page=wp-blaze#!/wp-blaze/posts/promote/post-0/%s',
				$this->get_site_hostname()
			)
		);
	}

	/**
	 * Generate an array of supported marketing campaign types.
	 *
	 * @return MarketingCampaignType[]
	 */
	protected function generate_campaign_types(): array {
		return array(
			'woo-blaze' => new MarketingCampaignType(
				'woo-blaze',
				$this,
				'Blaze Ads',
				$this->get_description(),
				$this->get_campaign_create_url(),
				$this->get_icon_url()
			),
		);
	}
}




