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

        $doNotValidateStartDate = false;
        $ini = eZINI::instance();
        $xrowworkflowINI = eZINI::instance( 'xrowworkflow.ini' );
        if ($xrowworkflowINI->hasVariable( 'Settings', 'DoNotValidateStartDate' )) {
            if ($xrowworkflowINI->variable( 'Settings', 'DoNotValidateStartDate' ) == 'enabled') {
                $doNotValidateStartDate = true;
            }
        }

        $now = new DateTime();
        $start = false;
        $end = false;
        $action = '';
        if( $http->hasPostVariable( 'workflow-action' ) )
            $action = $http->postVariable( 'workflow-action' );
        if( $http->hasPostVariable( 'workflow-start' ) )
        {
            $action = 'online';
            $startArray = $http->postVariable( 'workflow-start' );
            if( isset( $startArray['date'] ) && $startArray['date'] != '' )
                $start = self::getDate( $startArray );
        }
        if( $http->hasPostVariable( 'workflow-end' ) )
        {
            $endArray = $http->postVariable( 'workflow-end' );
            if( isset( $endArray['date'] ) && $endArray['date'] != '' )
                $end = self::getDate( $endArray );
        }
        if ( $start && $start < $now && $doNotValidateStartDate === false )
        {
            $result['is_valid'] = false; 
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Workflow: select a publication date in the future.' ) 
            );
        }
        if ( $end && $end < $now )
        {
            $result['is_valid'] = false;
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Workflow: select an expiry date in the future.' ) 
            );
        }
        if ( $end && $end < $start )
        {
            $result['is_valid'] = false;
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Workflow: select an expiry date newer then the publication date.' ) 
            );
        }
        if( $end && $action == 'move' && $http->hasPostVariable( 'workflow-move-id' ) && $http->postVariable( 'workflow-move-id' ) == '' && !$http->hasPostVariable( 'CustomActionButton' ) )
        {
            $result['is_valid'] = false;
            $result['warnings'][] = array( 
                'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Workflow: select a location for move.' ) 
            );
        }

        if( $action == 'move' && $http->hasPostVariable( 'workflow-move-id' ) && $http->postVariable( 'workflow-move-id' ) != '' && !$http->hasPostVariable( 'CustomActionButton' ) )
        {
            $node_id = $http->postVariable( 'workflow-move-id' );
            if ( is_numeric($node_id) )
            {
                $node = eZContentObjectTreeNode::fetch( $node_id );
            }
            if( !isset($node) || ( isset($node) && !$node instanceof eZContentObjectTreeNode ) )
            {
                $result['is_valid'] = false;
                $result['warnings'][] = array( 
                    'text' => ezpI18n::tr( 'extension/xrowworkflow', 'Workflow: the selected move location is not valid.' ) 
                );
            }
        }

        return $result;
    }

    function fetchInput( $http, &$module, &$class, $object, &$version, $contentObjectAttributes, $editVersion, $editLanguage, $fromLanguage )
    {
        $start = false;
        $end = false;
        $action = '';
        $type = 1;
        $now = time();

        if( $http->hasPostVariable( 'workflow-action' ) )
            $action = $http->postVariable( 'workflow-action' );
        // important for online workflow
        if( $http->hasPostVariable( 'workflow-start' ) )
        {
            $startArray = $http->postVariable( 'workflow-start' );
            if( isset( $startArray['date'] ) && $startArray['date'] != '' )
                $start = self::getDate( $startArray );
        }
        if( $http->hasPostVariable( 'workflow-end' ) )
        {
            $endArray = $http->postVariable( 'workflow-end' );
            if( isset( $endArray['date'] ) && $endArray['date'] != '' )
                $end = self::getDate( $endArray );
        }
        $row = array( 
            'contentobject_id' => $object->ID 
        );
        if( $action != '' && $end )
        {
            $row['end'] = $end->getTimestamp();
        }
        if( !$end )
        {
            $xrowworkflow_ini = eZINI::instance( 'xrowworkflow.ini' );
            $type = 2;
            if( $xrowworkflow_ini->hasVariable( 'Settings', 'ReplaceObjectUnpublishedWithField' ) )
            {
                $replaceUnpublished = $xrowworkflow_ini->variable( 'Settings', 'ReplaceObjectUnpublishedWithField' );
                $class_identifier = $object->attribute( 'class_identifier' );
                if( isset( $replaceUnpublished[$class_identifier] ) )
                {
                    $attributeName = $replaceUnpublished[$class_identifier];
                    if( $xrowworkflow_ini->hasGroup( 'ReplaceObjectUnpublished_' . $attributeName ) && $xrowworkflow_ini->hasVariable( 'ReplaceObjectUnpublished_' . $attributeName, 'DataTypeString' ) )
                    {
                        $dataTypeString = $xrowworkflow_ini->variable( 'ReplaceObjectUnpublished_' . $attributeName, 'DataTypeString' );
                        foreach ($contentObjectAttributes as $contentObjectAttributeItem) {
                            if ($contentObjectAttributeItem->DataTypeString == $dataTypeString) {
                                $contentObjectAttribute = $contentObjectAttributeItem;
                                break;
                            }
                        }
                        switch ( $dataTypeString )
                        {
                            case 'ezpublishevent':
                                $row['end'] = eZPublishEvent::getEndTimestampForWorkflow( $object, $http, $contentObjectAttribute );
                                $action = 'offline';
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }
        if ( $start )
        {
            $row['start'] = $start->getTimestamp();
        }
        if ( $action == 'move' )
        {
            if( $http->hasPostVariable( 'CustomActionButton' ) )
            {
                $customActionButton = $http->postVariable( 'CustomActionButton' );
                if ( isset( $customActionButton[$object->attribute( 'id' ) . '_browse_related_node'] ) )
                {
                    $ignoreNodesSelect = array();
                    $ignoreNodesSelectSubtree = array();
                    if( $http->hasPostVariable( 'ignoreNodesSelect' ) )
                    {
                        $ignoreNodesSelect = $http->postVariable( 'ignoreNodesSelect' );
                    }
                    if( $http->hasPostVariable( 'ignoreNodesSelectSubtree' ) )
                    {
                        $ignoreNodesSelectSubtree = $http->postVariable( 'ignoreNodesSelectSubtree' );
                    }
                    $ignoreNodesSelect = array_unique( $ignoreNodesSelect );
                    $ignoreNodesSelectSubtree = array_unique( $ignoreNodesSelectSubtree );
                    $ignoreNodesClick = $ignoreNodesSelectSubtree;
                    $http->removeSessionVariable( 'BrowseParameters' );
                    $browseParameters = array( 
                        'action_name' => 'AddNodeToMove', 
                        'browse_custom_action' => array( 
                            'name' => 'CustomActionButton[' . $object->attribute( 'id' ) . '_set_related_node]' , 
                            'value' => $object->attribute( 'id' ) 
                        ),
                        'ignore_nodes_select' => $ignoreNodesSelect,
                        'ignore_nodes_select_subtree' => $ignoreNodesSelectSubtree,
                        'ignore_nodes_click' => $ignoreNodesClick,
                        'custom_action_data' => $row,
                        'persistent_data' => array( 
                            'HasObjectInput' => 0 
                        ), 
                        'from_page' => '/content/edit/' . $object->attribute( 'id' ) . '/' . $editVersion . '/' . $editLanguage . '' 
                    );
                    return eZContentBrowse::browse( $browseParameters, $module );
                }
            }
        }
        if( $http->hasPostVariable( 'workflow-' . $action . '-id' ) )
        {
            $id = ( ! is_array( $http->postVariable( 'workflow-' . $action . '-id' ) ) ) ? array( 
                    'eZNode_' . $http->postVariable( 'workflow-' . $action . '-id' ) 
                    ) : $http->postVariable( 'workflow-' . $action . '-id' );
            $row['action'] = serialize( array( 
                'action' => $action, 
                'ID' => array( 
                    $action => $id 
                ) 
            ) );
        }
        else
        {
            $row['action'] = serialize( array( 'action' => $action ) );
        }
        $row['type'] = $type;

        $doNotValidateStartDate = false;
        $ini = eZINI::instance();
        $xrowworkflowINI = eZINI::instance( 'xrowworkflow.ini' );
        if ($xrowworkflowINI->hasVariable( 'Settings', 'DoNotValidateStartDate' )) {
            if ($xrowworkflowINI->variable( 'Settings', 'DoNotValidateStartDate' ) == 'enabled') {
                $doNotValidateStartDate = true;
            }
        }
        // save only if action is set (offline, move, delete) or online date is not empty
        if ( ( isset($row['start']) && $row['start'] < $now && $doNotValidateStartDate === false ) || ( isset($row['end']) && $row['end'] < $now ) || ( isset($row['start']) && isset($row['end']) && $row['end'] < $row['start'] ) )
        {
            eZDebug::writeDebug( 'no workflow saved', __METHOD__ );
        }
        elseif( ( $action != '' && isset($row['end']) ) || isset($row['start']) )
        {
            $obj = new xrowworkflow( $row );
            $obj->store();
        } else {
            $obj = new xrowworkflow($row);
            $obj->remove();
            eZDebug::writeError('Removed workflow for contentobject_id = ' . $row['contentobject_id'] . ' from table', __METHOD__);
            eZDebug::writeError( 'no workflow saved', __METHOD__ );
        }
        $http->removeSessionVariable( 'BrowseParameters' );
    }

    function publish( $contentObjectID, $version )
    {
        $xrowworkflow_ini = eZINI::instance( 'xrowworkflow.ini' );
        $workflow = xrowworkflow::fetchByContentObjectID( $contentObjectID );
        if( $xrowworkflow_ini->hasVariable( 'Settings', 'ReplaceObjectPublishedWithField' ) || ( $xrowworkflow_ini->hasVariable( 'Settings', 'OverwritePublishedOnDemand' ) && $xrowworkflow_ini->variable( 'Settings', 'OverwritePublishedOnDemand' ) == 'enabled' ) )
        {
            $cov = eZContentObjectVersion::fetchVersion( $version, $contentObjectID );
            if ( $cov instanceof eZContentObjectVersion )
            {
                $co = $cov->attribute( 'contentobject' );
                if ( $co instanceof eZContentObject )
                {
                    if( $xrowworkflow_ini->hasVariable( 'Settings', 'ReplaceObjectPublishedWithField' ) )
                    {
                        $replacePublished = $xrowworkflow_ini->variable( 'Settings', 'ReplaceObjectPublishedWithField' );
                        $class_identifier = $co->attribute( 'class_identifier' );
                        if( isset( $replacePublished[$class_identifier] ) )
                        {
                            $attributeName = $replacePublished[$class_identifier];
                            $dm = $cov->dataMap();
                            if ( isset( $dm[$attributeName] ) && $dm[$attributeName]->hasContent() )
                            {
                                $contentObjectAttribute = $dm[$attributeName];
                                switch ( $contentObjectAttribute->DataTypeString )
                                {
                                    case 'ezpublishevent':
                                        $content = $contentObjectAttribute->content( $contentObjectAttribute );
                                        $time = $content['perioddetails']['firststartdate'];
                                        break;
                                    default:
                                        $time = $contentObjectAttribute->toString( $contentObjectAttribute );
                                        break;
                                }
                                $co->setAttribute( 'published', $time );
                                $co->store();
                            }
                        }
                    }
                    // Check if OverwritePublishedOnDemand is enabled
                    else
                    {
                        if ( $workflow instanceof xrowworkflow && isset( $workflow->start ) and $workflow->start > 0 )
                        {
                            $co->setAttribute( 'published', $workflow->start );
                            $co->store();
                        }
                    }
                }
            }
        }
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
        $date->setTime( $args['hour'], $args['minute'] );
        return $date;
    }
}