<?php

#verbose cron output
$GLOBALS['eZDebugEnabled'] = true;

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

// workaround to remove all xrowworkflows without a valid contentobject, 
// should execute with a probability of 1 to 100 or something like that
$probability = 50;
$random = rand(0, $probability);
if (($random % $probability) == 1) {
    $db = eZDB::instance();
    // Corrupt objects
    $rows = $db->arrayQuery( "SELECT contentobject_id FROM xrowworkflow x LEFT JOIN ezcontentobject ez ON x.contentobject_id = ez.id WHERE ez.id IS NULL" );
    if (count($rows) > 0) {
        $counter = 0;
        foreach ($rows as $row) {
            $counter++;
            $xrowworkflowTest = xrowworkflow::fetchByContentObjectID($row['contentobject_id']);
            $xrowworkflowTest->remove();
        }
        $cli->output( "Removed ".$counter." xrowworkflow rows without associated contentobject." );
    }
    // Objects are in waste
    $rows = $db->arrayQuery( "SELECT x.contentobject_id FROM xrowworkflow x LEFT JOIN ezcontentobject_tree ez ON x.contentobject_id = ez.contentobject_id WHERE ez.contentobject_id IS NULL" );
    if (count($rows) > 0) {
        $counter = 0;
        foreach ($rows as $row) {
            $counter++;
            $xrowworkflowTest = xrowworkflow::fetchByContentObjectID($row['contentobject_id']);
            $xrowworkflowTest->remove();
        }
        $cli->output( "Removed ".$counter." xrowworkflow rows without associated contentobject." );
    }
}

$params = array( 
    'Limitation' => array(),
    'IgnoreVisibility' => true,
    'ExtendedAttributeFilter' => array( 
        'id' => 'xrowworkflow_end', 
        'params' => array() 
    ) 
);

$nodeArrayCount = (int)eZContentObjectTreeNode::subTreeCountByNodeID( $params, $nodeID );
if ( $nodeArrayCount > 0 )
{
    if ( ! $isQuiet )
    {
        $cli->output( 'Expire content of START node.' );
        $cli->output();
    }
    if ( ! $isQuiet )
    {
        $cli->output( "Do END-xrowworkflow for " . $nodeArrayCount . " node(s)." );
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
            eZContentObject::clearCache();
        }
    }
    while ( is_array( $nodeArray ) and count( $nodeArray ) > 0 );
}
