<?php
// Admin AdSense settings page
?><div class="wrap">
<h1><?php _e('AdSense Settings', 'indoor-tasks'); ?></h1>
<?php
if (isset($_POST['it_adsense_save']) && current_user_can('manage_options')) {
    update_option('indoor_tasks_adsense', wp_kses_post($_POST['adsense_code']));
    echo '<div class="updated"><p>AdSense code saved.</p></div>';
}
$adsense = get_option('indoor_tasks_adsense', '');
?>
<form method="post">
  <textarea name="adsense_code" style="width:100%;height:120px;" placeholder="Paste AdSense script here..."><?= esc_textarea($adsense) ?></textarea><br>
  <button type="submit" name="it_adsense_save" class="button button-primary">Save</button>
</form>
</div>
