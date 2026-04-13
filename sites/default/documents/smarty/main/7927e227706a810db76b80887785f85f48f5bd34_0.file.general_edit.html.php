<?php
/* Smarty version 4.3.4, created on 2025-12-26 04:37:09
  from 'C:\wamp64\www\phix-openemr\templates\prescription\general_edit.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_694e73653054d1_02512759',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '7927e227706a810db76b80887785f85f48f5bd34' => 
    array (
      0 => 'C:\\wamp64\\www\\phix-openemr\\templates\\prescription\\general_edit.html',
      1 => 1766748760,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_694e73653054d1_02512759 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.headerTemplate.php','function'=>'smarty_function_headerTemplate',),1=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xlt.php','function'=>'smarty_function_xlt',),2=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xla.php','function'=>'smarty_function_xla',),3=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.amcCollect.php','function'=>'smarty_function_amcCollect',),4=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\vendor\\smarty\\smarty\\libs\\plugins\\function.html_options.php','function'=>'smarty_function_html_options',),5=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\vendor\\smarty\\smarty\\libs\\plugins\\function.html_radios.php','function'=>'smarty_function_html_radios',),6=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xlj.php','function'=>'smarty_function_xlj',),7=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.datetimepickerSupport.php','function'=>'smarty_function_datetimepickerSupport',),));
?>
<!DOCTYPE html>
<html>
<head>

    <?php echo smarty_function_headerTemplate(array('assets'=>'datetime-picker|select2'),$_smarty_tpl);?>


<?php echo '<script'; ?>
>


    function my_process_lookup(drug, rxcode = '') {
      // Pass the variable
      let newOption = new Option(drug, drug, true, true);
      $("#rxnorm_drugcode").val(rxcode);
      $('#drug').append(newOption).trigger('change');
      $('#hiddendiv').hide();
      $("#hiddendiv").html( "&nbsp;" );
    }

<?php echo '</script'; ?>
>
<!---Gen Look up-->

<?php echo '<script'; ?>
>

 // This holds all the default drug attributes.
 // This was html escaped previously

 var drugopts = [<?php if (!empty($_smarty_tpl->tpl_vars['DRUG_ATTRIBUTES']->value)) {
echo $_smarty_tpl->tpl_vars['DRUG_ATTRIBUTES']->value;
}?>];


 // Helper to choose an option from its value.
 function selchoose(sel, value) {
  var o = sel.options;
  for (let i = 0; i < o.length; ++i) {
   o[i].selected = (o[i].value == value);
  }
 }

 // Fill in default values when a drop-down drug is selected.
 function drugselected(sel) {
  var f = document.forms[0];
  var i = f.drug_id.selectedIndex - 1;
  if (i >= 0) {
   var d = drugopts[i];
   var newOption = new Option(d[0], d[0], true, true);
   $('#drug').append(newOption).trigger('change');
   selchoose(f.form, d[1]);
   f.dosage.value = d[2];
   f.size.value = d[3];
   f.rxnorm_drugcode.value = d[11];
   selchoose(f.unit, d[4]);
   selchoose(f.route, d[5]);
   selchoose(f.interval, d[6]);
   selchoose(f.substitute, d[7]);
   f.quantity.value = d[8];
   f.disp_quantity.value = d[8];
   selchoose(f.refills, d[9]);
   f.per_refill.value = d[10];
  }
 }

 // Invoke the popup to dispense a drug.
 function dispense() {
  var f = document.forms[0];
  dlgopen('interface/drugs/dispense_drug.php' +
   '?drug_id=' + <?php echo js_url($_smarty_tpl->tpl_vars['prescription']->value->get_drug_id());?>
 +
   '&prescription=' + encodeURIComponent(f.id.value) +
   '&quantity=' + encodeURIComponent(f.disp_quantity.value) +
   '&fee=' + encodeURIComponent(f.disp_fee.value),
   '_blank', 400, 200);
 }

 function quantityChanged() {
  var f = document.forms[0];
  f.per_refill.value = f.quantity.value;
  if (f.disp_quantity) {
   f.disp_quantity.value = f.quantity.value;
  }
 }

<?php echo '</script'; ?>
>

</head>
<body id="prescription_edit">
<div class="container">
    <?php if ($_smarty_tpl->tpl_vars['HL7_STATUS_MESSAGE']->value) {?>
    <div class="alert alert-<?php echo (($tmp = $_smarty_tpl->tpl_vars['HL7_STATUS_TYPE']->value ?? null)===null||$tmp==='' ? 'info' ?? null : $tmp);?>
 alert-dismissible fade show pyxis-status-alert" role="alert" style="font-size: 1.3em; padding: 1.2em; margin: 1em auto; text-align: center; max-width: 700px; border: 2px solid; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <span style="font-size: 1em;"><?php echo $_smarty_tpl->tpl_vars['HL7_STATUS_MESSAGE']->value;?>
</span>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="font-size: 1.2em;">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php }?>
    <form name="prescribe" id="prescribe"  method="post" action="<?php echo $_smarty_tpl->tpl_vars['FORM_ACTION']->value;?>
" >
        <table>
            <tr>
                <td class="title font-weight-bold"><?php echo smarty_function_xlt(array('t'=>'Add'),$_smarty_tpl);?>
/<?php echo smarty_function_xlt(array('t'=>'Edit'),$_smarty_tpl);?>
&nbsp;</td>
                <td>
                    <a id="save" href=# onclick="submitfun();" class="btn btn-primary btn-sm btn-save"><?php echo smarty_function_xlt(array('t'=>'Save'),$_smarty_tpl);?>
</a>
                    <?php if (!empty($_smarty_tpl->tpl_vars['DRUG_ARRAY_VALUES']->value)) {?>
                    &nbsp; &nbsp; &nbsp; &nbsp;
                    <?php if ($_smarty_tpl->tpl_vars['prescription']->value->get_refills() >= $_smarty_tpl->tpl_vars['prescription']->value->get_dispensation_count()) {?>
                        <input type="submit" name="disp_button"class='btn btn-primary btn-sm my-0 mr-1 ml-1' value="<?php echo smarty_function_xla(array('t'=>'Save and Dispense'),$_smarty_tpl);?>
" />
                        <input class="input-sm" type="text" name="disp_quantity" size="2" maxlength="10" value="<?php echo attr($_smarty_tpl->tpl_vars['DISP_QUANTITY']->value);?>
" />
                        units, <?php echo text($_smarty_tpl->tpl_vars['GBL_CURRENCY_SYMBOL']->value);?>

                        <input class="input-sm" type="text" name="disp_fee" size="5" maxlength="10" value="<?php echo attr($_smarty_tpl->tpl_vars['DISP_FEE']->value);?>
" />
                    <?php } else { ?>&nbsp;
                        <?php echo smarty_function_xlt(array('t'=>'prescription has reached its limit of'),$_smarty_tpl);?>
 <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->get_refills());?>
 <?php echo smarty_function_xlt(array('t'=>'refills'),$_smarty_tpl);?>
.
                    <?php }?>
                <?php }?>
                <a id="back" class='btn btn-secondary btn-sm btn-back' href="controller.php?prescription&list&id=<?php echo attr_url($_smarty_tpl->tpl_vars['prescription']->value->patient->id);?>
"><?php echo smarty_function_xlt(array('t'=>'Back'),$_smarty_tpl);?>
</a>
                </td>
            </tr>
        </table>

        <?php if ($_smarty_tpl->tpl_vars['GLOBALS']->value['enable_amc_prompting']) {?>
        <div class='float-right border mr-5'>
            <div class='float-left m-1'>
            <?php echo smarty_function_amcCollect(array('amc_id'=>'e_prescribe_amc','patient_id'=>$_smarty_tpl->tpl_vars['prescription']->value->patient->id,'object_category'=>'prescriptions','object_id'=>$_smarty_tpl->tpl_vars['prescription']->value->id),$_smarty_tpl);?>

            <?php if (!$_smarty_tpl->tpl_vars['amcCollectReturn']->value) {?>
                <input type="checkbox" id="escribe_flag" name="escribe_flag" />
            <?php } else { ?>
                <input type="checkbox" id="escribe_flag" name="escribe_flag" checked="checked" />
            <?php }?>
            <span class="text"><?php echo smarty_function_xlt(array('t'=>'E-Prescription?'),$_smarty_tpl);?>
</span><br />

            <?php echo smarty_function_amcCollect(array('amc_id'=>'e_prescribe_chk_formulary_amc','patient_id'=>$_smarty_tpl->tpl_vars['prescription']->value->patient->id,'object_category'=>'prescriptions','object_id'=>$_smarty_tpl->tpl_vars['prescription']->value->id),$_smarty_tpl);?>

            <?php if (!$_smarty_tpl->tpl_vars['amcCollectReturn']->value) {?>
                <input type="checkbox" id="checked_formulary_flag" name="checked_formulary_flag" />
            <?php } else { ?>
                <input type="checkbox" id="checked_formulary_flag" name="checked_formulary_flag" checked="checked" />
            <?php }?>
            <span class="text"><?php echo smarty_function_xlt(array('t'=>'Checked Drug Formulary?'),$_smarty_tpl);?>
</span><br />

            <?php echo smarty_function_amcCollect(array('amc_id'=>'e_prescribe_cont_subst_amc','patient_id'=>$_smarty_tpl->tpl_vars['prescription']->value->patient->id,'object_category'=>'prescriptions','object_id'=>$_smarty_tpl->tpl_vars['prescription']->value->id),$_smarty_tpl);?>

            <?php if (!$_smarty_tpl->tpl_vars['amcCollectReturn']->value) {?>
                <input type="checkbox" id="controlled_substance_flag" name="controlled_substance_flag" />
            <?php } else { ?>
                <input type="checkbox" id="controlled_substance_flag" name="controlled_substance_flag" checked="checked" />
            <?php }?>
            <span class="text"><?php echo smarty_function_xlt(array('t'=>'Controlled Substance?'),$_smarty_tpl);?>
</span><br />
            </div>
        </div>
        <?php }?>

        <div class="form-group mt-3">
            <label><?php echo smarty_function_xlt(array('t'=>'Currently Active'),$_smarty_tpl);?>
</label>
            <input class="input-sm" type="checkbox" name="active" value="1"<?php if ($_smarty_tpl->tpl_vars['prescription']->value->get_active() > 0) {?> checked<?php }?> />
        </div>
        <div class="form-group mt-3">
            <label><?php echo smarty_function_xlt(array('t'=>'Starting Date'),$_smarty_tpl);?>
</label>
            <input type="text" size="10" class="datepicker form-control" name="start_date" id="start_date" value="<?php echo attr(oeFormatShortDate($_smarty_tpl->tpl_vars['prescription']->value->start_date));?>
" />
        </div>
        <div class="form-group mt-3">
            <label><?php echo smarty_function_xlt(array('t'=>'Provider'),$_smarty_tpl);?>
</label>
            <?php echo smarty_function_html_options(array('class'=>"input-sm form-control",'name'=>"provider_id",'options'=>$_smarty_tpl->tpl_vars['prescription']->value->provider->utility_provider_array(),'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->provider->get_id()),$_smarty_tpl);?>

            <input type="hidden" name="patient_id" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->patient->id);?>
" />
        </div>
        <div class="form-group mt-3">
            <label class="mr-2"><?php echo smarty_function_xlt(array('t'=>'Drug'),$_smarty_tpl);?>
</label>
            <div class="form-check-inline">
                <label class="form-check-label" title="<?php echo smarty_function_xlt(array('t'=>'Search from native inventory drugs table'),$_smarty_tpl);?>
">
                    <input type="radio" class="form-check-input" name="rxcui_select" checked /><?php echo smarty_function_xlt(array('t'=>'Use Default'),$_smarty_tpl);?>

                </label>
            </div>
            <div class="form-check-inline">
                <label class="form-check-label" title="<?php echo smarty_function_xlt(array('t'=>'Search from external RxNorm table. Vocabulary RXNORM'),$_smarty_tpl);?>
">
                    <input type="radio" class="form-check-input" name="rxcui_select" <?php if (empty($_smarty_tpl->tpl_vars['RXNORMS_AVAILABLE']->value)) {?> disabled<?php } else { ?> checked<?php }?> /><?php echo smarty_function_xlt(array('t'=>'Use RxNorm'),$_smarty_tpl);?>

                </label>
            </div>
            <div class="form-check-inline">
                <label class="form-check-label" title="<?php echo smarty_function_xlt(array('t'=>'Search from native loaded RxCUI table.'),$_smarty_tpl);?>
">
                    <input type="radio" class="form-check-input" name="rxcui_select" <?php if (empty($_smarty_tpl->tpl_vars['RXCUI_AVAILABLE']->value)) {?>disabled<?php } else { ?> checked<?php }?>  /><?php echo smarty_function_xlt(array('t'=>'Use RxCUI'),$_smarty_tpl);?>

                </label>
            </div>
            <select class="input-sm form-control" type="input" name="drug" id="drug"></select>
            <a href="javascript:;" id="druglookup" class="small" name="B4" onclick="$('#hiddendiv').show(); document.getElementById('hiddendiv').innerHTML='&lt;iframe src=&quot;controller.php?prescription&amp;lookup&amp;drug=&quot; width=&quot;100%&quot;height=&quot;75&quot; scrolling=&quot;no&quot; frameborder=&quot;no&quot;&gt;&lt;/iframe&gt;'">
                (<?php echo smarty_function_xlt(array('t'=>'Search Web API'),$_smarty_tpl);?>
)
            </a>
            <div class="jumbotron jumbotron-fluid" id="hiddendiv" style="display: none">&nbsp;</div>
        </div>
        <?php if (!empty($_smarty_tpl->tpl_vars['DRUG_ARRAY_VALUES']->value)) {?>
        <div class="form-group mt-3">
            <label>&nbsp; <?php echo smarty_function_xlt(array('t'=>'in-house'),$_smarty_tpl);?>
</label>
            <select class="input-sm form-control" name="drug_id" onchange="drugselected(this)">
                <?php echo smarty_function_html_options(array('values'=>$_smarty_tpl->tpl_vars['DRUG_ARRAY_VALUES']->value,'output'=>$_smarty_tpl->tpl_vars['DRUG_ARRAY_OUTPUT']->value,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->get_drug_id()),$_smarty_tpl);?>

            </select>
            <input type="hidden" name="rxnorm_drugcode" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->rxnorm_drugcode);?>
">
        </div>
        <?php }?>
        <div class="form-group mt-3">
            <label><?php echo smarty_function_xlt(array('t'=>'Quantity'),$_smarty_tpl);?>
</label>
            <input class="input-sm form-control" type="text" name="quantity" id="quantity" size="10" maxlength="31"
                value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->quantity);?>
" onchange="quantityChanged()" />
        </div>
        <?php if ($_smarty_tpl->tpl_vars['SIMPLIFIED_PRESCRIPTIONS']->value && !$_smarty_tpl->tpl_vars['prescription']->value->size) {?>
        <div class="form-group row mt-3 d-none">
        <?php } else { ?>
        <div class="form-group row mt-3">
        <?php }?>
            <div class="col-12">
                <label><?php echo smarty_function_xlt(array('t'=>'Medicine Units'),$_smarty_tpl);?>
</label>
            </div>
            <div class="col">
                <input class="input-sm form-control" type="text" name="size" id="size" size="11" maxlength="10" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->size);?>
"/>
            </div>
            <div class="col">
                <select class="input-sm form-control" name="unit" id="unit"><?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['prescription']->value->unit_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->unit),$_smarty_tpl);?>
</select>
            </div>
        </div>
        <div class="form-group row mt-3">
            <div class="col-12">
                <label><?php echo smarty_function_xlt(array('t'=>'Directions'),$_smarty_tpl);?>
</label>
            </div>
            <?php if ($_smarty_tpl->tpl_vars['SIMPLIFIED_PRESCRIPTIONS']->value && !$_smarty_tpl->tpl_vars['prescription']->value->form && !$_smarty_tpl->tpl_vars['prescription']->value->route && !$_smarty_tpl->tpl_vars['prescription']->value->interval) {?>
                <input class="input-sm form-control" type="text" name="dosage" id="dosage" size="30" maxlength="100" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->dosage);?>
" />
                <input type="hidden" name="form" id="form" value="0" />
                <input type="hidden" name="route" id="route" value="0" />
                <input type="hidden" name="interval" id="interval" value="0" />
            <?php } else { ?>
                <div class="col">
                    <input class="input-sm form-control" type="text" name="dosage" id="dosage" size="2" maxlength="10" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->dosage);?>
"/>
                </div>
                <div class="col">
                    <?php echo smarty_function_xlt(array('t'=>'in'),$_smarty_tpl);?>

                </div>
                <div class="col">
                    <select class="input-sm form-control" name="form" id="form"><?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['prescription']->value->form_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->form),$_smarty_tpl);?>
</select>
                </div>
                <div class="col">
                    <select class="input-sm form-control" name="route" id="route"><?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['prescription']->value->route_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->route),$_smarty_tpl);?>
</select>
                </div>
                <div class="col">
                    <select class="input-sm form-control" name="interval" id="interval"><?php echo smarty_function_html_options(array('options'=>$_smarty_tpl->tpl_vars['prescription']->value->interval_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->interval),$_smarty_tpl);?>
</select>
                </div>
            <?php }?>
        </div>
        <div class="form-group row mt-3">
            <div class="col-12">
                <label><?php echo smarty_function_xlt(array('t'=>'Refills'),$_smarty_tpl);?>
</label>
            </div>
            <div class="col">
                <?php echo smarty_function_html_options(array('name'=>"refills",'class'=>"form-control",'options'=>$_smarty_tpl->tpl_vars['prescription']->value->refills_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->refills),$_smarty_tpl);?>

            </div>
            <?php if ($_smarty_tpl->tpl_vars['SIMPLIFIED_PRESCRIPTIONS']->value) {?>
                <input type="hidden" id="per_refill" name="per_refill" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->per_refill);?>
" />
            <?php } else { ?>
                <div class="col">
                    &nbsp; &nbsp; # <?php echo smarty_function_xlt(array('t'=>'of tablets'),$_smarty_tpl);?>
:
                </div>
                <div class="col">
                    <input class="input-sm form-control" type="text" id="per_refill" name="per_refill" size="2" maxlength="10" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->per_refill);?>
" />
                </div>
            <?php }?>
        </div>
        <div class="form-group mt-3">
            <label><?php echo smarty_function_xlt(array('t'=>'Notes'),$_smarty_tpl);?>
</label>
            <textarea class="form-control" name="note" cols="30" rows="1" wrap="virtual"><?php echo text($_smarty_tpl->tpl_vars['prescription']->value->note);?>
</textarea>
        </div>
        <div class="form-group row mt-3">
            <?php if ($_smarty_tpl->tpl_vars['WEIGHT_LOSS_CLINIC']->value) {?>
                <label><?php echo smarty_function_xlt(array('t'=>'Substitution'),$_smarty_tpl);?>
</label>
                <?php echo smarty_function_html_options(array('name'=>"substitute",'class'=>"form-control",'options'=>$_smarty_tpl->tpl_vars['prescription']->value->substitute_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->substitute),$_smarty_tpl);?>

            <?php } else { ?>
                <div class="col-12">
                    <label><?php echo smarty_function_xlt(array('t'=>'Add to Medication List'),$_smarty_tpl);?>
</label>
                </div>
                <div class="col">
                    <?php echo smarty_function_html_radios(array('class'=>"input-sm",'name'=>"medication",'options'=>$_smarty_tpl->tpl_vars['prescription']->value->medication_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->medication),$_smarty_tpl);?>

                </div>
                <div class="col">
                    <?php echo smarty_function_html_options(array('class'=>"input-sm form-control",'name'=>"substitute",'options'=>$_smarty_tpl->tpl_vars['prescription']->value->substitute_array,'selected'=>$_smarty_tpl->tpl_vars['prescription']->value->substitute),$_smarty_tpl);?>

                </div>
            <?php }?>
        </div>

        <input type="hidden" name="id" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
" />
        <input type="hidden" name="process" value="<?php echo attr($_smarty_tpl->tpl_vars['PROCESS']->value);?>
" />
        <input type="hidden" id="rxnorm_drugcode" name="rxnorm_drugcode" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->rxnorm_drugcode);?>
" />

        <?php echo '<script'; ?>
>
            <?php if (!empty($_smarty_tpl->tpl_vars['ENDING_JAVASCRIPT']->value)) {
echo $_smarty_tpl->tpl_vars['ENDING_JAVASCRIPT']->value;
}?>
        <?php echo '</script'; ?>
>
    </form>
</div>

<!-- for the fancy jQuery stuff -->
<?php echo '<script'; ?>
>

function submitfun() {
    top.restoreSession();
    
    // Show loading message
    showPyxisLoading();
    
    if (CheckForErrors(this)) {
        document.forms["prescribe"].submit();
    }
    else {
        hidePyxisLoading();
        return false;
    }
}

function showPyxisLoading() {
    // Create loading overlay
    var loadingDiv = document.createElement('div');
    loadingDiv.id = 'pyxis-loading';
    loadingDiv.innerHTML = `
        <div class="alert alert-info pyxis-status-alert" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; min-width: 500px; font-size: 1.3em; padding: 1.2em; text-align: center; border: 2px solid; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            <span style="font-size: 1em;">Sending prescription to Pyxis and waiting for response...</span>
            <div class="spinner-border ml-2" role="status" style="width: 1.2em; height: 1.2em;">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    `;
    document.body.appendChild(loadingDiv);
}

function hidePyxisLoading() {
    var loadingDiv = document.getElementById('pyxis-loading');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}

function iframetopardiv(string){
    var name=string
    document.getElementById('drug').value=name;
    $("#hiddendiv").html( "&nbsp;" );
    $('#hiddendiv').hide();
}

function cancelParlookup () {
    $('#hiddendiv').hide();
    $("#hiddendiv").html( "&nbsp;" );
}

$(function () {

    $("#save,#back").on("click",function(){
        $("#clearButton",window.parent.document).css("display", "none");
        $("#backButton",window.parent.document).css("display", "none");
        $("#addButton",window.parent.document).css("display", "");
    });


    <?php if ($_smarty_tpl->tpl_vars['GLOBALS']->value['weno_rx_enable']) {?>

        $("#drug").select2({
        ajax: {
            url: "library/ajax/drug_autocomplete/search.php",
            dataType: 'json',
            data: function(params) {
                return {
                  csrf_token_form: <?php echo js_escape($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
,
                  term: params.term

                };
            },
            processResults: function(data) {
                return  {
                    results: $.map(data, function(item, index) {
                      return {
                            text: item,
                            id: index,
                            value: item
                        }
                    })
                };
                return x;
            },
            cache: true,
            minimumInputLength: 3
            }
          });
    <?php } else { ?>

        $("#drug").select2({
        ajax: {
            url: "library/ajax/prescription_drugname_lookup.php",
            dataType: 'json',
            data: function(params) {
                return {
                  csrf_token_form: <?php echo js_escape($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>
,

                  term: params.term,
                  use_rxnorm: document.prescribe.rxcui_select[1].checked,
                  use_rxcui: document.prescribe.rxcui_select[2].checked
                }
            },
            processResults: function(data) {
                return  {
                    results: $.map(data, function(item, index) {
                        return {
                            text: item['display_name'],
                            id: item['id_name'],
                            value: item['display_name']
                        }
                    })
                };
                return x;
            },
            cache: true
            },
            tags: true,
            minimumInputLength: 3
          });
    <?php }?>
    <?php if ($_smarty_tpl->tpl_vars['prescription']->value->drug) {?>

        // Show the current drug name in the select
        var newOption = new Option(<?php echo js_escape($_smarty_tpl->tpl_vars['prescription']->value->drug);?>
, <?php echo js_escape($_smarty_tpl->tpl_vars['prescription']->value->drug);?>
, true, true);
        $('#drug').append(newOption).trigger('change');
    <?php }?>


    $("#drug").focus();
    $("#prescribe").submit(function() { return CheckForErrors(this) });
});

// check the form for required fields before submitting
var CheckForErrors = function(eObj) {
    // REQUIRED FIELDS
    if (CheckRequired('drug') == false) { return false; }
    if (CheckRequired('quantity') == false) { return false; }
    //if (CheckRequired('unit') == false) { return false; }
    //if (CheckRequired('size') == false) { return false; }
    if (CheckRequired('dosage') == false) { return false; }
    //if (CheckRequired('form') == false) { return false; }
    //if (CheckRequired('route') == false) { return false; }
    //if (CheckRequired('interval') == false) { return false; }

    return top.restoreSession();
};

function CheckRequired(objID) {

    // for text boxes
    if ($('#'+objID).is('input')) {
        if ($('#'+objID).val() == "") {
            alert(<?php echo smarty_function_xlj(array('t'=>'Missing a required field and will be highlighted'),$_smarty_tpl);?>
);
            $('#'+objID).css("backgroundColor", "pink");
            return false;
        }
    }

    // for select boxes
    if ($('#'+objID).is('select')) {
        if ($('#'+objID).val() == "0") {
            alert(<?php echo smarty_function_xlj(array('t'=>'Missing a required field'),$_smarty_tpl);?>
);
            $('#'+objID).css("backgroundColor", "pink");
            return false;
        }
    }

    return true;
}

    $(document).on('select2:select', 'select#drug', function(e) {
        let idx = this.selectedIndex;
        if (idx === 0) {
            // already selected.
            return false;
        }
        let optionText = document.getElementById("drug").options[idx].text;
        let rxcode = (optionText.split('(RxCUI:').pop().split(')')[0]).trim();
        $("#rxnorm_drugcode").val(rxcode);
    });

$(function () {
    <?php echo smarty_function_datetimepickerSupport(array('input'=>'format'),$_smarty_tpl);?>

    
    // Hide loading if page has reloaded with status
    hidePyxisLoading();
});<?php echo '</script'; ?>
>



</body>
</html>
<?php }
}
