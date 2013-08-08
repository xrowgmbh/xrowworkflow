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
                }));
