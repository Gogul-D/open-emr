<?php
/* Smarty version 4.3.4, created on 2025-03-24 14:32:51
  from '/var/www/openemr/templates/documents/general_view.html' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.3.4',
  'unifunc' => 'content_67e1c173edb6d1_80061701',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '3dcddda4a4503fb40de75a7efa14c82bc6a9146e' => 
    array (
      0 => '/var/www/openemr/templates/documents/general_view.html',
      1 => 1700108885,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_67e1c173edb6d1_80061701 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/openemr/library/smarty/plugins/function.dispatchPatientDocumentEvent.php','function'=>'smarty_function_dispatchPatientDocumentEvent',),1=>array('file'=>'/var/www/openemr/library/smarty/plugins/function.xlj.php','function'=>'smarty_function_xlj',),2=>array('file'=>'/var/www/openemr/library/smarty/plugins/function.xlt.php','function'=>'smarty_function_xlt',),3=>array('file'=>'/var/www/openemr/library/smarty/plugins/function.xla.php','function'=>'smarty_function_xla',),));
?>

<?php echo '<script'; ?>
>

 <?php echo smarty_function_dispatchPatientDocumentEvent(array('event'=>"javascript_ready_fax_dialog"),$_smarty_tpl);?>


 function popoutcontent(othis) {
    let popsrc = $(othis).parents('body').find('#DocContents iframe').attr("src");
    let wname = '_' + Math.random().toString(36).substr(2, 6);
    let opt = "menubar=no,location=no,resizable=yes,scrollbars=yes,status=no";
    window.open(popsrc,wname, opt);

    return false;
}

 // Process click on Delete link.
 function deleteme(docid) {
  dlgopen('interface/patient_file/deleter.php?document=' + encodeURIComponent(docid) + '&csrf_token_form=' + <?php echo js_url($_smarty_tpl->tpl_vars['csrf_token_form']->value);?>
, '_blank', 500, 450);
  return false;
 }

 // Called by the deleter.php window on a successful delete.
 function imdeleted() {
  top.restoreSession();
  window.location.href=<?php echo js_escape($_smarty_tpl->tpl_vars['REFRESH_ACTION']->value);?>
;
 }

 // Called to show patient notes related to this document in the "other" frame.
 function showpnotes(docid) {
     let btnClose = <?php echo smarty_function_xlj(array('t'=>"Done"),$_smarty_tpl);?>
;
     let url = top.webroot_url + '/interface/patient_file/summary/pnotes.php?docid=' + encodeURIComponent(docid);
     dlgopen(url, 'pno1', 'modal-xl', 500, '', '', {
         buttons: [
             { text: btnClose, close: true, style: 'default btn-sm' }
         ]
     });
     return false;
 }

 function submitNonEmpty( e ) {
	if ( e.elements['passphrase'].value.length == 0 ) {
		alert( <?php echo smarty_function_xlj(array('t'=>'You must enter a pass phrase to encrypt the document'),$_smarty_tpl);?>
 );
	} else {
		e.submit();
	}
 }

// For tagging it with an encounter
function tagUpdate() {
	var f = document.forms['document_tag'];
	if (f.encounter_check.checked) {
		if(f.visit_category_id.value==0) {
			alert(<?php echo smarty_function_xlj(array('t'=>'Please select visit category'),$_smarty_tpl);?>
 );
			return false;
		}
	} else if (f.encounter_id.value == 0 ) {
		alert(<?php echo smarty_function_xlj(array('t'=>'Please select encounter'),$_smarty_tpl);?>
);
		return false;
	}
	//top.restoreSession();
	document.forms['document_tag'].submit();
}

// For new or existing encounter
function set_checkbox() {
	var f = document.forms['document_tag'];
	if (f.encounter_check.checked) {
		f.encounter_id.disabled = true;
		f.visit_category_id.disabled = false;
		$('.hide_clear').attr('href','javascript:void(0);');
	} else {
		f.encounter_id.disabled = false;
		f.visit_category_id.disabled = true;
		f.visit_category_id.value = 0;
		$('.hide_clear').attr('href','<?php echo $_smarty_tpl->tpl_vars['clear_encounter_tag']->value;?>
');
	}
}

// For tagging it with any procedure
function tagProcedure() {
	var f = document.forms['procedure_tag'];
	if(f.image_procedure_id.value == 0 ) {
		alert(<?php echo smarty_function_xlj(array('t'=>'Please select procedure'),$_smarty_tpl);?>
);
		return false;
	}
	f.procedure_code.value = f.image_procedure_id.options[f.image_procedure_id.selectedIndex].getAttribute('data-code');
	document.forms['procedure_tag'].submit();
}
<?php echo '</script'; ?>
>

<table class="w-100 align-top">
    <tr>
        <td>
            <div style="margin-bottom: 6px; padding-bottom: 6px; border-bottom: 3px solid var(--gray);">
            <h4><?php echo text($_smarty_tpl->tpl_vars['file']->value->get_name());?>

              <div class="btn-group btn-toggle">
                <button class="btn btn-sm btn-secondary properties"><?php echo smarty_function_xlt(array('t'=>'Properties'),$_smarty_tpl);?>
</button>
                <button class="btn btn-sm btn-primary active"><?php echo smarty_function_xlt(array('t'=>'Contents'),$_smarty_tpl);?>
</button>
              </div>
            <span class="float-right">
            <?php echo '<script'; ?>
>var file = <?php echo js_escape($_smarty_tpl->tpl_vars['file']->value->get_url());?>
;var mime = <?php echo js_escape($_smarty_tpl->tpl_vars['file']->value->get_mimetype());?>
;var docid = <?php echo js_escape($_smarty_tpl->tpl_vars['file']->value->get_id());?>
;<?php echo '</script'; ?>
>
            <?php echo smarty_function_dispatchPatientDocumentEvent(array('event'=>"actions_render_fax_anchor"),$_smarty_tpl);?>

            <a class="btn btn-primary" href='' onclick='return popoutcontent(this)' title="<?php echo smarty_function_xla(array('t'=>'Pop Out Full Screen.'),$_smarty_tpl);?>
"><?php echo smarty_function_xlt(array('t'=>'Pop Out'),$_smarty_tpl);?>
</a>
            <a class="btn btn-primary" href="<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
" title="<?php echo smarty_function_xla(array('t'=>'Original file'),$_smarty_tpl);?>
" onclick="top.restoreSession()"><?php echo smarty_function_xlt(array('t'=>'Download'),$_smarty_tpl);?>
</a>
            <a class="btn btn-primary" href='' onclick='return showpnotes(<?php echo attr_js($_smarty_tpl->tpl_vars['file']->value->get_id());?>
)'><?php echo smarty_function_xlt(array('t'=>'Show Notes'),$_smarty_tpl);?>
</a>
            <?php echo $_smarty_tpl->tpl_vars['delete_string']->value;?>

            </span>
            </h4>
            </div>
        </td>
    </tr>
    <tr id="DocProperties" style="display: none;">
		<td class="align-top">
			<?php if (!$_smarty_tpl->tpl_vars['hide_encryption']->value) {?>
			<div class="mb-2">
        <form class="form-inline w-100" method="post" name="document_encrypt" action="<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
" onsubmit="return top.restoreSession()">
          <div class="form-group">
            <label class="lead font-weight-bold mr-1" for='passphrase'><?php echo smarty_function_xlt(array('t'=>'Encryption Pass Phrase'),$_smarty_tpl);?>
:</label>
            <input class="form-control" title="<?php echo smarty_function_xla(array('t'=>'Supports TripleDES encryption/decryption only.'),$_smarty_tpl);?>
 <?php echo smarty_function_xla(array('t'=>'Leaving the pass phrase blank will not encrypt the document'),$_smarty_tpl);?>
" type='text' size='20' name='passphrase' id='passphrase' value='' />
            <input type="hidden" name="encrypted" value="true" />
          </div>
          <button type="button" class="btn btn-primary" onclick="submitNonEmpty(document.forms['document_encrypt']);"><?php echo smarty_function_xlt(array('t'=>'download encrypted file'),$_smarty_tpl);?>
</button>
        </form>
      </div>
      <?php }?>
	  <div class="mb-2">
        <form class="form-inline" method="post" name="document_validate" action="<?php echo attr($_smarty_tpl->tpl_vars['VALIDATE_ACTION']->value);?>
" onsubmit="return top.restoreSession()">
          <label class="font-weight-bolder"><?php echo text($_smarty_tpl->tpl_vars['file']->value->get_hash_algo_title());?>
 <?php echo smarty_function_xlt(array('t'=>'Hash'),$_smarty_tpl);?>
:</label>
            <p class="d-none"><small><?php echo text($_smarty_tpl->tpl_vars['file']->value->get_hash());?>
</small></p>
          <a class="btn btn-primary btn-sm" href="javascript:;" onclick="document.forms['document_validate'].submit();"><?php echo smarty_function_xlt(array('t'=>'Validate'),$_smarty_tpl);?>
</a>
        </form>
      </div>
      <div class="mb-2">
        <form method="post" name="document_update" action="<?php echo attr($_smarty_tpl->tpl_vars['UPDATE_ACTION']->value);?>
" onsubmit="return top.restoreSession()">
          <div class="form-group">
            <label for="docname"><?php echo smarty_function_xlt(array('t'=>'Rename'),$_smarty_tpl);?>
:</label>
            <input type='text' class="form-control" size='20' name='docname' id='docname' value='<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_name());?>
'/>
          </div>
          <div class="form-group">
            <label for="docdate"><?php echo smarty_function_xlt(array('t'=>'Date'),$_smarty_tpl);?>
:</label>
            <input type='text' size='10' class='form-control datepicker' name='docdate' id='docdate' value='<?php echo attr($_smarty_tpl->tpl_vars['DOCDATE']->value);?>
' title="<?php echo smarty_function_xla(array('t'=>'yyyy-mm-dd document date'),$_smarty_tpl);?>
" />
          </div>
          <div class="form-group">
            <select name="issue_id" class="form-control"><?php echo $_smarty_tpl->tpl_vars['ISSUES_LIST']->value;?>
</select>
          </div>
          <button class="btn btn-primary btn-sm" onclick="document.forms['document_update'].submit();"><?php echo smarty_function_xlt(array('t'=>'Update'),$_smarty_tpl);?>
</button>
        </form>
      </div>
      <div class="mb-2">
        <form class="form-inline" method="post" name="document_move" action="<?php echo attr($_smarty_tpl->tpl_vars['MOVE_ACTION']->value);?>
" onsubmit="return top.restoreSession()">
          <div class="input-group">
            <select class="form-control mr-1" name="new_category_id"><?php echo $_smarty_tpl->tpl_vars['tree_html_listbox']->value;?>
</select>
              <label><?php echo smarty_function_xlt(array('t'=>'Move to Patient PID'),$_smarty_tpl);?>
</label><input class="ml-1" type="text" class="form-control" name="new_patient_id" size="4" />
            <a class="btn btn-search btn-secondary" href="javascript:{}" onclick="top.restoreSession();var URL='controller.php?patient_finder&find&form_id=<?php echo attr_url("document_move['new_patient_id']");?>
&form_name=<?php echo attr_url("document_move['new_patient_name']");?>
'; window.open(URL, 'document_move', 'toolbar=0,scrollbars=1,location=0,statusbar=1,menubar=0,resizable=1,width=450,height=400,left=425,top=250');"></a>
              <input type="hidden" name="new_patient_name" value="" />
          </div>
          <button class="btn btn-primary ml-1" onclick="document.forms['document_move'].submit();"><?php echo smarty_function_xlt(array('t'=>'Move'),$_smarty_tpl);?>
</button>
        </form>
      </div>
		<div class="mb-2">
        <form class="form-inline" method="post" name="document_tag" id="document_tag" action="<?php echo attr($_smarty_tpl->tpl_vars['TAG_ACTION']->value);?>
" onsubmit="return top.restoreSession()">
          <label class="font-weight-bold mr-1"><?php echo smarty_function_xlt(array('t'=>'Tag to Encounter'),$_smarty_tpl);?>
</label>
          <div class="input-group">
            <select class="form-control" id="encounter_id"  name="encounter_id"><?php echo $_smarty_tpl->tpl_vars['ENC_LIST']->value;?>
</select>&nbsp;
            <a href="<?php echo $_smarty_tpl->tpl_vars['clear_encounter_tag']->value;?>
" class="btn btn-danger hide_clear"><?php echo smarty_function_xlt(array('t'=>'clear'),$_smarty_tpl);?>
</a>&nbsp;&nbsp;
            <input type="checkbox" name="encounter_check" id="encounter_check" onclick='set_checkbox(this)'/>
            <label for="encounter_check" class="font-weight-bold"><?php echo smarty_function_xlt(array('t'=>'Create Encounter'),$_smarty_tpl);?>
</label>&nbsp;&nbsp;
              <label class="font-weight-bold mr-1"><?php echo smarty_function_xlt(array('t'=>'Visit Category'),$_smarty_tpl);?>
</label><select class="form-control" id="visit_category_id" name="visit_category_id"  disabled><?php echo $_smarty_tpl->tpl_vars['VISIT_CATEGORY_LIST']->value;?>
</select>&nbsp;
          </div>
          <button class="btn btn-primary" onclick="tagUpdate();"><?php echo smarty_function_xlt(array('t'=>'submit'),$_smarty_tpl);?>
</button>
        </form>
      </div>
      <div class="mb-2">
        <form class="form-inline" method="post" name="procedure_tag" id="procedure_tag" action="<?php echo attr($_smarty_tpl->tpl_vars['PROCEDURE_TAG_ACTION']->value);?>
" onsubmit="return top.restoreSession()">
          <input type='hidden' name='procedure_code' value='' />
          <label class="font-weight-bold mr-1"><?php echo smarty_function_xlt(array('t'=>'Tag to Procedure'),$_smarty_tpl);?>
</label>
          <div class="input-group w-50">
              <select class="form-control ml-1" id="image_procedure_id" name="image_procedure_id"><?php echo $_smarty_tpl->tpl_vars['TAG_PROCEDURE_LIST']->value;?>
</select>
              <div class="btn-group">
                <a class="btn btn-primary btn-sm" href="javascript:;" onclick="tagProcedure();"><?php echo smarty_function_xlt(array('t'=>'Submit'),$_smarty_tpl);?>
</a>
                <a class="btn btn-danger btn-sm" href="<?php echo attr($_smarty_tpl->tpl_vars['clear_procedure_tag']->value);?>
"><?php echo smarty_function_xlt(array('t'=>'Clear'),$_smarty_tpl);?>
</a>
              </div>
          </div>
        </form>
      </div>
      <form name="notes" method="post" action="<?php echo attr($_smarty_tpl->tpl_vars['NOTE_ACTION']->value);?>
" onsubmit="return top.restoreSession()">
        <div class="text">
          <div>
            <div class="float-left mt-2 mr-1">
              <strong><?php echo smarty_function_xlt(array('t'=>'Notes'),$_smarty_tpl);?>
</strong>
            </div>
            <div class="float-none form-inline">
              <a class="btn btn-primary btn-sm" href="javascript:;" onclick="document.notes.identifier.value='no';document.forms['notes'].submit();"><?php echo smarty_function_xlt(array('t'=>'Add Note'),$_smarty_tpl);?>
</a>
              &nbsp;&nbsp;&nbsp;<strong><?php echo smarty_function_xlt(array('t'=>'Email'),$_smarty_tpl);?>
</strong>&nbsp;
              <input type="text" class="form-control" size="25" name="provide_email" id="provide_email" />
              <input type="hidden" name="identifier" id="identifier" />
              <a class="btn btn-primary btn-sm" href="javascript:;" onclick="javascript:document.notes.identifier.value='yes';document.forms['notes'].submit();"><?php echo smarty_function_xlt(array('t'=>'Send'),$_smarty_tpl);?>
</a>
            </div>
            <div>
              <textarea cols="53" rows="8" wrap="virtual" name="note" class="form-control w-100"></textarea><br />
              <input type="hidden" name="process" value="<?php echo attr($_smarty_tpl->tpl_vars['PROCESS']->value);?>
" />
              <input type="hidden" name="foreign_id" value="<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_id());?>
" />

              <?php if ($_smarty_tpl->tpl_vars['notes']->value) {?>
              <div class="mt-1">
                <?php
$_from = $_smarty_tpl->smarty->ext->_foreach->init($_smarty_tpl, $_smarty_tpl->tpl_vars['notes']->value, 'note', false, NULL, 'note_loop', array (
));
$_smarty_tpl->tpl_vars['note']->do_else = true;
if ($_from !== null) foreach ($_from as $_smarty_tpl->tpl_vars['note']->value) {
$_smarty_tpl->tpl_vars['note']->do_else = false;
?>
                <div>
                  <?php echo smarty_function_xlt(array('t'=>'Note'),$_smarty_tpl);?>
 #<?php echo text($_smarty_tpl->tpl_vars['note']->value->get_id());?>

                  <?php echo smarty_function_xlt(array('t'=>'Date:'),$_smarty_tpl);?>
 <?php echo text($_smarty_tpl->tpl_vars['note']->value->get_date());?>

                  <?php echo text($_smarty_tpl->tpl_vars['note']->value->get_note());?>

                  <?php if ($_smarty_tpl->tpl_vars['note']->value->get_owner()) {?>
                    &nbsp;-<?php echo text($_smarty_tpl->tpl_vars['note']->value->getOwnerName());?>

                  <?php }?>
                </div>
                <?php
}
$_smarty_tpl->smarty->ext->_foreach->restore($_smarty_tpl, 1);?>
              <?php }?>
              </div>
            </div>
          </div>
        </form>
        <h4><?php echo smarty_function_xlt(array('t'=>'Contents'),$_smarty_tpl);?>
</h4>
		</td>
	</tr>
	<tr id="DocContents" class="h-100">
		<td>
      <?php if ($_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "image/tiff" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "text/plain") {?>
			<embed  style="height:84vh; border: 0px" type="<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_mimetype());?>
" src="<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
as_file=false"></embed>
			<?php } elseif ($_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "image/png" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "image/jpg" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "image/jpeg" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "image/gif" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "application/pdf") {?>
			<iframe style="height:84vh; border: 0px" type="<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_mimetype());?>
" src="<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
as_file=false"></iframe>
      <?php } elseif ($_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "application/dicom" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "application/dicom+zip") {?>
      <iframe style="height:84vh; border: 0px" type="<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_mimetype());?>
" src="<?php echo $_smarty_tpl->tpl_vars['webroot']->value;?>
/library/dicom_frame.php?web_path=<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
as_file=false"></iframe>
      <?php } elseif ($_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "audio/ogg" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "audio/wav" || $_smarty_tpl->tpl_vars['file']->value->get_mimetype() == "audio/mpeg") {?>
      <audio class="w-100" preload="metadata" controls="true" type="<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_mimetype());?>
" src="<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
as_file=false"><?php echo smarty_function_xlt(array('t'=>'Your browser does not support HTML5 audio'),$_smarty_tpl);?>
</audio>
      <?php } elseif ($_smarty_tpl->tpl_vars['file']->value->get_ccr_type($_smarty_tpl->tpl_vars['file']->value->get_id()) != "CCR" && $_smarty_tpl->tpl_vars['file']->value->get_ccr_type($_smarty_tpl->tpl_vars['file']->value->get_id()) != "CCD") {?>
      <iframe style="height:84vh; border: 0px" type="<?php echo attr($_smarty_tpl->tpl_vars['file']->value->get_mimetype());?>
" src="<?php echo attr($_smarty_tpl->tpl_vars['web_path']->value);?>
as_file=true"></iframe>
			<?php }?>
		</td>
	</tr>
</table>
<?php echo '<script'; ?>
>

$('.btn-toggle').click(function() {
    $(this).find('.btn').toggleClass('active');

    if ($(this).find('.btn-primary').length > 0) {
        $(this).find('.btn').toggleClass('btn-primary');
    }

    $(this).find('.btn').toggleClass('btn-secondary');
    var show_prop = ($(this).find('.properties.active').length > 0 ? 'block':'none');
    $("#DocProperties").css('display', show_prop);
});

<?php echo '</script'; ?>
>
<?php }
}
