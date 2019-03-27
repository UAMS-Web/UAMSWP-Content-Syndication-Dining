<?php
/**
 * A base class for UAMS dining syndicate shortcodes.
 *
 * Class UAMS_Syndicate_Shortcode_Dining
 */
class UAMS_Syndicate_Shortcode_Dining {
	/**
	 * Default path used to consume the REST API from an individual site.
	 *
	 * @var string
	 */
	public $default_path = 'nutrition/menu/new_menu_json.asp';
	/**
	 * Default attributes applied to all shortcodes that extend this base.
	 *
	 * @var array
	 */
	public $defaults_atts = array(
		'object' => 'json_data',
		'output' => 'json',
		'host' => 'www.uams.edu',
		'scheme' => 'http',
		'site' => '',
		'category' => '',
		'date_format' => 'F j, Y',
		'cache_bust' => '',
	);
	/**
	 * Defaults for individual base attributes can be overridden for a
	 * specific shortcode.
	 *
	 * @var array
	 */
	public $local_default_atts = array();
	/**
	 * Defaults can be extended with additional keys by a specific shortcode.
	 *
	 * @var array
	 */
	public $local_extended_atts = array();
	/**
	 * @var string The shortcode name.
	 */
	public $shortcode_name = '';
	/**
	 * A common constructor that initiates the shortcode.
	 */
	public function construct() {
		$this->add_shortcode();
	}
	/**
	 * Required to add a shortcode definition.
	 */
	public function add_shortcode() {}
	/**
	 * Required to display the content of a shortcode.
	 *
	 * @param array $atts A list of attributes assigned to the shortcode.
	 *
	 * @return string Final output for the shortcode.
	 */
	public function display_shortcode( $atts ) {
		return '';
	}
	/**
	 * Process passed attributes for a shortcode against arrays of base defaults,
	 * local defaults, and extended local defaults.
	 *
	 * @param array $atts Attributes passed to a shortcode.
	 *
	 * @return array Fully populated list of attributes expected by the shortcode.
	 */
	public function process_attributes( $atts ) {
		$defaults = apply_filters( 'uamswp_dining_syndicate_default_atts', $this->defaults_atts );
		$defaults = shortcode_atts( $defaults, $this->local_default_atts );
		$defaults = array_merge( $defaults, $this->local_extended_atts );
		$local_defaults = array();
		// Allow for different attribute values to be passed when results from the
		// local site are merged into results from a remote site.
		foreach ( $defaults as $attribute => $value ) {
			// The core default attributes should stay the same
			if ( array_key_exists( $attribute, $this->defaults_atts ) ) {
				continue;
			}
			$local_defaults[ 'local_' . $attribute ] = $value;
		}
		$defaults = array_merge( $defaults, $local_defaults );
		return shortcode_atts( $defaults, $atts, $this->shortcode_name );
	}
	/**
	 * Create a hash of all attributes to use as a cache key. If any attribute changes,
	 * then the cache will regenerate on the next load.
	 *
	 * @param array  $atts      List of attributes used for the shortcode.
	 * @param string $shortcode Shortcode being displayed.
	 *
	 * @return bool|string False if cache is not available or expired. Content if available.
	 */
	public function get_content_cache( $atts, $shortcode ) {
		$atts_key = md5( serialize( $atts ) ); // @codingStandardsIgnoreLine
		$content = wp_cache_get( $atts_key, $shortcode );
		return $content;
	}
	/**
	 * Store generated content from the shortcode in cache.
	 *
	 * @param array  $atts      List of attributes used for the shortcode.
	 * @param string $shortcode Shortcode being displayed.
	 * @param string $content   Generated content after processing the shortcode.
	 */
	public function set_content_cache( $atts, $shortcode, $content ) {
		$atts_key = md5( serialize( $atts ) ); // @codingStandardsIgnoreLine
		wp_cache_set( $atts_key, $content, $shortcode, 600 );
	}
	/**
	 * Processes a given site URL and shortcode attributes into data to be used for the
	 * request.
	 *
	 * @since 0.10.0
	 *
	 * @param array $site_url Contains host and path of the requested URL.
	 * @param array $atts     Contains the original shortcode attributes.
	 *
	 * @return array List of request information.
	 */
	public function build_initial_request( $site_url, $atts ) {
		$url_scheme = 'http';
		$local_site_id = false;
		// Account for a previous version that allowed "local" as a manual scheme.
		if ( 'local' === $atts['scheme'] ) {
			$atts['scheme'] = 'http';
		}
		$home_url_data = wp_parse_url( trailingslashit( get_home_url() ) );
		if ( $home_url_data['host'] === $site_url['host'] && $home_url_data['path'] === $site_url['path'] ) {
			$local_site_id = 1;
			$url_scheme = $home_url_data['scheme'];
			// Local is assigned as a scheme only if the requesting site is the requested site.
			$atts['scheme'] = 'local';
		} elseif ( is_multisite() ) {
			$local_site = get_blog_details( array(
				'domain' => $site_url['host'],
				'path' => $site_url['path'],
			), false );
			if ( $local_site ) {
				$local_site_id = $local_site->blog_id;
				$local_home_url = get_home_url( $local_site_id );
				$url_scheme = wp_parse_url( $local_home_url, PHP_URL_SCHEME );
				$atts['scheme'] = $url_scheme;
			}
		}
		$request_url = esc_url( $url_scheme . '://' . $site_url['host'] . $site_url['path'] . $this->default_path ) . $atts['query'];
		$request = array(
			'url' => $request_url,
			'scheme' => $atts['scheme'],
			'site_id' => $local_site_id,
		);
		return $request;
	}
	/**
	 * Determine what the base URL should be used for REST API data.
	 *
	 * @param array $atts List of attributes used for the shortcode.
	 *
	 * @return bool|array host and path if available, false if not.
	 */
	public function get_request_url( $atts ) {
		// If a site attribute is provided, it overrides the host attribute.
		if ( ! empty( $atts['site'] ) ) {
			$site_url = trailingslashit( esc_url( $atts['site'] ) );
		} else {
			$site_url = trailingslashit( esc_url( $atts['host'] ) );
		}
		$site_url = wp_parse_url( $site_url );
		if ( empty( $site_url['host'] ) ) {
			return false;
		}
		return $site_url;
	}
	
	/**
	 * @var string Shortcode name.
	 */
	public $shortcode_name = 'uamswp_dining';

	public function __construct() {
		parent::construct();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_syndication_dining_stylesheet' ) );
		if ( class_exists('UAMS_Shortcakes') ) {
			add_action( 'admin_init', array( $this, 'build_shortcake' ) );
			// add_editor_style( plugins_url( '/css/uams-syndication-admin.css', __DIR__ ) );
			add_action( 'enqueue_shortcode_ui', function() {
				// wp_enqueue_script( 'uams_syndications_editor_js', plugins_url( '/js/uams-syndication-shortcake.js', __DIR__ ) );
			});
		}
		add_action( 'admin_init', array( $this, 'enqueue_syndication_stylesheet_admin' ) );
	}
	/**
	 * Add the shortcode provided.
	 */
	public function add_shortcode() {
		add_shortcode( 'uamswp_dining', array( $this, 'display_shortcode' ) );
	}

	/**
	 * Enqueue styles specific to the network admin dashboard.
	 */
	public function enqueue_syndication_dining_stylesheet() {
		$post = get_post();
	 	if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'uamswp_dining' ) ) {
			wp_enqueue_style( 'uamswp-syndication-dining-style', plugins_url( '/css/uamswp-syndication-dining.css', __DIR__ ), array(), '' );
		}
	}

	/**
	 * Enqueue styles specific to the network admin dashboard.
	 */
	public function enqueue_syndication_stylesheet_admin() {
		add_editor_style( 'uamswp-syndication-dining-style-admin', plugins_url( '/css/uamswp-syndication-dining.css', __DIR__ ), array(), '' );
	}
	public function build_shortcake() {
		shortcode_ui_register_for_shortcode(
	 
			/** Your shortcode handle */
			'uamswp_dining',
			 
			/** Your Shortcode label and icon */
			array(
			 
			/** Label for your shortcode user interface. This part is required. */
			'label' => esc_html__('Dining Syndication', 'uamswp_dining'),
			 
			/** Icon or an image attachment for shortcode. Optional. src or dashicons-$icon.  */
			'listItemImage' => 'dashicons-carrot',
			 
			/** Shortcode Attributes */
			'attrs'          => array(
			 
				/** Output format */
				array(
				'label'     => esc_html__('Format', 'uamswp_dining'),
				'attr'      => 'output',
				'type'      => 'radio',
				    'options' => array(
						'headline'	=> 'Headlines Only',
				        'list'      => 'List',
				        'excerpts'    => 'Excerpt',
				        'cards'     => 'Card', // Maybe
				        //'full'     => 'Full', // Maybe
				    ),
				'description'  => 'Preferred output format',
				),

				/** Count - Number of dining */
				array(
				'label'        => esc_html__('Count', 'uamswp_dining'),
				'attr'         => 'count',
				'type'         => 'number',
				'description'  => 'Number of dining to display',
				'meta'   => array(
						'placeholder' 	=> esc_html__( '1' ),
						'min'			=> '1',
						'step'			=> '1',
					),
				),
			 
			),
			 
			/** You can select which post types will show shortcode UI */
			'post_type'     => array( 'post', 'page' ), 
			)
		);
	}

	/**
	 * Display dining information for the [uamswp_dining] shortcode.
	 *
	 * @param array $atts
	 *
	 * @return string
	 */
	public function display_shortcode( $atts ) {
		$atts = $this->process_attributes( $atts );

		$site_url = $this->get_request_url( $atts );
		if ( ! $site_url ) {
			return '<!-- uamswp_dining ERROR - an empty host was supplied -->';
		}

        $request = $this->build_initial_request( $site_url, $atts );
        
        return $request;

		// Build taxonomies on the REST API request URL, except for `category`
		// as it's a different taxonomy in this case than the function expects.
		$taxonomy_filters_atts = $atts;

		unset( $taxonomy_filters_atts['category'] );

		// Handle the 'type' taxonomy separately, too.
		unset( $taxonomy_filters_atts['type'] );
		
		$request_url = $this->build_taxonomy_filters( $taxonomy_filters_atts, $request['url'] );

		//Add event post data args
		$request_url = add_query_arg( array(
			'filter[orderby]'=> 'meta_value',
			'filter[meta_key]' => 'event_begin',
			'filter[order]'=> 'ASC',
			'filter[meta_query][0][key]' => 'event_end',
			'filter[meta_query][1][key]' => 'event_end',
			'filter[meta_query][1][value]' => rawurlencode('0:0:00 0:'),
			'filter[meta_query][1][compare]' => rawurlencode('!='),
			'filter[meta_query][2][key]' => 'event_end',
			'filter[meta_query][2][value]' => ':00',
			'filter[meta_query][2][compare]' => rawurlencode('!='),
			'filter[meta_query][3][key]' => 'event_begin',
			'filter[meta_query][4][key]' => 'event_begin',
			'filter[meta_query][4][value]' => rawurlencode('0:0:00 0:'),
			'filter[meta_query][4][compare]' => rawurlencode('!='),
			'filter[meta_query][5][key]' => 'event_end',
			'filter[meta_query][5][value]' => rawurlencode((new \DateTime(null, new DateTimeZone('America/Chicago')))->format('Y-m-d H:i:s')),
			'filter[meta_query][5][compare]' => rawurlencode('>='),
		), $request_url );
		
		if ( 'past' === $atts['period'] ) {
			$request_url = add_query_arg( array(
				'tribe_event_display' => 'past',
			), $request_url );
		}
		// if ( '' !== $atts['category'] ) {
		// 	// $request_url = add_query_arg( array(
		// 	// 	'filter[taxonomy]' => 'tribe_dining_cat',
		// 	// ), $request_url );

		// 	$terms = explode( ',', $atts['category'] );
		// 	foreach ( $terms as $term ) {
		// 		$term = trim( $term );
		// 		$request_url = add_query_arg( array(
		// 			'filter[term]' => sanitize_key( $term ),
		// 		), $request_url );
		// 	}
		// }

		if ( $atts['count'] ) {
			$count = ( 100 < absint( $atts['count'] ) ) ? 100 : $atts['count'];
			$request_url = add_query_arg( array(
				'per_page' => absint( $count ),
			), $request_url );
		}

		$new_data = $this->get_content_cache( $atts, 'uamswp_dining' );

		if ( ! is_array( $new_data ) ) {
			$response = wp_remote_get( $request_url );

			if ( ! is_wp_error( $response ) && 404 !== wp_remote_retrieve_response_code( $response ) ) {
				$data = wp_remote_retrieve_body( $response );

				$new_data = array();
				if ( ! empty( $data ) ) {
					$data = json_decode( $data );

					if ( null === $data ) {
						$data = array();
					}

					if ( isset( $data->code ) && 'rest_no_route' === $data->code ) {
						$data = array();
					}

					foreach ( $data as $post ) {
						$subset = new StdClass();
			
							$subset->FoodID = $post->FoodID;
							$subset->Food = $post->Food;
							$subset->CategoryID = $post->CategoryID;
							$subset->Category = $post->Category;
							$subset->HeartHealthy = $post->HeartHealthy;
							$subset->Vegetarian = $post->Vegetarian;
							$subset->Spicy = $post->Spicy;
							$subset->GlutenFree = $post->GlutenFree;
							$subset->Nut = $post->Nut;
							$subset->Soy = $post->Soy;
							$subset->Dairy = $post->Dairy;
							$subset->Seafood = $post->Seafood;
							$subset->PortionSize = $post->PortionSize;
							$subset->Grams = $post->Grams;
							$subset->Calories = $post->Calories;
							$subset->Cholesterol = $post->Cholesterol;
							$subset->Sodium = $post->Sodium;
							$subset->Carbs = $post->Carbs;
							$subset->Fiber = $post->Fiber;
							$subset->Protein = $post->Protein;
							$subset->Potassium = $post->Potassium;
						
						/**
						 * Filter the data stored for an individual result after defaults have been built.
						 *
						 * @since 0.7.10
						 *
						 * @param object $subset Data attached to this result.
						 * @param object $post   Data for an individual post retrieved via `wp-json/posts` from a remote host.
						 * @param array  $atts   Attributes originally passed to the `uamswp_news` shortcode.
						 */
						$subset = apply_filters( 'uams_dining_syndication_host_data', $subset, $post, $atts );
			
						if ( $post->date ) {
							$subset_key = strtotime( $post->date );
						} else {
							$subset_key = time();
						}
			
						while ( array_key_exists( $subset_key, $new_data ) ) {
							$subset_key++;
						}
						$new_data[ $subset_key ] = $subset;
					} // End foreach().
				}

				// Store the built content in cache for repeated use.
				$this->set_content_cache( $atts, 'uamswp_dining', $new_data );
			}
		}
		if ( ! is_array( $new_data ) ) {
			$new_data = array();
		}

		$content = apply_filters( 'uamswp_content_syndicate_dining_output', false, $new_data, $atts );
		if ( false === $content ) {
			$content = $this->generate_shortcode_output( $new_data, $atts );
		}
		$content = apply_filters( 'uamswp_content_syndicate_dining', $content, $atts );

		return $content;
	}
	/**
	 * Generates the content to display for a shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array $new_data Data containing the dining to be displayed.
	 * @param array $atts     Array of options passed with the shortcode.
	 *
	 * @return string Content to display for the shortcode.
	 */
	public function generate_shortcode_output( $new_data, $atts ) {
		ob_start();
		// By default, we output a JSON object that can then be used by a script.
		if ( 'json' === $atts['output'] ) {
            echo '<!-- UAMSWP Output JSON -->';
            // print_r ($new_data);
            echo '<script>var ' . esc_js( $atts['object'] ) . ' = ' . wp_json_encode( $new_data ) .';</script>' . '<script>'. $url_var .';</script>';
		} elseif ( 'headlines' === $atts['output'] ) {
			?>
            <!-- UAMSWP Output Headlines -->
			<div class="uamswp-content-syndication-wrapper">
				<ul class="uamswp-content-syndication-event-headline">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?><li class="uamswp-content-syndication-item"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a></li><?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'list' === $atts['output'] ) {
			?>
            <!-- UAMSWP Output Headlines -->
			<div class="uamswp-content-syndication-wrapper">
				<ul class="uamswp-content-syndication-event-list">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<li class="uamswp-content-syndication-item"><a href="<?php echo esc_url( $content->link ); ?>"><?php echo esc_html( $content->title ); ?></a>
						<?php echo (!is_null($content->event_begin) ? '<small>'. $this->delta_date($this->parsedate($content->event_begin), $this->parsedate($content->event_end)) .'<span class="event_location">'. esc_html($content->event_address) .'</small></span>' : '' ) ?>
						</li><?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'excerpts' === $atts['output'] ) {
			?>
            <!-- UAMSWP Output Excerpts -->
			<div class="uamswp-content-syndication-wrapper">
				<ul class="uamswp-content-syndication-event-excerpts">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
						<li class="uamswp-content-syndication-item" itemscope itemtype="http://schema.org/NewsArticle">
							<meta itemscope itemprop="mainEntityOfPage"  itemType="https://schema.org/WebPage" itemid="<?php echo esc_url( $content->link ); ?>"/>
							<a class="content-item-thumbnail" href="<?php echo esc_url( $content->link ); ?>" itemprop="image" itemscope itemtype="https://schema.org/ImageObject"><?php if ( $content->thumbnail ) : ?><img src="<?php echo esc_url( $content->thumbnail ); ?>" alt="<?php echo esc_html( $content->thumbalt ); ?>" itemprop="url"><?php endif; ?></a>
							<span class="content-item-title" itemprop="headline"><a href="<?php echo esc_url( $content->link ); ?>" class="news-link" itemprop="url"><?php echo esc_html( $content->title ); ?></a></span>
							<?php echo (!is_null($content->event_begin) ? '<small>'. $this->delta_date($this->parsedate($content->event_begin), $this->parsedate($content->event_end)) .'<span class="event_location">'. esc_html($content->event_address) .'</small></span>' : '' ) ?>
							<span class="content-item-excerpt" itemprop="articleBody"><?php echo wp_kses_post( $content->excerpt ); ?></span>
							<span itemprop="publisher" itemscope itemtype="http://schema.org/Organization">
								<meta itemprop="name" content="University of Arkansas for Medical Sciences"/>
								<span itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
									<meta itemprop="url" content="http://web.uams.edu/wp-content/uploads/sites/51/2017/09/UAMS_Academic_40-1.png"/>
								    <meta itemprop="width" content="297"/>
								    <meta itemprop="height" content="40"/>
								</span>
							</span>
						</li>
						<?php
					}
					?>
				</ul>
			</div>
			<?php
		} elseif ( 'cards' === $atts['output'] ) {
			?>
            <!-- UAMSWP Output Cards -->
			<div class="uamswp-content-syndication-wrapper">
				<div class="uamswp-content-syndication-event-cards">
					<?php
					$offset_x = 0;
					foreach ( $new_data as $content ) {
						if ( $offset_x < absint( $atts['offset'] ) ) {
							$offset_x++;
							continue;
						}
						?>
					    <div class="default-card" itemscope itemtype="http://schema.org/NewsArticle">
					    	<meta itemscope itemprop="mainEntityOfPage"  itemType="https://schema.org/WebPage" itemid="<?php echo esc_url( $content->link ); ?>"/>
					    	<?php if ( $content->image ) : ?><div class="card-image" itemprop="image" itemscope itemtype="https://schema.org/ImageObject"><img src="<?php echo esc_url( $content->image ); ?>" alt="<?php echo esc_html( $content->imagecaption ); ?>" itemprop="url"></div><?php endif; ?>
							<div class="card-body">
					      		<span>
					      			<h3 itemprop="headline">
					                	<a href="<?php echo esc_url( $content->link ); ?>" class="pic-title"><?php echo esc_html( $content->title ); ?></a>
					              	</h3>
					              	<span itemprop="articleBody">
									  <?php echo (!is_null($content->event_begin) ? '<small>'. $this->delta_date($this->parsedate($content->event_begin), $this->parsedate($content->event_end)) .'<span class="event_location">'. esc_html($content->event_address) .'</small></span>' : '' ) ?>
					      			<?php echo wp_kses_post( $content->excerpt ); ?>
					      			</span>
					              	<a href="<?php echo esc_url( $content->link ); ?>" class="pic-text-more uams-btn btn-sm btn-red" itemprop="url">Read more</a>
					              	<span class="content-item-byline-author" itemprop="author" itemscope itemtype="http://schema.org/Person"><meta itemprop="name" content="<?php echo esc_html( $content->author_name ); ?>"/></span>
					              	<meta itemprop="datePublished" content="<?php echo esc_html( date( 'c', strtotime( $content->date ) ) ); ?>"/>
					              	<meta itemprop="dateModified" content="<?php echo esc_html( date( 'c', strtotime( $content->modified ) ) ); ?>"/>
					            </span>

							</div>
							<span itemprop="publisher" itemscope itemtype="http://schema.org/Organization">
								<meta itemprop="name" content="University of Arkansas for Medical Sciences"/>
								<span itemprop="logo" itemscope itemtype="https://schema.org/ImageObject">
									<meta itemprop="url" content="http://web.uams.edu/wp-content/uploads/sites/51/2017/09/UAMS_Academic_40-1.png"/>
								    <meta itemprop="width" content="297"/>
								    <meta itemprop="height" content="40"/>
								</span>
							</span>
					    </div>
						<?php
					}
					?>
				</div>
			</div>
			<?php
		} // End if().
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
     *
     * @param string $date
     * @param string $sep
     * @return string
     */
    public function parsedate($date, $sep = '') {
        if (!empty($date)) {
            return substr($date, 0, 10) . $sep . substr($date, 11, 8);
        } else {
            return '';
        }
    }

	/**
     *
     * @param mixed $date
     * @param string $format
     * @return type
     */
    public function human_date($date, $format = 'l j F Y') {
        // if($this->settings['dateforhumans']){
            if (is_numeric($date) && date('d/m/Y', $date) == date('d/m/Y')) {
                return __('Today', 'event-post');
            } elseif (is_numeric($date) && date('d/m/Y', $date) == date('d/m/Y', strtotime('+1 day'))) {
                return __('Tomorrow', 'event-post');
            } elseif (is_numeric($date) && date('d/m/Y', $date) == date('d/m/Y', strtotime('-1 day'))) {
                return __('Yesterday', 'event-post');
            }
        // }
        return date_i18n($format, $date);
    }

    /**
     *
     * @param timestamp $time_start
     * @param timestamp $time_end
     * @return string
     */
    public function delta_date($time_start, $time_end){
        if(!$time_start || !$time_end){
            return;
		}
		
		$time_start = strtotime($time_start);
		$time_end = strtotime($time_end);

        //Display dates
        $dates="\t\t\t\t".'<div class="event_date" data-start="' . $this->human_date($time_start) . '" data-end="' . $this->human_date($time_end) . '">';
        if (date('d/m/Y', $time_start) == date('d/m/Y', $time_end)) { // same day
            $dates.= "\n\t\t\t\t\t\t\t".'<time itemprop="dtstart" datetime="' . date_i18n('c', $time_start) . '">'
                    . '<span class="date date-single">' . $this->human_date($time_end, get_option('date_format')) . "</span>";
            if (date('H:i', $time_start) != date('H:i', $time_end) && date('H:i', $time_start) != '00:00' && date('H:i', $time_end) != '00:00') {
                $dates.='   <span class="linking_word linking_word-from">' . _x('from', 'Time', 'event-post') . '</span>
                            <span class="time time-start">' . date_i18n(get_option('time_format'), $time_start) . '</span>
                            <span class="linking_word linking_word-to">' . _x('to', 'Time', 'event-post') . '</span>
                            <span class="time time-end">' . date_i18n(get_option('time_format'), $time_end) . '</span>';
            }
            elseif (date('H:i', $time_start) != '00:00') {
                $dates.='   <span class="linking_word">' . _x('at', 'Time', 'event-post') . '</span>
                            <time class="time time-single" itemprop="dtstart" datetime="' . date_i18n('c', $time_start) . '">' . date_i18n(get_option('time_format'), $time_start) . '</time>';
            }
            $dates.="\n\t\t\t\t\t\t\t".'</time>';
        } else { // not same day
            $dates.= '
                <span class="linking_word linking_word-from">' . _x('from', 'Date', 'event-post') . '</span>
                <time class="date date-start" itemprop="dtstart" datetime="' . date('c', $time_start) . '">' . $this->human_date($time_start, get_option('date_format'));
            if (date('H:i:s', $time_start) != '00:00:00' || date('H:i:s', $time_end) != '00:00:00'){
              $dates.= ', ' . date_i18n(get_option('time_format'), $time_start);
            }
            $dates.='</time>
                <span class="linking_word linking_word-to">' . _x('to', 'Date', 'event-post') . '</span>
                <time class="date date-end" itemprop="dtend" datetime="' . date('c', $time_end) . '">' . $this->human_date($time_end, get_option('date_format'));
            if (date('H:i:s', $time_start) != '00:00:00' || date('H:i:s', $time_end) != '00:00:00') {
              $dates.=  ', ' . date_i18n(get_option('time_format'), $time_end);
            }
            $dates.='</time>';
        }
        $dates.="\n\t\t\t\t\t\t".'</div><!-- .event_date -->';
        return $dates;
    }
	
}
