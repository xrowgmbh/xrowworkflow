<?php

//login a admin user
$user = eZUser::fetch( eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) );

if( $user instanceof eZUser )
{
    if ( $user->isEnabled() )
    {
        $user->loginCurrent();
    }
    else
    {
        $cli->output( "Could not login deactivated user with ID: " . eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) );
        eZDebug::writeError( array( "Could not login deactivated user with ID: " . eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) ), __METHOD__ );
    }
}
else
{
    $cli->output( "Could not fetch user with ID: " . eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) );
    eZDebug::writeError( array( "Could not fetch user with ID: " . eZINI::instance()->variable( 'UserSettings', 'UserCreatorID' ) ), __METHOD__ );
}

eZINI::instance()->setVariable( 'SiteAccessSettings', 'ShowHiddenNodes', 'false' );
$nodeID = 1;

/* workaround to remove all xrowworkflows without a valid contentobject
$db = eZDB::instance();
$rows = $db->arrayQuery( "SELECT contentobject_id FROM xrowworkflow" );
$counter = 0;
foreach ($rows as $row) {
    $test = eZContentObject::fetch($row['contentobject_id']);
    if (!$test instanceof eZContentObject){
        $counter++;
        var_dump($row['contentobject_id']);
        $xrowworkflowTest = xrowworkflow::fetchByContentObjectID($row['contentobject_id']);
        $xrowworkflowTest->remove();
    }
}
die(var_dump($counter));
*/

$params = array( 
    'Limitation' => array(),
    'IgnoreVisibility' => true,
    'ExtendedAttributeFilter' => array( 
        'id' => 'xrowworkflow_end', 
        'params' => array() 
    ) 
);

$nodeArrayCountNotOnline = (int)eZContentObjectTreeNode::subTreeCountByNodeID( $params, $nodeID );
if ( $nodeArrayCountNotOnline > 0 )
{
    if ( ! $isQuiet )
    {
        $cli->output( 'Expire content of START node.' );
        $cli->output();
    }
    if ( ! $isQuiet )
    {
        $cli->output( "Do END-xrowworkflow for " . $nodeArrayCountNotOnline . " node(s)." );
    }
    $params['Limit'] = 50;
    $params['Offset'] = 0;
    do
    {
        $nodeArray = eZContentObjectTreeNode::subTreeByNodeID( $params, $nodeID );
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
                echo ".";
            }
            $params["Offset"] = $params["Offset"] + count( $nodeArray );
            eZContentObject::clearCache();
        }
    }
    while ( is_array( $nodeArray ) and count( $nodeArray ) > 0 );
}

$params = array( 
    'Limitation' => array(),
    'IgnoreVisibility' => true,
    'ExtendedAttributeFilter' => array( 
        'id' => 'xrowworkflow_start', 
        'params' => array() 
    ) 
);
$nodeArrayCount = (int)eZContentObjectTreeNode::subTreeCountByNodeID( $params, $nodeID );
if ( $nodeArrayCount > 0 )
{
    if ( ! $isQuiet )
    {
        $cli->output( 'Publishing content of node START.' );
        $cli->output();
    }

    if ( ! $isQuiet )
    {
        $cli->output( "Publishing {$nodeArrayCount} node(s)." );
    }
    $params['Limit'] = 100;
    $params['Offset'] = 0;
    do
    {
        $nodeArray = eZContentObjectTreeNode::subTreeByNodeID( $params, $nodeID );
        if( is_array( $nodeArray ) && count( $nodeArray ) > 0 )
        {
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
            $params["Offset"] = $params["Offset"] + count( $nodeArray );
        }
    }
    while ( is_array( $nodeArray ) and count( $nodeArray ) > 0 );
}
if ($nodeArrayCountNotOnline > 0 || $nodeArrayCount > 0){
    eZContentObject::clearCache();
}