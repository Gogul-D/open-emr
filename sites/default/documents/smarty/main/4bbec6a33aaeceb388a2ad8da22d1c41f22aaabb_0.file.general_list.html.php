<?php
/* Smarty version 4.3.4, created on 2025-12-26 04:37:05
  from 'C:\wamp64\www\phix-openemr\templates\prescription\general_list.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_694e736106aff1_91469142',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '4bbec6a33aaeceb388a2ad8da22d1c41f22aaabb' => 
    array (
      0 => 'C:\\wamp64\\www\\phix-openemr\\templates\\prescription\\general_list.html',
      1 => 1766748760,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_694e736106aff1_91469142 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.headerTemplate.php','function'=>'smarty_function_headerTemplate',),1=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xlj.php','function'=>'smarty_function_xlj',),2=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xlt.php','function'=>'smarty_function_xlt',),3=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xl.php','function'=>'smarty_function_xl',),4=>array('file'=>'C:\\wamp64\\www\\phix-openemr\\library\\smarty\\plugins\\function.xla.php','function'=>'smarty_function_xla',),));
?>
<html>
<head>

<?php echo smarty_function_headerTemplate(array('assets'=>'no_textformat|no_dialog'),$_smarty_tpl);?>



<?php echo '<script'; ?>
>

function changeLinkHref(id,addValue,value) {
    var myRegExp = new RegExp(":" + value + ":");
    if (addValue){ //add value to href
        if(document.getElementById(id) !== null)document.getElementById(id).href += ':' + value + ':';
    }
    else { //remove value from href
        if(document.getElementById(id) !== null)document.getElementById(id).href = document.getElementById(id).href.replace(myRegExp,'');
    }
}

function changeLinkHrefAll(addValue, value) {
    changeLinkHref('multiprint', addValue, value);
    changeLinkHref('multiprintcss', addValue, value);
    changeLinkHref('multiprintToFax', addValue, value);
}


function changeLinkHref_All(id,addValue,value) {
    var myRegExp = new RegExp(":" + value + ":");
    if (addValue){ //add value to href
        if(document.getElementById(id) !== null)document.getElementById(id).href += ':' + value + ':';
    }
    else { //remove value from href
        if(document.getElementById(id) !== null)document.getElementById(id).href = document.getElementById(id).href.replace(myRegExp,'');
        // TajEmo Work By CB 2012/06/14 02:17:16 PM remove the target change
    //document.getElementById(id).target = '';
    }
}

function Check(chk) {
    var len=chk.length;
    if (len==undefined) {
        chk.checked=true;
    }
    else {
        //clean the checked id's before check all the list again
        var multiprint=document.getElementById('multiprint');
        if(multiprint!==null) {
            multiprint.href = document.getElementById('multiprint').href.substring(0, document.getElementById('multiprint').href.indexOf('=') + 1);
        }

        var multiprintcss=document.getElementById('multiprintcss');
        if(multiprintcss!==null) {
            multiprintcss.href =  document.getElementById('multiprintcss').href.substring(0, document.getElementById('multiprintcss').href.indexOf('=') + 1);
        }

        var multiprintToFax=document.getElementById('multiprintToFax');
        if(multiprintToFax!==null) {
            multiprintToFax.href = document.getElementById('multiprintToFax').href.substring(0, document.getElementById('multiprintToFax').href.indexOf('=') +1);
        }
        for (let pr = 0; pr < chk.length; pr++){
            if($(chk[pr]).parents("tr.inactive").length==0)
                {
                    chk[pr].checked=true;
                    changeLinkHref_All('multiprint',true,chk[pr].value);
                    changeLinkHref_All('multiprintcss',true, chk[pr].value);
                    changeLinkHref_All('multiprintToFax',true, chk[pr].value);
                }
        }
    }
}

function deleteDrug(d) {
    let msg = <?php echo smarty_function_xlj(array('t'=>'Do you really want to delete?'),$_smarty_tpl);?>
;
    let choice = confirm(msg);
    if (choice == true) {
        top.restoreSession();
        $.ajax({
                url: "./library/deletedrug.php",
                type: 'POST',
                data: {
                    drugid: d,
                    csrf_token_form: <?php echo js_escape($_smarty_tpl->tpl_vars['CSRF_TOKEN_FORM']->value);?>

            },
            success: function(data) {
            console.log(data);
            document.location.href = '<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/controller.php?prescription&list&id=' + <?php echo js_url($_SESSION['pid']);?>
;
        },
        error: function(error) {
            console.log(error)
        },
    });
    }
}

function Uncheck(chk) {
    var len=chk.length;
    if (len==undefined) {
        chk.checked=false;
    }
    else {
        for (pr = 0; pr < chk.length; pr++){
            chk[pr].checked=false;
            changeLinkHref_All('multiprint',false,chk[pr].value);
            changeLinkHref_All('multiprintcss',false, chk[pr].value);
            changeLinkHref_All('multiprintToFax',false, chk[pr].value);
        }
    }
}

var CheckForChecks = function(chk) {
    // Checks for any checked boxes, if none are found than an alert is raised and the link is killed
    if (Checking(chk) == false) { return false; }
    return top.restoreSession();
};

function Checking(chk) {
    var len=chk.length;
    var foundone=false;

    if (len==undefined) {
            if (chk.checked == true){
                foundone=true;
            }
    }
    else {
        for (pr = 0; pr < chk.length; pr++){
            if (chk[pr].checked == true) {
                foundone=true;
            }
        }
    }
    if (foundone) {
        return true;
    } else {
        alert(<?php echo smarty_function_xlj(array('t'=>'Please select at least one prescription!'),$_smarty_tpl);?>
);
        return false;
    }
}

$(function () {
  $(":checkbox:checked").each(function () {
      changeLinkHref('multiprint',this.checked, this.value);
      changeLinkHref('multiprintcss',this.checked, this.value);
      changeLinkHref('multiprintToFax',this.checked, this.value);
  });
});

<?php echo '</script'; ?>
>


</head>
<body id="prescription_list">
    <div class="container-fluid">
        <?php if ($_smarty_tpl->tpl_vars['HL7_STATUS_MESSAGE']->value) {?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-<?php echo (($tmp = $_smarty_tpl->tpl_vars['HL7_STATUS_TYPE']->value ?? null)===null||$tmp==='' ? 'info' ?? null : $tmp);?>
 alert-dismissible fade show pyxis-status-alert" role="alert" style="font-size: 1.3em; padding: 1.2em; margin: 1em auto; text-align: center; max-width: 700px; border: 2px solid; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span style="font-size: 1em;"><?php echo $_smarty_tpl->tpl_vars['HL7_STATUS_MESSAGE']->value;?>
</span>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="font-size: 1.2em;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        </div>
        <?php }?>
        <div class="row">
            <?php if ($_smarty_tpl->tpl_vars['prescriptions']->value) {?>
            <div class="col-12">
                <h3><?php echo smarty_function_xlt(array('t'=>'List'),$_smarty_tpl);?>
</h3>
            </div>
            <div class="col-12 d-flex justify-content-between">
                <div class="btn-group">
                    <?php if ($_smarty_tpl->tpl_vars['GLOBALS']->value['rx_zend_pdf_template']) {?>
                        <a target="_blank" id="multiprint" href="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['baseModDir'];
echo $_smarty_tpl->tpl_vars['GLOBALS']->value['zendModDir'];?>
/public/prescription-pdf-template/<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['rx_zend_pdf_action'];?>
?id=<?php if (!empty($_smarty_tpl->tpl_vars['printm']->value)) {
echo attr_url($_smarty_tpl->tpl_vars['printm']->value);
}?>" onclick="top.restoreSession()" class="btn btn-primary btn-sm"><?php echo smarty_function_xlt(array('t'=>'Download'),$_smarty_tpl);?>
 (<?php echo smarty_function_xlt(array('t'=>'PDF'),$_smarty_tpl);?>
)</a>
                    <?php } else { ?>
                        <a id="multiprint" href="<?php echo $_smarty_tpl->tpl_vars['CONTROLLER']->value;?>
prescription&multiprint&id=<?php if (!empty($_smarty_tpl->tpl_vars['printm']->value)) {
echo attr_url($_smarty_tpl->tpl_vars['printm']->value);
}?>" onclick="top.restoreSession()" class="btn btn-primary btn-sm btn-download"><?php echo smarty_function_xlt(array('t'=>'Download'),$_smarty_tpl);?>
 (<?php echo smarty_function_xl(array('t'=>'PDF'),$_smarty_tpl);?>
)</a>
                    <?php }?>
                    <?php if ($_smarty_tpl->tpl_vars['GLOBALS']->value['rx_zend_html_template']) {?>
                        <a target="_blank" id="multiprintcss" href="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['baseModDir'];
echo $_smarty_tpl->tpl_vars['GLOBALS']->value['zendModDir'];?>
/public/prescription-html-template/<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['rx_zend_html_action'];?>
?id=<?php if (!empty($_smarty_tpl->tpl_vars['printm']->value)) {
echo attr_url($_smarty_tpl->tpl_vars['printm']->value);
}?>" onclick="top.restoreSession()" class="btn btn-primary btn-sm"><?php echo smarty_function_xl(array('t'=>text('View Printable Version')),$_smarty_tpl);?>
 (<?php echo smarty_function_xlt(array('t'=>'HTML'),$_smarty_tpl);?>
)</a>
                    <?php } else { ?>
                    <!-- TajEmo work by CB 2012/06/14 02:16:32 PM target="_script" opens better -->
                        <a target="_script" id="multiprintcss" href="<?php echo $_smarty_tpl->tpl_vars['CONTROLLER']->value;?>
prescription&multiprintcss&id=<?php if (!empty($_smarty_tpl->tpl_vars['printm']->value)) {
echo attr_url($_smarty_tpl->tpl_vars['printm']->value);
}?>" onclick="top.restoreSession()" class="btn btn-primary btn-sm btn-print"><?php echo smarty_function_xlt(array('t'=>'View Printable Version'),$_smarty_tpl);?>
 (<?php echo smarty_function_xlt(array('t'=>'HTML'),$_smarty_tpl);?>
)</a>
                    <?php }?>
                    <?php if ($_smarty_tpl->tpl_vars['GLOBALS']->value['rx_use_fax_template']) {?>
                        <a id="multiprintToFax" href="<?php echo $_smarty_tpl->tpl_vars['CONTROLLER']->value;?>
prescription&multiprintfax&id=<?php if (!empty($_smarty_tpl->tpl_vars['printm']->value)) {
echo attr_url($_smarty_tpl->tpl_vars['printm']->value);
}?>" onclick="top.restoreSession()" class="btn btn-primary btn-sm btn-download"><?php echo smarty_function_xlt(array('t'=>'Download'),$_smarty_tpl);?>
 (<?php echo smarty_function_xlt(array('t'=>'Fax'),$_smarty_tpl);?>
)</a>
                    <?php }?>
                    <?php if ($_smarty_tpl->tpl_vars['CAMOS_FORM']->value == true) {?>
                        <a id="four_panel_rx" href="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/interface/forms/CAMOS/rx_print.php?sigline=plain" onclick="top.restoreSession()" class="btn btn-primary btn-sm"><?php echo smarty_function_xlt(array('t'=>'View Four Panel'),$_smarty_tpl);?>
</a>
                    <?php }?>
                </div>
                <div class="btn-group">
                    <a href="#" class="small" onClick="Check(document.presc.check_list);"><span><?php echo smarty_function_xlt(array('t'=>'Check All'),$_smarty_tpl);?>
</span></a> |
                    <a href="#" class="small" onClick="Uncheck(document.presc.check_list);"><span><?php echo smarty_function_xlt(array('t'=>'Clear All'),$_smarty_tpl);?>
</span></a>
                </div>
            </div>
            <div class="col-12">
                <div id="prescription_list">
                    <form name="presc">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                    <!-- TajEmo Changes 2012/06/14 02:01:43 PM by CB added Heading for checkbox column -->
                                        <th>&nbsp;</th>
                                        <th>&nbsp;</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Drug'),$_smarty_tpl);?>
</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'RxNorm'),$_smarty_tpl);?>
</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Created'),$_smarty_tpl);?>
<br /><?php echo smarty_function_xlt(array('t'=>'Changed'),$_smarty_tpl);?>
</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Dosage'),$_smarty_tpl);?>
</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Qty'),$_smarty_tpl);?>
.</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Unit'),$_smarty_tpl);?>
</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Refills'),$_smarty_tpl);?>
</th>
                                        <th><?php echo smarty_function_xlt(array('t'=>'Provider'),$_smarty_tpl);?>
</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['prescriptions']->value, 'prescription');
$_smarty_tpl->tpl_vars['prescription']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['prescription']->value) {
$_smarty_tpl->tpl_vars['prescription']->do_else = false;
?>
                            <!-- TajEmo Changes 2012/06/14 02:03:17 PM by CB added cursor:pointer for easier user understanding -->
                            <tr id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
" class="showborder onescript <?php if ($_smarty_tpl->tpl_vars['prescription']->value->active <= 0) {?> inactive<?php }?>">
                                <td class="text-center">
                                <input class="check_list" id="check_list" type="checkbox" value="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
" <?php if (!empty($_smarty_tpl->tpl_vars['prescription']->value->encounter) && $_smarty_tpl->tpl_vars['prescription']->value->encounter == $_smarty_tpl->tpl_vars['prescription']->value->get_encounter() && $_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?>checked="checked" <?php }?>onclick="changeLinkHref('multiprint',this.checked, this.value);changeLinkHref('multiprintcss',this.checked, this.value);changeLinkHref('multiprintToFax',this.checked, this.value)" title="<?php echo smarty_function_xla(array('t'=>'Select for printing'),$_smarty_tpl);?>
">
                                </td>
                                <?php if (empty($_smarty_tpl->tpl_vars['prescription']->value->erx_source) || $_smarty_tpl->tpl_vars['prescription']->value->erx_source == 0) {?>
                                <td class="editscript"  id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                    <a class='editscript btn btn-primary btn-sm btn-edit' id='<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
' href="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/controller.php?prescription&edit&id=<?php echo attr_url($_smarty_tpl->tpl_vars['prescription']->value->id);?>
"><?php echo smarty_function_xlt(array('t'=>'Edit'),$_smarty_tpl);?>
</a>
                                <!-- TajEmo Changes 2012/06/14 02:02:22 PM by CB commented out, to avoid duplicate display of drug name
                                    <?php if ($_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?><b><?php }
echo $_smarty_tpl->tpl_vars['prescription']->value->drug;
if ($_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?></b><?php }?>&nbsp;
                                -->
                                </td>
                                <td class="editscript"  id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php if ($_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?><b><?php }
echo text($_smarty_tpl->tpl_vars['prescription']->value->drug);
if ($_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?></b><?php }?>&nbsp;
                            <br /><?php echo text($_smarty_tpl->tpl_vars['prescription']->value->note);?>

                                </td>
                                <?php } else { ?>
                            <td>&nbsp;</td>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php if ($_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?><b><?php }
echo text($_smarty_tpl->tpl_vars['prescription']->value->drug);
if ($_smarty_tpl->tpl_vars['prescription']->value->active > 0) {?></b><?php }?>&nbsp;
                            <br /><?php echo text($_smarty_tpl->tpl_vars['prescription']->value->note);?>

                                </td>
                                <?php }?>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->rxnorm_drugcode);?>
&nbsp;
                                </td>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text(oeFormatShortDate($_smarty_tpl->tpl_vars['prescription']->value->date_added));?>
<br />
                                <?php echo text(oeFormatShortDate($_smarty_tpl->tpl_vars['prescription']->value->date_modified));?>
&nbsp;
                                </td>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->get_dosage_display());?>
 &nbsp;
                                </td>
                                <?php if (empty($_smarty_tpl->tpl_vars['prescription']->value->erx_source) || $_smarty_tpl->tpl_vars['prescription']->value->erx_source == 0) {?>
                                <td class="editscript" id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->quantity);?>
 &nbsp;
                                </td>
                                <?php } else { ?>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->quantity);?>
 &nbsp;
                                </td>
                                <?php }?>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->get_size());?>
 <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->get_unit_display());?>
&nbsp;
                                </td>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->refills);?>
 &nbsp;
                                </td>
                                <td id="<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
">
                                <?php echo text($_smarty_tpl->tpl_vars['prescription']->value->provider->get_name_display());?>
&nbsp;
                                </td>
                                <td><a href="#" id="deleteDrug" class="btn btn-danger btn-sm btn-delete" onclick="deleteDrug(<?php echo attr($_smarty_tpl->tpl_vars['prescription']->value->id);?>
)"><?php echo smarty_function_xlt(array('t'=>'Delete'),$_smarty_tpl);?>
</a></td>
                            </tr>
                                <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
            <?php if ($_smarty_tpl->tpl_vars['GLOBALS']->value['rx_show_drug_drug']) {?>
                <div id="drug-drug">
                    <hr>
                    <h3><?php echo smarty_function_xlt(array('t'=>'Drug-Drug Interaction'),$_smarty_tpl);?>
</h3>
                    <p title="<?php echo smarty_function_xla(array('t'=>'Severity information will be missing if interaction is found'),$_smarty_tpl);?>
"><a href="#">*<?php echo smarty_function_xlt(array('t'=>'Notice'),$_smarty_tpl);?>
</a></p>
                    <div id="return_info">
                        <?php echo $_smarty_tpl->tpl_vars['INTERACTION']->value;?>

                    </div>
                    <hr>
                </div>
            <?php }?>

            <?php } else { ?>
            <div class="text mt-3"><?php echo smarty_function_xlt(array('t'=>'There are currently no prescriptions'),$_smarty_tpl);?>
.</div>
            <?php }?>
        </div>
    </div>
</body>

<?php echo '<script'; ?>
>

$(function () {
$("#multiprint").on("click", function() { return CheckForChecks(document.presc.check_list); });
$("#multiprintcss").on("click", function() { return CheckForChecks(document.presc.check_list); });
$("#multiprintToFax").on("click", function() { return CheckForChecks(document.presc.check_list); });
$(".editscript").on("click", function() { ShowScript(this); });
$(".onescript").on("mouseover", function() { $(this).children().toggleClass("highlight"); });
$(".onescript").on("mouseout", function() { $(this).children().toggleClass("highlight"); });
});

var ShowScript = function(eObj) {
    top.restoreSession();
    objID = eObj.id;
    document.location.href="<?php echo $_smarty_tpl->tpl_vars['GLOBALS']->value['webroot'];?>
/controller.php?prescription&edit&id=" + encodeURIComponent(objID);
    return true;
};

<?php echo '</script'; ?>
>

</html>
<?php }
}
