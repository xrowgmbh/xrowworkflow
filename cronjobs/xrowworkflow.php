<?php

//login as admin 
$user = eZUser::fetch( eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) );
$user->loginCurrent();

eZINI::instance()->setVariable( 'SiteAccessSettings', 'ShowHiddenNodes', 'false' );
$nodeID = 1;
$rootNode = eZContentObjectTreeNode::fetch( $nodeID );

if ( ! $isQuiet )
{
    $cli->output( 'Expire content of node "' . $rootNode->attribute( 'name' ) . '" (' . $nodeID . ')' );
    $cli->output();
}

$params = array( 
    'Offset' => 0,
    'Limitation' => array() , 
    'ExtendedAttributeFilter' => array( 
        'id' => 'xrowworkflow_end' , 
        'params' => array() 
    ) 
);

$nodeArrayCount = $rootNode->subTreeCount( $params );

if ( $nodeArrayCount > 0 )
{
    if ( ! $isQuiet )
    {
        $cli->output( "Do xrowworkflow for {$nodeArrayCount} node(s)." );
    }
    $params['Limit'] = 100;
    do
    {
        $nodeArray = $rootNode->subTree( $params );
        if( is_array( $nodeArray ) && count( $nodeArray ) > 0 )
        {
            foreach ( $nodeArray as $node )
            {
                if( $node instanceof eZContentObjectTreeNode )
                {
                    $workflow = xrowworkflow::fetchByContentObjectID( $node->ContentObjectID );
                    if ( $workflow instanceof xrowworkflow )
                    {
                        $action = $workflow->attribute( 'get_action_list' );
                        switch ( $action['action'] )
                        {
                            case 'move':
                                $workflow->moveTo();
                                if ( ! $isQuiet )
                                {
                                    $cli->output( "Move '" . $node->attribute( 'name' ) . "' (" . $node->NodeID . ")." );
                                }
                                break;
                            case 'delete':
                                $workflow->delete();
                                if ( ! $isQuiet )
                                {
                                    $cli->output( "Delete '" . $node->attribute( 'name' ) . "' (" . $node->NodeID . ")." );
                                }
                                break;
                            default:
                                $workflow->offline();
                                if ( ! $isQuiet )
                                {
                                    $cli->output( "Set offline '" . $node->attribute( 'name' ) . "' (" . $node->NodeID . ")." );
                                }
                                break;
                        }
                    }
                }
                else
                {
                    eZDebug::writeError( array( $node, " is not instanceof eZContentObjectTreeNode" ), __METHOD__ );
                }
            }
            $params["Offset"] = $params["Offset"] + count( $nodeArray );
            eZContentObject::clearCache();
        }
    }
    while ( is_array( $nodeArray ) and count( $nodeArray ) > 0 );
}

if ( ! $isQuiet )
{
    $cli->output( 'Publishing content of node "' . $rootNode->attribute( 'name' ) . '" (' . $nodeID . ')' );
    $cli->output();
}

$params = array( 
    'Limitation' => array() , 
    'ExtendedAttributeFilter' => array( 
        'id' => 'xrowworkflow_start' , 
        'params' => array() 
    ) 
);
$nodeArrayCount = $rootNode->subTreeCount( $params );

if ( $nodeArrayCount > 0 )
{
    if ( ! $isQuiet )
    {
        $cli->output( "Publishing {$nodeArrayCount} node(s)." );
    }
    $params['Limit'] = 100;
    do
    {
        $nodeArray = $rootNode->subTree( $params );
        foreach ( $nodeArray as $node )
        {
            if( $node instanceof eZContentObjectTreeNode )
            {
                $workflow = xrowworkflow::fetchByContentObjectID( $node->ContentObjectID );
                if ( $workflow instanceof xrowworkflow )
                {
                    $workflow->online();
                }
                
                if ( ! $isQuiet )
                {
                    $cli->output( 'Publishing node: "' . $node->attribute( 'name' ) . '" (' . $node->attribute( 'node_id' ) . ')' );
                }
            }
            else
            {
                eZDebug::writeError( array( $node, " is not instanceof eZContentObjectTreeNode" ), __METHOD__ );
            }
        }
        eZContentObject::clearCache();
    }
    while ( is_array( $nodeArray ) and count( $nodeArray ) > 0 );
}