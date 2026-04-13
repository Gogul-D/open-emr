<?php

/**
 * Order to Implement and Carry out Measures for Tuberculosis form.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Naina Mohamed <naina@capminds.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2012-2013 Naina Mohamed <naina@capminds.com> CapMinds Technologies
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/forms.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;

if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
    CsrfUtils::csrfNotVerified();
}

if (!$encounter) { // comes from globals.php
    die(xlt("Internal error: we do not seem to be in an encounter!"));
}

$id = (int) (isset($_GET['id']) ? $_GET['id'] : '');

$sets ="pid = ?                                                 //* Section 01 (To-whom --> Client) *//
  groupname = ?,
  user = ?,
  authorized = ?,
  activity = 1, 
  date = NOW(),
  client_name = ?,
  client_address = ?,
  client_phone_num = ?,
  health_auth_sig = ?,                                          //* Section 02 (Health authority) *//
  health_auth_name = ?,
  sig_date = ?,
  sig_month = ?,
  sig_year = ?,
  authority_city = ?,
  auth_dir_region = ?,
  client_sig = ?,                                               //* Section 03 (Client's Acknowledgement) *//
  client_sig_date = NOW(),
  witness_name = ?,
  witness_date = NOW(),
  client_print_name = ?,                                        //* Section 04 (Instructions for Client) *//
  client_print_name_date = NOW(),
  sec_01_client_initials = ?,
  sec_02_client_initials = ?,
  sec_03_client_location = ?,
  sec_03_client_initials = ?,
  sec_03_a_client_initials = ?,
  sec_04_client_initials = ?,
  sec_05_client_inititals = ?,
  sec_06_a_client_initials = ?,
  sec_06_a_client_checkbox_01 = ?,
  sec_06_a_client_chb01_initials = ?,
  sec_06_a_physician_sig = ?,
  sec_06_a_checkbox_01_date = ?,
  sec_06_b_client_checkbox_02 = ?,
  sec_06_b_client_chb02_initials = ?,
  sec_06_b_physician_sig = ?,
  sec_06_b_checkbox_02_date = ?,
  sec_07_special_orders = ?,
  sec_07_client_initials = ?";

if (empty($id)) {
    $newid = sqlInsert(
        "INSERT INTO form_order_to_implement_and_carry_out_measures SET $sets",
        [
            $_SESSION["pid"],
            $_SESSION["authProvider"],
            $_SESSION["authUser"],
            $userauthorized,
            $_POST["client_name"],
            $_POST["client_phone_num"],
            $_POST["client_address"],
            $_POST["health_auth_sig"],
            $_POST["health_auth_name"],
            $_POST["sig_date"],
            $_POST["sig_month"],
            $_POST["sig_year"],
            $_POST["authority_city"],
            $_POST["auth_dir_region"],
            $_POST["client_sig"],
            $_POST["client_sig_date"],
            $_POST["witness_name"],
            $_POST["witness_date"],
            $_POST["client_print_name"],
            $_POST["client_print_name_date"],
            $_POST["physician_print_name"],
            $_POST["sec_01_client_initials"],
            $_POST["sec_02_client_initials"],
            $_POST["sec_03_client_location"],
            $_POST["sec_03_client_initials"],
            $_POST["sec_03_a_client_inititals"],
            $_POST["sec_04_client_inititals"],
            $_POST["sec_05_client_initials"],
            $_POST["sec_06_a_client_inititals"],
            $_POST["sec_06_a_client_checkbox_01"],
            $_POST["sec_06_a_client_chb01_initials"],
            $_POST["sec_06_a_physician_sig"],
            $_POST["sec_06_a_checkbox_01_date"],
            $_POST["sec_06_b_client_checbox_02"],
            $_POST["sec_06_b_client_chb02_initials"],
            $_POST["sec_06_b_physician_sig"],
            $_POST["sec_06_b_checkbox_02_date"],
            $_POST["sec_07_special_orders"],
            $_POST["sec_07_client_initials"],
        ]
    );

    addForm($encounter, "Treatment form for Tuberculosis", $newid, "order_to_implement_and_carry_out_measures", $pid, $userauthorized);
} else {
    sqlStatement(
        "UPDATE form_order_to_implement_and_carry_out_measures SET $sets WHERE id = ?",
        [
            $_SESSION["pid"],
            $_SESSION["authProvider"],
            $_SESSION["authUser"],
            $userauthorized,
            $_POST["client_name"],
            $_POST["client_phone_num"],
            $_POST["client_address"],
            $_POST["health_auth_sig"],
            $_POST["health_auth_name"],
            $_POST["sig_date"],
            $_POST["sig_month"],
            $_POST["sig_year"],
            $_POST["authority_city"],
            $_POST["auth_dir_region"],
            $_POST["client_sig"],
            $_POST["client_sig_date"],
            $_POST["witness_name"],
            $_POST["witness_date"],
            $_POST["client_print_name"],
            $_POST["client_print_name_date"],
            $_POST["physician_print_name"],
            $_POST["sec_01_client_initials"],
            $_POST["sec_02_client_initials"],
            $_POST["sec_03_client_location"],
            $_POST["sec_03_client_initials"],
            $_POST["sec_03_a_client_inititals"],
            $_POST["sec_04_client_inititals"],
            $_POST["sec_05_client_initials"],
            $_POST["sec_06_a_client_inititals"],
            $_POST["sec_06_a_client_checkbox_01"],
            $_POST["sec_06_a_client_chb01_initials"],
            $_POST["sec_06_a_physician_sig"],
            $_POST["sec_06_a_checkbox_01_date"],
            $_POST["sec_06_b_client_checbox_02"],
            $_POST["sec_06_b_client_chb02_initials"],
            $_POST["sec_06_b_physician_sig"],
            $_POST["sec_06_b_checkbox_02_date"],
            $_POST["sec_07_special_orders"],
            $_POST["sec_07_client_initials"],
            $id
        ]
    );
}

formHeader("Redirecting....");
formJump();
formFooter();
