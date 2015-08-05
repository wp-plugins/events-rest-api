<?php
if ( !defined( 'EVENT_ESPRESSO_VERSION' ) ) {
	exit( 'No direct script access allowed' );
}

/**
 *
 * EE_REST_API_Controller_Model_Base
 *
 * Base controller which also has something to do with models
 *
 * @package			Event Espresso
 * @subpackage
 * @author				Mike Nelson
 *
 */
class EE_REST_API_Controller_Model_Base extends EE_REST_API_Controller_Base {
	/**
	 * Holds reference to the model versio info, which knows the requested version
	 * @var EE_REST_API_Model_Version_Info
	 */
	protected $_model_version_info;

/**
	 * Sets the version the user requested
	 * @param string $version eg '4.8'
	 */
	public function set_requested_version( $version ) {
		parent::set_requested_version( $version );
		$this->_model_version_info = new EE_REST_API_Model_Version_Info( $version );
	}

	/**
	 * Gets the object that should be used for getting any info from the models,
	 * because it's takes the requested and current core version into account
	 * @return EE_REST_API_Model_Version_Info
	 */
	public function get_model_version_info(){
		if( ! $this->_model_version_info ) {
			throw new EE_Error( sprintf( __( 'Cannot use model version info before setting the requested version in the controller', 'event_espresso' ) ) );
		}
		return $this->_model_version_info;
	}

	/**
	 * Determines if $object is of one of the classes of $classes. Similar to
	 * in_array(), except this checks if $object is a subclass of the classnames provided
	 * in $classnames
	 * @param type $object
	 * @param type $classnames
	 * @return boolean
	 */
	public function is_subclass_of_one( $object, $classnames ) {
		foreach( $classnames as $classname ) {
			if( is_a( $object, $classname ) ) {
				return true;
			}
		}
		return false;
	}

}

// End of file EE_REST_API_Controller_Model_Base.class.php