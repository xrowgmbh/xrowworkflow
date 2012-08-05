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
            $result['tables'] = ',' . implode( ',', $tables );
            $result['joins']  = ' xrowworkflow.contentobject_id = ezcontentobject.id and ezcontentobject.id  AND xrowworkflow.start < ' . time() . ' AND start > 0 AND ';
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
            $result['tables'] = ',' . implode( ',', $tables );
            $result['joins']  = ' xrowworkflow.contentobject_id = ezcontentobject.id and ezcontentobject.id  AND xrowworkflow.end < ' . time() . ' AND end > 0 AND ';
        return $result;
    }
}






?>
