<?php

$FunctionList = array();

$FunctionList['by_object_id'] = array( 'name' => 'by_object_id',
                                       'call_method' => array( 
                                       'class' => 'xrowWorkflowFunctionCollection',
                                                  'method' => 'byObjectID' ),
                                       'parameter_type' => 'standard',
                                       'parameters' => array( array( 'name' => 'id',
                                                                     'type' => 'integer',
                                                                     'required' => true ) ) );

