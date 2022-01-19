<form id="dialog-mywaypay" method="get" action="#" class="has-form-actions hide">
    <div class="form-horizontal">
        <div class="form-row">
            <h3><?php _e('Learn more about mywaypay', 'payment_pro'); ?></h3>
            <p>
                <?php printf(__('mywaypay official website: %s', 'payment_pro'), '<a href="https://mywaypay.com/">https://mywaypay.com/</a>'); ?>.
                <br/>
                <?php printf(__('Getting started: %s', 'payment_pro'), '<a href="https://mywaypay.com/docs">https://mywaypay.com/docs</a>'); ?>.
                <br/>
            </p>
        </div>
        <div class="form-actions">
            <div class="wrapper">
                <a class="btn" href="javascript:void(0);" onclick="$('#dialog-mywaypay').dialog('close');"><?php _e('Cancel'); ?></a>
            </div>
        </div>
    </div>
</form>

<script type="text/javascript" >
    $(document).ready(function(){
        $("#dialog-mywaypay").dialog({
            autoOpen: false,
            modal: true,
            width: '90%',
            title: '<?php echo osc_esc_js( __('mywaypay help', 'payment_pro') ); ?>'
        });
    });
</script>