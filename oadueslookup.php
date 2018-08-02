<?php
/*
 * Plugin Name: OA Dues Lookup
 * Plugin URI: https://github.com/oa-bsa/dues-lookup/
 * Description: Wordpress plugin to use in conjunction with OA LodgeMaster to allow members to look up when they last paid dues
 * Version: 1.0.7
 * Author: Dave Miller
 * Author URI: http://twitter.com/justdavemiller
 * Author Email: github@justdave.net
 * */

/*
 * Copyright (C) 2014 David D. Miller
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action( 'admin_menu', 'oadueslookup_plugin_menu' );
add_action( 'parse_request', 'oadueslookup_url_handler' );
add_action( 'plugins_loaded', 'oadueslookup_update_db_check' );
register_activation_hook( __FILE__, 'oadueslookup_install' );
register_activation_hook( __FILE__, 'oadueslookup_install_data' );
add_action( 'wp_enqueue_scripts', 'oadueslookup_enqueue_css' );
add_action( 'init', 'oadueslookup_plugin_updater_init' );

function oadueslookup_enqueue_css() {
    wp_register_style( 'oadueslookup-style', plugins_url('style.css', __FILE__) );
    wp_enqueue_style('oadueslookup-style');
}

function oadueslookup_plugin_updater_init() {
    /* Load Plugin Updater */
    require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'includes/plugin-updater.php' );

    /* Updater Config */
    $config = array(
        'base'      => plugin_basename( __FILE__ ), //required
        'repo_uri'  => 'http://www.justdave.net/dave/',
        'repo_slug' => 'oadueslookup',
    );

    /* Load Updater Class */
    new OADuesLookup_Plugin_Updater( $config );
}

global $oadueslookup_db_version;
$oadueslookup_db_version = 2;

function oadueslookup_create_table($ddl) {
    global $wpdb;
    $table = "";
    if (preg_match("/create table\s+(\w+)\s/i", $ddl, $match)) {
        $table = $match[1];
    } else {
        return false;
    }
    foreach ($wpdb->get_col("SHOW TABLES",0) as $tbl ) {
        if ($tbl == $table) {
            return true;
        }
    }
    // if we get here it doesn't exist yet, so create it
    $wpdb->query($ddl);
    // check if it worked
    foreach ($wpdb->get_col("SHOW TABLES",0) as $tbl ) {
        if ($tbl == $table) {
            return true;
        }
    }
    return false;
}

function oadueslookup_install() {
    /* Reference: http://codex.wordpress.org/Creating_Tables_with_Plugins */

    global $wpdb;
    global $oadueslookup_db_version;

    $dbprefix = $wpdb->prefix . "oalm_";

    //
    // CREATE THE TABLES IF THEY DON'T EXIST
    //

    // This code checks if each table exists, and creates it if it doesn't.
    // No checks are made that the DDL for the table actually matches,
    // only if it doesn't exist yet. If the columns or indexes need to
    // change it'll need update code (see below).

    $sql = "CREATE TABLE ${dbprefix}dues_data (
  bsaid            INT NOT NULL,
  max_dues_year    VARCHAR(4),
  dues_paid_date   DATE,
  level            VARCHAR(12),
  reg_audit_date   DATE,
  reg_audit_result VARCHAR(15),
  PRIMARY KEY (bsaid)
);";
    oadueslookup_create_table( $sql );

    //
    // DATABSE UPDATE CODE
    //

    // Check the stored database schema version and compare it to the version
    // required for this version of the plugin.  Run any SQL updates required
    // to bring the DB schema into compliance with the current version.
    // If new tables are created, you don't need to do anything about that
    // here, since the table code above takes care of that.  All that needs
    // to be done here is to make any required changes to existing tables.
    // Don't forget that any changes made here also need to be made to the DDL
    // for the tables above.

    $installed_version = get_option("oadueslookup_db_version");
    if (empty($installed_version)) {
        // if we get here, it's a new install, and the schema will be correct
        // from the initialization of the tables above, so make it the
        // current version so we don't run any update code.
        $installed_version = $oadueslookup_db_version;
        add_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }

    if ($installed_version < 2) {
        # Add a column for the Last Audit Date field
        $wpdb->query("ALTER TABLE ${dbprefix}dues_data ADD COLUMN reg_audit_date DATE");
    }

    // insert next database revision update code immediately above this line.
    // don't forget to increment $oadueslookup_db_version at the top of the file.

    if ($installed_version < $oadueslookup_db_version ) {
        // updates are done, update the schema version to say we did them
        update_option( "oadueslookup_db_version", $oadueslookup_db_version );
    }
}

function oadueslookup_update_db_check() {
    global $oadueslookup_db_version;
    if (get_site_option( 'oadueslookup_db_version' ) != $oadueslookup_db_version) {
        oadueslookup_install();
    }
    # do these here instead of in the starting data insert code because these
    # need to be created if they don't exist when the plugin gets upgraded,
    # too, not just on a new install.  add_option does nothing if the option
    # already exists, sets default value if it does not.
    add_option('oadueslookup_slug', 'oadueslookup');
    add_option('oadueslookup_dues_url', 'http://www.example.tld/paydues');
    add_option('oadueslookup_dues_register', '1');
    add_option('oadueslookup_dues_register_msg', 'You must register and login on the MyCouncil site before paying dues.');
    add_option('oadueslookup_update_url', 'http://www.example.tld/paydues');
    add_option('oadueslookup_update_option_text', 'Update Contact Information');
    add_option('oadueslookup_update_option_link_text', 'dues form');
    add_option('oadueslookup_help_email', 'duesadmin@example.tld');
    add_option('oadueslookup_last_import', '1900-01-01');
    add_option('oadueslookup_last_update', '1900-01-01');
    add_option('oadueslookup_max_dues_year', '2016');
}

function oadueslookup_insert_sample_data() {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    $wpdb->query("INSERT INTO ${dbprefix}dues_data " .
        "(bsaid,    max_dues_year, dues_paid_date, level,        reg_audit_date, reg_audit_result) VALUES " .
        "('123453','2013',         '2012-11-15',   'Brotherhood','1900-01-01',   'No Match Found'), " .
        "('123454','2014',         '2013-12-28',   'Ordeal',     '1900-01-01',   'Not Registered'), " .
        "('123455','2014',         '2013-12-28',   'Brotherhood','1900-01-01',   'Registered'), " .
        "('123456','2013',         '2013-07-15',   'Ordeal',     '1900-01-01',   'Registered'), " .
        "('123457','2014',         '2013-12-18',   'Brotherhood','1900-01-01',   'No Match Found'), " .
        "('123458','2013',         '2013-03-15',   'Vigil',      '1900-01-01',   'Not Registered'), " .
        "('123459','2015',         '2014-03-15',   'Ordeal',     '1900-01-01',   '')"
    );
    $wpdb->query($wpdb->prepare("UPDATE ${dbprefix}dues_data SET reg_audit_date=%s", get_option('oadueslookup_last_update')));
}

function oadueslookup_install_data() {
    global $wpdb;
    $dbprefix = $wpdb->prefix . "oalm_";

    oadueslookup_insert_sample_data();

}

require_once("includes/user-facing-lookup-page.php");
require_once("includes/management-options-page.php");

