<?php

class xrowworkflowhandler extends eZContentObjectEditHandler
{
	/*
    static function storeActionList()
    {
        return array( 'WorkflowSet' );
    }
    */
   function validateInput( $http, &$module, &$class, $object, &$version, $contentObjectAttributes, $editVersion, $editLanguage, $fromLanguage, $validationParameters )
    {
    	//Check for states
        $result = array( 'is_valid' => true, 'warnings' => array() );
 
        $now = new DateTime();
        $start = self::getDate( $http->postVariable( 'workflow-start' ) );
        $end = self::getDate( $http->postVariable( 'workflow-end' ) );
 		
        if ( $start < $now )
        {
        	$result['warnings'][] = array( 'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Select a publication date in the future.' ) );
        }
        if ( $end < $now )
        {
        	$result['warnings'][] = array( 'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Select a expiry date in the future.' ) );
        }
        if ( $end < $start )
        {
        	$result['warnings'][] = array( 'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Select a expiry date newer then the publication date.' ) );
        }
        return $result;
    }
    function fetchInput( $http, &$module, &$class, $object, &$version, $contentObjectAttributes, $editVersion, $editLanguage, $fromLanguage )
    {
    	$start = self::getDate( $http->postVariable( 'workflow-start' ) );
        $end = self::getDate( $http->postVariable( 'workflow-end' ) );
        
    	$row = array( 'contentobject_id' => $object->ID );
    	if( $start )
    	{
    		$row['start'] = $start->getTimestamp();
    	}
        if( $end )
    	{
    		$row['end'] = $end->getTimestamp();
    	}
    	$obj = new xrowworkflow( $row );
    	$obj->store();
    }

    function publish( $contentObjectID, $version )
    {
        $workflow = xrowworkflow::fetchByContentObjectID( $contentObjectID );  
        if( $workflow instanceof xrowworkflow )
        {
        	$workflow->check();
        }
    }
    /*
     * @return Datetime
     */
    function getDate( $args )
    {
    	if ( empty( $args['date'] ) )
    	{
    		return false;
    	}
    	$date = new DateTime( $args['date'] );
        $date->setTime ( $args['hour'], $args['minute']);#
        return $date;
    }
}
