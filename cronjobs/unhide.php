<?php
/**
 * File containing the unhide.php cronjob.
 *
 * @copyright Copyright (C) 1999-2010 eZ Systems AS. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU General Public License v2.0
 * @version 4.4.0
 * @package kernel
 */


$user_a = eZUser::fetchByName( 'xrow' );
$user_a->loginCurrent();

$custom_ini = eZINI::instance( 'custom_options.ini' );
$mm_publication_block = $custom_ini->group( 'mm_publication' );
$online_state = $mm_publication_block['states']['online'];
$rootNodeIDList = $custom_ini->variable( 'UnHideSettings','RootNodeList' );
$unhideAttributeArray = $custom_ini->variable( 'UnHideSettings', 'UnHideDateAttributeList' );
$statesIdentifiers = array( 'status/online_queue');
$mm_publication_block = $custom_ini->group( 'mm_publication' );
$attribute_identifier_unpublish = $mm_publication_block['unpublish_identifier'];
$attribute_identifier_publish = $mm_publication_block['publish_identifier'];
$currrentDate = time();

eZINI::instance()->setVariable( 'SiteAccessSettings', 'ShowHiddenNodes', 'false' );

$newMemoryUsage = $oldMemoryUsage = 0;
foreach( $rootNodeIDList as $nodeID )
{
    $rootNode = eZContentObjectTreeNode::fetch( $nodeID );
    if ( !$isQuiet )
    {
        $cli->output( 'Unhiding content of node "' . $rootNode->attribute( 'name' ) . '" (' . $nodeID . ')' );
        $cli->output();
    }
    foreach ( $unhideAttributeArray as $unhideClass => $attributeIdentifier )
    {
    	if ( $unhideClass != "mm_magazine")
    	{
	    	$unpublish_gt = array( 'and',
	        							 array( $unhideClass.'/'.$attribute_identifier_unpublish, '>=', $currrentDate ),
										 array( $unhideClass.'/'.$attributeIdentifier, '<=', $currrentDate ),
										 array( $unhideClass.'/'.$attributeIdentifier, '>', 0 ) );
										
			$unpublish_zero = array( 'and',
	        							 array( $unhideClass.'/'.$attribute_identifier_unpublish, '=', 0 ),
										 array( $unhideClass.'/'.$attributeIdentifier, '<=', $currrentDate ),
										 array( $unhideClass.'/'.$attributeIdentifier, '>', 0 ) );
	    	
	        $countParams1 = array( 'ClassFilterType' => 'include',
	                              'ClassFilterArray' => array( $unhideClass ),
	                              'Limitation' => array(),
	        					  'ExtendedAttributeFilter' => array( 'id' => 'ObjectStateFilter',
	                                                    			  'params' => array( 'states_identifiers' => $statesIdentifiers, 
	                                                                  					 'operator' => 'or' ) ),
	                              'AttributeFilter' => $unpublish_gt );
	        $nodeArrayCount1 = $rootNode->subTreeCount( $countParams1 );
	        
	        $countParams2 = array( 'ClassFilterType' => 'include',
	                              'ClassFilterArray' => array( $unhideClass ),
	                              'Limitation' => array(),
	        					  'ExtendedAttributeFilter' => array( 'id' => 'ObjectStateFilter',
	                                                    			  'params' => array( 'states_identifiers' => $statesIdentifiers, 
	                                                                  					 'operator' => 'or' ) ),
	                              'AttributeFilter' => $unpublish_zero );
	
	        $nodeArrayCount2 = $rootNode->subTreeCount( $countParams2 );
	        
	        $nodeArrayCount = $nodeArrayCount1+$nodeArrayCount2;
    	}
        else
        {
        	$no_unpublish =  array( 'and',
										 array( $unhideClass.'/'.$attributeIdentifier, '<=', $currrentDate ),
										 array( $unhideClass.'/'.$attributeIdentifier, '>', 0 ) );	
        	
        	$countParams = array( 'ClassFilterType' => 'include',
	                              'ClassFilterArray' => array( $unhideClass ),
	                              'Limitation' => array(),
	        					  'ExtendedAttributeFilter' => array( 'id' => 'ObjectStateFilter',
	                                                    			  'params' => array( 'states_identifiers' => $statesIdentifiers, 
	                                                                  					 'operator' => 'or' ) ),
        	 					  'AttributeFilter' => $no_unpublish );
           	
        	$nodeArrayCount = $rootNode->subTreeCount( $countParams );
    
        }
        if ( $nodeArrayCount > 0 )
        {
            if ( !$isQuiet )
            {
                $cli->output( "Unhiding {$nodeArrayCount} node(s) of class {$unhideClass}." );
            }

            do
            {
            	if ( $unhideClass != "mm_magazine")
    			{
	                $nodeArray1 = $rootNode->subTree( $countParams1 );
	                $nodeArray2 = $rootNode->subTree( $countParams2 );
	                $nodeArray = array_merge( $nodeArray1, $nodeArray2 );
    			}
    			else
    			{
    				 $nodeArray = $rootNode->subTree( $countParams );
    			}
    		
                foreach ( $nodeArray as $node )
                {
					$dataMap = $node->attribute( 'data_map' );
	                if ( $unhideClass != "mm_magazine")
	    			{
		                $timestamp_unpublish_date = $dataMap[$attribute_identifier_unpublish]->attribute( 'data_int' );
	    			}
						$contentobject_id = $node->ContentObjectID;
						eZContentOperationCollection::updateObjectState( $contentobject_id, array( $online_state ) );
						$obj = $node->attribute( 'object' );
						$timestamp = $dataMap[$attribute_identifier_publish]->attribute( 'data_int' );
						if ( $timestamp > 0 )
						{
							$obj->setAttribute( 'published', $timestamp );
							#$obj->setAttribute( 'modified', $timestamp );
							$obj->store();
							eZContentOperationCollection::registerSearchObject( $contentobject_id, $obj->attribute( 'current_version' ) );
						}
						if ( !$isQuiet )
						{
							if ( $unhideClass != "mm_magazine")
			    			{
				               $cli->output( 'Unhiding node: "' . utf8_decode( $node->attribute( 'name' ) ) . '" (' . $node->attribute( 'node_id' ) . '). publish_date: '.$timestamp.', unpublish_date: '.$timestamp_unpublish_date );
			    			}
			    			else
			    			{
			    				 $cli->output( 'Unhiding node: "' . utf8_decode( $node->attribute( 'name' ) ) . '" (' . $node->attribute( 'node_id' ) . '). publish_date: '.$timestamp );
			    			}
						}
                }
            } while( is_array( $nodeArray ) and count( $nodeArray ) > 0 );

            if ( !$isQuiet )
            {
                $cli->output();
            }
        }
        else
        {
            if ( !$isQuiet )
            {
                $cli->output( "Nothing to unhide for: " . $unhideClass );
            }
        }
    }
     // clear memory after every batch
     eZContentObject::clearCache();
    if ( !$isQuiet )
    {
        $cli->output();
    }
}

?>
