<?php

class xrowworkflowhandler extends eZContentObjectEditHandler
{
    function validateInput( $http, &$module, &$class, $object, &$version, $contentObjectAttributes, $editVersion, $editLanguage, $fromLanguage, $validationParameters )
    {
        //Check for states
        $result = array( 
            'is_valid' => true , 
            'warnings' => array() 
        );

        $now = new DateTime();
        $start = self::getDate( $http->postVariable( 'workflow-start' ) );
        $end = self::getDate( $http->postVariable( 'workflow-end' ) );

        if ( $start < $now )
        {
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Select a publication date in the future.' ) 
            );
        }
        if ( $end < $now )
        {
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Select a expiry date in the future.' ) 
            );
        }
        if ( $end < $start )
        {
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Select a expiry date newer then the publication date.' ) 
            );
        }

        return $result;
    }

    function fetchInput( $http, &$module, &$class, $object, &$version, $contentObjectAttributes, $editVersion, $editLanguage, $fromLanguage )
    {
        $start = 0;
        $end = 0;
        $action = '';
        if( $http->hasPostVariable( 'workflow-start' ) )
            $start = self::getDate( $http->postVariable( 'workflow-start' ) );
        if( $http->hasPostVariable( 'workflow-end' ) )
            $end = self::getDate( $http->postVariable( 'workflow-end' ) );
        if( $http->hasPostVariable( 'workflow-action' ) )
            $action = $http->postVariable( 'workflow-action' );
        $row = array( 
            'contentobject_id' => $object->ID 
        );
        if ( $start && $start > 0 )
        {
            $row['start'] = $start->getTimestamp();
        }
        if ( $end && $end > 0 )
        {
            $row['end'] = $end->getTimestamp();
        }
        if( $action != '' && $action != 'offline' )
        {
            $id = ( ! is_array( $http->postVariable( 'workflow-' . $action . '-id' ) ) ) ? array( 
                'eZNode_' . $http->postVariable( 'workflow-' . $action . '-id' ) 
            ) : $http->postVariable( 'workflow-' . $action . '-id' );
            if ( $action == 'move' )
            {
                if( $http->hasPostVariable( 'CustomActionButton' ) )
                {
                    $customActionButton = $http->postVariable( 'CustomActionButton' );
                    if ( isset( $customActionButton[$object->attribute( 'id' ) . '_browse_related_node'] ) )
                    {
                        /*
                        /**
                         * Fetch array of container classes
                         *
                        $classes = eZPersistentObject::fetchObjectList( eZContentClass::definition(), array( 
                            'identifier' 
                        ), array( 
                            'is_container' => 1 
                        ), null, null, false );
                        /**
                         * Prepare array of allowed class identifiers based on above fetch results
                         
                        $allowedClasses = array();
                        foreach ( $classes as $class )
                        {
                            $allowedClasses[] = $class['identifier'];
                        }
                        */
                        $browseParameters = array( 
                            'action_name' => 'AddNodeToMove' , 
                            'browse_custom_action' => array( 
                                'name' => 'CustomActionButton[' . $object->attribute( 'id' ) . '_set_related_node]' , 
                                'value' => $object->attribute( 'id' ) 
                            ) , 
    //                        'class_array' => $allowedClasses , 
                            'persistent_data' => array( 
                                'HasObjectInput' => 0 
                            ) , 
                            'from_page' => '/content/edit/' . $object->attribute( 'id' ) . '/' . $editVersion . '/' . $editLanguage . '' 
                        );
                        
                        return eZContentBrowse::browse( $browseParameters, $module );
                    }
                }
            }
            $row['action'] = serialize( array( 
                'action' => $action , 
                'ID' => array( 
                    $action => $id 
                ) 
            ) );
        }
        $obj = new xrowworkflow( $row );
        $obj->store();
    }

    function publish( $contentObjectID, $version )
    {
        $cov = eZContentObjectVersion::fetchVersion( $version, $contentObjectID );
        if ( $cov instanceof eZContentObjectVersion )
        {
            $co = $cov->attribute( 'contentobject' );
            if ( $co && $co->attribute( 'class_identifier' ) == 'event' )
            {
                $dm = $cov->dataMap();
                if ( isset( $dm['start'] ) && $dm['start']->hasContent() )
                {
                    $time = $dm['start']->attribute( 'data_int' );
                    $co->setAttribute( 'published', $time );
                    $co->store();
                }
            }
        }
        $workflow = xrowworkflow::fetchByContentObjectID( $contentObjectID );
        if ( $workflow instanceof xrowworkflow )
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
        $date->setTime( $args['hour'], $args['minute'] ); #
        return $date;
    }
}
