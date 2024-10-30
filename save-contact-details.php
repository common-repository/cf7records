<?php
/*
 * Plugin Name: cf7 Records
 * Plugin URI: http://myplugin.nexuslinkservices.com
 * Description: This Plugins Saves Contact form 7 Form submission into Database so you can Preview. You must install contact form 7 in order to use this plugin
 * Version: 1.1.0
 * Author: NexusLink Services
 * Author URI: http://nexuslinkservices.com
 * License: GPL2
 * */

class clsSaveDetails {


    function __construct() {
        add_action('wpcf7_submit', array($this, 'action_savedata_submit'), 10, 2);

        add_action('admin_menu', array($this, 'savedata_px_register_admin_menu'));

        add_action('admin_head', array($this, 'savedata_px_backend_styles'));

        add_action('admin_enqueue_scripts', array($this, 'savedata_px_backend_styles'));

        add_action('admin_init', array($this, 'savedata_child_plugin_has_parent_plugin'));

        add_action('wp_ajax_nexus_cf7_options', array($this, 'nexus_cf7_options'));

        add_action('wp_ajax_nopriv_cf7_insta_options',array($this, 'nexus_cf7_options'));

        /*add_action('wp_ajax_nexus_cf7export_options', array($this, 'nexus_cf7export_options'));
        add_action('wp_ajax_nopriv_cf7export_insta_options',array($this, 'nexus_cf7export_options'));*/

        // embed the javascript file that makes the AJAX request
        wp_enqueue_script( 'my-ajax-request', plugins_url('/libs/savedatascripts-ajax.js', __FILE__), array( 'jquery' ) );

        // declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
        wp_localize_script( 'my-ajax-request', 'MyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );



    }

    function nexus_cf7_options() {
        global $wpdb;
        $table_name = $wpdb->prefix . "savedata";
        $data = $_POST['data'];

        $where_del = array('form_submit_id' => $data['deleteid']);


        $wpdb->delete($table_name, $where_del);

        die();
    }

    function savedata_px_register_admin_menu() {
        add_menu_page( 'Submitted Forms', 'CF7 Records', 'manage_options', basename(__FILE__), array($this, 'px_options_page'),'dashicons-list-view');
    }

    function savedata_child_plugin_has_parent_plugin() {
        if (is_admin() && current_user_can('activate_plugins') && !is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            add_action('savedata_admin_notices', array($this, 'savedata_child_plugin_notice'));

            deactivate_plugins(plugin_basename(__FILE__));

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }

    function savedata_child_plugin_notice() {
        ?><div class="error"><p>This plugin requires the Contact form 7 plugin to be installed and active.</p></div><?php
    }

    function savedata_px_backend_styles() {

        wp_enqueue_style('datatables',plugin_dir_url(__FILE__).'/libs/datatables.min.css');
        wp_enqueue_style('savedatasave_styles',plugin_dir_url(__FILE__).'/libs/savedatastyles.css');


        wp_enqueue_script('savedatasave_script',plugin_dir_url(__FILE__).'/libs/savedatascripts.js');
        wp_enqueue_script('datatables',plugin_dir_url(__FILE__).'/libs/datatables.min.js');

    }

    function action_savedata_submit($instance, $result) {

        if($result['status'] === 'validation_failed'){
            return;
        }

        global $wpdb;

        $post = $_POST;

        $id = ($instance->id);
        $title = ($instance->title);
        $subject = ($instance->mail['subject']);
        $recipient = ($instance->mail['recipient']);
        $email = $instance->mail['body'];
        $email_2 = $instance->mail_2['body'];

        $subject_2 = '';
        $recipient_2 = '';

        if ($instance->mail_2['active']) {
            $subject_2 = ($instance->mail_2['subject']);
            $recipient_2 = ($instance->mail_2['recipient']);
        }

        $max_submit_id = $wpdb->get_row('SELECT MAX(form_submit_id) FROM ' . $wpdb->prefix . 'savedata', ARRAY_A);

        $max_submit_id = $max_submit_id['MAX(form_submit_id)'] + 1;


        $browser=$_SERVER['HTTP_USER_AGENT'];
        foreach ($post as $key => $p) {

            if (strpos($key, '_') === 0) {
                continue;
            }

            $insert_array = array('sv_data' => $id, 'sv_title' => $title, 'sv_subject' => $subject, 'sv_recipient' => $recipient, 'sv_subject_2' => $subject_2, 'sv_recipient_2' => $recipient_2, 'key' => $key, 'value' => $p, 'mail' => $email, 'mail_2' => $email_2, 'form_submit_id' => $max_submit_id, 'ipaddress' => $_SERVER['REMOTE_ADDR'], 'useragent' => $browser);

            $wpdb->insert($wpdb->prefix . 'savedata', $insert_array);
        }
    }



    function px_options_page() {

        global $wpdb;

        $query = 'SELECT DISTINCT sv_data, sv_title FROM ' . $wpdb->prefix . 'savedata GROUP BY sv_title' ;

        $result = $wpdb->get_results($query, OBJECT_K);

        ?>
        <div class="wrap">

            <h1>CF7 Records</h1>
            <h3>Select contact form</h3>

            <form method="get" action="">

                <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">

                <select name="pxcf7_form_id" title="Select Form" class="input-full">
                    <?php foreach ($result as $row): ?>
                        <option value="<?php echo $row->sv_data ?>" <?php if(($row->sv_data==$_GET['pxcf7_form_id']) || ($row->sv_data==$_GET['sv_data'])){ echo 'selected="selected"';}?>  ><?php echo $row->sv_title ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button button-primary button-large" value="View Form">
            </form>

            <!-- when post [show table] -->

            <?php
            if (isset($_GET['pxcf7_form_id'])) {

                $form_id = $_GET['pxcf7_form_id'];
                $query = 'SELECT *  FROM ' . $wpdb->prefix . 'savedata WHERE sv_data="' . $form_id . '" GROUP BY form_submit_id ORDER BY date DESC';
                $result = $wpdb->get_results($query);

                ?>
                <?php
                if (isset($_GET['export_csv'])) {
                    ob_end_clean();
                    $csv_fields=array();
                    $csv_fields[]="Date";
                    $csv_fields[]="Submitted From";
                    $counter=0;
                    foreach ($result as $key => $value):
                        $counter++;
                        if($counter==1) {
                            $query = 'SELECT `key`, value, id FROM ' . $wpdb->prefix . 'savedata WHERE form_submit_id="' . $value->form_submit_id . '"';
                            $data = $wpdb->get_results($query);
                            foreach ($data as $item):
                                $queryD = 'SELECT value FROM ' . $wpdb->prefix . 'savedata WHERE id="' . $item->id . '"';
                                $dataD = $wpdb->get_results($queryD);
                                $csv_fields[]=str_replace("-"," ",ucfirst($item->key));
                            endforeach;
                        }
                    endforeach;

                    $output_filename = 'MyReport_Contact_Messages.csv';
                    $output_handle = @fopen( 'php://output', 'w' );

                    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
                    header( 'Content-Description: File Transfer' );
                    header( 'Content-type: text/csv' );
                    header( 'Content-Disposition: attachment; filename=' . $output_filename );
                    header( 'Expires: 0' );
                    header( 'Pragma: public' );


                    fputcsv( $output_handle, $csv_fields );

                    foreach ($result as $one_result) {
                        $newarray = array();
                        $newarray[]=$one_result->date;
                        $newarray[]=$one_result->ipaddress;

                        $query = 'SELECT value FROM ' . $wpdb->prefix . 'savedata WHERE form_submit_id="' . $one_result->form_submit_id . '"';
                        $data = $wpdb->get_results($query);

                        foreach ($data as $item) {
                            $newarray[] = $item->value;
                        }
                        fputcsv($output_handle, $newarray);

                    }

                    fclose( $output_handle );
                    die();

                }
                ?>
                <div class="container">

                    <form method="get">
                        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
                        <input type="hidden" name="pxcf7_form_id" value="<?php echo $_GET['pxcf7_form_id'] ?>">
                        <input type="hidden" value="true" name="export_csv">
                        <button type="submit" class="button button-primary button-large export_to_csv" style="float: right;margin-bottom: 10px;">Export to CSV</button>
                    </form>


                    <form id="frm-example" action="/nosuchpage" method="POST">
                        <table id="example" class="table table-hover table-striped display select maintable">
                        <thead>
                        <tr>
                            <th><input name="select_all" value="1" type="checkbox"></th>
                            <th>Date</th>
                            <?php
                            $counter=0;
                            foreach ($result as $key => $value):
                                $counter++;
                                if($counter==1) {
                                    $query = 'SELECT `key`, value, id FROM ' . $wpdb->prefix . 'savedata WHERE form_submit_id="' . $value->form_submit_id . '"';
                                    $data = $wpdb->get_results($query);
                                    $cntH=0;
                                    foreach ($data as $item):
                                        $queryD = 'SELECT value FROM ' . $wpdb->prefix . 'savedata WHERE id="' . $item->id . '"';
                                        $dataD = $wpdb->get_results($queryD);
                                        echo '<th>' . str_replace("-"," ",ucfirst($item->key)) . '</th>';
                                            $cntH++;
                                            if($cntH>3){
                                                break;
                                            }
                                    endforeach;
                                }
                            endforeach;
                            ?>
                            <!--<th>View Details</th>-->
                        </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result as $key => $value): ?>
                                <tr id="delete_<?php echo $value->form_submit_id ?>">
                                    <td><?php echo $value->form_submit_id ?></td>
                                    <td><a href="<?php echo admin_url(); ?>admin.php?page=save-contact-details.php&sv_data=<?php echo $form_id ?>&form_submit_id=<?php echo $value->form_submit_id ?>" style="color: #0A246A"><?php echo $value->date ?></a></td>
                                    <?php

                                    $query2 = 'SELECT `key`, value, id FROM ' . $wpdb->prefix . 'savedata WHERE form_submit_id="' . $value->form_submit_id . '"'; ?>
                                    <?php
                                    $data2 = $wpdb->get_results($query2);
                                    $cntH=0;
                                    foreach ($data2 as $item2):
                                        $queryD2 = 'SELECT value FROM ' . $wpdb->prefix . 'savedata WHERE id="' . $item2->id . '"';
                                        $dataD = $wpdb->get_results($queryD2);
                                        echo '<td>'. wp_trim_words( stripslashes($item2->value), 30, '...' ).'</td>';
                                            $cntH++;
                                            if($cntH>3){
                                                break;
                                            }
                                    endforeach;
                                    ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </form>
                </div>
                <?php
            }
            ?>

            <!-- EXECUTE BELOW WHEN POST -->
            <?php
            if (isset($_GET['sv_data']) && $_GET['form_submit_id']) {

                $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'savedata WHERE sv_data = "' . $_GET['sv_data'] . '" AND form_submit_id = "' . $_GET['form_submit_id'] . '"');
                //echo "<pre/>";
                //print_r($result);
                ?>
                <br>
                <div class="cf7_item_wrapper">
                    <h2>User Details</h2>
                    <div class="row">
                        <label>Date</label>
                        <label style="width: 75%; float: right"><?php echo $result[0]->date ?></label>
                        <div style="clear: both"></div>
                    </div>
                    <?php
                    foreach ($result as $item) {
                        ?>
                        <div class="row">
                            <label><?php echo str_replace("-"," ",ucfirst($item->key)); ?></label>
                            <label style="width: 75%; float: right"><?php echo $item->value ?></label>
                            <div style="clear: both"></div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <br>
                <div class="cf7_item_wrapper">
                    <h2>Meta</h2>
                    <?php
                    $cnt=0;
                    foreach ($result as $key => $rowmeta) :

                        if($cnt==0) {
                            ?>
                            <div class="row">
                                <label>Ip Address</label>
                                <label style="width: 75%; float: right"><?php echo $rowmeta->ipaddress ?></label>
                                <div style="clear: both"></div>
                            </div>
                            <div class="row">
                                <label>User-Agent</label>
                                <label style="width: 75%; float: right"><?php echo $rowmeta->useragent ?></label>
                                <div style="clear: both"></div>
                            </div>
                            <?php
                        }
                        $cnt++;
                        break;
                    endforeach;
                    ?>
                </div>

            <?php }
            ?>
            <!--     close div wrap-->
        </div>
        <?php
    }

    function ajax_delete_enqueues() {
        wp_localize_script( 'ajax-search', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
    }
}

new clsSaveDetails();

register_activation_hook(__FILE__, 'savedata_create_table');

function savedata_create_table() {

    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    //customers
    $table_name = $wpdb->prefix . 'savedata';
    if ($wpdb->get_var('SHOW TABLES LIKE "' . $table_name . '"') != $table_name) {
        $sql = 'CREATE TABLE ' . $table_name . '(
			id INTEGER UNSIGNED AUTO_INCREMENT,
			sv_data INTEGER,
			form_submit_id INTEGER,
			mail TEXT,
			mail_2 TEXT,
			sv_title VARCHAR(255),
			sv_subject VARCHAR(255),
			sv_subject_2 VARCHAR(255),
			sv_recipient VARCHAR(255),
			sv_recipient_2 VARCHAR(255),
			`key` VARCHAR(255),
			value TEXT,
			date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			ipaddress VARCHAR(255),
			useragent TEXT,			
			PRIMARY KEY  (id) )';
        dbDelta($sql);
    }
}

function register_cf7_untable() {
    global $wpdb;
    $table_name = $wpdb->prefix . "savedata";
    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query($sql);
    delete_option("my_plugin_db_version");
}

register_uninstall_hook(__FILE__, 'register_cf7_untable');