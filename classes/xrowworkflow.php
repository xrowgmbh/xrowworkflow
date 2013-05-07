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
        $object->setAttribute( 'published', $this->attribute( 'start' ) );
        $object->store();
        self::updateObjectState( $this->contentobject_id, array( 
            eZContentObjectState::fetchByIdentifier( xrowworkflow::ONLINE, eZContentObjectStateGroup::fetchByIdentifier( xrowworkflow::STATE_GROUP )->ID )->ID 
        ) );
        
        $this->setAttribute( 'start', 0 );
        $this->store();
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
        $db = eZDB::instance();
        $db->begin();
        self::updateObjectState( $this->contentobject_id, array( 
            eZContentObjectState::fetchByIdentifier( xrowworkflow::OFFLINE, eZContentObjectStateGroup::fetchByIdentifier( xrowworkflow::STATE_GROUP )->ID )->ID 
        ) );
        $this->remove();
        
        // Remove from the flow
        if ( $this->contentobject_id > 0 )
        {
            $rows = $db->arrayQuery( 'SELECT DISTINCT ezm_block.node_id FROM ezm_pool, ezm_block WHERE object_id = ' . (int) $this->contentobject_id . ' AND ezm_pool.block_id = ezm_block.id' );
            $db->query( 'DELETE FROM ezm_pool WHERE object_id = ' . (int) $this->contentobject_id );
        }
        $db->commit();
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
        eZDebug::writeDebug( __METHOD__ );
    }

    function moveTo()
    {
        $object = eZContentObject::fetch( $this->contentobject_id );
        foreach ( $object->attribute( 'assigned_nodes' ) as $node )
        {
            if ( ! $node->attribute( 'is_main' ) )
                $node->remove();
        }
        
        $actionList = $this->attribute( 'get_action_list' );
        $moveToID = $actionList['ID']['move'];
        $moveToID = explode( '_', $moveToID[0] );
        
        eZContentObjectTreeNodeOperations::move( $object->attribute( 'main_node_id' ), $moveToID[1] );
        
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
                        eZContentObject::fetch( $this->contentobject_id )->remove();
                        $deleted = true;
                        break;
                    default:
                        eZContentObjectTreeNode::fetch($id[1])->removeNodeFromTree();
                        break;
                }
            }
        }
        if(!$deleted)
            $this->offline();
        eZDebug::writeDebug( __METHOD__ );
    }
}