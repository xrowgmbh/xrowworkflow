<?php

class eZObjectStatesFilter
{

    /**
     * Return the array needed by the extended attribute filter system
     *
     * @param array $params : needs to have a 'states_identifiers' key
     * containing an array of states identifiers to filter on. States
     * identifiers are string like "state_group_identifier/state_identifier". It
     * can also have a 'operator' key that can be 'and' or 'or' string.
     *
     * @return array
     */
    static function createSQLParts( $params )
    {
        $result = array( 'tables'  => false,
                         'columns' => false,
                         'joins'   => false );
        $operator = ' AND ';
        if ( isset( $params['operator'] )
                && ( strcasecmp( $params['operator'], 'and' ) === 0
                    || strcasecmp( $params['operator'], 'or' ) === 0 ) )
        {
            $operator = ' ' . $params['operator'] . ' ';
        }
        if ( isset( $params['states_identifiers'] )
                && is_array( $params['states_identifiers'] ) )
        {
            $db = eZDB::instance();
            $joins = array();
            
            foreach( $params['states_identifiers'] as $stateString )
            {
                list( $groupIdentifier, $stateIdentifier ) = explode( '/', $stateString );
                $joins[]  = ' sg1.identifier="' . $db->escapeString( $groupIdentifier ) . '" AND s1.identifier="' . $db->escapeString( $stateIdentifier ) . '" ';
            }

            $result['tables'] = ' INNER JOIN ezcobj_state_link sl1 ON (sl1.contentobject_id=ezcontentobject.id)
                                  INNER JOIN ezcobj_state s1 ON ( sl1.contentobject_state_id=s1.id)
                                  INNER JOIN ezcobj_state_group sg1 ON ( sg1.id=s1.group_id )';
            $result['joins']  = ' ( ' . implode( $operator, $joins ) . ' ) AND ';
        }
        else
        {
            eZDebug::writeError( 'No states identifiers given to filter on', __METHOD__ );
        }
        return $result;
    }

}
