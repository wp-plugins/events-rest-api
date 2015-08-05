<?php
if ( !defined( 'EVENT_ESPRESSO_VERSION' ) ) {
	exit( 'No direct script access allowed' );
}

/**
 *
 * EE_REST_API_Controller_Model_Read
 *
 * Handles requests relating to meta info
 *
 * @package			Event Espresso
 * @subpackage
 * @author				Mike Nelson
 *
 */
class EE_REST_API_Controller_Model_Meta extends EE_REST_API_Controller_Model_Base {


	public static function handle_request_models_meta( $_path ) {
		$controller = new EE_REST_API_Controller_Model_Meta();
		try{
			$regex = '~' . EED_REST_API::ee_api_namespace_for_regex . 'resources~';
			$success = preg_match( $regex, $_path, $matches );
			if ( $success && is_array( $matches ) && isset( $matches[ 1 ] )) {
				$controller->set_requested_version( $matches[ 1 ] );
				return $controller->send_response( $controller->_get_models_metadata_entity() );
			} else {
				return $controller->send_response( new WP_Error( 'endpoint_parsing_error', __( 'We could not parse the URL. Please contact event espresso support', 'event_espresso' ) ) );
			}
		}catch( EE_Error $e ){
			return $controller->send_response( new WP_Error( 'ee_exception', $e->getMessage() . ( defined('WP_DEBUG') && WP_DEBUG ? $e->getTraceAsString() : '' ) ) );
		}

	}

	/*
	 * Gets the model metadata resource entity
	 * @return array for JSON response, describing all the models available in teh requested version
	 */
	protected function _get_models_metadata_entity(){
		$response = array();
		foreach( $this->get_model_version_info()->models_for_requested_version() as $model_name => $model_classname ){
			$model = $this->get_model_version_info()->load_model( $model_name );
			$fields_json = array();
			foreach( $this->get_model_version_info()->fields_on_model_in_this_version( $model ) as $field_name => $field_obj ) {

				if( $field_obj instanceof EE_Boolean_Field ) {
					$datatype = 'Boolean';
				}elseif( $field_obj->get_wpdb_data_type() == '%d' ) {
					$datatype = 'Number';
				}elseif( $field_name instanceof EE_Serialized_Text_Field ) {
					$datatype = 'Object';
				}else{
					$datatype = 'String';
				}
				$field_json = array(
					'name' => $field_name,
					'nicename' => $field_obj->get_nicename(),
					'raw' => true,
					'type' => str_replace('EE_', '', get_class( $field_obj ) ),
					'datatype' => $datatype,
					'nullable' => $field_obj->is_nullable(),
					'default' => $field_obj->get_default_value() === INF ? EE_INF_IN_DB : $field_obj->get_default_value(),
					'table_alias' => $field_obj->get_table_alias(),
					'table_column' => $field_obj->get_table_column(),
					'always_available' => true
				);
				if( $this->get_model_version_info()->field_is_ignored( $field_obj ) ) {
					continue;
				}
				if( $this->get_model_version_info()->field_is_raw( $field_obj ) ) {
					$raw_field_json = $field_json;
					//specify that the non-raw version isn't queryable or editable
					$field_json[ 'raw' ] = false;
					$field_json[ 'always_available' ] = false;

					//change the name of the 'raw' version
					$raw_field_json[ 'name' ] = $field_json[ 'name' ] . '_raw';
					$raw_field_json[ 'nicename' ] = sprintf( __( '%1$s (%2$s)', 'event_espresso'), $field_json[ 'nicename' ], 'raw' );
					$fields_json[ $raw_field_json[ 'name' ] ] = $raw_field_json;
				}
				if( $this->get_model_version_info()->field_is_pretty( $field_obj ) ) {
					$pretty_field_json = $field_json;
					//specify that the non-raw version isn't queryable or editable
					$pretty_field_json[ 'raw' ] = false;

					//change the name of the 'raw' version
					$pretty_field_json[ 'name' ] = $field_json[ 'name' ] . '_pretty';
					$pretty_field_json[ 'nicename' ] = sprintf( __( '%1$s (%2$s)', 'event_espresso'), $field_json[ 'nicename' ], 'pretty' );
					$fields_json[ $pretty_field_json[ 'name' ] ] = $pretty_field_json;
				}
				$fields_json[ $field_json[ 'name' ] ] = $field_json;

			}
			$fields_json = array_merge( $fields_json, $this->get_model_version_info()->extra_resource_properties_for_model( $model ) );
			$response[ $model_name ]['fields'] = apply_filters( 'FHEE__EE_REST_API_Controller_Model_Meta__handle_request_models_meta__fields', $fields_json, $model );
			$relations_json = array();
			foreach( $model->relation_settings()  as $relation_name => $relation_obj ) {
				$relation_json = array(
					'name' => $relation_name,
					'type' => str_replace( 'EE_', '', get_class( $relation_obj ) )
				);
				$relations_json[ $relation_name ] = $relation_json;
			}
			$response[ $model_name ][ 'relations' ] = apply_filters( 'FHEE__EE_REST_API_Controller_Model_Meta__handle_request_models_meta__relations', $relations_json, $model );
		}
		return $response;
	}

	public static function filter_ee_metadata_into_index( $existing_index_info ) {
		$addons = array();
		foreach( EE_Registry::instance()->addons as $addon){
			$addon_json = array(
				'name' => $addon->name(),
				'version' => $addon->version()
			);
			$addons[ $addon_json[ 'name' ] ] = $addon_json;
		}
		$existing_index_info[ 'ee' ] = array(
			'version' => EEM_System_Status::instance()->get_ee_version(),
			'addons' => $addons,
			'maintenance_mode' => EE_Maintenance_Mode::instance()->real_level(),
			'served_core_versions' => array_keys( EED_REST_API::versions_served() )
		);
		return $existing_index_info;
	}
}


// End of file EE_REST_API_Controller_Model_Read.class.php