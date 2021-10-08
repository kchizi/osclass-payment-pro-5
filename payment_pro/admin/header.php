<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');
$route = Params::getParam('route');
?>
<style>
    .col-items ul {
        list-style: none;
        padding-left: 0rem;
        margin-bottom: 0rem;
    }
</style>
    <div class="header-title-market">
        <h3><?php _e('Manage all your payments settings from here.', 'payment_pro'); ?></h3>
    </div>
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link <?php if($route == 'payment-pro-admin-conf'){ echo 'active';} ?>" href="<?php echo osc_route_admin_url('payment-pro-admin-conf'); ?>"><?php _e('Settings', 'payment_pro'); ?></a></li>
        <li class="nav-item"><a class="nav-link <?php if($route == 'payment-pro-admin-prices'){ echo 'active';} ?>" href="<?php echo osc_route_admin_url('payment-pro-admin-prices'); ?>"><?php _e('Prices per category', 'payment_pro'); ?></a></li>
        <li class="nav-item"><a class="nav-link <?php if($route == 'payment-pro-admin-wallet'){ echo 'active';} ?>" href="<?php echo osc_route_admin_url('payment-pro-admin-wallet'); ?>"><?php _e('Add credit to users', 'payment_pro'); ?></a></li>
        <li class="nav-item"><a class="nav-link <?php if($route == 'payment-pro-admin-packs'){ echo 'active';} ?>" href="<?php echo osc_route_admin_url('payment-pro-admin-packs'); ?>"><?php _e('Manage credit packs', 'payment_pro'); ?></a></li>
        <li class="nav-item"><a class="nav-link <?php if($route == 'payment-pro-admin-log'){ echo 'active';} ?>" href="<?php echo osc_route_admin_url('payment-pro-admin-log'); ?>"><?php _e('History of payments', 'payment_pro'); ?></a></li>
        <!-- <li <?php if($route == 'payment-pro-admin-subs'){ echo 'class="active"';} ?>><a href="<?php echo osc_route_admin_url('payment-pro-admin-subs'); ?>"><?php _e('Subscriptions', 'payment_pro'); ?></a></li> -->
        <?php osc_run_hook('payment_pro_admin_header_tab', $route); ?>
    </ul>
    <?php

