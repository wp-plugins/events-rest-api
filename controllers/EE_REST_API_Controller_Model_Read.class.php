<?php
if ( !defined( 'EVENT_ESPRESSO_VERSION' ) ) {
	exit( 'No direct script access allowed' );
}

/**
 *
 * EE_REST_API_Controller_Model_Read
 *
 * Handles requests relating to GETting model information
 *
 * @package			Event Espresso
 * @subpackage
 * @author				Mike Nelson
 *
 */
class EE_REST_API_Controller_Model_Read extends EE_REST_API_Controller_Model_Base {





	public function __construct() {
		parent::__construct();
		EE_Registry::instance()->load_helper( 'Inflector' );
	}
	/**
	 * Handles requests to get all (or a filtered subset) of entities for a particular model
	 * @param string $_path
	 * @param array $filter The query parameters to be passed onto the EE models system.
	 * Using syntax like "/wp-json/ee4/v2/events?filter[where][EVT_name][]=like&filter[where][EVT_name][]=%25monkey%25 to create a query params array like "array(array('EVT_name' => array('LIKE','%monkey%'))", which
	 * will create SQL like "WHERE EVT_name LIKE '%monkey%'"
	 * @param string $include string indicating which fields to include in the response, including fields
	 * on related entities. Eg, when querying for events, an include string like
	 * "...&include=EVT_name,EVT_desc,Datetime, Datetime.Ticket.TKT_ID, Datetime.Ticket.TKT_name, Datetime.Ticket.TKT_price" instructs us to only include the event's name and description, each related datetime, and
	 * each related datetime's ticket's name and price. Eg json would be:
	 * '{"EVT_ID":12,"EVT_name":"star wars party","EVT_desc":"so cool...","datetimes":[{"DTT_ID":123,...,
	 * "tickets":[{"TKT_ID":234,"TKT_name":"student rate","TKT_price":32.0},...]}]}', ie, events with all
	 * their associated datetimes (including ones that are trashed) embedded in the json object, and each
	 * datetime also has each associated ticket embedded in its json object.
	 * @return WP_JSON_Response|WP_Error
	 */
	public static function handle_request_get_all( $_path, $filter = array(), $include = '*' ) {
		$controller = new EE_REST_API_Controller_Model_Read();
		try{
			$regex = '~' . EED_REST_API::ee_api_namespace_for_regex . '(.*)~';
			$success = preg_match( $regex, $_path, $matches );
			if ( is_array( $matches ) && isset( $matches[ 1 ] ) && isset( $matches[ 2 ] ) ) {
				$requested_version = $matches[ 1 ];
				$controller->set_requested_version( $requested_version );
				$model_name_plural = $matches[ 2 ];
				$model_name_singular = EEH_Inflector::singularize_and_upper( $model_name_plural );
				if ( ! $controller->get_model_version_info()->is_model_name_in_this_verison( $model_name_singular ) ) {
					return $controller->send_response( new WP_Error( 'endpoint_parsing_error', sprintf( __( 'There is no model for endpoint %s. Please contact event espresso support', 'event_espresso' ), $model_name_singular ) ) );
				}
				return $controller->send_response(
						$controller->get_entities_from_model(
								$controller->get_model_version_info()->load_model( $model_name_singular ),
								$filter,
								$include ) );
			} else {
				return $controller->send_response( new WP_Error( 'endpoint_parsing_error', __( 'We could not parse the URL. Please contact event espresso support', 'event_espresso' ) ) );
			}
		}catch( EE_Error $e ){
			return $controller->send_response( new WP_Error( 'ee_exception', $e->getMessage() . ( defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : '' ) ) );
		}
	}

	/**
	 * Gets a single entity related to the model indicated in the path and its id
	 * @param string $_path
	 * @param string $id ID of the thing to be retrieved
	 * @param string $include @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string $filter @see handle_request_get_all, for now only the 'caps' item is used
	 * @return WP_JSON_Response|WP_Error
	 */
	public static function handle_request_get_one( $_path, $id, $include = '*', $filter = array() ) {
		$controller = new EE_REST_API_Controller_Model_Read();
		try{
			$regex = '~' . EED_REST_API::ee_api_namespace_for_regex . '(.*)/(.*)~';
			$success = preg_match( $regex, $_path, $matches );
			if ( $success && is_array( $matches ) && isset( $matches[ 1 ] ) && isset( $matches[ 2 ] ) ) {
				$requested_version = $matches[ 1 ];
				$controller->set_requested_version( $requested_version );
				$model_name_plural = $matches[ 2 ];
				$model_name_singular = EEH_Inflector::singularize_and_upper( $model_name_plural );
				if ( ! $controller->get_model_version_info()->is_model_name_in_this_verison( $model_name_singular ) ) {
					return $controller->send_response( new WP_Error( 'endpoint_parsing_error', sprintf( __( 'There is no model for endpoint %s. Please contact event espresso support', 'event_espresso' ), $model_name_singular ) ) );
				}
				return $controller->send_response(
						$controller->get_entity_from_model(
								$controller->get_model_version_info()->load_model( $model_name_singular ),
								$id,
								$include,
								$controller->validate_context( isset( $filter[ 'caps' ] ) ? $filter[ 'caps' ] : EEM_Base::caps_read ) ) );
			}else{
				return $controller->send_response( new WP_Error( 'endpoint_parsing_error', __( 'We could not parse the URL. Please contact event espresso support', 'event_espresso' ) ) );
			}
		}catch( EE_Error $e ){
			return $controller->send_response( new WP_Error( 'ee_exception', $e->getMessage() . ( defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : '' ) ) );
		}
	}

	/**
	 *
	 * Gets all the related entities (or if its a belongs-to relation just the one)
	 * to the item with the given id
	 * @param string $_path
	 * @param string $id
	 * @param array $filter @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string $include @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @return WP_JSON_Response|WP_Error
	 */
	public static function handle_request_get_related( $_path, $id, $filter = array(), $include = '*' ) {
		$controller = new EE_REST_API_Controller_Model_Read();
		try{
			$regex = '~' . EED_REST_API::ee_api_namespace_for_regex . '(.*)/(.*)/(.*)~';
			$success = preg_match( $regex, $_path, $matches );
			if ( is_array( $matches ) && isset( $matches[ 1 ] ) && isset( $matches[ 2 ] ) &&  isset( $matches[ 4 ] ) ) {
				$requested_version = $matches[ 1 ];
				$controller->set_requested_version( $requested_version );
				$main_model_name_plural = $matches[ 2 ];
				$main_model_name_singular = EEH_Inflector::singularize_and_upper( $main_model_name_plural );
				if ( ! $controller->get_model_version_info()->is_model_name_in_this_verison( $main_model_name_singular ) ) {
					return $controller->send_response( new WP_Error( 'endpoint_parsing_error', sprintf( __( 'There is no model for endpoint %s. Please contact event espresso support', 'event_espresso' ), $main_model_name_singular ) ) );
				}
				$main_model = $controller->get_model_version_info()->load_model( $main_model_name_singular );
				$related_model_name_maybe_plural = $matches[ 4 ];
				$related_model_name_singular = EEH_Inflector::singularize_and_upper( $related_model_name_maybe_plural );
				if ( ! $controller->get_model_version_info()->is_model_name_in_this_verison( $related_model_name_singular ) ) {
					return $controller->send_response( new WP_Error( 'endpoint_parsing_error', sprintf( __( 'There is no model for endpoint %s. Please contact event espresso support', 'event_espresso' ), $related_model_name_singular ) ) );
				}

				return $controller->send_response(
						$controller->get_entities_from_relation(
								$id,
								$main_model->related_settings_for( $related_model_name_singular ) ,
								$filter,
								$include ) );
			}
		}catch( EE_Error $e ){
			return $controller->send_response( new WP_Error( 'ee_exception', $e->getMessage() . ( defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : '' ) ) );
		}
	}



	/**
	 * Gets a collection for the given model and filters
	 * @param EEM_Base $model
	 * @param array $filter @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string $include @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @return array
	 */
	public function get_entities_from_model( $model, $filter, $include ) {
		$query_params = $this->create_model_query_params( $model, $filter );
		if( ! EE_REST_API_Capabilities::current_user_has_partial_access_to( $model, $query_params[ 'caps' ] ) ) {
			$model_name_plural = EEH_Inflector::pluralize_and_lower( $model->get_this_model_name() );
			return new WP_Error( sprintf( 'json_%s_cannot_list', $model_name_plural), sprintf( __( 'Sorry, you are not allowed to list %s. Missing permissions: %s' ), $model_name_plural, EE_REST_API_Capabilities::get_missing_permissions_string( $model,  $query_params[ 'caps' ] ) ), array( 'status' => 403 ) );
		}

		$this->_set_debug_info( 'model query params', $query_params );
		$results = $model->get_all_wpdb_results( $query_params );
		$nice_results = array( );
		foreach ( $results as $result ) {
			$nice_results[ ] = $this->create_entity_from_wpdb_result( $model, $result, $include,  $query_params[ 'caps' ] );
		}
		return $nice_results;
	}

	/**
	 * Gets the coollection for given relation object
	 *
	 * The same as EE_REST_API_Controller_Model_Read::get_entities_from_model(), except if the relation
	 * is a HABTM relation, in which case it merges any non-foreign-key fields from
	 * the join-model-object into the results
	 * @param string $id the ID of the thing we are fetching related stuff from
	 * @param EE_Model_Relation_Base $relation
	 * @param array $filter @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string $include @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @return array
	 */
	public function get_entities_from_relation( $id,  $relation, $filter, $include ) {
		$context = $this->validate_context( isset( $filter[ 'caps' ] ) ? $filter[ 'caps' ] : EEM_Base::caps_read);
		$model = $relation->get_this_model();
		$related_model = $relation->get_other_model();
		//check if they can access the 1st model object
		$query_params = array( array( $model->primary_key_name() => $id ),'limit' => 1 );
		if( $model instanceof EEM_Soft_Delete_Base ){
			$query_params = $model->alter_query_params_so_deleted_and_undeleted_items_included($query_params);
		}
		$restricted_query_params = $query_params;
		$restricted_query_params[ 'caps' ] = $context;
		$this->_set_debug_info( 'main model query params', $restricted_query_params );
		$this->_set_debug_info( 'missing caps', EE_REST_API_Capabilities::get_missing_permissions_string( $related_model, $context ) );

		if( ! ( EE_REST_API_Capabilities::current_user_has_partial_access_to( $related_model, $context ) &&
				$model->exists( $restricted_query_params ) ) ){
			if( $relation instanceof EE_Belongs_To_Relation ) {
				$related_model_name_maybe_plural = strtolower( $related_model->get_this_model_name() );
			}else{
				$related_model_name_maybe_plural = EEH_Inflector::pluralize_and_lower( $related_model->get_this_model_name() );
			}
			return new WP_Error( sprintf( 'json_%s_cannot_list', $related_model_name_maybe_plural ), sprintf( __( 'Sorry, you are not allowed to list %s related to %s. Missing permissions: %s' ), $related_model_name_maybe_plural, $related_model->get_this_model_name(), implode(',', array_keys( EE_REST_API_Capabilities::get_missing_permissions( $related_model, $context ) ) )  ), array( 'status' => 403 ) );
		}
		$query_params = $this->create_model_query_params( $relation->get_other_model(), $filter, $context );
		$this->_set_debug_info( 'model query params', $query_params );
		$query_params[0][ $relation->get_this_model()->get_this_model_name() . '.' . $relation->get_this_model()->primary_key_name() ] = $id;
		$query_params[ 'default_where_conditions' ] = 'none';
		$results = $relation->get_other_model()->get_all_wpdb_results( $query_params );
		$nice_results = array();
		foreach( $results as $result ) {
			$nice_result = $this->create_entity_from_wpdb_result( $relation->get_other_model(), $result, $include, $query_params[ 'caps' ] );
			if( $relation instanceof EE_HABTM_Relation ) {
				//put the unusual stuff (properties from the HABTM relation) first, and make sure
				//if there are conflicts we prefer the properties from the main model
				$join_model_result = $this->create_entity_from_wpdb_result( $relation->get_join_model(), $result, $include, $query_params[ 'caps' ] );
				$joined_result = array_merge( $nice_result, $join_model_result );
				//but keep the meta stuff from the main model
				if( isset( $nice_result['meta'] ) ){
					$joined_result['meta'] = $nice_result['meta'];
				}
				$nice_result = $joined_result;
			}
			$nice_results[] = $nice_result;
		}
		if( $relation instanceof EE_Belongs_To_Relation ){
			return array_shift( $nice_results );
		}else{
			return $nice_results;
		}
	}



	/**
	 * Changes database results into REST API entities
	 * @param EEM_Base $model
	 * @param array $db_row like results from $wpdb->get_results()
	 * @param string $include @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string $context one of the return values from EEM_Base::valid_cap_contexts()
	 * @return array ready for being converted into json for sending to client
	 */
	public function create_entity_from_wpdb_result( $model, $db_row, $include, $context ) {
		$result = $model->deduce_fields_n_values_from_cols_n_values( $db_row );
		$result = array_intersect_key( $result, $this->get_model_version_info()->fields_on_model_in_this_version( $model ) );
		foreach( $result as $field_name => $raw_field_value ) {
			$field_obj = $model->field_settings_for($field_name);
			$field_value = $field_obj->prepare_for_set_from_db( $raw_field_value );
			if( $this->is_subclass_of_one(  $field_obj, $this->get_model_version_info()->fields_ignored() ) ){
				unset( $result[ $field_name ] );
			}elseif( $this->is_subclass_of_one(  $field_obj, $this->get_model_version_info()->fields_raw() ) ){
				$result[ $field_name . '_raw' ] = $field_obj->prepare_for_get( $field_value );
				$result[ $field_name ] = $field_obj->prepare_for_pretty_echoing( $field_value );
			}elseif( $this->is_subclass_of_one( $field_obj, $this->get_model_version_info()->fields_pretty() ) ){
				$result[ $field_name ] = $field_obj->prepare_for_get( $field_value );
				$result[ $field_name . '_pretty' ] = $field_obj->prepare_for_pretty_echoing( $field_value );
			}elseif( $field_obj instanceof EE_Datetime_Field ){
				$result[ $field_name ] = json_mysql_to_rfc3339( $raw_field_value );
			}else{
				$value_prepared = $field_obj->prepare_for_get( $field_value );

				$result[ $field_name ] = $value_prepared === INF ? EE_INF_IN_DB : $value_prepared;
			}
		}
		if( $model instanceof EEM_CPT_Base ) {
			$attachment = wp_get_attachment_image_src( get_post_thumbnail_id( $db_row[ $model->get_primary_key_field()->get_qualified_column() ] ), 'full' );
			$result[ 'featured_image_url' ] = !empty( $attachment ) ? $attachment[ 0 ] : null;
			$result[ 'link' ] = get_permalink( $db_row[ $model->get_primary_key_field()->get_qualified_column() ] );
		}
		//add links to related data
		$result['meta']['links'] = array(
			'self' => $this->get_versioned_link_to( EEH_Inflector::pluralize_and_lower( $model->get_this_model_name() ) . '/' . $result[ $model->primary_key_name() ]
		) );

		if( $model instanceof EEM_CPT_Base ) {
			$result[ 'meta' ][ 'links' ][ 'self_wp_post' ] =  json_url( '/posts/' . $db_row[ $model->get_primary_key_field()->get_qualified_column() ] );
		}

		//filter fields if specified
		$includes_for_this_model = $this->extract_includes_for_this_model( $include );
		if( ! empty( $includes_for_this_model ) ) {
			if( $model->has_primary_key_field() ) {
				//always include the primary key
				$includes_for_this_model[] = $model->primary_key_name();
			}
			$result = array_intersect_key( $result, array_flip( $includes_for_this_model ) );
		}
		//add meta links and possibly include related models
		foreach( apply_filters( 'FHEE__EE_REST_API_Controller_Model_Read__create_entity_from_wpdb_result__related_models_to_include', $model->relation_settings() ) as $relation_name => $relation_obj ) {
			$related_model_part = $this->get_related_entity_name( $relation_name, $relation_obj );
			if( empty( $includes_for_this_model ) || isset( $includes_for_this_model['meta'] ) ) {
				$result['meta']['links'][$related_model_part] = $this->get_versioned_link_to( EEH_Inflector::pluralize_and_lower( $model->get_this_model_name() ) . '/' . $result[ $model->primary_key_name() ] . '/' . $related_model_part );
			}
			$related_fields_to_include = $this->extract_includes_for_this_model( $include, $relation_name );
			if( $related_fields_to_include ) {
				 $related_results = $this->get_entities_from_relation( $result[ $model->primary_key_name() ], $relation_obj, array('caps' => $context ), implode(',',$this->extract_includes_for_this_model( $include, $relation_name ) )  );
				 $result[ $related_model_part ] = $related_results instanceof WP_Error ? null : $related_results;
			}
		}
		$result = apply_filters( 'FHEE__EE_REST_API_Controller_Model_Read__create_entity_from_wpdb_results__entity_before_innaccessible_field_removal', $result, $model, $context );
		$result_without_inaccessible_fields = EE_REST_API_Capabilities::filter_out_inaccessible_entity_fields( $result, $model, $context );
		$this->_set_debug_info( 'inaccessible fields', array_keys( array_diff_key( $result, $result_without_inaccessible_fields ) ) );
		return apply_filters( 'FHEE__EE_REST_API_Controller_Model_Read__create_entity_from_wpdb_results__entity_return', $result_without_inaccessible_fields, $model, $context );
	}

	/**
	 * Gets the full URL to the resource, taking the requested version into account
	 * @param string $link_part_after_version_and_slash eg "events/10/datetimes"
	 * @return string url eg "http://mysite.com/wp-json/ee/v4.6/events/10/datetimes"
	 */
	public function get_versioned_link_to( $link_part_after_version_and_slash ) {
		return json_url( EED_REST_API::ee_api_namespace . $this->get_model_version_info()->requested_version() . '/' . $link_part_after_version_and_slash );
	}

	/**
	 * Gets the correct lowercase name for the relation in the API according
	 * to the relation's type
	 * @param string $relation_name
	 * @param EE_Model_Relation_Base $relation_obj
	 * @return string
	 */
	public static function get_related_entity_name( $relation_name, $relation_obj ){
		if( $relation_obj instanceof EE_Belongs_To_Relation ) {
			return strtolower( $relation_name );
		}else{
			return EEH_Inflector::pluralize_and_lower( $relation_name );
		}
	}

//	public function


	/**
	 * Gets the one model object with the specified id for the specified model
	 * @param EEM_Base $model
	 * @param string $id ID of the entity we want to retrieve
	 * @param string $include @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string string one of the return values from EEM_Base::valid_cap_contexts()
	 * @return array
	 */
	public function get_entity_from_model( $model, $id, $include, $context ) {
		$query_params = array( array( $model->primary_key_name() => $id ),'limit' => 1);
		if( $model instanceof EEM_Soft_Delete_Base ){
			$query_params = $model->alter_query_params_so_deleted_and_undeleted_items_included($query_params);
		}
		$restricted_query_params = $query_params;
		$restricted_query_params[ 'caps' ] =  $context;
		$this->_set_debug_info( 'model query params', $restricted_query_params );
		$model_rows = $model->get_all_wpdb_results( $restricted_query_params );
		if ( ! empty ( $model_rows ) ) {
			return $this->create_entity_from_wpdb_result( $model, array_shift( $model_rows ), $include, $context );
		} else {
			//ok let's test to see if we WOULD have found it, had we not had restrictions from missing capabilities
			$lowercase_model_name = strtolower( $model->get_this_model_name() );
			$model_rows_found_sans_restrictions = $model->get_all_wpdb_results( $query_params );
			if( ! empty( $model_rows_found_sans_restrictions ) ) {
				//you got shafted- it existed but we didn't want to tell you!
				return new WP_Error( 'json_user_cannot_read', sprintf( __( 'Sorry, you cannot read this %1$s. Missing permissions are: %2$s', 'event_espresso' ), strtolower( $model->get_this_model_name() ), EE_REST_API_Capabilities::get_missing_permissions_string( $model, $context ) ), array( 'status' => 403 ) );
			}else{
				//it's not you. It just doesn't exist
				return new WP_Error( sprintf( 'json_%s_invalid_id', $lowercase_model_name ), sprintf( __( 'Invalid %s ID.', 'event_espresso' ), $lowercase_model_name ), array( 'status' => 404 ) );
			}
		}
	}

	/**
	 * If a context is provided which isn't valid, maybe it was added in a future
	 * version so just treat it as a default read
	 * @param string $context
	 * @param EEM_Base $model
	 * @return string array key of EEM_Base::cap_contexts_to_cap_action_map()
	 */
	public function validate_context( $context ) {
		$valid_contexts = EEM_Base::valid_cap_contexts();
		if( in_array( $context, $valid_contexts )  ){
			return $context;
		}else{
			return EEM_Base::caps_read;
		}
	}



	/**
	 * Translates API filter get parameter into $query_params array used by EEM_Base::get_all()
	 * @param EEM_Base $model
	 * @param array $filter from $_GET['filter'] parameter @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @return array like what EEM_Base::get_all() expects or FALSE to indicate
	 * that absolutely no results should be returned
	 */
	public function create_model_query_params( $model, $filter ) {
		$model_query_params = array( );
		if ( isset( $filter[ 'where' ] ) ) {
			$model_query_params[ 0 ] = $this->prepare_rest_query_params_key_for_models( $model, $filter[ 'where' ] );
		}
		if ( isset( $filter[ 'order_by' ] ) ) {
			$order_by = $filter[ 'order_by' ];
		} elseif ( isset( $filter[ 'orderby' ] ) ) {
			$order_by = $filter[ 'orderby' ];
		}else{
			$order_by = null;
		}
		if( $order_by !== null ){
			$model_query_params[ 'order_by' ] = is_array( $order_by ) ?
				$this->prepare_rest_query_params_key_for_models( $model, $order_by ) :
				$this->prepare_raw_field_for_use_in_models( $order_by );
		}
		if ( isset( $filter[ 'group_by' ] ) ) {
			$group_by = $filter[ 'group_by' ];
		} elseif ( isset( $filter[ 'groupby' ] ) ) {
			$group_by = $filter[ 'groupby' ];
		}else{
			$group_by = null;
		}
		if( $group_by !== null ){
			if( is_array( $group_by ) ) {
				$group_by = $this->prepare_rest_query_params_values_for_models( $model, $group_by );
			}else{
				$group_by = $this->prepare_raw_field_for_use_in_models( $models, $group_by );
			}
			$model_query_params[ 'group_by' ] = $group_by;
		}
		if ( isset( $filter[ 'having' ] ) ) {
			//@todo: no good for permissions
			$model_query_params[ 'having' ] = $this->prepare_rest_query_params_key_for_models( $model, $filter[ 'having' ] );
		}
		if ( isset( $filter[ 'order' ] ) ) {
			$model_query_params[ 'order' ] = $filter[ 'order' ];
		}
		if ( isset( $filter[ 'mine' ] ) ){
			$model_query_params = $model->alter_query_params_to_only_include_mine( $model_query_params );
		}
		if( isset( $filter[ 'limit' ] ) ) {
			//limit should be either a string like '23' or '23,43', or an array with two items in it
			if( is_string( $filter[ 'limit' ] ) ) {
				$limit_array = explode(',', $filter['limit']);
			}else {
				$limit_array = $filter[ 'limit' ];
			}
			$sanitized_limit = array();
			foreach( $limit_array as $key => $limit_part ) {
				if( $this->_debug_mode && ( ! is_numeric( $limit_part ) || count( $sanitized_limit ) > 2 ) ) {
					throw new EE_Error( sprintf( __( 'An invalid limit filter was provided. It was: %s. If the EE4 JSON REST API weren\'t in debug mode, this message would not appear.', 'event_espresso' ), json_encode( $filter[ 'limit' ] ) ) );
				}
				$sanitized_limit[] = intval( $limit_part );
			}
			$model_query_params[ 'limit' ] = implode( ',', $sanitized_limit );
		}else{
			$model_query_params[ 'limit' ] = 50;
		}
		if( isset( $filter[ 'caps' ] ) ) {
			$model_query_params[ 'caps' ] = $this->validate_context( $filter[ 'caps' ] );
		}else{
			$model_query_params[ 'caps' ] = EEM_Base::caps_read;
		}
		return apply_filters( 'FHEE__EE_REST_API_Controller_Model_Read__create_model_query_params', $model_query_params, $filter, $model );
	}

	/**
	 * Changes the REST-style query params for use in the models
	 * @param EEM_Base $model
	 * @param array $query_params sub-array from @see EEM_Base::get_all()
	 */
	public function prepare_rest_query_params_key_for_models( $model,  $query_params ) {
		$model_ready_query_params = array();
		foreach( $query_params as $key => $value ) {
			$key = $this->prepare_raw_field_for_use_in_models( $key );
			if( is_array( $value ) ) {
				$model_ready_query_params[ $key ] = $this->prepare_rest_query_params_key_for_models( $model, $value );
			}else{
				$model_ready_query_params[ $key ] = $value;
			}
		}
		return $model_ready_query_params;
	}
	public function prepare_rest_query_params_values_for_models( $model,  $query_params ) {
		$model_ready_query_params = array();
		foreach( $query_params as $key => $value ) {
			if( is_array( $value ) ) {
				$model_ready_query_params[ $key ] = $this->prepare_rest_query_params_values_for_models( $model, $value );
			}else{
				$model_ready_query_params[ $key ] = $this->prepare_raw_field_for_use_in_models( $value );;
			}
		}
		return $model_ready_query_params;
	}

	/**
	 * Changes a string like 'Event.EVT_desc_raw*foobar' into
	 * 'Event.EVT_desc*foobar' in order to prepare it for use by EE models
	 * @param type $query_param
	 * @return string
	 */
	public function prepare_raw_field_for_use_in_models( $query_param ) {
		$parts = explode( '*', $query_param );
		$key_sans_star = count( $parts) > 1 ? reset( $parts ) : $query_param;
		$after_star_content = count( $parts ) > 1 ? end( $parts) : '';
		if(  strpos( $key_sans_star, '_raw' ) == strlen( $key_sans_star ) - strlen( '_raw' ) ){
			$key_sans_star = substr( $key_sans_star, 0, strpos( $key_sans_star, '_raw' ) );
		}
		return $after_star_content ? $key_sans_star . '*' . $after_star_content : $key_sans_star;
	}

	/**
	 * Parses the $include_string so we fetch all the field names relating to THIS model
	 * (ie have NO period in them), or for the provided model (ie start with the model
	 * name and then a period).
	 * @param string $include_string @see EE_REST_API_Controller_Model_Read:handle_request_get_all
	 * @param string $model_name
	 * @return array of fields for this model. If $model_name is provided, then
	 * the fields for that model, with the model's name removed from each.
	 */
	public function extract_includes_for_this_model( $include_string, $model_name = null ) {
		if( is_array( $include_string ) ) {
			$include_string = implode( ',', $include_string );
		}
		if( $include_string === '*' ) {
			return array();
		}
		$includes = explode( ',', $include_string );
		$extracted_fields_to_include = array();
		if( $model_name ){
			foreach( $includes as $field_to_include ) {
				$field_to_include = trim( $field_to_include );
				if( strpos( $field_to_include, $model_name . '.' ) === 0 ) {
					//found the model name at the exact start
					$field_sans_model_name = str_replace( $model_name . '.', '', $field_to_include );
					$extracted_fields_to_include[] = $field_sans_model_name;
				}elseif( $field_to_include == $model_name ){
					$extracted_fields_to_include[] = '*';
				}
			}
		}else{
			//look for ones with no period
			foreach( $includes as $field_to_include ) {
				$field_to_include = trim( $field_to_include );
				if( strpos($field_to_include, '.' ) === FALSE && ! $this->get_model_version_info()->is_model_name_in_this_verison( $field_to_include ) ) {
					$extracted_fields_to_include[] = $field_to_include;
				}
			}
		}
		return $extracted_fields_to_include;

	}




	/**
	 * Sets some debug info that we'll send back in headers
	 * @param string $key
	 * @param string|array $info
	 */
	protected function _set_debug_info( $key, $info ){
		$this->_debug_info[ $key ] = $info;
	}
}


// End of file EE_REST_API_Controller_Model_Read.class.php