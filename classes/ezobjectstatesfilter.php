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
            $tables = array();
            $i = 1;
            foreach( $params['states_identifiers'] as $stateString )
            {
                list( $groupIdentifier, $stateIdentifier ) = explode( '/', $stateString );

                $tables[] = ' INNER JOIN ezcobj_state_link sl' . $i . ' ON (sl' . $i . '.contentobject_id=ezcontentobject.id) 
                              INNER JOIN ezcobj_state s' . $i . ' ON ( sl' . $i . '.contentobject_state_id=s' . $i . '.id) 
                              INNER JOIN ezcobj_state_group sg' . $i . ' ON ( sg' . $i . '.id=s' . $i . '.group_id )';
                $joins[]  = ' sg' . $i . '.identifier="' . $db->escapeString( $groupIdentifier ) . '" AND s' . $i . '.identifier="' . $db->escapeString( $stateIdentifier ) . '" ';

                $i++;
            }

            $result['tables'] = implode( ',', $tables );
            $result['joins']  = ' ( ' . implode( $operator, $joins ) . ' ) AND ';
        }
        else
        {
            eZDebug::writeError( 'No states identifiers given to filter on', __METHOD__ );
        }
        return $result;
    }

}
