{def $locale = fetch( 'content', 'locale' )
     $workflowdata = fetch( 'xrowworkflow', 'by_object_id', hash( 'id', $#object.id ) )
     $nochildren = true()
     $main_node = false()
     $assigned_nodes = array()}
{if $attribute.object.assigned_nodes|count|gt( 0 )}
    {foreach $attribute.object.assigned_nodes as $node}
        {if $node.is_main}
            {set $main_node = $node}
        {else}
            {set $assigned_nodes = $assigned_nodes|append( $node )}
        {/if}
        {if $node.children_count|gt( 0 )}
            {set $nochildren = false()}
        {/if}
    {/foreach}
{/if}

<h2>{'Workflow'|i18n( 'xrowworkflow' )}</h2>
<fieldset>
    <legend>{'Date of publication'|i18n( 'xrowworkflow' )}</legend>
    <label for="workflow-start-date">{'Date'|i18n( 'xrowworkflow' )}</label>
    <input type="text" id="workflow-start-date" name="workflow-start[date]" {if $workflowdata.start} data-time="{$workflowdata.start}"{/if}/>
    <label for="workflow-start-hour">{'Time'|i18n( 'xrowworkflow' )}</label>
    <select id="workflow-start-hour" name="workflow-start[hour]">
    {for 0 to 23 as $counter}
        <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
    {/for}
    </select>:
    <select id="workflow-start-minute" name="workflow-start[minute]">
    {for 0 to 59 as $counter}
        <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
    {/for}
    </select>
</fieldset>
<fieldset>
    <legend>{'Date of expiry'|i18n( 'xrowworkflow' )}</legend>
        <div id="workflow-end" class="left">
            <label for="workflow-end-date">{'Date'|i18n( 'xrowworkflow' )}</label>
            <input type="text" id="workflow-end-date" name="workflow-end[date]" {if $workflowdata.end} data-time="{$workflowdata.end}"{/if}/>
            <label for="workflow-end-hour">{'Time'|i18n( 'xrowworkflow' )}</label>
            <select id="workflow-end-hour" name="workflow-end[hour]">
            {for 0 to 23 as $counter}
                <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
            {/for}
            </select>:
            <select id="workflow-end-minute" name="workflow-end[minute]">
            {for 0 to 59 as $counter}
                <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
            {/for}
            </select>
        </div>
        {def $moveValue = ''}
        {if ezhttp().post.BrowseActionName|eq( 'AddNodeToMove' )}
            {set $moveValue = ezhttp().post.SelectedNodeIDArray.0}
        {elseif and( ezhttp().post.BrowseActionName|eq( 'AddNodeToMove' ), $workflowdata.get_action_list.ID.move.0 )}
            {set $moveValue = ezhttp().post.SelectedNodeIDArray.0}
        {elseif $workflowdata.get_action_list.ID.move.0}
            {set $moveValue = $workflowdata.get_action_list.ID.move.0|explode( '_' ).1}
        {/if}
        <div id="workflow-action" class="right">
            <div>
                <label for="workflow-action-offline">{'offline'|i18n( 'xrowworkflow' )}</label>
                <input type="radio" id="workflow-action-offline" name="workflow-action" value="offline"{if $workflowdata.get_action_list.action|eq( 'offline' )} checked=true{/if} />
            </div>
            {* MOVE *}
            <div>
                <label for="workflow-action-move">{'move to'|i18n( 'xrowworkflow' )}</label>
                <input type="radio" id="workflow-action-move" name="workflow-action" value="move" {if or( $workflowdata.get_action_list.action|eq( 'move' ), ezhttp().post.BrowseActionName|eq( 'AddNodeToMove' ) )}checked=true{/if} />
                <input type="text" id="workflow-move-id" name="workflow-move-id" value="{$moveValue}"/>
                <input class="button workflow-action-move" type="submit" name="CustomActionButton[{$attribute.object.id}_browse_related_node]" value="{'Browse for node'|i18n( 'xrowworkflow' )}" />
            </div>
            {* DELETE *}
            <div>
                <label for="workflow-action-delete">{'delete'|i18n( 'xrowworkflow' )}</label>
                <input type="radio" id="workflow-action-delete" style="position: relative; top: -55px" name="workflow-action" value="delete" {if $workflowdata.get_action_list.action|eq( 'delete' )}checked=true{/if} />
                <select name="workflow-delete-id[]" id="workflow-delete-ids" multiple="multiple">
                    <option value="eZObject_{$attribute.contentobject_id}"{if $nochildren|not()} disabled="disabled"{/if} {if $workflowdata.get_action_list.ID.delete|contains(concat('eZObject_',$attribute.contentobject_id))}selected="selected"{/if}>{'Object'|i18n( 'xrowworkflow' )}: {$attribute.object.main_node.url_alias|wash}</option>
                    {* main node only enabled if no children or there are more assigned_nodes *}
                    <option value="eZNode_{$main_node.node_id}"{if or( $main_node.children_count|gt( 0 ), $assigned_nodes|count|eq( 0 ) )} disabled="disabled"{/if} {if $workflowdata.get_action_list.ID.delete|contains(concat('eZNode_',$main_node.node_id))}selected="selected"{/if}>{'Main Node'|i18n( 'xrowworkflow' )}: {$main_node.url_alias|wash}</option>
                {if $assigned_nodes|count|gt( 0 )}
                {foreach $assigned_nodes as $anode}
                    <option value="eZNode_{$anode.node_id}"{if $anode.children_count|gt( 0 )} disabled="disabled"{/if} {if $workflowdata.get_action_list.ID.delete|contains(concat('eZNode_',$anode.node_id))}selected="selected"{/if}>{'Node'|i18n( 'xrowworkflow' )}: {$anode.url_alias|wash}</option>
                {/foreach}
                {/if}
                </select>
            <div>
        </div>
</fieldset>
{literal}
<script type="text/javascript">
<!--
jQuery(function( $ )//called on document.ready
{
    $.datepicker.setDefaults( $.datepicker.regional[ '{/literal}{$locale.http_locale_code|extract(0,2)}{literal}' ] );
    $( "#workflow-start-date" ).datepicker( {minDate: 0, changeMonth: true, changeYear: true, showWeek: true} );
    if ( $( "#workflow-start-date" ).data( 'time') )
    {
        var start = new Date();
        start.setTime( $( "#workflow-start-date" ).data( 'time') * 1000 );
        $( "#workflow-start-date" ).datepicker( "setDate" , start );
        $("#workflow-start-hour").val(start.getHours());
        $("#workflow-start-minute").val(start.getMinutes());
    }
    $( "#workflow-end-date" ).datepicker( {minDate: 0, changeMonth: true, changeYear: true, showWeek: true} );
    if ( $( "#workflow-end-date" ).data( 'time') )
    {
        var end = new Date();
        end.setTime( $( "#workflow-end-date" ).data( 'time') * 1000 );
        $( "#workflow-end-date" ).datepicker( "setDate" , end );
        $("#workflow-end-hour").val(end.getHours());
        $("#workflow-end-minute").val(end.getMinutes());
    }

    
});
-->
</script>
{/literal}