<?php

class xrowworkflowFilter
{
    static function start( $params )
    {
        $result = array( 'tables'  => false,
                         'columns' => false,
                         'joins'   => false );

            $db = eZDB::instance();
            $joins = array();
            $tables = array( 'xrowworkflow' );
            $result['tables'] = 'INNER JOIN ' . implode( ',', $tables ) .' ON (xrowworkflow.contentobject_id = ezcontentobject.id) ';
            $result['joins']  = ' xrowworkflow.start < ' . time() . ' AND xrowworkflow.start > 0  AND ';
        return $result;
    }
    static function end( $params )
    {
        $result = array( 'tables'  => false,
                         'columns' => false,
                         'joins'   => false );

            $db = eZDB::instance();
            $joins = array();
            $tables = array( 'xrowworkflow' );
            $result['tables'] = 'INNER JOIN ' . implode( ',', $tables ) .' ON (xrowworkflow.contentobject_id = ezcontentobject.id)' ;
            $result['joins']  = ' xrowworkflow.end < ' . time() . ' AND xrowworkflow.end > 0 AND ';
        return $result;
    }
}