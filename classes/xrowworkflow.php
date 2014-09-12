<?php

class xrowworkflow extends eZPersistentObject
{
    const STATE_GROUP = 'workflow_date';
    const ONLINE = 'online';
    const OFFLINE = 'offline';
    const QUEUE = 'queue';

    function __construct( $row )
    {
        parent::__construct( $row );
    }

    static function definition()
    {
        return array( 
            'fields' => array( 
                'contentobject_id' => array( 
                    'name' => 'contentobject_id' , 
                    'datatype' => 'integer' , 
                    'default' => null , 
                    'required' => true 
                ) , 
                'start' => array( 
                    'name' => 'start' , 
                    'datatype' => 'integer' , 
                    'default' => 0 , 
                    'required' => true 
                ) , 
                'end' => array( 
                    'name' => 'end' , 
                    'datatype' => 'integer' , 
                    'default' => 0 , 
                    'required' => true 
                ) , 
                'action' => array( 
                    'name' => 'action' , 
                    'datatype' => 'string' , 
                    'default' => null , 
                    'required' => true 
                ) 
            ) , 
            'keys' => array( 
                'contentobject_id' 
            ) , 
            'function_attributes' => array( 
                "get_action_list" => "getActionList" 
            ) , 
            'sort' => array( 
                'contentobject_id' => 'asc' 
            ) , 
            'class_name' => 'xrowworkflow' , 
            'name' => 'xrowworkflow' 
        );
    }

    function check()
    {
        if ( isset( $this->end ) and $this->end < time() and $this->end > 0 )
        {
            return $this->offline();
        }
        if ( isset( $this->start ) and $this->start < time() and $this->start > 0 )
        {
            return $this->online();
        }
        if ( isset( $this->start ) and $this->start > time() )
        {
            return $this->queue();
        }
    }

    static public function fetchByContentObjectID( $contentObjectID )
    {
        return eZPersistentObject::fetchObject( xrowworkflow::definition(), null, array( 
            'contentobject_id' => $contentObjectID 
        ) );
    }

    function getActionList()
    {
        return unserialize( $this->action );
    }

    /**
     * Update a contentobject's state
     *
     * @param int $objectID
     * @param int $selectedStateIDList
     *
     * @return array An array with operation status, always true
     */
    static public function updateObjectState( $objectID, $selectedStateIDList )
    {
        $object = eZContentObject::fetch( $objectID );
        // we don't need to re-assign states the object currently already has assigned
        $currentStateIDArray = $object->attribute( 'state_id_array' );
        $selectedStateIDList = array_diff( $selectedStateIDList, $currentStateIDArray );
        foreach ( $selectedStateIDList as $selectedStateID )
        {
            $state = eZContentObjectState::fetchById( $selectedStateID );
            $object->assignState( $state );
        }
        //call appropriate method from search engine
        eZSearch::updateObjectState( $objectID, $selectedStateIDList );
        eZContentCacheManager::clearContentCacheIfNeeded( $objectID );
    }

    function online()
    {
        $object = eZContentObject::fetch( $this->contentobject_id );
        if ( $this->attribute( 'start' ) > 0 && $object->attribute( 'class_identifier' ) != 'event' )
        {
            $object->setAttribute( 'published', $this->attribute( 'start' ) );
            $object->store();
        }
        self::updateObjectState( $this->contentobject_id, array( 
            eZContentObjectState::fetchByIdentifier( xrowworkflow::ONLINE, eZContentObjectStateGroup::fetchByIdentifier( xrowworkflow::STATE_GROUP )->ID )->ID 
        ) );
        if( $this->attribute( 'end' ) !== NULL && $this->attribute( 'end' ) > 0 )
        {
            $this->setAttribute( 'start', 0 );
            $this->store();
        }
        else
        {
            $this->remove();
        }
        eZContentCacheManager::clearContentCache( $this->contentobject_id );
        eZDebug::writeDebug( __METHOD__ );
    }

    function queue()
    {
        eZDebug::writeDebug( __METHOD__ );
        self::updateObjectState( $this->contentobject_id, array( 
            eZContentObjectState::fetchByIdentifier( xrowworkflow::QUEUE, eZContentObjectStateGroup::fetchByIdentifier( xrowworkflow::STATE_GROUP )->ID )->ID 
        ) );
        eZDebug::writeDebug( __METHOD__ );
    }

    function offline()
    {
        self::updateObjectState( $this->contentobject_id, array( 
            eZContentObjectState::fetchByIdentifier( xrowworkflow::OFFLINE, eZContentObjectStateGroup::fetchByIdentifier( xrowworkflow::STATE_GROUP )->ID )->ID 
        ) );
        $this->releaseRelations();
        $this->clear();
        $this->remove();
        eZDebug::writeDebug( __METHOD__ );
    }

    function moveTo()
    {
        $actionList = $this->attribute( 'get_action_list' );
        $moveTo = $actionList['ID']['move'];
        $moveToArray = explode( '_', $moveTo[0] );
        $moveToNodeID = $moveToArray[1];
        if( $moveToNodeID != '' )
        {
            $moveToNode = eZContentObjectTreeNode::fetch( $moveToNodeID );
            if( $moveToNode instanceof eZContentObjectTreeNode )
            {
                $object = eZContentObject::fetch( $this->contentobject_id );
                $deleteIDArray = array();
                foreach ( $object->attribute( 'assigned_nodes' ) as $node )
                {
                    if( $node instanceof eZContentObjectTreeNode )
                    {
                        if ( ! $node->attribute( 'is_main' ) )
                        {
                            // check children
                            $countChildren = $node->childrenCount();
                            if( $countChildren == 0 )
                            {
                                $deleteIDArray[] = $node->NodeID;
                            }
                        }
                        else
                        {
                            $mainNodeID = $node->NodeID;
                        }
                    }
                    else
                    {
                        eZDebug::writeError( array( $node, " is not instanceof eZContentObjectTreeNode" ), __METHOD__ );
                    }
                }

                eZContentObjectTreeNodeOperations::move( $mainNodeID, $moveToNodeID );
                eZDebug::writeDebug( "Move $mainNodeID to $moveToID[1]", __METHOD__ );
                if( count( $deleteIDArray ) > 0 )
                {
                    eZDebug::writeDebug( "Move action: remove NodeIDs " . implode( ', ', $deleteIDArray ), __METHOD__ );
                    eZContentObjectTreeNode::removeSubtrees( $deleteIDArray, false );
                }
                self::updateObjectState( $this->contentobject_id, array( 
                    eZContentObjectState::fetchByIdentifier( xrowworkflow::OFFLINE, eZContentObjectStateGroup::fetchByIdentifier( xrowworkflow::STATE_GROUP )->ID )->ID 
                ) );

                $this->releaseRelations();
                $this->clear();
                $this->remove();
            }
            else
            {
                $this->offline();
                eZDebug::writeError( "Can't move $this->contentobject_id  to $moveToNodeID. $moveToNodeID does not exist. Set it offline. All ID's are object ID's.", __METHOD__ );
                self::sendErrorMail( "Can't move $this->contentobject_id to $moveToNodeID. $moveToNodeID does not exist. Set it offline. All ID's are object ID's." );
            }
        }
        else
        {
            $this->offline();
            eZDebug::writeError( "Can't move $this->contentobject_id (object ID) to empty NodeID. Set it offline.", __METHOD__ );
            self::sendErrorMail( "Can't move $this->contentobject_id (object ID) to empty NodeID. Set it offline." );
        }
        eZDebug::writeDebug( __METHOD__ );
    }

    function delete()
    {
        $actionList = $this->attribute( 'get_action_list' );
        $deleteIDs = $actionList['ID']['delete'];
        $deleted = false;
        foreach ( $deleteIDs as $index => $id )
        {
            if ( $deleted === false )
            {
                $id = explode( '_', $id );
                switch ( $id[0] )
                {
                    case 'eZObject':
                        $object = eZContentObject::fetch( $this->contentobject_id );
                        if( $object instanceof eZContentObject )
                        {
                            foreach ( $object->attribute( 'assigned_nodes' ) as $node )
                            {
                                if( $node instanceof eZContentObjectTreeNode )
                                {
                                    // check children
                                    $countChildren = $node->childrenCount();
                                    if( $countChildren == 0 )
                                    {
                                        $deleteIDArray[] = $node->NodeID;
                                    }
                                }
                                else
                                {
                                    eZDebug::writeError( array( $node, " is not instanceof eZContentObjectTreeNode" ), __METHOD__ );
                                }
                            }
                            $eZObject = true;
                        }
                        break;
                    default:
                        $node = eZContentObjectTreeNode::fetch( $id[1] );
                        if( $node instanceof eZContentObjectTreeNode )
                        {
                            // check children
                            $countChildren = $node->childrenCount();
                            if( $countChildren == 0 )
                            {
                                $deleteIDArray[] = $node->NodeID;
                            }
                        }
                        else
                        {
                            eZDebug::writeError( array( $node, " is not instanceof eZContentObjectTreeNode" ), __METHOD__ );
                        }
                        break;
                }
            }
        }
        if( count( $deleteIDArray ) > 0 )
        {
            if( $eZObject )
            {
                if ( eZOperationHandler::operationIsAvailable( 'content_delete' ) )
                {
                    $operationResult = eZOperationHandler::execute( 'content',
                                                                'delete',
                                                                array( 'node_id_list' => $deleteIDArray,
                                                                       'move_to_trash' => false ),
                                                                null, true );
                }
                else
                {
                    eZDebug::writeDebug( "Delete action: remove Object and NodeIDs " . implode( ', ', $deleteIDArray ), __METHOD__ );
                    eZContentOperationCollection::deleteObject( $deleteIDArray, false );
                }
                $this->clear();
            }
            else
            {
                eZDebug::writeDebug( "Delete action: remove NodeIDs " . implode( ', ', $deleteIDArray ), __METHOD__ );
                eZContentObjectTreeNode::removeSubtrees( $deleteIDArray, false );
            }
        }
        eZDebug::writeDebug( __METHOD__ );
        self::sendErrorMail( "Can't move $this->contentobject_id to 123. 546 does not exist. Set it offline. All ID's are object ID's." );
        $this->remove();
    }

    function remove( $conditions = null, $extraConditions = null )
    {
        $def = $this->definition();
        $keys = $def["keys"];
        if ( !is_array( $conditions ) )
        {
            $conditions = array();
            foreach ( $keys as $key )
            {
                $value = $this->attribute( $key );
                $conditions[$key] = $value;
            }
        }
        $db = eZDB::instance();
        $table = $def["name"];
        if ( is_array( $extraConditions ) )
        {
            foreach ( $extraConditions as $key => $cond )
            {
                $conditions[$key] = $cond;
            }
        }
        $fields = $def['fields'];
        eZPersistentObject::replaceFieldsWithShortNames( $db, $fields, $conditions );
        $cond_text = eZPersistentObject::conditionText( $conditions );
        $db->begin();
        $db->query( "DELETE FROM $table $cond_text" );
        $db->commit();
    }

    function clear( $removeEZFlowBlocks = true )
    {
        $db = eZDB::instance();
        // Remove from the flow
        if ( $removeEZFlowBlocks && $this->contentobject_id > 0 )
        {
            $db->begin();
            $db->query( 'DELETE FROM ezm_pool WHERE object_id = ' . (int) $this->contentobject_id );
            $db->commit();
        }
        if( $this->contentobject_id > 0 )
        {
            $rows = $db->arrayQuery( 'SELECT DISTINCT ezm_block.node_id FROM ezm_pool, ezm_block WHERE object_id = ' . (int) $this->contentobject_id . ' AND ezm_pool.block_id = ezm_block.id' );
        }

        if ( isset( $rows ) && count( $rows ) )
        {
            foreach ( $rows as $row )
            {
                $contentObject = eZContentObject::fetchByNodeID( $row['node_id'] );
                if ( $contentObject )
                    eZContentCacheManager::clearContentCache( $contentObject->attribute( 'id' ) );
            }
        }
        eZContentCacheManager::clearContentCache( $this->contentobject_id );
    }
    
    function releaseRelations()
    {
        $obj = eZContentObject::fetch( $this->contentobject_id );

        /*** clearing the gis relations ***/
        $relations_attribute = eZFunctionHandler::execute( 'content', 'reverse_related_objects', array( 'object_id' => $this->contentobject_id,
                                                                                                        'all_relations' => array( "attribute" )
                                               ) );
        foreach ( $relations_attribute as $relation )
        {
            $data_map = $relation->dataMap();
            foreach ( $data_map as $key => $attribute)
            {
                if ( $attribute->DataTypeString === "xrowgis" AND $attribute->hasContent() AND $attribute->DataInt == $this->contentobject_id AND $attribute->SortKeyInt == $this->contentobject_id )
                {
                    //clear cache
                    eZContentCacheManager::clearObjectViewCache( $relation->ID );

                    //removing the xrowgisrelation by cleaning the values
                    $data_map["xrowgis"]->setAttribute( 'data_int', NULL );
                    $data_map["xrowgis"]->setAttribute( 'sort_key_int', 0 );
                    $data_map["xrowgis"]->store();
                    $relation->store();
                    //relation cleanup?
                }
                elseif ( ( $attribute->DataTypeString === "ezobjectrelation" OR $attribute->DataTypeString === "ezobjectrelationlist") AND $attribute->hasContent() )
                {
                    $this->myOwnremoveReverseRelations( $this->contentobject_id, $relation );
                }
            }
        
        }

        /*** removing the related embeds ***/
        $relations_embed = eZFunctionHandler::execute( 'content', 'reverse_related_objects', array( 'object_id' => $this->contentobject_id,
                                                                                                    'all_relations' => array( "xml_embed" )
                                               ) );

        foreach ( $relations_embed as $relation )
        {
            $data_map = $relation->dataMap();
            foreach ( $data_map as $data_map_item )
            {
                if ( $data_map_item->DataTypeString == "ezxmltext" )
                {
                    $dom = new DOMDocument;
                    $dom->loadXML($data_map_item->DataText);
                    $xpath = new DOMXpath($dom);
                    //find all embed elements with the correct object_id
                    foreach ( $xpath->query("*//embed[@object_id='" . $obj->ID . "']") as $element )
                    {
                        $element->parentNode->removeChild($element);
                    }
                    
                    $data_map_item->setAttribute( 'data_text', $dom->saveXML() );
                    $data_map_item->store();
                }

            }

            //clear cache
            eZContentCacheManager::clearObjectViewCache( $relation->ID );

            //from -> to
            $relation->removeContentObjectRelation( $obj->ID, false, 0, 2 );
        }

        /*** removing the related links ***/
        $relations_link = eZFunctionHandler::execute( 'content', 'reverse_related_objects', array( 'object_id' => $this->contentobject_id,
                                                                                                   'all_relations' => array( "xml_link" )
                                               ) );

        foreach ( $relations_link as $relation )
        {
            $data_map = $relation->dataMap();
            foreach ( $data_map as $data_map_item )
            {
                if ( $data_map_item->DataTypeString == "ezxmltext" )
                {
                    $dom = new DOMDocument;
                    $dom->loadXML($data_map_item->DataText);
                    $xpath = new DOMXpath($dom);
                    //find all link elements with the correct linked object_id to remove
                    foreach ( $xpath->query("*//link[@object_id='" . $obj->ID . "']") as $element )
                    {
                        $text_element = $dom->createTextNode($element->nodeValue);
                        $element->parentNode->replaceChild($text_element, $element);
                    }

                    //removing linked nodes
                    foreach ( $obj->assignedNodes() as $node)
                    {
                        foreach ( $xpath->query("*//link[@node_id='" . $node->NodeID . "']") as $element )
                        {
                            $text_element = $dom->createTextNode($element->nodeValue);
                            $element->parentNode->replaceChild($text_element, $element);
                        }
                    }

                    //clear cache
                    eZContentCacheManager::clearObjectViewCache( $relation->ID );

                    //store object
                    $data_map_item->setAttribute( 'data_text', $dom->saveXML() );
                    $data_map_item->store();

                    //release relation in database
                    $relation->removeContentObjectRelation( $obj->ID, false, 0, 4 );
                }
            }
        }

    }
    
    /* P1: Contentobject ID from offline Image P2: The related Object where it will be removed of*/
    function myOwnremoveReverseRelations( $contentobject_id, $relation )
    {
        $db = eZDB::instance();
        $result = $db->arrayQuery( "SELECT attr.*
                        FROM ezcontentobject_link link,
                             ezcontentobject_attribute attr
                        WHERE link.from_contentobject_id=attr.contentobject_id AND
                              link.from_contentobject_version=attr.version AND
                              link.contentclassattribute_id=attr.contentclassattribute_id AND
                              link.to_contentobject_id=$contentobject_id" );

        // Remove references from XML.
        if ( count( $result ) > 0 )
        {
            foreach( $result as $row )
            {
                $attr = new eZContentObjectAttribute( $row );
                $dataType = $attr->dataType();
                //removes the item from list
                $dataType->removeRelatedObjectItem( $attr, $contentobject_id );
                eZContentCacheManager::clearObjectViewCache( $attr->attribute( 'contentobject_id' ));
                $attr->storeData();
                //removes the db connection in ezcontentobject_link
                $relation->removeContentObjectRelation( $contentobject_id, false, $attr->attribute("contentclassattribute_id") );
            }
        }
    }

    static function sendErrorMail( $mail_errorstring )
    {
        $ini = eZINI::instance( 'site.ini' );
        $xrowworkflow_ini = eZINI::instance( 'xrowworkflow.ini' );
        if( $xrowworkflow_ini->hasVariable( 'Settings', 'ReceiverArray' ) && count( $xrowworkflow_ini->variable( 'Settings', 'ReceiverArray' ) ) > 0 )
        {
            ezcMailTools::setLineBreak( "\n" );
            $mail = new ezcMailComposer();
            $mail->charset = 'utf-8';
            $mail->from = new ezcMailAddress( $ini->variable( 'MailSettings', 'EmailSender' ), $ini->variable( 'SiteSettings', 'SiteName' ), $mail->charset );
            $mail->returnPath = $mail->from;
            $mail->subject = 'xrowworkflow error during move action';
            $mail->plainText = $mail_errorstring . " mail sent from: " . eZSys::hostname() . "(" . eZSys::serverURL() . ")";
            $mail->build();
    
            $receiverArray = $xrowworkflow_ini->variable( 'Settings', 'ReceiverArray' );
            $transport = new ezcMailMtaTransport();
            foreach ( $receiverArray as $receiver )
            {
                $mail->addTo( new ezcMailAddress( $receiver, '', $mail->charset ) );
            }
            if( !$transport->send( $mail ) )
            {
                eZDebug::writeError( "Can't send error mail after not moving a node (xrowworkflow).", __METHOD__ );
            }
        }
    }
}