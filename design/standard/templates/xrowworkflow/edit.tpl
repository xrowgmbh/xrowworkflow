{def $locale = fetch( 'content', 'locale' )}
{def $workflowdata=fetch( 'xrowworkflow', 'by_object_id', hash( 'id', $#object.id ) )}

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
</select>
:
<select id="workflow-start-minute" name="workflow-start[minute]">
{for 0 to 59 as $counter}
 <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
{/for}
</select>

</fieldset>

<fieldset>
    <legend>{'Date of expiry'|i18n( 'xrowworkflow' )}</legend>
    <label for="workflow-end-date">{'Date'|i18n( 'xrowworkflow' )}</label>
    <input type="text" id="workflow-end-date" name="workflow-end[date]" {if $workflowdata.end} data-time="{$workflowdata.end}"{/if}/>

<label for="workflow-end-hour">{'Time'|i18n( 'xrowworkflow' )}</label>
<select id="workflow-end-hour" name="workflow-end[hour]">
{for 0 to 23 as $counter}
 <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
{/for}
</select>
:
<select id="workflow-end-minute" name="workflow-end[minute]">

{for 0 to 59 as $counter}
 <option value="{$counter}">{if $counter|le(9)}0{/if}{$counter}</option>
{/for}
</select>

</fieldset>