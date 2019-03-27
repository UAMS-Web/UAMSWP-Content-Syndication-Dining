<?php
/**
 * A base class for UAMS dining syndicate shortcodes.
 *
 * Class UAMS_Syndicate_Shortcode_Dining
 */
class UAMS_Syndicate_Shortcode_Dining {
	/**
     * Instance of this class.
     *
     * @var      UAMS_Syndicate_Shortcode_Dining
     */
    private static $instance;
 
    /**
     * Initializes the plugin so that the dining information is appended to the end of a single post.
     * Note that this constructor relies on the Singleton Pattern
     *
     * @access private
     */
    public function __construct() {
		add_shortcode( 'uamswp_dining', array( $this, 'display_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_syndication_dining_stylesheet' ) );
		if ( class_exists('UAMS_Shortcakes') ) {
			add_action( 'admin_init', array( $this, 'build_shortcake' ) );
			add_action( 'enqueue_shortcode_ui', function() {
				wp_enqueue_script( 'uams_syndications_editor_js', plugins_url( '/js/uams-dining-shortcake.js', __DIR__ ) );
			});
		}
	} // end constructor
	
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
     * Creates an instance of this class
     *
     * @access public
     * @return UAMS_Syndicate_Shortcode_Dining    An instance of this class
     */
    public function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
	} // end get_instance
	
	/**
	 * Enqueue styles specific to the network admin dashboard.
	 */
	public function enqueue_syndication_stylesheet_admin() {
		//add_editor_style( 'uamswp-syndication-dining-style-admin', plugins_url( '/css/uamswp-syndication-dining.css', __DIR__ ), array(), '' );
	}
	/**
	 * Build Shortcode-UI
	 */
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
						//'headline'	=> 'Headlines Only',
				        'list'      => 'List',
				        //'excerpts'    => 'Excerpt',
				        //'cards'     => 'Card', // Maybe
				        'full'     => 'Full', // Includes nutrition
				    ),
				'description'  => 'Preferred output format',
				),

				/** Location - ID */
				array(
					'label'        => esc_html__('Location', 'uamswp_dining'),
					'attr'         => 'loc',
					'type'         => 'text',
					'description'  => 'Location ID - Default is main cafeteria',
				),

				/** Category - ID */
				array(
				'label'        => esc_html__('Category', 'uamswp_dining'),
				'attr'         => 'cat',
				'type'         => 'number',
				'description'  => 'Category to display',
				// 'meta'   => array(
				// 		'placeholder' 	=> esc_html__( '1' ),
				// 		'min'			=> '1',
				// 		'step'			=> '1',
				// 	),
				),

				array(
					'label'        => esc_html__('Show title of category', 'uamswp_dining'),
					'attr'         => 'title',
					'type'         => 'checkbox',
					'description'  => 'Show title of category on lists',
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
	 * [uamswp_dining loc=3 cat=7 type="list"]
	 * 
	 * @param array $atts
	 * 
	 * @return string
	 */
    public function display_shortcode( $atts ) {
		
		$attributes = (object) $atts;

		$location = 1;
		if (isset($attributes->loc)){
			$location = $attributes->loc;
		}

		$category = '';
		if (isset($attributes->cat)){
			$category = $attributes->cat;
		}

		$type = 'list';
		if (isset($attributes->type)){
			$type = $attributes->type;
		}
		
		$showtitle = '';
		if (isset($attributes->title)){
			$showtitle = $attributes->title;
		}

		// ...attempt to make a response to dining. Note that you should replace your username here!
		if ( null == ( $json_response = $this->get_dining_request( $location ) ) ) {

			// ...display a message that the request failed
			$html = '
			<div id="dining-content">';
			$html .= '<!-- uamswp_dining ERROR - an empty host was supplied -->';
			$html .= '</div>
			<!-- /#dining-content -->';

			// ...otherwise, read the information provided by dining
		} else {

			$html = '<div id="dining-content">';
			$curentCat = '';
			$dining_length = count($json_response);
			$i = 1;
			foreach($json_response as $item){

				$FoodID = $item["FoodID"] ? 'ID: ' . $item["FoodID"] : '';
				$Food = $item["Food"] ? $item["Food"] : '';
				$CategoryID = $item["CategoryID"] ? $item["CategoryID"] : '';
				$CategoryName = $item["Category"] ? $item["Category"] : '';
				$HeartHealthy = $item["HeartHealthy"] == "True"  ? ' <span class="uams-icon-heart" style="color:red;" rel="tooltip" title="Heart Healthy"></span>': '';
				$Vegetarian = $item["Vegetarian"] == "True"  ? ' <span class="uams-icon-leaves10" style="color:green;" rel="tooltip" title="Vegetarian"></span>': '';
				$Spicy = $item["Spicy"] == "True" ? ' <span class="uams-icon-fire" style="color:red;" rel="tooltip" title="Spicy"></span>' : '';
				$GlutenFree = $item["GlutenFree"] == "True"  ? ' <span class="uams-icon-glutenfree" style="color:peru;" rel="tooltip" title="Gluten Friendly"></span>' : '';
				$Nut = $item["Nut"] == "True"  ? ' <span class="uams-icon-peanut" style="color:darkgoldenrod;" rel="tooltip" title="Nut Allergy"></span>' : '';
				$Soy = $item["Soy"] == "True"  ? ' <span class="uams-icon-peas" style="color:green;" rel="tooltip" title="Soy Allergy"></span>' : '';
				$Dairy = $item["Dairy"] == "True"  ? ' <span class="uams-icon-fresh7" rel="tooltip" title="Dairy Allergy"></span>': '';
				$Seafood = $item["Seafood"] == "True"  ? ' <span class="uams-icon-fish51" style="color:DarkOliveGreen;" rel="tooltip" title="Seafood"></span>' : '';
				$PortionSize = $item["PortionSize"] ? $item["PortionSize"] : 'N/A';
				$Grams = $item["Grams"] ? ' (' . $item["Grams"] . 'g)' : '';
				$Calories = $item["Calories"] ? $item["Calories"] : 'N/A';
				$Fat = $item["Fat"] ? $item["Fat"] . 'g' : 'N/A';
				$Cholesterol = $item["Cholesterol"] ? $item["Cholesterol"] . 'mg' : 'N/A';
				$Sodium = $item["Sodium"] ? $item["Sodium"] . 'mg' : 'N/A';
				$Carbs = $item["Carbs"] ? $item["Carbs"] . 'g' : 'N/A';
				$Fiber = $item["Fiber"] ? $item["Fiber"] . 'g' : 'N/A';
				$Protein = $item["Protein"] ? $item["Protein"] . 'g' : 'N/A';
				$Potassium = $item["Potassium"] ? $item["Potassium"] . 'mg' : 'N/A';

				
				if($FoodID && 'list' == $type)	 {
					if($category == $CategoryID || empty($category)){
						if ( $curentCat != $CategoryID && (empty($category) || $showtitle ) ) {
							$html .= '<h4 id="categoryid-'.$CategoryID.'">' . $CategoryName . "</h4>";
						}
						$html .= $Food;
						$html .= $HeartHealthy;
						$html .= $Vegetarian;
						$html .= $Spicy;
						$html .= $GlutenFree;
						$html .= $Nut;
						$html .= $Soy;
						$html .= $Dairy;
						$html .= $Seafood . "<br/>";
						$dining_count += 1;
					} elseif ($i==$dining_length-1 && $dining_count == 0) { //Reached end & no items matched
						$html .= "No items match";
					}
				} elseif ($FoodID && 'full' == $type) {
					if($category == $CategoryID || empty($category)){
						if ($curentCat != $CategoryID && (empty($category) || $showtitle ) ) {
							$html .= '<h4>' . $CategoryName . "</h4>";
						}
						$html .= $Food;
						$html .= $HeartHealthy;
						$html .= $Vegetarian;
						$html .= $Spicy;
						$html .= $GlutenFree;
						$html .= $Nut;
						$html .= $Soy;
						$html .= $Dairy;
						$html .= $Seafood . "<br/>";
						$html .= $PortionSize;
						$html .= $Grams . "<br/>";
						$html .= $Calories . "<br/>";
						$html .= $Fat . "<br/>";
						$html .= $Cholesterol  . "<br/>";
						$html .= $Sodium . "<br/>";
						$html .= $Carbs . "<br/>";
						$html .= $Fiber . "<br/>";
						$html .= $Protein . "<br/>";
						$html .= $Potassium . "<br/>";
						$dining_count = 1;
					} elseif ($i==$dining_length-1 && $dining_count == 0) { //Reached end & no items matched
						$html .= "No items match";
					}
				}
				$curentCat = $CategoryID;
				$i++;
			} // end foreach
			$html .= '</div>
					<!-- /#dining-content -->';
		} // end if/else

		//$content .= $html;
 
        return $html;
 
    } // end display_shortcode
 
    /**
     * Attempts to request the locations JSON feed from dining
     *
     * @access public
     * @param  $location   The location for the dining JSON feed we're attempting to retrieve
     * @return $request    The user's JSON feed or null of the request failed
     */
    private function get_dining_request( $location ) {

		$url = 'http://www.uams.edu/nutrition/menu/new_menu_json.asp?mLoc=' . $location;
		$cache_key = 'dining_' . $location;
		$request = get_transient( $cache_key );

		if ( false === $request ) {
			$request = json_decode(wp_remote_retrieve_body( wp_remote_get( $url ) ), true);
	
			if ( is_wp_error( $request ) ) {
				// Cache failures for a short time, will speed up page rendering in the event of remote failure.
				set_transient( $cache_key, $request, MINUTE_IN_SECONDS * 15 );
			} else {
				// Success, cache for a longer time.
				set_transient( $cache_key, $request, HOUR_IN_SECONDS );
			}
		}
		return $request;
 
    } // end get_dining_request
 
    /**
     * Retrieves the number of followers from the JSON feed
     *
     * @access private
     * @param  $json     The user's JSON feed
     * @return           The number of followers for the user. -1 if the JSON data isn't properly set.
     */
    // private function get_follower_count( $json ) {
    //     return ( -1 < $json->followers_count ) ? $json->followers_count : -1;
    // } // end get_follower_count
 
    /**
     * Retrieves the last tweet from the user's JSON feed
     *
     * @access private
     * @param  $json     The user's JSON feed
     * @return           The last tweet from the user's feed. '[ No tweet found. ]' if the data isn't properly set.
     */
    // private function get_last_tweet( $json ) {
    //     return ( 0 < strlen( $json->status->text ) ) ? $json->status->text : '[ No tweet found. ]';
    // } // end get_last_tweet
	
}
UAMS_Syndicate_Shortcode_Dining::get_instance();