jQuery(document)
        .ready(
                (function () {
                    jQuery('#workflow-delete-ids').change(function(){
                        jQuery('#workflow-action-delete').attr("checked","checked");
                    });
                    jQuery('#workflow-move-id').click(function(){
                        jQuery('#workflow-action-move').attr("checked","checked");
                    });
                    jQuery('.workflow-action-move').click(function(){
                        jQuery('#workflow-action-move').attr("checked","checked");
                    });
                    
                    //improvement to always have one value selected if nothing is selected so far
                    jQuery('#workflow-action-delete').click(function(){
                        if( jQuery('#workflow-delete-ids').val() == null )
                        {
                            $("#workflow-delete-ids option:first").attr('selected','selected');
                        }
                    });

                }));
