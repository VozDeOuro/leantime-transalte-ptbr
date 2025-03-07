<?php
foreach ($__data as $var => $val) {
    $$var = $val; // necessary for blade refactor
}
    $user = $tpl->get("user");
?>

<?php $tpl->dispatchTplEvent('beforePageHeaderOpen'); ?>
<div class="pageheader">
    <?php $tpl->dispatchTplEvent('afterPageHeaderOpen'); ?>
    <div class="pagetitle">
        <h1><?php echo $tpl->language->__("headlines.create_account"); ?></h1>
    </div>
    <?php $tpl->dispatchTplEvent('beforePageHeaderClose'); ?>
</div>
<?php $tpl->dispatchTplEvent('afterPageHeaderClose'); ?>
<div class="regcontent">
    <?php $tpl->dispatchTplEvent('afterRegcontentOpen'); ?>
    <form id="resetPassword" action="" method="post">
        <?php $tpl->dispatchTplEvent('afterFormOpen'); ?>

        <?php echo $tpl->displayInlineNotification(); ?>

        <p><?php echo $tpl->language->__("text.welcome_to_leantime"); ?><br /><br /></p>

        <div class="">
            <input type="text" name="firstname" id="firstname" placeholder="<?php echo $tpl->language->__("input.placeholders.firstname"); ?>" value="<?=$tpl->escape($user['firstname']); ?>" />

        </div>
        <div class="">
            <input type="text" name="lastname" id="lastname" placeholder="<?php echo $tpl->language->__("input.placeholders.lastname"); ?>" value="<?=$tpl->escape($user['lastname']); ?>" />

        </div>
        <div class="">
            <input type="password" name="password" id="password" placeholder="<?php echo $tpl->language->__("input.placeholders.enter_new_password"); ?>" />
            <span id="pwStrength" style="width:100%;"></span>
        </div>
        <div class=" ">
            <input type="password" name="password2" id="password2" placeholder="<?php echo $tpl->language->__("input.placeholders.confirm_password"); ?>" />
        </div>
        <small><?=$tpl->__('label.passwordRequirements') ?></small><br /><br />
        <div class="">
            <input type="hidden" name="saveAccount" value="1" />
            <?php $tpl->dispatchTplEvent('beforeSubmitButton'); ?>
            <input type="submit" name="createAccount" value="<?php echo $tpl->language->__("buttons.create_account"); ?>" />

        </div>
        <?php $tpl->dispatchTplEvent('beforeFormClose'); ?>
    </form>
    <?php $tpl->dispatchTplEvent('beforeRegcontentClose'); ?>
</div>

<script>
    leantime.usersController.checkPWStrength('password');
</script>
