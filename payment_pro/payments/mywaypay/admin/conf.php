<h2 class="render-title separate-top"><?php _e('Mywaypay settings', 'payment_pro'); ?> <span><a href="javascript:void(0);" onclick="$('#dialog-mywaypay').dialog('open');" ><?php _e('help', 'payment_pro'); ?></a></span> <span style="font-size: 0.5em" ><a href="javascript:void(0);" onclick="$('.mywaypay').toggle();" ><?php _e('Show options', 'payment_pro'); ?></a></span></h2>
<div class="form-row mywaypay hide">
    <div class="form-label"><?php _e('Enable mywaypay'); ?></div>
    <div class="form-controls">
        <div class="form-label-checkbox">
            <label>
                <input type="checkbox" <?php echo (osc_get_preference('mywaypay_enabled', 'payment_pro') ? 'checked="true"' : ''); ?> name="mywaypay_enabled" value="1" />
                <?php _e('Enable mywaypay as a method of payment', 'payment_pro'); ?>
            </label>
        </div>
    </div>
</div>
<div class="form-row mywaypay hide">
    <div class="form-label"><?php _e('Enable Sandbox'); ?></div>
    <div class="form-controls">
        <div class="form-label-checkbox">
            <label>
                <input type="checkbox" <?php echo (osc_get_preference('mywaypay_sandbox', 'payment_pro') ? 'checked="true"' : ''); ?> name="mywaypay_sandbox" value="1" />
                <?php _e('Enable sandbox for development testing', 'payment_pro'); ?>
            </label>
        </div>
    </div>
</div>
<div class="form-row mywaypay hide">
    <div class="form-label"><?php _e('mywaypay secret key', 'payment_pro'); ?></div>
    <div class="form-controls"><input type="text" class="xlarge" name="mywaypay_secret_key" value="<?php echo payment_pro_decrypt(osc_get_preference('mywaypay_secret_key', 'payment_pro')); ?>" /></div>
</div>
<div class="form-row mywaypay hide">
    <div class="form-label"><?php _e('mywaypay consumer key', 'payment_pro'); ?></div>
    <div class="form-controls"><input type="text" class="xlarge" name="mywaypay_consumer_key" value="<?php echo payment_pro_decrypt(osc_get_preference('mywaypay_consumer_key', 'payment_pro')); ?>" /></div>
</div>
<div class="form-row mywaypay hide">
    <div class="form-label"><?php _e('mywaypay secret key (test)', 'payment_pro'); ?></div>
    <div class="form-controls"><input type="text" class="xlarge" name="mywaypay_secret_key_test" value="<?php echo payment_pro_decrypt(osc_get_preference('mywaypay_secret_key_test', 'payment_pro')); ?>" /></div>
</div>
<div class="form-row mywaypay hide">
    <div class="form-label"><?php _e('mywaypay consumer key (test)', 'payment_pro'); ?></div>
    <div class="form-controls"><input type="text" class="xlarge" name="mywaypay_consumer_key_test" value="<?php echo payment_pro_decrypt(osc_get_preference('mywaypay_consumer_key_test', 'payment_pro')); ?>" /></div>
</div>