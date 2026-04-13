<?php

/**
 * Working on the 4th signature box, will need to fix formatting and alignment, look into lines 550-560
 * treatment plan form.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Naina Mohamed <naina@capminds.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2012-2013 Naina Mohamed <naina@capminds.com> CapMinds Technologies
 * @copyright Copyright (c) 2017-2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once(__DIR__ . "/../../globals.php");
require_once("$srcdir/api.inc.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

formHeader("Form: Order to Implement and Carry Out Measures");
$returnurl = 'encounter_top.php';
$formid = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
$obj = $formid ? formFetch("form_order_to_implement_and_carry_out_measures", $formid) : array();

// Get the providers list.
 $ures = sqlStatement("SELECT id, username, fname, lname FROM users WHERE " .
  "authorized != 0 AND active = 1 ORDER BY lname, fname");
    ?>
<html>
<head>

<?php Header::setupHeader('datetime-picker'); ?>
<!--------------------------------------------------------------------------------------- container + name styles --------------------------------------------->
<style>
    .container {
        width: 100%;
        margin: auto;
        text-align: center;
    }
    .client_name{
        position: static;
        left: 5px;
        top: 300px;
        text-align: left;
    }
    .client_phone_num{
        position: static;
        text-align: left;
    }
    </style>

<script>
    
 $(function () {
  var win = top.printLogSetup ? top : opener.top;
  win.printLogSetup(document.getElementById('printbutton'));

  $('.datepicker').datetimepicker({
    <?php $datetimepicker_timepicker = false; ?>
    <?php $datetimepicker_showseconds = false; ?>
    <?php $datetimepicker_formatInput = false; ?>
    <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
    <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
  });
 });
</script>

<!--------------------------------------------------------------------------------------- text + textboxes + signatureboxes + alignment styles ---------------->
<style>
    .front_pg_text01{
        position: static;
        width: 100%;
        /* margin: auto; */
        left: 25px;
        top: 350px;
    }
    .front_pg_text02{
        position: static;
        left: 300px;
        top: 200px;
    }
    .signed_field{
        position: static;
        left: 425px;
        top: 20px;
    }
    .input_health_auth_sign{
        position: relative;
        left: 57px;
        top: -35px; 
    }
    .input_health_auth_printed_name{
        position: relative;
        left: 720px;
        bottom: 65px;   /* was 67px -> 65px */
    }
    .sig_date{
        position: relative;
        right: 503px;     /* was -1px ->  503*/
        top: -65px;     /* was -3px -> -65*/
    }
    .input_sig_date{
        position:relative;
        left: 135px;
        top: 5px;
    }
    .sig_month{
        position: relative;
        right: 377px;     /* was 377px -> 390 */
        top: -55px;       /* was -55px -> 55 */
    }
    .sig_month_box{
        position: relative;
        right: -10px;
        top: -6px;
    }
    .sig_year{
        position: relative;
        right: 207px;        /* was 125px -> 207 */
        top: 145px;          /* was 30px -> 145 */
    }
    .input_sig_year{
        position: relative;
        left: 29px;
        top: -1px;
    }
    .health_auth_city{
        position: relative;
        left: -304px;       /* was -330px -> -300 */
        top: -57px;
    }
    .health_auth_dir{
        position: relative; 
        left: -155px;       /* was -187 -> -155 */
        top: -62px;
    }
    .line-break{
        position:static;
        bottom: 95px;
        left: -195px;
    }
    
    .input_client_sign{
        position: relative;
        bottom: 50px;
        right: -820px;
    }
    .client_signed{
        position: relative;
        bottom: 5px;
        right: 525px;
    }
    .date{
        position: relative;
        left:200px; 
        top: 25px;
    }
    .client_sig_date{
        position:relative;
        left: 255px;    /* was at 182px -> 963 -> 209px -> 1 -> 250 -> 259 */
        bottom: -34px;    /* was at 39px -> -34px */
    }
    .witness_name{
        position: relative;
        left: -174px;       /* was at -165px -> 145px  -> -108 -> -191 -> */
        bottom: -10px;      /* was at -10px -> px -10px */
    }
    .client_sign2{
        position: relative;
        left: 50px;
        bottom: 100px; 
    }
    .witness_sig_date{
        position:absolute;
        right: -120px;       /* was at -49px -> -45 -> 50 -> -90 -> -120 */
        bottom: 47px;       /* was at 76px -> 65 -> 47*/
    }
    .client_print_name{
        position: relative;
        right: 12px;        /* was at 80px -> 5 -> 9 */
        bottom: -35px;
    }
    .input_client_print_name_date{
        position: inherit;
    }
    .client_print_name_date{
        position: relative;
        left: 799px;        /* was at 789px -> 799 */
        bottom: 43px;
    }
    .physicians_print_name{
        position: relative;
        left: -796px;       /* was -790px -> 750 */
        bottom: -8px;
    }
    .back_pg_text01{
        position: relative;
        left: -90px;
        top: 5px;
    }.sec_02_client_initials{
        position: relative;
        
    }
    .signature_box_test{
        position: relative;
        top: 150px;
        right: -250px;
    }
    .instructions_formations{
        position: relative;
        top: -12px;         /* was 105px -> 125 */
        right: -75px;       /* was -75px -> 275 */
    }
    .sec_06_a_physician_sig{
        position: relative;
        top: 5px;       /* was 5 */
        right: -19px;
    }
    .sig_x{
        position: relative;
        top: -3px;         /* was 100px -> -150 -> -192 -> 189 */
        right: -200px;       /* was 200px -> -200 -> -265 -> -280 */
    }
    .sec_06_a_checkbox_01_date{
        position: relative;
        left: 3px;
        top:  -9px;        /* was 35px -> 15 -> 2 -> -1 -> -7 -> -9 -> */
    }
    .sec_06_b_physician_sig{
        position: relative;
        top: 5px;      /* was at 5px -> 50 */
        right: -1px;   /* was at -25px -> 7 */
    }
    .sig_y{
        position: relative;
        top: 1px;         /* was 100px -> -101 -> -11 -> -35 -> 15 ->  */
        right: -194px;       /* was 200px -> 10 -> -25 -> -189 -> -200 -> 150 */
    }
    .sec_06_b_checkbox_01_date{
        position: relative;
        top:-9px;      /* was at 3px -> 30 -> -9 -> -15 -> -75 -> -9 */
        /*right: -9px;     was at -9px -> -69 -> */
        left: 3px;
    }
    /************************************************************* */
    .checkbox_02{
        position: relative;
        top: -5px;        /* was at -155 -> -85 -> -43 -> -23 -> -18 -> -5 */
        right: 385px;       /* was at 250 -> 295 ->  355 -> 340 -> 375 -> 385 */
    }
    .special_orders{
        position: relative;
        top: -12px; 
        right: 60px ;      /* was at -75px -> -95 -> 50 -> 25 -> 15  */
    }
    .sec_07_client_initials{
        position: relative;
        /*right: -190px;*/
        left: 485px;
        top: -48px;
    }
</style>

<!--------------------------------------------------------------------------------------- This customizes the sign pad, buttons, box, borders, etc ------------>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300&display=swap');
    .body {
        align-items: center;
        justify-content: center;
        height: 100vh;
        width: 100vw;
        background: #ececec;
    }
    .flex-row {
        display: flex;
    }
    .wrapper {
        border: 1px solid #4b00ff;
    }
    canvas#signature-pad {
        background: #fff;
        width: 100%;
        height: 100%;
        cursor:cell;
    }
    canvas#signature-pad02 {
        background: #fff;
        width: 100%;
        height: 100%;
        cursor:cell;
    }
    canvas#signature-pad03 {
        background: #fff;
        height: 100%;
        width: 100%;
        cursor: cell;
    }
    canvas#signature-pad04 {
        background: #fff;
        height: 100%;
        width: 100%;
        cursor: cell;
    }
    button#clear {
        height: 100%;
        background: #4b00ff;
        border: 1px solid transparent;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
    }
    button#clear span {
        transform: rotate(90deg);
        display: block;
    }
</style>

</head>

<body class="container">
<p><span class="title" align="center" style="font-size: 20px"><?php echo xlt(''); ?>Texas Department of State Health Services Order to Implement <br> and Carry out Measures For a Client w/ Tuberculosis</b></span><br/></p>
<br/>
<?php
echo "<form method='post' name='my_form' " .
  "action='$rootdir/forms/form_order_to_implement_and_carry_out_measures/save.php?id=" . attr_url($formid) . "'>\n";
?>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
<table_1  border="1">

<tr>
<table border="1" class="container">
<td align="left" class="client_name">To (Name) : </td>
        <td class="client_name">
            <label class="client_name"> <?php if (is_numeric($pid)) {
                $result = getPatientData($pid, "fname,lname,squad");
                echo text($result['fname']) . " " . text($result['lname']);
                                       }

                                       $patient_name = ($result['fname']) . " " . ($result['lname']);
                                        ?>
   </label>

      <tr>
      <td align="left"  class="forms"><?php echo xlt(' (Address) '); ?>:</td>
        <td class="forms">
            <label class="forms-data" align="left"> <?php if (is_numeric($pid)) {
                $result = getPatientData($pid, "*");
                echo text($result['Address']);
                                        }

                                        $patient_id = $result['Address'];
                                        ?>
   </label>
    <input type="hidden" name="client_address" value="<?php echo attr($patient_address);?>">
        </td>

    <tr>
      <td align="left" class="client_phone_num"><?php echo xlt(' (Phone #) '); ?>:</td>
        <td class="forms" align="left">
            <label class="forms-data" > <?php if (is_numeric($pid)) {
                $result = getPatientData($pid, "*");
                echo text($result['pid']);
                                        }

                                        $patient_id = $result['pid'];
                                        ?>
   </label>
    <input type="hidden" name="client_number" value="<?php echo attr($patient_id);?>">
        </td>
 </table>

 <section align="left">
    <td style="font-size: 17.5px;" type="text" class="front_pg_text01" ><?php echo xlt(''); ?>
    <p style="font-size: 17.5px;">
       I have reasonable cause to believe that your diagnosis, based on information available at this time,  is <b>(probably/ definitely)</b> TUBERCULOSIS, 
       which is a serious communicable disease. By the authority given to me by the State of Texas, Health and Safety Code, section 81.083,
       I hereby order you to do the following :
    </p></td>
</section>


<section align="left" width="20%">
    <td style="font-size: 17.5px;" type="text" class="front_pg_text02" >
        <p style="font-size: 17.5px;"> 
            <b>1.</b> Keep all appointments w/ clinical staff as instructed. <br>
            <b>2.</b> Follow all medical instructions from your physician or clinic staff regarding treatment for your tuberculosis.<br>
            <b>3.</b> Come to the Public Health Department Clinic or be at an agreed location and time for taking Directly Observed Therapy (DOT).<br>
            <b>4.</b> Do not return to work or school until authorized by your clinic physician.<br>
            <b>5.</b> Do not allow anyone other than those living w/ you or health department staff into your home until authorized.<br>
            <b>6.</b> Do not leave your home except as authorized by your clinic physician.<br>
            <b>7.</b> Special Orders - see reverse side.<br>
        </p>
        <p align="center" style="font-size: 19px;"><b><u>YOU MUST UNDERSTAND, INITIAL AND FOLLOW THE INSTRUCTIONS OF THIS ORDER</u></b></p>
<tr> 
    <p align="center" class="front_pg_text03" width="45%" type="text"><br> This order shall be effective until no longer need treatment for TUBERCULOSIS.</p>
    <p align="left" style="font-size: 17.5px;" type="text" class="front_pg_text03" width="20%" ><br>
        If you fail to follow these orders,cour proceedings may b inititated against you as dictated by the State law.After a hearing, the Court may order you to 
        be hospitalized at The Texas Center for Infectious Diseases in San Antonio or another facility. The Court also has the option to order you to go to treatment
        at a health clinic. The court proceedings could also include having you placed in the custody of the County Sherrif until the hearing.<br>
        <tr> <form><div class="signed_field"><label for="Signed">Signed :</label></form>
        <body_1 class="input_health_auth_sign">
            <div class="flex-row">
                <div class="wrapper">
                    <canvas id="signature-pad" width="450" height="189"></canvas>
                </div>
                <div class="clear-btn">
                    <button onclick=clearSignature1() type="button" id="clear1" ><span> Clear </span></button>
                </div>
            </div>
        </body_1>

        <textarea align="right" class="input_health_auth_printed_name" type="text" placeholder="Health Authority Printed Name" rows="1" cols="50"><?php echo text($obj["health_auth_name"]);?></textarea></tr> 
        
</tr>
</section>

<tr>
<section class="sig_month">
    <label>on this </lable><textarea  type="text" placeholder="e.g, 20th" rows="1" cols="7"><?php echo text($obj["sig_date"]);?></textarea>
<tr>
        <td align="left" method="post" width="280px" select name="Month" placeholder="month" class="sig_month" id="sig_month"><?php echo xlt('day of'); ?></td>
        <select name="Month" placeholder="select month" class="sig_month_box">
    <?php
        if (isset($_POST['sig_month'])) {
            if($_POST["sig_month"] == "January"){
                echo '<option value="January" selected="selected">January</option><option value="February">February</option>';
            }
            elseif($_POST["sig_month"] == "February"){
                echo '<option value="January">January</option><option value="February" selected="selected">February</option>';
            }
            elseif($_POST["sig_month"] == "March"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March" selected="selected">March</option>';
            }
            elseif($_POST["sig_month"] == "April"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April" selected="selected">April</option>';
            }
            elseif($_POST["sig_month"] == "May"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May" selected="selected">May</option>';
            }
            elseif($_POST["sig_month"] == "June"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June" selected="selected">June</option>';
            }
            elseif($_POST["sig_month"] == "July"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July" selected="selected">July</option>';
            }
            elseif($_POST["sig_month"] == "August"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July">July</option><option value="August" selected="selected">August</option>';
            }
            elseif($_POST["sig_month"] == "September"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July">July</option><option value="August">August</option><option value="September" selected="selected">September</option>';
            }
            elseif($_POST["sig_month"] == "October"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July">July</option><option value="August">August</option><option value="September">September</option><option value="October" selected="selected">October</option>';
            }
            elseif($_POST["sig_month"] == "November"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July">July</option><option value="August">August</option><option value="September">September</option><option value="October">October</option><option value="November" selected="selected">November</option>';
            }
            elseif($_POST["sig_month"] == "December"){
                echo '<option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July">July</option><option value="August">August</option><option value="September">September</option><option value="October">October</option><option value="November">November</option><option value="December" selected="selected">December</option>';
            }
        }
        else{
            echo '<option value="Choose Month">Choose Month</option><option value="January">January</option><option value="February">February</option><option value="March">March</option><option value="April">April</option><option value="May">May</option><option value="June">June</option><option value="July">July</option><option value="August">August</option><option value="September">September</option><option value="October">October</option><option value="November">November</option><option value="December">December</option>';
        }
        
    ?> 
    <textarea class="input_sig_year" type="text" placeholder="20XX" rows="1" cols="4"><?php echo text($obj["sig_year"]);?></textarea>
    </td>
</section>

<section class="health_auth_city">
    <label> Health Authority of </lable><textarea type="text" placeholder="city/county" rows="1" cols="27"><?php echo text($obj["authority_city"]);?></textarea> City/County.
</section>

<section class="health_auth_dir">
    <label>Acting Health Authority or Director of Public Health Region </lable><textarea type="text" placeholder="Health Director/Authority" rows="1" cols="40"><?php echo text($obj["auth_dir_region"]);?></textarea> .
</section>

<section class="line-break" style="font-size: 20px">
    <p><b>-----------------------------------------------------------------------------------------------------------------------------------------</b></p>
    <p class="line-break" style="font-size: 17.5px" align="center"> Please sign in the space provided below to show that you received these orders and understand them.<br>
    <b style="font-size: 20px">I hearby ackowledge that I received a copy of these orders and understand them.</b></p>
        <body_2 class="forms">
            <div class="flex-row">Signed: 
                <div class="wrapper">
                    <canvas id="signature-pad02" width="450" height="189"></canvas>
                </div>
            <div class="clear-btn">
                <button onclick=clearSignature2() type="button" id="clear2"><span>Clear</span></button>
            </div>
        </body_2>
        <section class="input_health_auth_sign">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.3.5/signature_pad.min.js" integrity="sha512-kw/nRM/BMR2XGArXnOoxKOO5VBHLdITAW00aG8qK4zBzcLVZ4nzg7/oYCaoiwc8U9zrnsO9UHqpyljJ8+iqYiQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
            <script>
                var canvas1 = document.getElementById("signature-pad");
                var canvas2 = document.getElementById("signature-pad02");

                function resizeCanvas(canvas) {
                    var ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                }
                window.onresize = function() {
                resizeCanvas(canvas1);
                resizeCanvas(canvas2);
                };

        var signaturePad1 = new SignaturePad(canvas1, {
            backgroundColor: 'rgb(250,250,250)'
        });
        var signaturePad2 = new SignaturePad(canvas2, {
            backgroundColor: 'rgb(250,250,250)'
        });
        function clearSignature1() {
            signaturePad1.clear();
        }

        function clearSignature2() {
            signaturePad2.clear();
        }
        document.getElementById("clear1").addEventListener('click', clearSignature1);
        document.getElementById("clear2").addEventListener('click', clearSignature2);
    </script>
    <tr>
        <lable class="client_sig_date">Date: <input type='text' size='7' class='datepicker' name='client_sig_date' id='client_sig_date' placeholder='date' <?php echo attr($disabled) ?>;></label>
        </form>
    </tr>
            </div>
    
    <tr>
        <form><div class="witness_name"><label for="witness name"> Witness </label>
         <textarea class="input_witness_sign" type="text" placeholder="Witness printed name" rows="1" cols="73"><?php echo text($obj["witness_name"]);?></textarea><div></tr><br></form>
         <lable class="witness_sig_date">Date: <input type='text' size='7' class='datepicker' name='witness_sig_date' id='witness_sig_date' placeholder='date'<?php echo attr($disabled) ?>;></label>
    </tr>
</section>  


<section class="container">

    <p><span class="title" align="center" style="font-size: 20px"><?php echo xlt(''); ?><b><u>Instructions for Client</u></b></span></p><br>
    <section>
        <tr><form><div class="client_print_name" align="left"><label for="client printed name" style="font-size: 17.5px"> Client's Name</label></form>
        <textarea class="input_client_print_name" type="text" placeholder="Client printed name" rows="1" cols="90"><?php echo text($obj["client_print_name"]);?></textarea><div></tr>
        <lable class="client_print_name_date" style="font-size: 17.5px">Date: <input type='text' size='7' class='datepicker' name='client_print_name_date' id='client_print_name_date' placeholder='date'<?php echo attr($disabled) ?>;></label>

        <form><div class="physicians_print_name"><label for="physicians name" style="font-size: 17.5px">Physician's Name </label></form>
        <textarea class="input_physicians_print_name" type="text" placeholder="Physician's Name" rows="1" cols="90"><?php echo text($obj["phyisician_print_name"]);?></textarea><div></tr>
    </section>

        <section> <!--  class="container" back_pg_text01 -->
            <p align="left" style="font-size: 18px;" type="text" class="instructions_formation" width="20%"><b>1.</b> Keep all appointments given to you by clinical staff.<br>
               Several appointments will be necessary to be sure your treatment is working. The treatment for Several appointments will be necessary to be sure your treatment
               is working. The treatment for tuberculosis is usually for six or more months. It is very important for keep all of the appointments made for you.
               <textarea class="sec_01_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_01_client_initials"]);?></textarea><br>

            <br><b>2.</b> Be sure you take your medicine for the treatment of your tuberculosis as your doctor or other clinic staff tells you. This means you must: keep all appointments 
                  at the clinic or other locations  that have been dicussed with you; take your medications as advised; provide sputum, urine, or blood specimen as requested;
                  report changes in your health; report when you move from where you live now and provide information about those w/ whome you spend a lot of time.
                  <textarea class="sec_02_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_02_client_initials"]);?></textarea><br>
            <br><b>3.</b> Come to the Public Health Department Clinic or be at an agreed place and time to take Directly Observed Therapy (DOT). DOT is a way we can be sure that you take
                   all the medicine needed to cure your give you your medications as ordered by the doctor.<br>
                   Location for DOT: 
                   <textarea class="sec_03_client_location" type="text" placeholder="DOT location" rows="1" cols="40"><?php echo text($obj["seco_03_client_location"]);?></textarea> 
                   <textarea class="sec_03_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_03_client_initials"]);?></textarea><br>
                   DOT will give you the best chance to cure your TB.<textarea class="sec_03_a_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_03_a_client_initials"]);?></textarea><br>

            <br><b>4.</b> Do not return to work or school until authorized by your clinic physician. <textarea class="sec_04_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_04_client_initials"]);?></textarea><br>
            <br><b>5.</b> Do not allow anyone other than those living w/ you or health department staff into your home until authorized. 
                <textarea class="sec_05_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_05_client_initials"]);?></textarea><br>

            <br><b>6.</b> Do not leave your home unless authorized by your clinic physician.  <textarea class="sec_06_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_06_client_initials"]);?></textarea><br>
                <input class="" type="checkbox"> You are or may be capable of spreading TB to others and must remain in your home or in a place where you will not expose others to the TB germ. When you take your
                 TB medicine, you may quickly decrease the likelihood of spreading TB to others. Your doctor will decide when this occurs at your follow-up appointments.</input>   
                 <!-- <textarea class="sec_06_a_client_chb01_initials" type="text" placeholder="(client initials)" rows="1" cols="12"><?php echo text($obj["sec_06_a_client_chb01_initials"]);?></textarea>-->
                 <!-- <input type='text' size='7' width='11' height='1' class='datepicker' name='sec_06_a_checkbox_01_date' id='sec_06_a_checkbox_01_date' placeholder='date-test2'> -->
        </section>
        <section>
                <div>
                <body_3 class="sec_06_a_physician_sig">

                    <div class="flex-row">
                        <div class="wrapper">
                            <canvas id="signature-pad03" width="450" height="189"></canvas>
                        </div>
                        <div class="clear-btn">
                            <button onclick=clearSignature3() type="button" id="clear3"><span> Clear </span></button>
                        </div>
                    
                    <div class ="sig_x"><textarea class="sec_06_a_client_chb01_initials" type="text" placeholder="(client initials)" rows="1" cols="12"><?php echo text($obj["sec_06_a_client_chb01_initials"]);?></textarea> 
                    <!-- <input type='text' size='7' class="sec_06_a_checkbox_01_date" width='11' height='1' class='datepicker' name='sec_06_a_checkbox_01_date' id='sec_06_a_checkbox_01_date' placeholder='date'></div> -->
                    <lable class="sec_06_a_checkbox_01_date">Date: <input type='text' size='7' class='datepicker' name='sec_06_a_checkbox_01_date' id='sec_06_a_checkbox_01_date' placeholder='date' <?php echo attr($disabled) ?>;></label>
                </div>
                </body_3>
                </div>
            <br>
            <div class="checkbox_02" ><input class="" type="checkbox"> You may attend school and/or go to work </input> </div>

                <!-- <section>  -->
                <div>
                <body_4 class="sec_06_b_physician_sig">
                    <div class="flex-row">
                        <div class="wrapper">
                            <canvas id="signature-pad04" width="450" height="189"></canvas>
                        </div>
                        <div class="clear-btn">
                            <button onclick=clearSignature4() type="button" id="clear4"><span> Clear </span></button>
                        </div>
                    <div class="sig_y"><textarea class="sec_06_b_client_chb02_initials" type="text" placeholder="(client initials)" rows="1" cols="12"><?php echo text($obj["sec_06_b_client_chb02_initials"]);?></textarea>
                    <!-- <input type='text' size='7' class="sec_06_b_checkbox_01_date" width='11' height='1' class='datepicker' name='sec_06_b_checkbox_01_date' id='sec_06_b_checkbox_01_date' placeholder='date'></div> -->
                    <lable class="sec_06_b_checkbox_01_date">Date: <input type='text' size='7' class='datepicker' name='sec_06_b_checkbox_01_date' id='sec_06_b_checkbox_01_date' placeholder='date' <?php echo attr($disabled) ?>;></label>
                    </div>
                    
                </body_4>
                </div>
        </section>

        <section>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.3.5/signature_pad.min.js" integrity="sha512-kw/nRM/BMR2XGArXnOoxKOO5VBHLdITAW00aG8qK4zBzcLVZ4nzg7/oYCaoiwc8U9zrnsO9UHqpyljJ8+iqYiQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
            <script>
                var canvas3 = document.getElementById("signature-pad03");   // would be 3
                var canvas4 = document.getElementById("signature-pad04");   // would be 4

                function resizeCanvas(canvas) {
                    var ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                }
                window.onresize = function() {
                resizeCanvas(canvas3);
                resizeCanvas(canvas4);
                };

        var signaturePad3 = new SignaturePad(canvas3, {
            backgroundColor: 'rgb(250,250,250)'
        });
        var signaturePad4 = new SignaturePad(canvas4, {
            backgroundColor: 'rgb(250,250,250)'
        });

        function clearSignature3() {
            signaturePad3.clear();
        }

        function clearSignature4() {
            signaturePad4.clear();
        }

        document.getElementById("clear3").addEventListener('click', clearSignature3);
        document.getElementById("clear4").addEventListener('click', clearSignature4);
    </script>

    <br><section class="special_orders"><b>7. Special orders </b><textarea class="sec_07_special_orders" type="text" rows="3" cols="110" placeholder="Additional notes..."><?php echo text($obj["sec_07_special_orders"]);?></textarea></section> 
    <textarea class="sec_07_client_initials" type="text" placeholder="client's initials" rows="1" cols="13"><?php echo text($obj["sec_07_client_initials"]);?></textarea>
    </p>
    </section>
    <br>
    <p style="font-size: 19px" class="container" type="text"><b> TB-410 Order to Implement and Carry Out Meaasures for a Client w/ TB - Revised 1/2020 </b></p>


<table align='center'>
  <td colspan='3' nowrap style='font-size:8pt'>
   &nbsp;
    </td>
    <tr>
        <td align="left colspan="3" style="padding-bottom:7px;"></td>
    </tr>

    <tr>
        <td align="left colspan="3" style="padding-bottom:7px;"></td>
    </tr>
    <tr>
        <td></td>
    <td><input type='submit'  value='<?php echo xlt('Save');?>' class="button-css">&nbsp;
    <input type='button' value='<?php echo xla('Print'); ?>' id='printbutton' />&nbsp;
    <input type='button' class="button-css" value='<?php echo xla('Cancel'); ?>'
onclick="parent.closeTab(window.name, false)" />

</td>
    </tr>
</table>
</form>

<?php
formFooter();
?>
    <!-- <tr>
        <td align="left colspan="3" style="padding-bottom:7px;"></td>
    </tr>
    <tr>
        <td align="left colspan="3" style="padding-bottom:7px;"></td>
    </tr>
    <tr>
        <td></td>
    <td><input type='submit'  value='<?php echo xla('Save');?>' class="button-css">&nbsp;
  <input type='button' value='<?php echo xla('Print'); ?>' id='printbutton' />&nbsp;
  <input type='button' class="button-css" value='<?php echo xla('Cancel');?>'
 onclick="parent.closeTab(window.name, false)" /></td>
    </tr>
</table>
</form>
<?php
formFooter();
?> -->
